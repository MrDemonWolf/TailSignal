<?php
/**
 * Tests for TailSignal_Cron receipt parsing helpers.
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-expo.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-cron.php';

class Test_TailSignal_Cron extends TailSignal_TestCase {

	/**
	 * Invoke a private static method on TailSignal_Cron.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments (by reference supported).
	 * @return mixed
	 */
	private function invoke_private( $method, array &$args ) {
		$ref = new ReflectionMethod( TailSignal_Cron::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}

	/**
	 * Legacy plain list of ticket IDs parses to the same list.
	 */
	public function test_parse_ticket_ids_legacy_list() {
		$lookup = array();
		$args   = array( '["ticket-a","ticket-b"]', &$lookup );

		$result = $this->invoke_private( 'parse_ticket_ids', $args );

		$this->assertSame( array( 'ticket-a', 'ticket-b' ), $result );
		$this->assertSame( array(), $lookup );
	}

	/**
	 * Ticket=>token map parses to ticket IDs and fills the token lookup.
	 */
	public function test_parse_ticket_ids_token_map() {
		$lookup = array();
		$json   = '{"ticket-a":"ExponentPushToken[aaa]","ticket-b":"ExponentPushToken[bbb]"}';
		$args   = array( $json, &$lookup );

		$result = $this->invoke_private( 'parse_ticket_ids', $args );

		$this->assertSame( array( 'ticket-a', 'ticket-b' ), $result );
		$this->assertSame( 'ExponentPushToken[aaa]', $lookup['ticket-a'] );
		$this->assertSame( 'ExponentPushToken[bbb]', $lookup['ticket-b'] );
	}

	/**
	 * Empty / invalid JSON parses to an empty list.
	 */
	public function test_parse_ticket_ids_invalid() {
		$lookup = array();

		$args = array( null, &$lookup );
		$this->assertSame( array(), $this->invoke_private( 'parse_ticket_ids', $args ) );

		$args = array( 'not-json', &$lookup );
		$this->assertSame( array(), $this->invoke_private( 'parse_ticket_ids', $args ) );
	}

	/**
	 * Stale token resolves from the receipt details when Expo echoes it.
	 */
	public function test_resolve_stale_token_from_receipt_details() {
		$receipt = array(
			'status'  => 'error',
			'details' => array(
				'error'         => 'DeviceNotRegistered',
				'expoPushToken' => 'ExponentPushToken[ccc]',
			),
		);
		$lookup  = array();
		$args    = array( $receipt, 'ticket-c', $lookup );

		$this->assertSame( 'ExponentPushToken[ccc]', $this->invoke_private( 'resolve_stale_token', $args ) );
	}

	/**
	 * Stale token falls back to the stored ticket=>token map.
	 */
	public function test_resolve_stale_token_from_lookup() {
		$receipt = array(
			'status'  => 'error',
			'details' => array( 'error' => 'DeviceNotRegistered' ),
		);
		$lookup  = array( 'ticket-d' => 'ExponentPushToken[ddd]' );
		$args    = array( $receipt, 'ticket-d', $lookup );

		$this->assertSame( 'ExponentPushToken[ddd]', $this->invoke_private( 'resolve_stale_token', $args ) );
	}

	/**
	 * Non-DeviceNotRegistered errors never resolve a stale token (the old
	 * code pushed receipt UUIDs into deactivate_tokens, which matched nothing).
	 */
	public function test_resolve_stale_token_ignores_other_errors() {
		$receipt = array(
			'status'  => 'error',
			'details' => array( 'error' => 'MessageRateExceeded' ),
		);
		$lookup  = array( 'ticket-e' => 'ExponentPushToken[eee]' );
		$args    = array( $receipt, 'ticket-e', $lookup );

		$this->assertNull( $this->invoke_private( 'resolve_stale_token', $args ) );
	}
}
