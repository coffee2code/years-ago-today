<?php

defined( 'ABSPATH' ) or die();

class Years_Ago_Today_Test extends WP_UnitTestCase {

	public static function setUpBeforeClass() {
		// Make all requests as if in the admin, which is the only place the plugin
		// affects.
		define( 'WP_ADMIN', true );

		// Re-initialize plugin now that WP_ADMIN is true.
		c2c_YearsAgoToday::init();
	}

	public function tearDown() {
		global $wp_meta_boxes;

		parent::tearDown();

		$wp_meta_boxes = NULL;
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
	//
	// DATA PROVIDERS
	//
	//


	public static function get_default_hooks() {
		return array(
			array( 'action', 'wp_dashboard_setup',       'dashboard_setup',                10 ),
			array( 'action', 'personal_options',         'add_daily_email_optin_checkbox', 10 ),
			array( 'action', 'personal_options_update',  'option_save',                    10 ),
			array( 'action', 'edit_user_profile_update', 'option_save',                    10 ),
			array( 'action', 'c2c_years_ago_daily_cron', 'cron_email',                     10 ),
			array( 'action', 'load-index.php',           'add_admin_css',                  10 ),
		);
	}


	//
	//
	// TESTS
	//
	//


	public function test_plugin_version() {
		$this->assertEquals( '1.5.1', c2c_YearsAgoToday::version() );
	}

	public function test_class_is_available() {
		$this->assertTrue( class_exists( 'c2c_YearsAgoToday' ) );
	}

	public function test_default_option_name() {
		$this->assertEquals( 'c2c_years_ago_today_daily_email_optin', c2c_YearsAgoToday::$option_name );
	}

	public function test_default_cron_name() {
		$this->assertEquals( 'c2c_years_ago_daily_cron', c2c_YearsAgoToday::$cron_name );
	}

	public function test_default_enabled_option_value() {
		$this->assertEquals( '1', c2c_YearsAgoToday::$enabled_option_value );
	}

	public function test_plugins_loaded_action_triggers_do_init() {
		$this->assertNotFalse( has_filter( 'plugins_loaded', array( 'c2c_YearsAgoToday', 'init' ) ) );
	}

	/**
	 * @dataProvider get_default_hooks
	 */
	public function test_default_hooks( $hook_type, $hook, $function, $priority, $class_method = true ) {
		$callback = $class_method ? array( 'c2c_YearsAgoToday', $function ) : $function;

		$prio = $hook_type === 'action' ?
			has_action( $hook, $callback ) :
			has_filter( $hook, $callback );

		$this->assertNotFalse( $prio );
		if ( $priority ) {
			$this->assertEquals( $priority, $prio );
		}
	}

	/*
	 * Cron
	 */

	public function test_cron_task_is_created() {
		c2c_YearsAgoToday::activate();

		$this->assertNotFalse( wp_next_scheduled( c2c_YearsAgoToday::$cron_name ) );
	}

	/*
	 * dashboard_setup()
	 */

	public function test_dashboard_setup() {
		global $wp_meta_boxes;

		include_once( ABSPATH . '/wp-admin/includes/dashboard.php' );
		include_once( ABSPATH . '/wp-admin/includes/template.php' );

		set_current_screen( 'index' );

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		wp_dashboard_setup();

		$this->assertNotNull( $wp_meta_boxes );
		$this->assertArrayHasKey( 'dashboard_years_ago_today', $wp_meta_boxes['dashboard']['normal']['core'] );
		$this->assertSame(
			array(
				'id' => 'dashboard_years_ago_today',
				'title' => 'Years Ago Today',
				'callback' => array( 'c2c_YearsAgoToday', 'wp_dashboard_years_ago_today' ),
				'args' => array( '__widget_basename' => 'Years Ago Today' )
			),
			$wp_meta_boxes['dashboard']['normal']['core']['dashboard_years_ago_today']
		);
	}

	public function test_dashboard_setup_when_not_on_dashboard() {
		global $wp_meta_boxes;

		include_once( ABSPATH . '/wp-admin/includes/dashboard.php' );
		include_once( ABSPATH . '/wp-admin/includes/template.php' );

		set_current_screen( 'post' );

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->assertNull( $wp_meta_boxes );
	}

	/*
	 * wp_dashboard_years_ago_today()
	 */

	public function test_shows_message_about_no_previous_year_posts() {
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = 'No posts were published on <strong>' . current_time( 'M jS' ) . '</strong> from any past year.';

		$this->expectOutputRegex( '~' . preg_quote( $expected ) . '~', c2c_YearsAgoToday::wp_dashboard_years_ago_today() );
	}

	public function test_shows_singular_message_with_single_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = '<strong>1</strong> post has been published on <strong>' . current_time( 'M jS' ) . '</strong> in a previous year:';

		$this->expectOutputRegex( '~' . preg_quote( $expected ) . '~', c2c_YearsAgoToday::wp_dashboard_years_ago_today() );
	}

