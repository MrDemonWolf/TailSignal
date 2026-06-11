<?php
/**
 * Tests for TailSignal_Notification.
 *
 * @package TailSignal
 */

use Brain\Monkey\Functions;

require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-db.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-expo.php';
require_once dirname( __DIR__ ) . '/src/includes/class-tailsignal-notification.php';

class Test_TailSignal_Notification extends TailSignal_TestCase {

	/**
	 * Test on_post_published skips non-publish transitions.
	 */
	public function test_on_post_published_skips_non_publish() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		$notification->on_post_published( 'draft', 'draft', $post );
		$this->assertTrue( true );
	}

	/**
	 * Test on_post_published skips if already published.
	 */
	public function test_on_post_published_skips_already_published() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		$notification->on_post_published( 'publish', 'publish', $post );
		$this->assertTrue( true );
	}

	/**
	 * Test on_post_published skips non-post types.
	 */
	public function test_on_post_published_skips_pages() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'page';

		Functions\expect( 'apply_filters' )
			->with( 'tailsignal_post_types', array( 'post', 'portfolio' ) )
			->andReturn( array( 'post' ) );

		$notification->on_post_published( 'publish', 'draft', $post );
		$this->assertTrue( true );
	}

	/**
	 * Test on_post_published skips when auto-notify disabled.
	 */
	public function test_on_post_published_skips_when_disabled() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';

		Functions\expect( 'apply_filters' )
			->with( 'tailsignal_post_types', array( 'post', 'portfolio' ) )
			->andReturn( array( 'post' ) );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_auto_notify', '1' )
			->andReturn( '0' );

		$notification->on_post_published( 'publish', 'draft', $post );
		$this->assertTrue( true );
	}

	/**
	 * Test on_post_published skips when per-post notify is disabled.
	 */
	public function test_on_post_published_skips_when_per_post_disabled() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';
		$post->ID        = 42;

		Functions\expect( 'apply_filters' )
			->with( 'tailsignal_post_types', array( 'post', 'portfolio' ) )
			->andReturn( array( 'post' ) );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_auto_notify', '1' )
			->andReturn( '1' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_tailsignal_notify', true )
			->andReturn( '0' );

		$notification->on_post_published( 'publish', 'draft', $post );
		$this->assertTrue( true );
	}

	/**
	 * Test on_post_published skips when already notified.
	 */
	public function test_on_post_published_skips_already_notified() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->post_type = 'post';
		$post->ID        = 42;

		Functions\expect( 'apply_filters' )
			->with( 'tailsignal_post_types', array( 'post', 'portfolio' ) )
			->andReturn( array( 'post' ) );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_auto_notify', '1' )
			->andReturn( '1' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_tailsignal_notify', true )
			->andReturn( '1' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_tailsignal_notified', true )
			->andReturn( '1' );

		$notification->on_post_published( 'publish', 'draft', $post );
		$this->assertTrue( true );
	}

	/**
	 * Test on_post_published skips when no tokens available.
	 */
	public function test_on_post_published_skips_no_tokens() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->post_type    = 'post';
		$post->ID           = 42;
		$post->post_title   = 'Test Post';
		$post->post_content = 'Content';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'apply_filters' )
			->with( 'tailsignal_post_types', array( 'post', 'portfolio' ) )
			->andReturn( array( 'post' ) );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_auto_notify', '1' )
			->andReturn( '1' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_tailsignal_notify', true )
			->andReturn( '' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_tailsignal_notified', true )
			->andReturn( '' );

		// build_post_notification_params stubs.
		Functions\expect( 'get_post_meta' )
			->with( 42, '_tailsignal_custom_title', true )
			->andReturn( '' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_tailsignal_custom_body', true )
			->andReturn( '' );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_default_title', Mockery::any() )
			->andReturn( 'New from {site_name}' );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_default_body', Mockery::any() )
			->andReturn( '{post_title}' );

		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );
		Functions\expect( 'get_permalink' )->andReturn( 'http://example.com/test-post' );

		Functions\expect( 'get_post_meta' )
			->with( 42, '_tailsignal_include_image', true )
			->andReturn( '' );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_use_featured_image', '1' )
			->andReturn( '1' );

		Functions\expect( 'get_option' )
			->with( 'tailsignal_dev_mode', '0' )
			->andReturn( '0' );

		// Notified marker is set pre-send and cleared again when no tokens.
		Functions\expect( 'update_post_meta' )
			->with( 42, '_tailsignal_notified', '1' )
			->once();
		Functions\expect( 'delete_post_meta' )
			->with( 42, '_tailsignal_notified' )
			->once();

		// No tokens.
		$wpdb->shouldReceive( 'get_col' )->andReturn( array() );

		$notification->on_post_published( 'publish', 'draft', $post );
		$this->assertTrue( true );
	}

	/**
	 * Test parse_placeholders replaces all tokens.
	 */
	public function test_parse_placeholders() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 1;
		$post->post_title   = 'Hello World';
		$post->post_content = 'This is the post content with several words to test the excerpt generation limit of twenty words max.';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'get_bloginfo' )
			->with( 'name' )
			->andReturn( 'My Blog' );

		Functions\expect( 'get_the_author_meta' )
			->with( 'display_name', 1 )
			->andReturn( 'John Doe' );

		Functions\expect( 'wp_strip_all_tags' )
			->andReturnFirstArg();

		$category       = new stdClass();
		$category->name = 'Technology';

		Functions\expect( 'get_the_category' )
			->with( 1 )
			->andReturn( array( $category ) );

		Functions\expect( 'has_post_thumbnail' )
			->with( 1 )
			->andReturn( false );

		$template = 'New from {site_name}: {post_title} by {author_name} in {category}';
		$result   = $notification->parse_placeholders( $template, $post );

		$this->assertSame( 'New from My Blog: Hello World by John Doe in Technology', $result );
	}

	/**
	 * Test parse_placeholders handles post excerpt.
	 */
	public function test_parse_placeholders_excerpt() {
		$notification = new TailSignal_Notification();

		$words = array_fill( 0, 25, 'word' );
		$post  = Mockery::mock( 'WP_Post' );
		$post->ID           = 1;
		$post->post_title   = 'Test';
		$post->post_content = implode( ' ', $words );
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );

		$result = $notification->parse_placeholders( '{post_excerpt}', $post );

		$this->assertStringEndsWith( '...', $result );
		$word_count = count( explode( ' ', str_replace( '...', '', $result ) ) );
		$this->assertSame( 20, $word_count );
	}

	/**
	 * Test parse_placeholders with short excerpt (under 20 words, no ellipsis).
	 */
	public function test_parse_placeholders_short_excerpt() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 1;
		$post->post_title   = 'Test';
		$post->post_content = 'Short content here';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );

		$result = $notification->parse_placeholders( '{post_excerpt}', $post );

		$this->assertSame( 'Short content here', $result );
		$this->assertStringNotContainsString( '...', $result );
	}

	/**
	 * Test parse_placeholders with featured image placeholder.
	 */
	public function test_parse_placeholders_featured_image() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 1;
		$post->post_title   = 'Test';
		$post->post_content = 'Content';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->with( 1 )->andReturn( true );
		Functions\expect( 'get_the_post_thumbnail_url' )->with( 1, 'large' )->andReturn( 'https://example.com/image.jpg' );

		$result = $notification->parse_placeholders( '{featured_image}', $post );

		$this->assertSame( 'https://example.com/image.jpg', $result );
	}

	/**
	 * Test parse_placeholders with empty categories.
	 */
	public function test_parse_placeholders_no_category() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 1;
		$post->post_title   = 'Test';
		$post->post_content = 'Content';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->with( 1 )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );

		$result = $notification->parse_placeholders( 'Category: {category}', $post );

		$this->assertSame( 'Category: ', $result );
	}

	/**
	 * Test build_post_notification_params uses default templates.
	 */
	public function test_build_post_notification_params_defaults() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 10;
		$post->post_title   = 'My Post';
		$post->post_content = 'Post content here';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'get_post_meta' )->andReturnUsing( function( $id, $key, $single = false ) {
			return '';
		} );

		Functions\expect( 'get_option' )->andReturnUsing( function( $key, $default = false ) {
			$options = array(
				'tailsignal_default_title'      => 'New from {site_name}',
				'tailsignal_default_body'        => '{post_title}',
				'tailsignal_use_featured_image'  => '1',
			);
			return $options[ $key ] ?? $default;
		} );

		Functions\expect( 'get_bloginfo' )->andReturn( 'My Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );
		Functions\expect( 'get_permalink' )->andReturn( 'http://example.com/my-post' );

		$params = $notification->build_post_notification_params( $post );

		$this->assertSame( 'New from My Blog', $params['title'] );
		$this->assertSame( 'My Post', $params['body'] );
		$this->assertArrayHasKey( 'data', $params );
		$this->assertArrayNotHasKey( 'image_url', $params );
	}

	/**
	 * Test build_post_notification_params uses per-post overrides.
	 */
	public function test_build_post_notification_params_custom() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 10;
		$post->post_title   = 'My Post';
		$post->post_content = 'Content';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'get_post_meta' )->andReturnUsing( function( $id, $key, $single = false ) {
			$meta = array(
				'_tailsignal_custom_title'  => 'Custom Title',
				'_tailsignal_custom_body'   => 'Custom Body',
				'_tailsignal_include_image' => '0',
			);
			return $meta[ $key ] ?? '';
		} );

		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->andReturn( false );
		Functions\expect( 'get_permalink' )->andReturn( 'http://example.com/my-post' );

		$params = $notification->build_post_notification_params( $post );

		$this->assertSame( 'Custom Title', $params['title'] );
		$this->assertSame( 'Custom Body', $params['body'] );
	}

	/**
	 * Test build_post_notification_params with featured image.
	 */
	public function test_build_post_notification_params_with_image() {
		$notification = new TailSignal_Notification();

		$post = Mockery::mock( 'WP_Post' );
		$post->ID           = 10;
		$post->post_title   = 'My Post';
		$post->post_content = 'Content';
		$post->post_author  = 1;
		$post->post_type    = 'post';

		Functions\expect( 'get_post_meta' )->andReturnUsing( function( $id, $key, $single = false ) {
			$meta = array(
				'_tailsignal_include_image' => '1',
			);
			return $meta[ $key ] ?? '';
		} );

		Functions\expect( 'get_option' )->andReturnUsing( function( $key, $default = false ) {
			$options = array(
				'tailsignal_default_title' => '{post_title}',
				'tailsignal_default_body'  => '{post_title}',
			);
			return $options[ $key ] ?? $default;
		} );

		Functions\expect( 'get_bloginfo' )->andReturn( 'Blog' );
		Functions\expect( 'get_the_author_meta' )->andReturn( 'Author' );
		Functions\expect( 'wp_strip_all_tags' )->andReturnFirstArg();
		Functions\expect( 'get_the_category' )->andReturn( array() );
		Functions\expect( 'has_post_thumbnail' )->with( 10 )->andReturn( true );
		Functions\expect( 'get_the_post_thumbnail_url' )->with( 10, 'large' )->andReturn( 'https://example.com/featured.jpg' );
		Functions\expect( 'get_permalink' )->andReturn( 'http://example.com/my-post' );

		$params = $notification->build_post_notification_params( $post );

		$this->assertArrayHasKey( 'image_url', $params );
		$this->assertSame( 'https://example.com/featured.jpg', $params['image_url'] );
	}

	/**
	 * Test send_notification creates record and returns ID.
	 */
	public function test_send_notification_returns_id() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 5;

		// insert_notification.
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		// Expo::send mocks.
		Functions\expect( 'get_option' )
			->with( 'tailsignal_expo_access_token', '' )
			->andReturn( '' );

		TailSignal_Expo::reset_instance();

		// update_notification.
		$wpdb->shouldReceive( 'update' )->andReturn( 1 );

		// deactivate_tokens uses prepare + query (batch UPDATE).
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'prepared' );
		$wpdb->shouldReceive( 'query' )->andReturn( 0 );

		// wp_schedule_single_event for receipt check.
		Functions\expect( 'wp_schedule_single_event' )->andReturn( true );

		$tokens = array( 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]' );
		$params = array( 'title' => 'Test', 'body' => 'Body' );

		$result = TailSignal_Notification::send_notification( $params, $tokens );

		$this->assertSame( 5, $result );
	}

	/**
	 * Test send_notification returns false on DB failure.
	 */
	public function test_send_notification_returns_false_on_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 0;

		$wpdb->shouldReceive( 'insert' )->andReturn( false );

		$result = TailSignal_Notification::send_notification(
			array( 'title' => 'Test', 'body' => 'Body' ),
			array( 'ExponentPushToken[aaa]' )
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test send_notification links to post history when post_id is provided.
	 */
	public function test_send_notification_with_post_id() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 8;

		// insert_notification.
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );

		// Expo::send.
		Functions\expect( 'get_option' )
			->with( 'tailsignal_expo_access_token', '' )
			->andReturn( '' );

		TailSignal_Expo::reset_instance();

		// update_notification + insert_notification_history.
		$wpdb->shouldReceive( 'update' )->andReturn( 1 );

		// deactivate_tokens uses prepare + query (batch UPDATE).
		$wpdb->shouldReceive( 'prepare' )->andReturn( 'prepared' );
		$wpdb->shouldReceive( 'query' )->andReturn( 0 );

		Functions\expect( 'wp_schedule_single_event' )->andReturn( true );

		$tokens = array( 'ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]' );
		$params = array( 'title' => 'Test', 'body' => 'Body' );

		$result = TailSignal_Notification::send_notification( $params, $tokens, 'post', 42 );

		$this->assertSame( 8, $result );
	}

	/**
	 * Test schedule_notification creates scheduled record.
	 */
	public function test_schedule_notification_creates_record() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 7;

		$wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );

		Functions\expect( 'wp_schedule_single_event' )->once();

		$result = TailSignal_Notification::schedule_notification(
			array( 'title' => 'Scheduled Test', 'body' => 'Body' ),
			'2025-03-01 14:00:00',
			'all'
		);

		$this->assertSame( 7, $result );
	}

	/**
	 * Test schedule_notification returns false on DB failure.
	 */
	public function test_schedule_notification_returns_false_on_failure() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$wpdb->insert_id = 0;

		$wpdb->shouldReceive( 'insert' )->once()->andReturn( false );

		$result = TailSignal_Notification::schedule_notification(
			array( 'title' => 'Test', 'body' => 'Body' ),
			'2025-03-01 14:00:00'
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test cancel_scheduled updates status and unschedules cron.
	 */
	public function test_cancel_scheduled() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$notification = new stdClass();
		$notification->id     = 7;
		$notification->status = 'scheduled';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $notification );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		Functions\expect( 'wp_next_scheduled' )
			->with( 'tailsignal_send_scheduled', array( 7 ) )
			->andReturn( 1234567890 );

		Functions\expect( 'wp_unschedule_event' )
			->with( 1234567890, 'tailsignal_send_scheduled', array( 7 ) )
			->once();

		$result = TailSignal_Notification::cancel_scheduled( 7 );
		$this->assertTrue( $result );
	}

	/**
	 * Test cancel_scheduled returns false for non-scheduled notification.
	 */
	public function test_cancel_scheduled_wrong_status() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$notification = new stdClass();
		$notification->id     = 7;
		$notification->status = 'sent';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $notification );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$result = TailSignal_Notification::cancel_scheduled( 7 );
		$this->assertFalse( $result );
	}

	/**
	 * Test cancel_scheduled returns false for non-existent notification.
	 */
	public function test_cancel_scheduled_not_found() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );

		$result = TailSignal_Notification::cancel_scheduled( 999 );
		$this->assertFalse( $result );
	}

	/**
	 * Test cancel_scheduled when no cron event is scheduled.
	 */
	public function test_cancel_scheduled_no_cron_event() {
		global $wpdb;

		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';

		$notification = new stdClass();
		$notification->id     = 7;
		$notification->status = 'scheduled';

		$wpdb->shouldReceive( 'get_row' )->once()->andReturn( $notification );
		$wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		Functions\expect( 'wp_next_scheduled' )
			->with( 'tailsignal_send_scheduled', array( 7 ) )
			->andReturn( false );

		// wp_unschedule_event should NOT be called.
		$result = TailSignal_Notification::cancel_scheduled( 7 );
		$this->assertTrue( $result );
	}
}
