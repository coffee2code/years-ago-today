<?php

defined( 'ABSPATH' ) or die();

class Years_Ago_Today_Test extends WP_UnitTestCase {

	public function tearDown() {
		parent::tearDown();

		remove_filter( 'c2c_years_ago_today-email-if-no-posts', '__return_true' );
		remove_filter( 'c2c_years_ago_today-email-body-no-posts', array( $this, 'email_body_no_posts' ) );
		remove_filter( 'c2c_years_ago_today-first_published_year', array( $this, 'first_published_year' ) );
	}


	//
	// HELPER FUNCTIONS
	//


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


	//
	//
	// FUNCTIONS FOR HOOKING ACTIONS/FILTERS
	//
	//


	public function email_body_no_posts( $text ) {
		return 'Sorry, no posts were made on this day (%2$s) to %1$s in any prior year.';
	}

	public function first_published_year( $year ) {
		return '2014';
	}


	//
	// TESTS
	//


	public function test_plugin_version() {
		$this->assertEquals( '1.2.2', c2c_YearsAgoToday::version() );
	}

	public function test_class_is_available() {
		$this->assertTrue( class_exists( 'c2c_YearsAgoToday' ) );
	}

	public function test_cron_task_is_created() {
		c2c_YearsAgoToday::activate();

		$this->assertNotFalse( wp_next_scheduled( c2c_YearsAgoToday::$cron_name ) );
	}

	/*
	 * wp_dashboard_years_ago_today()
	 */

	public function test_shows_message_about_no_previous_year_posts() {
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		ob_start();
		c2c_YearsAgoToday::wp_dashboard_years_ago_today();
		$out = ob_get_contents();
		ob_end_clean();

		$this->assertContains( 'No posts were published on <strong>' . current_time( 'M jS' ) . '</strong> from any past year.', $out );
	}

	public function test_shows_singular_message_with_single_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		ob_start();
		c2c_YearsAgoToday::wp_dashboard_years_ago_today();
		$out = ob_get_contents();
		ob_end_clean();

