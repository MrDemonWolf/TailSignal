<?php
/**
 * Expo Push Service wrapper.
 *
 * @package TailSignal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ExpoSDK\Expo;
use ExpoSDK\ExpoMessage;
use ExpoSDK\Utils;

/**
 * Extended ExpoMessage that supports the "richContent" field
 * for rich push notifications with image previews on iOS and Android.
 */
class TailSignal_ExpoMessage extends ExpoMessage {

	/**
	 * Rich content payload (e.g. image URL).
	 *
	 * @var array|null
	 */
	private $richContent = null;

	/**
	 * Set the rich content payload.
	 *
	 * @param array|null $richContent Rich content data.
	 * @return $this
	 */
	public function setRichContent( $richContent ) {
		$this->richContent = $richContent;
		return $this;
	}

	/**
	 * Convert to array, including the richContent field.
	 *
	 * @return array
	 */
	public function toArray(): array {
		$attributes = parent::toArray();

		if ( null !== $this->richContent ) {
			$attributes['richContent'] = $this->richContent;
		}

		return $attributes;
	}
}

class TailSignal_Expo {

	/**
	 * Maximum messages per Expo push request.
	 *
	 * The Expo Push API rejects requests with more than 100 messages and the
	 * bundled SDK does not chunk, so we must chunk here.
	 */
	const PUSH_CHUNK_SIZE = 100;

	/**
	 * Maximum receipt IDs per Expo receipts request.
	 */
	const RECEIPT_CHUNK_SIZE = 1000;

	/**
	 * Expo SDK instance.
	 *
	 * @var Expo|null
	 */
	private static $expo = null;

	/**
	 * Access token the cached instance was built with.
	 *
	 * @var string|null
	 */
	private static $expo_token = null;

	/**
	 * Get or create the Expo SDK instance.
	 *
	 * @return Expo
	 */
	public static function get_instance() {
		$access_token = (string) get_option( 'tailsignal_expo_access_token', '' );

		if ( null === self::$expo || self::$expo_token !== $access_token ) {
			if ( ! empty( $access_token ) ) {
				self::$expo = Expo::driver( 'file' )->setAccessToken( $access_token );
			} else {
				self::$expo = Expo::driver( 'file' );
			}
			self::$expo_token = $access_token;
		}

		return self::$expo;
	}

	/**
	 * Reset the instance (useful for testing).
	 */
	public static function reset_instance() {
		self::$expo       = null;
		self::$expo_token = null;
	}

	/**
	 * Validate an Expo push token.
	 *
	 * @param string $token The token to validate.
	 * @return bool True if valid.
	 */
	public static function is_valid_token( $token ) {
		return Utils::isExpoPushToken( $token );
	}

	/**
	 * Build an ExpoMessage.
	 *
	 * @param array $params Message parameters.
	 * @return ExpoMessage
	 */
	public static function build_message( $params ) {
		$attributes = array(
			'title' => $params['title'] ?? '',
			'body'  => $params['body'] ?? '',
			'sound' => 'default',
		);

		// Custom data payload.
		$data = array();
		if ( ! empty( $params['data'] ) ) {
			$parsed = is_string( $params['data'] ) ? json_decode( $params['data'], true ) : $params['data'];
			if ( is_array( $parsed ) ) {
				$data = $parsed;
			}
		}

		if ( ! empty( $data ) ) {
			$attributes['data'] = $data;
		}

		// Rich notification with image via Expo's richContent field.
		// mutableContent is required for iOS to process the image attachment.
		if ( ! empty( $params['image_url'] ) ) {
			$attributes['richContent']    = array( 'image' => $params['image_url'] );
			$attributes['mutableContent'] = true;
		}

		return new TailSignal_ExpoMessage( $attributes );
	}

	/**
	 * Send push notifications.
	 *
	 * @param array $tokens  Array of Expo push tokens.
	 * @param array $params  Message parameters (title, body, data, image_url).
	 * @return array Result with 'ticket_ids', 'ticket_token_map', 'success_count', 'failed_count', 'stale_tokens'.
	 */
	public static function send( $tokens, $params ) {
		$result = array(
			'ticket_ids'       => array(),
			'ticket_token_map' => array(),
			'success_count'    => 0,
			'failed_count'     => 0,
			'stale_tokens'     => array(),
		);

		if ( empty( $tokens ) ) {
			return $result;
		}

		// Filter valid tokens.
		$valid_tokens = array_filter( $tokens, array( __CLASS__, 'is_valid_token' ) );
		$result['failed_count'] = count( $tokens ) - count( $valid_tokens );

		if ( empty( $valid_tokens ) ) {
			return $result;
		}

		$expo    = self::get_instance();
		$message = self::build_message( $params );

		// Expo rejects more than 100 messages per request and the SDK does
		// not chunk, so split into batches ourselves.
		$chunks = array_chunk( array_values( $valid_tokens ), self::PUSH_CHUNK_SIZE );

		foreach ( $chunks as $chunk_tokens ) {
			try {
				$response = $expo->send( $message )->to( $chunk_tokens )->push();

				$data = $response ? $response->getData() : null;

				if ( empty( $data ) ) {
					continue;
				}

				foreach ( $data as $index => $ticket ) {
					if ( isset( $ticket['status'] ) && 'ok' === $ticket['status'] ) {
						$result['success_count']++;
						if ( isset( $ticket['id'] ) ) {
							$result['ticket_ids'][] = $ticket['id'];
							if ( isset( $chunk_tokens[ $index ] ) ) {
								$result['ticket_token_map'][ $ticket['id'] ] = $chunk_tokens[ $index ];
							}
						}
					} else {
						$result['failed_count']++;

						// Track DeviceNotRegistered for cleanup.
						if ( isset( $ticket['details']['error'] ) && 'DeviceNotRegistered' === $ticket['details']['error'] ) {
							if ( isset( $chunk_tokens[ $index ] ) ) {
								$result['stale_tokens'][] = $chunk_tokens[ $index ];
							}
						}
					}
				}
			} catch ( \Exception $e ) {
				$result['failed_count'] += count( $chunk_tokens );
				error_log( 'TailSignal Expo send error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		// Auto-remove stale tokens.
		if ( ! empty( $result['stale_tokens'] ) ) {
			TailSignal_DB::deactivate_tokens( $result['stale_tokens'] );
		}

		return $result;
	}

	/**
	 * Check receipt status for ticket IDs.
	 *
	 * @param array $ticket_ids Array of Expo ticket IDs.
	 * @return array Receipt data.
	 */
	public static function check_receipts( $ticket_ids ) {
		if ( empty( $ticket_ids ) ) {
			return array();
		}

		$expo     = self::get_instance();
		$receipts = array();

		// Expo caps receipt requests, so chunk large backlogs.
		foreach ( array_chunk( array_values( $ticket_ids ), self::RECEIPT_CHUNK_SIZE ) as $chunk ) {
			try {
				$response = $expo->getReceipts( $chunk )->check();

				if ( $response ) {
					$data = $response->getData();
					if ( ! empty( $data ) && is_array( $data ) ) {
						$receipts = array_merge( $receipts, $data );
					}
				}
			} catch ( \Exception $e ) {
				error_log( 'TailSignal receipt check error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		return $receipts;
	}
}
