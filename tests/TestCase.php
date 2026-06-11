<?php
/**
 * Base test case for TailSignal tests.
 *
 * @package TailSignal
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TailSignal_TestCase extends PHPUnitTestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Reset request superglobals so state never leaks between tests.
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();

		// Define WordPress constants used in production code.
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		// Mock common WordPress functions.
		Functions\stubs( array(
			'get_transient'    => false,
			'set_transient'    => true,
			'delete_transient' => true,
			'sanitize_text_field'     => function( $str ) { return trim( strip_tags( $str ) ); },
			'sanitize_textarea_field' => function( $str ) { return trim( strip_tags( $str ) ); },
			'esc_url_raw'             => function( $url ) { return $url; },
			'esc_url'                 => function( $url ) { return $url; },
			'esc_html'                => function( $str ) { return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' ); },
			'esc_attr'                => function( $str ) { return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' ); },
			'esc_js'                  => function( $str ) { return $str; },
			'wp_parse_args'           => function( $args, $defaults ) { return array_merge( $defaults, $args ); },
			'wp_json_encode'          => function( $data ) { return json_encode( $data ); },
			'wp_unslash'              => function( $value ) { return $value; },
			'current_time'            => function( $type = 'mysql' ) {
				if ( 'timestamp' === $type ) {
					return strtotime( '2025-02-12 12:00:00' );
				}
				return '2025-02-12 12:00:00';
			},
			'get_gmt_from_date'       => function( $date, $format = 'Y-m-d H:i:s' ) {
				$ts = strtotime( $date );
				if ( false === $ts ) {
					return 'U' === $format ? 0 : '';
				}
				return 'U' === $format ? $ts : gmdate( $format, $ts );
			},
			'wp_is_post_revision'     => false,
			'__'                      => function( $text ) { return $text; },
			'_e'                      => function( $text ) { echo $text; },
			'esc_html__'              => function( $text ) { return $text; },
			'esc_html_e'              => function( $text ) { echo $text; },
			'esc_attr__'              => function( $text ) { return $text; },
			'esc_attr_e'              => function( $text ) { echo $text; },
			'checked'                 => function( $checked, $current = true, $echo = true ) {
				$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
				if ( $echo ) { echo $result; }
				return $result;
			},
			'selected'                => function( $selected, $current = true, $echo = true ) {
				$result = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
				if ( $echo ) { echo $result; }
				return $result;
			},
			'wp_kses_post'            => function( $str ) { return $str; },
			'absint'                  => function( $val ) { return abs( intval( $val ) ); },
			'wp_trim_words'           => function( $text, $num = 55, $more = '...' ) {
				$words = explode( ' ', $text );
				if ( count( $words ) > $num ) {
					return implode( ' ', array_slice( $words, 0, $num ) ) . $more;
				}
				return $text;
			},
			'human_time_diff'         => function() { return '1 hour'; },
			'number_format_i18n'      => function( $number ) { return number_format( $number ); },
			'date_i18n'               => function( $format, $timestamp = false ) { return date( $format, $timestamp ?: time() ); },
			'admin_url'               => function( $path = '' ) { return 'http://example.com/wp-admin/' . $path; },
			'wp_nonce_url'            => function( $url ) { return $url . '&_wpnonce=test'; },
			'submit_button'           => function() {},
		) );
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