		$this->assertContains( '<strong>1</strong> post has been published on <strong>' . current_time( 'M jS' ) . '</strong> in a previous year:', $out );
	}

	public function test_shows_plural_message_with_multiple_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		ob_start();
		c2c_YearsAgoToday::wp_dashboard_years_ago_today();
		$out = ob_get_contents();
		ob_end_clean();

		$this->assertContains( '<strong>2</strong> posts have been published on <strong>' . current_time( 'M jS' ) . '</strong> in previous years:', $out );
	}

	/*
	 * get_posts()
	 */

	public function test_get_posts_query_obj_with_no_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012', false ) ) );

		$posts = c2c_YearsAgoToday::get_posts();

		$this->assertTrue( is_a( $posts, 'WP_Query' ) );
		$this->assertFalse( $posts->have_posts() );
	}

	public function test_get_posts_query_obj_with_matching_past_year_posts() {
		$post_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$query = c2c_YearsAgoToday::get_posts();

		$this->assertTrue( $query->have_posts() );
		$this->assertEquals( 1, $query->found_posts );
		$this->assertEquals( array( get_post( $post_id ) ), $query->get_posts() );
	}

	public function test_get_posts_with_no_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012', false ) ) );

		$this->assertEmpty( c2c_YearsAgoToday::get_posts( true ) );
	}

	public function test_get_posts_with_matching_past_year_posts() {
		$post_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$posts = c2c_YearsAgoToday::get_posts( true );

		$this->assertNotEmpty( $posts );
		$this->assertEquals( 1, count( $posts ) );
		$this->assertEquals( get_post( $post_id ), $posts[0] );
	}

	/*
	 * get_users_to_email()
	 */

	public function test_get_users_to_email_with_no_users() {
		$user1_id = $this->factory->user->create();
		$user2_id = $this->factory->user->create();
		$user3_id = $this->factory->user->create();

		$this->assertEmpty( c2c_YearsAgoToday::get_users_to_email() );
	}

	public function test_get_users_to_email_with_users() {
		$user1_id = $this->factory->user->create();
		$user2_id = $this->factory->user->create();
		$user3_id = $this->factory->user->create();

		$user1 = get_user_by( 'id', $user1_id );
		$user3 = get_user_by( 'id', $user3_id );

		update_user_option( $user1_id, c2c_YearsAgoToday::$option_name, c2c_YearsAgoToday::$enabled_option_value );
		update_user_option( $user3_id, c2c_YearsAgoToday::$option_name, c2c_YearsAgoToday::$enabled_option_value );

		$this->assertEquals( array( $user1, $user3 ), c2c_YearsAgoToday::get_users_to_email() );
	}

	/*
	 * get_first_published_year()
	 */

	public function test_get_first_published_year_with_no_posts() {
		$this->assertEquals( current_time( 'Y' ), c2c_YearsAgoToday::get_first_published_year() );
	}

	public function test_get_first_published_year_with_posts() {
		$post1_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		$post2_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2013' ) ) );
		$post3_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2011' ) ) );
		$post4_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2010' ), 'post_status' => 'draft' ) );
		$post5_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2013', false ) ) );

		$this->assertEquals( '2011', c2c_YearsAgoToday::get_first_published_year() );
	}

	/*
	 * get_email_subject()
	 */

	public function test_get_email_subject() {
		$this->assertEquals(
			'[Test Blog] Years Ago Today daily update',
			c2c_YearsAgoToday::get_email_subject()
		);
	}

	/*
	 * get_email_body()
	 */

	public function test_get_email_body_with_no_posts() {
		$this->assertEmpty( c2c_YearsAgoToday::get_email_body() );
	}

	public function test_get_email_body_with_no_posts_but_email_forced() {
		add_filter( 'c2c_years_ago_today-email-if-no-posts', '__return_true' );

		$this->assertEquals(
			sprintf(
				'No posts were published to the site %1$s on <strong>%2$s</strong> in any past year.',
				'Test Blog',
				current_time( 'M jS' )
			),
			c2c_YearsAgoToday::get_email_body()
		);
	}

	public function test_get_email_body_shows_singular_message_with_single_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$this->assertContains(
			'<strong>1</strong> post has been published to the site Test Blog on <strong>' . current_time( 'M jS' ) . '</strong> in a previous year:',
			c2c_YearsAgoToday::get_email_body()
		);
	}

	public function test_get_email_body_whole_email_with_single_matching_past_year_posts() {
		$post_title = 'A blast from the past';
		$post = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $this->get_date( '2012' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$email  = '<strong>1</strong> post has been published to the site Test Blog on <strong>' . current_time( 'M jS' ) . '</strong> in a previous year:';
		$email .= "\n\n== 2012 ==\n";
		$email .= "* {$post_title} : " . get_permalink( $post ) . "\n";

		$this->assertEquals(
			$email,
			c2c_YearsAgoToday::get_email_body()
		);
	}

	public function test_get_email_body_shows_plural_message_with_multiple_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$this->assertContains(
			'<strong>2</strong> posts have been published to the site Test Blog on <strong>' . current_time( 'M jS' ) . '</strong> in previous years:',
			c2c_YearsAgoToday::get_email_body()
		);
	}

	public function test_get_email_body_whole_email_with_multiple_matching_past_year_posts() {
		$post_title1 = 'A blast from the past';
		$post1 = $this->factory->post->create( array( 'post_title' => $post_title1, 'post_date' => $this->get_date( '2012' ) ) );
		$post_title2 = 'Days of future years past';
		$post2 = $this->factory->post->create( array( 'post_title' => $post_title2, 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$email  = '<strong>2</strong> posts have been published to the site Test Blog on <strong>' . current_time( 'M jS' ) . '</strong> in previous years:';
		$email .= "\n\n== 2014 ==\n";
		$email .= "* {$post_title2} : " . get_permalink( $post2 ) . "\n";
		$email .= "\n\n== 2012 ==\n";
		$email .= "* {$post_title1} : " . get_permalink( $post1 ) . "\n";

		$this->assertEquals(
			$email,
			c2c_YearsAgoToday::get_email_body()
		);
	}

	/*
	 * add_user_email_footer()
	 */

	public function test_add_user_email_footer() {
		$user_id = $this->factory->user->create();
		$user_profile_url = get_edit_profile_url( $user_id );
		$text = <<<HTML
Hi!


-------------------------------
You received this email because you have opted into receiving a daily email about posts published on this day in years past on the site Test Blog, which is using the Years Ago Today plugin.

If you wish to discontinue receiving these emails, simply log into the site and visit your profile at {$user_profile_url} to uncheck the checkbox labeled "Email me daily about posts published on this day in years past."

HTML;

		$this->assertEquals( $text, c2c_YearsAgoToday::add_user_email_footer( $user_id, 'Hi!' ) );
	}

	/*
	 * Filter: c2c_years_ago_today-email-body-no-posts
	 */

	public function test_filter_email_body_no_posts() {
		add_filter( 'c2c_years_ago_today-email-if-no-posts',  '__return_true' );
		add_filter( 'c2c_years_ago_today-email-body-no-posts', array( $this, 'email_body_no_posts' ) );

		$this->assertEquals(
			sprintf(
				'Sorry, no posts were made on this day (%s) to %s in any prior year.',
				current_time( 'M jS' ),
				'Test Blog'
			),
			c2c_YearsAgoToday::get_email_body()
		);
	}

	/*
	 * Filter :c2c_years_ago_today-first_published_year
	 */

	public function test_filter_first_published_year() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );

		$this->assertEquals( '2012', c2c_YearsAgoToday::get_first_published_year() );

		add_filter( 'c2c_years_ago_today-first_published_year', array( $this, 'first_published_year' ) );

		$this->assertEquals( '2014', c2c_YearsAgoToday::get_first_published_year() );
	}
}
