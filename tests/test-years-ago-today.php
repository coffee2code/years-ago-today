<?php

class Years_Ago_Today_Test extends WP_UnitTestCase {



	/*
	 * HELPER FUNCTIONS
	 */



	private function get_date( $year = null, $today = true ) {
		if ( ! $year ) {
			$year = current_time( 'Y' );
		}

		$date = $year . current_time( '-m-d 13:00:04' );

		// If not requesting the current day, then offset a few days.
		if ( ! $today ) {
			$date = date( 'Y-m-d', strtotime( '-2 days', strtotime( $date ) ) );
		}

		return $date;
	}



	/*
	 * TESTS
	 */



	function test_plugin_version() {
		$this->assertEquals( '1.0', c2c_YearsAgoToday::version() );
	}

	function test_class_is_available() {
		$this->assertTrue( class_exists( 'c2c_YearsAgoToday' ) );
	}

	function test_cron_task_is_created() {
		c2c_YearsAgoToday::activate();

		$this->assertNotFalse( wp_next_scheduled( c2c_YearsAgoToday::$cron_name ) );
	}

	function test_shows_message_about_no_previous_year_posts() {
		ob_start();
		c2c_YearsAgoToday::wp_dashboard_years_ago_today();
		$out = ob_get_contents();
		ob_end_clean();

		$this->assertContains( 'No posts were published on this day from any past year.', $out );
	}

	function test_get_posts_query_obj_with_no_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012', false ) ) );

		$this->assertFalse( c2c_YearsAgoToday::get_posts()->have_posts() );
	}

	function test_get_posts_query_obj_with_matching_past_year_posts() {
		$post_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$query = c2c_YearsAgoToday::get_posts();

		$this->assertTrue( $query->have_posts() );
		$this->assertEquals( 1, $query->found_posts );
		$this->assertEquals( array( get_post( $post_id ) ), $query->get_posts() );
	}

	function test_get_posts_with_no_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012', false ) ) );

		$this->assertEmpty( c2c_YearsAgoToday::get_posts( true ) );
	}

	function test_get_posts_with_matching_past_year_posts() {
		$post_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$posts = c2c_YearsAgoToday::get_posts( true );

		$this->assertNotEmpty( $posts );
		$this->assertEquals( 1, count( $posts ) );
		$this->assertEquals( get_post( $post_id ), $posts[0] );
	}

	function test_get_users_to_email_with_no_users() {
		$user1_id = $this->factory->user->create();
		$user2_id = $this->factory->user->create();
		$user3_id = $this->factory->user->create();

		$this->assertEmpty( c2c_YearsAgoToday::get_users_to_email() );
	}

	function test_get_users_to_email_with_users() {
		$user1_id = $this->factory->user->create();
		$user2_id = $this->factory->user->create();
		$user3_id = $this->factory->user->create();

		$user1 = get_user_by( 'id', $user1_id );
		$user3 = get_user_by( 'id', $user3_id );

		update_user_option( $user1_id, c2c_YearsAgoToday::$option_name, c2c_YearsAgoToday::$enabled_option_value );
		update_user_option( $user3_id, c2c_YearsAgoToday::$option_name, c2c_YearsAgoToday::$enabled_option_value );

		$this->assertEquals( array( $user1, $user3 ), c2c_YearsAgoToday::get_users_to_email() );
	}

	function test_get_first_published_year_with_no_posts() {
		$this->assertEquals( current_time( 'Y' ), c2c_YearsAgoToday::get_first_published_year() );
	}

	function test_get_first_published_year_with_posts() {
		$post1_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		$post2_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2013' ) ) );
		$post3_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2011' ) ) );
		$post4_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2010' ), 'post_status' => 'draft' ) );
		$post5_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2013', false ) ) );

		$this->assertEquals( '2011', c2c_YearsAgoToday::get_first_published_year() );
	}

}
