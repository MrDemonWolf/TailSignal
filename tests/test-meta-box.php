<?php
/**
 * Tests for TailSignal_Meta_Box.
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-expo.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-notification.php';
require_once dirname( __DIR__ ) . '/src/admin/class-tailsignal-meta-box.php';

class Test_TailSignal_Meta_Box extends TailSignal_TestCase {

	/**
	 * @var TailSignal_Meta_Box
	 */
	private $meta_box;

	protected function setUp(): void {
		parent::setUp();
		$this->meta_box = new TailSignal_Meta_Box();
	}

	/**
	 * Test save_meta_box requires nonce.
	 */
	public function test_save_meta_box_requires_nonce() {
		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		// No nonce in $_POST - should return early.
		$_POST = array();

		// wp_verify_nonce should NOT be called since nonce key is missing.
		$this->meta_box->save_meta_box( 1, $post );

		// If we got here without errors, the early return works.
		$this->assertTrue( true );
	}

	/**
	 * Test save_meta_box rejects invalid nonce.
	 */
	public function test_save_meta_box_rejects_invalid_nonce() {
		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		$_POST = array(
			'tailsignal_meta_box_nonce' => 'bad_nonce',
		);

		Functions\expect( 'wp_verify_nonce' )
			->with( 'bad_nonce', 'tailsignal_meta_box' )
			->andReturn( false );

		// update_post_meta should NOT be called.
		$this->meta_box->save_meta_box( 1, $post );
		$this->assertTrue( true );
	}

	/**
	 * Test save_meta_box saves meta when valid.
	 */
	public function test_save_meta_box_saves_meta() {
		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		$_POST = array(
			'tailsignal_meta_box_nonce' => 'good_nonce',
			'tailsignal_notify'         => '1',
			'tailsignal_include_image'  => '1',
			'tailsignal_custom_title'   => 'Custom Title',
			'tailsignal_custom_body'    => 'Custom Body',
		);

		Functions\expect( 'wp_verify_nonce' )
			->with( 'good_nonce', 'tailsignal_meta_box' )
			->andReturn( true );

		Functions\expect( 'current_user_can' )->andReturn( true );

		Functions\when( 'get_option' )->alias( function( $key, $default = false ) { return $default; } );

		Functions\expect( 'update_post_meta' )->times( 4 );

		$this->meta_box->save_meta_box( 1, $post );
		$this->assertTrue( true );
	}

	/**
	 * Test add_meta_box registers meta box.
	 */
	public function test_add_meta_box() {
		Functions\expect( 'apply_filters' )
			->with( 'tailsignal_post_types', array( 'post', 'portfolio' ) )
			->andReturn( array( 'post', 'portfolio' ) );

		Functions\expect( 'add_meta_box' )->twice();

		$this->meta_box->add_meta_box();
		$this->assertTrue( true );
	}

	/**
	 * Test save_meta_box checks permissions.
	 */
	public function test_save_meta_box_checks_permissions() {
		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		$_POST = array(
			'tailsignal_meta_box_nonce' => 'good_nonce',
		);

		Functions\expect( 'wp_verify_nonce' )
			->with( 'good_nonce', 'tailsignal_meta_box' )
			->andReturn( true );

		Functions\expect( 'current_user_can' )
			->with( 'edit_post', 1 )
			->andReturn( false );

		// update_post_meta should NOT be called.
		$this->meta_box->save_meta_box( 1, $post );
		$this->assertTrue( true );
	}

	/**
	 * Test save_meta_box requires tailsignal_manage capability.
	 */
	public function test_save_meta_box_requires_tailsignal_manage() {
		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		$_POST = array(
			'tailsignal_meta_box_nonce' => 'good_nonce',
			'tailsignal_notify'         => '1',
		);

		Functions\expect( 'wp_verify_nonce' )->andReturn( true );

		Functions\expect( 'current_user_can' )->andReturnUsing( function( $cap ) {
			if ( 'edit_post' === $cap ) {
				return true;
			}
			if ( 'tailsignal_manage' === $cap ) {
				return false;
			}
			return false;
		} );

		// update_post_meta should NOT be called since tailsignal_manage check fails.
		$this->meta_box->save_meta_box( 1, $post );
		$this->assertTrue( true );
	}

	/**
	 * Test save_meta_box saves '0' when checkboxes unchecked.
	 */
	public function test_save_meta_box_saves_zero_when_unchecked() {
		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		$_POST = array(
			'tailsignal_meta_box_nonce' => 'good_nonce',
			// No tailsignal_notify or tailsignal_include_image keys.
		);

		Functions\expect( 'wp_verify_nonce' )->andReturn( true );
		Functions\expect( 'current_user_can' )->andReturn( true );

		Functions\when( 'get_option' )->alias( function( $key, $default = false ) { return $default; } );

		$saved_meta = array();
		Functions\expect( 'update_post_meta' )->andReturnUsing( function( $post_id, $key, $value ) use ( &$saved_meta ) {
			$saved_meta[ $key ] = $value;
		} );

		$this->meta_box->save_meta_box( 1, $post );

		$this->assertSame( '0', $saved_meta['_tailsignal_notify'] );
		$this->assertSame( '0', $saved_meta['_tailsignal_include_image'] );
	}

	/**
	 * Test save_meta_box handles missing custom_title and custom_body.
	 */
	public function test_save_meta_box_missing_custom_fields() {
		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		$_POST = array(
			'tailsignal_meta_box_nonce' => 'good_nonce',
			'tailsignal_notify'         => '1',
			'tailsignal_include_image'  => '1',
			// No custom_title or custom_body.
		);

		Functions\expect( 'wp_verify_nonce' )->andReturn( true );
		Functions\expect( 'current_user_can' )->andReturn( true );

		Functions\when( 'get_option' )->alias( function( $key, $default = false ) { return $default; } );

		// Only 2 update_post_meta calls (notify + include_image), not 4.
		Functions\expect( 'update_post_meta' )->times( 2 );

		$this->meta_box->save_meta_box( 1, $post );
		$this->assertTrue( true );
	}

	/**
	 * Test render_meta_box calls wp_nonce_field.
	 */
	public function test_render_meta_box_calls_nonce_field() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$post     = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';
		$post->ID = 5;

		Functions\expect( 'wp_nonce_field' )
			->with( 'tailsignal_meta_box', 'tailsignal_meta_box_nonce' )
			->once();

		Functions\expect( 'get_post_meta' )->andReturn( '' );

		Functions\expect( 'get_option' )->andReturnUsing( function( $key, $default = false ) {
			if ( 'tailsignal_dev_mode' === $key ) {
				return '0';
			}
			return $default;
		} );

		// get_all_groups.
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );

		// get_post_notification_history.
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$this->meta_box->render_meta_box( $post );
		$this->assertTrue( true );
	}

	/**
	 * Test render_meta_box uses default value for empty notify meta.
	 */
	public function test_render_meta_box_default_notify() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$post     = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';
		$post->ID = 5;

		Functions\expect( 'wp_nonce_field' )->once();
		Functions\expect( 'get_post_meta' )->andReturn( '' );
		Functions\expect( 'get_option' )->andReturn( '1' );
		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		// If we get here without errors, the default logic works.
		$this->meta_box->render_meta_box( $post );
		$this->assertTrue( true );
	}

	/**
	 * Test render_meta_box gets post notification history.
	 */
	public function test_render_meta_box_gets_history() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$post     = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';
		$post->ID = 7;

		Functions\expect( 'wp_nonce_field' )->once();
		Functions\expect( 'get_post_meta' )->andReturn( '' );
		Functions\expect( 'get_option' )->andReturn( '0' );

		$wpdb->shouldReceive( 'get_results' )->andReturn( array() );
		$wpdb->shouldReceive( 'prepare' )
			->with( Mockery::pattern( '/notification_history/' ), 7 )
			->andReturn( '' );

		$this->meta_box->render_meta_box( $post );
		$this->assertTrue( true );
	}
}
