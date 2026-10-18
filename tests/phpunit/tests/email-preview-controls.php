<?php

class Test_Years_Ago_Today_Preview_Controls extends WP_UnitTestCase {

	private function invoke( $method, ...$args ) {
		$ref = new ReflectionMethod( 'c2c_YearsAgoToday', $method );
		$ref->setAccessible( true );
		return $ref->invoke( null, ...$args );
	}

	public function setUp(): void {
		parent::setUp();
		wp_cache_delete( c2c_YearsAgoToday::get_first_published_year_cache_key(), c2c_YearsAgoToday::$cache_group );
		delete_transient( c2c_YearsAgoToday::get_post_ids_cache_key() );
	}

	public function tearDown(): void {
		wp_cache_delete( c2c_YearsAgoToday::get_first_published_year_cache_key(), c2c_YearsAgoToday::$cache_group );
		delete_transient( c2c_YearsAgoToday::get_post_ids_cache_key() );
		parent::tearDown();
	}

	public function test_selected_date_uses_separate_cache_and_excludes_same_year_posts() {
		$past = $this->factory->post->create( array( 'post_date' => '2018-03-15 12:00:00' ) );
		$this->factory->post->create( array( 'post_date' => '2019-03-15 12:00:00' ) );
		$this->factory->post->create( array( 'post_date' => '2018-03-16 12:00:00' ) );

		$this->assertSame( array( $past ), c2c_YearsAgoToday::query_post_ids( '2019-03-15' ) );
		$this->assertNotSame( c2c_YearsAgoToday::get_post_ids_cache_key( '2019-03-15' ), c2c_YearsAgoToday::get_post_ids_cache_key( '2019-03-16' ) );
		$this->assertStringContainsString( c2c_YearsAgoToday::get_formatted_date_string( strtotime( '2019-03-15 12:00:00 UTC' ), true ), c2c_YearsAgoToday::get_email_subject( '2019-03-15' ) );
	}

	public function test_arrows_skip_empty_dates_and_cross_year_boundaries() {
		$this->factory->post->create( array( 'post_date' => '2018-03-15 12:00:00' ) );
		$this->factory->post->create( array( 'post_date' => '2018-07-01 12:00:00' ) );

		$this->assertSame( array( 'previous' => '2019-03-15', 'next' => '2020-03-15' ), $this->invoke( 'get_adjacent_email_dates', '2019-07-01' ) );
		$this->assertSame( array( 'previous' => '2019-03-15', 'next' => '2019-07-01' ), $this->invoke( 'get_adjacent_email_dates', '2019-06-01' ) );
	}


	public function test_next_arrow_can_jump_from_before_the_sites_first_year() {
		$this->factory->post->create( array( 'post_date' => '2018-03-15 12:00:00' ) );

		$this->assertSame( array( 'previous' => '', 'next' => '2019-03-15' ), $this->invoke( 'get_adjacent_email_dates', '1990-01-01' ) );
	}

	public function test_leap_day_navigation_finds_the_next_valid_anniversary() {
		$this->factory->post->create( array( 'post_date' => '2016-02-29 12:00:00' ) );

		$this->assertSame( array( 'previous' => '2020-02-29', 'next' => '2024-02-29' ), $this->invoke( 'get_adjacent_email_dates', '2021-03-01' ) );
	}

	public function test_falls_back_to_a_date_with_posts_and_validates_requested_dates() {
		$year = (int) wp_date( 'Y' );
		$day = '01-15';
		if ( '01-15' === wp_date( 'm-d' ) ) {
			$day = '02-15';
		}
		$this->factory->post->create( array( 'post_date' => ( $year - 1 ) . '-' . $day . ' 12:00:00' ) );

		$this->assertSame( $year . '-' . $day, $this->invoke( 'get_email_preview_date' ) );
		$this->assertSame( $year . '-' . $day, $this->invoke( 'get_email_preview_date', '2025-02-30' ) );
		$this->assertSame( '2024-02-29', $this->invoke( 'get_email_preview_date', '2024-02-29' ) );
	}

	public function test_preview_test_send_only_targets_current_user_and_selected_date() {
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber', 'user_email' => 'preview@example.org' ) );
		$this->factory->post->create( array( 'post_date' => '2018-03-15 12:00:00', 'post_title' => 'March anniversary' ) );
		$original_user = get_current_user_id();
		$original_get = $_GET;
		$original_post = $_POST;
		$original_request = $_REQUEST;
		$original_method = $_SERVER['REQUEST_METHOD'] ?? null;
		$original_screen = get_current_screen();
		$mail = array();
		$send_result = true;
		$intercept = static function ( $result, $args ) use ( &$mail, &$send_result ) {
			$mail[] = $args;
			return $send_result;
		};
		wp_set_current_user( $user_id );
		set_current_screen( 'profile' );
		$_GET = array( 'preview-years-ago-today-email' => '1', 'content-type' => 'full', 'type' => 'html', 'date' => '2019-03-15' );
		$_POST = array( 'send-years-ago-today-test' => '1', 'recipient' => 'other@example.org' );
		$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'send-years-ago-today-test' ) );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		add_filter( 'pre_wp_mail', $intercept, 10, 2 );
		ob_start();
		try {
			c2c_YearsAgoToday::handle_email_template_preview();
			$output = ob_get_contents();
			$this->assertCount( 1, $mail );
			$this->assertSame( 'preview@example.org', $mail[0]['to'] );
			$this->assertSame( c2c_YearsAgoToday::get_email_subject( '2019-03-15' ), $mail[0]['subject'] );
			$this->assertStringContainsString( 'March anniversary', $mail[0]['message'] );
			$this->assertStringContainsString( 'Send test email to me', $output );
			$this->assertStringContainsString( 'name="date" value="2019-03-15"', $output );
			$this->assertStringContainsString( 'Test email sent to your email address.', $output );


			$send_result = false;
			ob_clean();
			c2c_YearsAgoToday::handle_email_template_preview();
			$failure_output = ob_get_contents();
			$this->assertCount( 2, $mail );
			$this->assertStringContainsString( 'The test email could not be sent.', $failure_output );
			$this->assertStringContainsString( 'notice-error', $failure_output );
			$this->assertStringNotContainsString( 'Test email sent to your email address.', $failure_output );

			$reject_nonce = static function () {
				return static function () { throw new RuntimeException( 'Invalid nonce rejected.' ); };
			};
			$_REQUEST['_wpnonce'] = 'invalid';
			add_filter( 'wp_die_handler', $reject_nonce );
			try {
				c2c_YearsAgoToday::handle_email_template_preview();
				$this->fail( 'An invalid nonce must reject the send.' );
			} catch ( RuntimeException $error ) {
				$this->assertSame( 'Invalid nonce rejected.', $error->getMessage() );
			} finally {
				remove_filter( 'wp_die_handler', $reject_nonce );
			}
			$this->assertCount( 2, $mail );
		} finally {
			ob_end_clean();
			remove_filter( 'pre_wp_mail', $intercept, 10 );
			$_GET = $original_get;
			$_POST = $original_post;
			$_REQUEST = $original_request;
			if ( null === $original_method ) { unset( $_SERVER['REQUEST_METHOD'] ); }
			else { $_SERVER['REQUEST_METHOD'] = $original_method; }
			$GLOBALS['current_screen'] = $original_screen;
			wp_set_current_user( $original_user );
			delete_transient( c2c_YearsAgoToday::get_post_ids_cache_key( '2019-03-15' ) );
		}
	}
}