	public function test_shows_plural_message_with_multiple_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = '<strong>2</strong> posts have been published on <strong>' . current_time( 'M jS' ) . '</strong> in previous years:';

		$this->expectOutputRegex( '~' . preg_quote( $expected ) . '~', c2c_YearsAgoToday::wp_dashboard_years_ago_today() );
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
	 * get_formatted_date_string()
	 */

	public function test_get_formatted_date_string() {
		$this->assertEquals( date_i18n( 'M jS', current_time( 'timestamp' ) ), c2c_YearsAgoToday::get_formatted_date_string() );
	}

	public function test_get_formatted_date_string_with_timestamp() {
		$timestamp = mysql2date( 'U', '2012-11-12' );

		$this->assertEquals( date_i18n( 'M jS', $timestamp ), c2c_YearsAgoToday::get_formatted_date_string( $timestamp ) );
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
				'No posts were published to the site %1$s on %2$s in any past year.',
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
			'1 post has been published to the site Test Blog on ' . current_time( 'M jS' ) . ' in a previous year:',
			c2c_YearsAgoToday::get_email_body()
		);
	}

	public function test_get_email_body_whole_email_with_single_matching_past_year_posts() {
		$post_title = 'A blast from the past';
		$post = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $this->get_date( '2012' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$email  = '1 post has been published to the site Test Blog on ' . current_time( 'M jS' ) . ' in a previous year:';
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
			'2 posts have been published to the site Test Blog on ' . current_time( 'M jS' ) . ' in previous years:',
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

		$email  = '2 posts have been published to the site Test Blog on ' . current_time( 'M jS' ) . ' in previous years:';
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
	 * add_admin_css()
	 */

	public function test_add_admin_css() {
		$this->assertFalse( has_action( 'admin_head', array( 'c2c_YearsAgoToday', 'admin_css' ) ) );

		c2c_YearsAgoToday::add_admin_css();

		$this->assertEquals( 10, has_action( 'admin_head', array( 'c2c_YearsAgoToday', 'admin_css' ) ) );
	}

	/*
	 * add_daily_email_optin_checkbox()
	 */

	public function test_add_daily_email_optin_checkbox() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$user = get_user_by( 'ID', $user_id );

		$expected = <<<HTML
		<table class="form-table">
		<tr>
			<th scope="row">"Years Ago Today" email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" disabled='disabled' />
					Email me daily about posts published on this day in years past.				</label>
			</td>
		</tr>
		</table>

HTML;

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user ) );
	}

	public function test_add_daily_email_optin_checkbox_when_checkbox_already_checked() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'c2c_years_ago_today_daily_email_optin', '1' );
		$user = get_user_by( 'ID', $user_id );

		$expected = <<<HTML
		<table class="form-table">
		<tr>
			<th scope="row">"Years Ago Today" email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" checked='checked' disabled='disabled' />
					Email me daily about posts published on this day in years past.				</label>
			</td>
		</tr>
		</table>

HTML;

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user ) );
	}

	public function test_add_daily_email_optin_checkbox_for_another_user_when_current_user_has_checkbox_checked() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'c2c_years_ago_today_daily_email_optin', '1' );
		$user2 = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );

		$expected = <<<HTML
		<table class="form-table">
		<tr>
			<th scope="row">"Years Ago Today" email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" disabled='disabled' />
					Email this user daily about posts published on this day in years past.				</label>
			</td>
		</tr>
		</table>

HTML;

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
	}

	public function test_add_daily_email_optin_checkbox_for_another_user_when_that_user_has_checkbox_checked() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		delete_user_option( $user_id, 'c2c_years_ago_today_daily_email_optin' );
		$user2 = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );
		update_user_option( $user2->ID, 'c2c_years_ago_today_daily_email_optin', '1' );

		$expected = <<<HTML
		<table class="form-table">
		<tr>
			<th scope="row">"Years Ago Today" email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" checked='checked' disabled='disabled' />
					Email this user daily about posts published on this day in years past.				</label>
			</td>
		</tr>
		</table>

HTML;

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
	}

	public function test_add_daily_email_optin_checkbox_for_another_user_when_current_user_cannot_edit_that_user() {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		$user2 = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );

		$this->assertEmpty( c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
	}

	/*
	 * admin_css()
	 */

	public function test_admin_css() {
		$expected = "<style>
			#dashboard-widgets .years-ago-today-posts h4 {
				font-weight: bold;
			}
			#dashboard-widgets .years-ago-today-posts li:not(.years-ago-today-year) {
				margin-left: 30px;
				list-style: initial;
			}
		</style>\n";

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::admin_css() );
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
