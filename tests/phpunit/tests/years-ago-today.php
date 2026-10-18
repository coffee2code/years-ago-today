<?php

defined( 'ABSPATH' ) or die();

class Years_Ago_Today_Test extends WP_UnitTestCase {

	private static $default_bcc_batch_size = 40;
	private static $default_bcc_to = '';
	private static $default_email_subject = '[Test Blog] Years Ago Today - %s';
	private static $default_title = 'Years Ago Today on Test Blog';

	private static $text_footer = '';
	private static $html_footer = '';

	private $ref;

	public static function setUpBeforeClass(): void {
		// Make all requests as if in the admin, which is the only place the plugin
		// affects.
		define( 'WP_ADMIN', true );

		// Re-initialize plugin now that WP_ADMIN is true.
		c2c_YearsAgoToday::init();

		self::$default_bcc_to = 'noreply@' . wp_parse_url( home_url(), PHP_URL_HOST );

		$profile_url = admin_url( 'profile.php' );

		self::$default_email_subject = sprintf( self::$default_email_subject, self::get_formatted_date( '', true ) );

		self::$text_footer = <<<HTML



-------------------------------
You received this email because you have opted into receiving a daily email about posts published on this day in previous years to the site Test Blog, which is using the Years Ago Today plugin.

If you wish to discontinue receiving these emails, simply log into the site and visit your profile at {$profile_url} to uncheck the checkbox labeled "Email me daily about posts published on this day in previous years."

HTML;

		self::$html_footer = <<<HTML
<p>You received this email because you have opted into receiving a daily email about posts published on this day in previous years to the site Test Blog, which is using the Years Ago Today plugin.</p>
<p>If you wish to discontinue receiving these emails, simply log into the site and visit your profile at <a href="{$profile_url}">{$profile_url}</a> to uncheck the checkbox labeled &quot;Email me daily about posts published on this day in previous years.&quot;</p>

HTML;

	}

	public function setUp(): void {
		parent::setUp();

		// Reflection for the private helper.
		$this->ref = new ReflectionMethod( 'c2c_YearsAgoToday', 'get_html_email_template' );
	}

	public function tearDown(): void {
		global $wp_meta_boxes;

		parent::tearDown();

		$wp_meta_boxes = NULL;
		delete_transient( c2c_YearsAgoToday::get_post_ids_cache_key() );

		remove_filter( 'gettext_years-ago-today', array( $this, 'translate_text' ) );
		remove_all_filters( 'c2c_years_ago_today-email-if-no-posts' );
		remove_all_filters( 'c2c_years_ago_today-email-body-no-posts' );

		wp_deregister_style( 'c2c-years-ago-today-admin-css' );
		wp_dequeue_style( 'c2c-years-ago-today-admin-css' );
		wp_deregister_script( 'c2c-years-ago-today-admin-js' );
		wp_dequeue_script( 'c2c-years-ago-today-admin-js' );
	}

	//
	// HELPER FUNCTIONS
	//


	private function get_date( $year = null, $today = true ) {
		if ( ! $year ) {
			$year = wp_date( 'Y' );
		}

		$date = $year . wp_date( '-m-d 13:00:04' );

		// If not requesting the current day, then offset a few days.
		if ( ! $today ) {
			$date = date( 'Y-m-d', strtotime( '-2 days', strtotime( $date ) ) );
		}

		return $date;
	}

	public static function get_formatted_date( $timestamp = '', $include_year = false ) {
		$date_format = $include_year ? 'F j, Y' : 'F jS';
		return $timestamp ? date_i18n( $date_format, $timestamp ) : wp_date( $date_format );
	}

	public function translate_text( $translation, $text ) {
		if ( '== %d ==' === $text ) {
			$translation = '~~~ < %d > ~~~';
		}

		return $translation;
	}

	public function get_full_html_body( $html_body ) {
		$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
	<meta name="viewport" content="width=device-width" />
	<meta charset="UTF-8" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>{{subject}}</title>
</head>
<body style="font-family:Arial,sans-serif;font-size:16px;color:#222;background:#fbfbfb;margin:0;padding:0;">
	<div class="container" style="max-width:600px;margin:20px auto;background:#fff;border:1px solid #eee;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.03);padding:32px 24px;">
		{{body}}
		<div class="footer" style="font-size:13px;color:#888;margin-top:48px;border-top:1px solid #eee;padding-top:16px;">{{footer}}</div>
	</div>
</body>
</html>
HTML;

		return str_replace(
			[ '{{subject}}', '{{body}}', '{{footer}}' ],
			[ self::$default_email_subject, $html_body, self::$html_footer ],
			$html
		);
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
			array( 'action', 'admin_enqueue_scripts',    'enqueue_admin_style',            10 ),
			array( 'action', 'save_post',                'clear_transient_on_publish',     10 ),
			array( 'action', 'load-profile.php',         'handle_email_template_preview',  10 ),
			array( 'action', 'init',                     'register_shortcode',             10 ),
			array( 'action', 'init',                     'register_block',                 10 ),
		);
	}


	//
	//
	// TESTS
	//
	//


	public function test_WP_RUNNING_TESTS() {
		$this->assertTrue( defined( 'WP_RUNNING_TESTS' ) );
		$this->assertTrue( WP_RUNNING_TESTS );
	}

	public function test_plugin_version() {
		$this->assertEquals( '2.0', c2c_YearsAgoToday::version() );
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
	 * clear_transient_on_publish()
	 */

	public function test_clear_transient_on_publish__fires_on_backdated_publish() {
		$ids = [ '15', '16' ];
		set_transient( c2c_YearsAgoToday::get_post_ids_cache_key(), $ids );

		$this->assertEquals( $ids, get_transient( c2c_YearsAgoToday::get_post_ids_cache_key() ) );

		$post = $this->factory->post->create_and_get( array( 'post_status' => 'publish', 'post_date' => $this->get_date( '2021' ) ) );

		$this->assertEmpty( get_transient( c2c_YearsAgoToday::get_post_ids_cache_key() ) );
	}

	public function test_clear_transient_on_publish__does_not_fire_on_publish_for_other_day() {
		$ids = [ '15', '16' ];
		set_transient( c2c_YearsAgoToday::get_post_ids_cache_key(), $ids );

		$this->assertEquals( $ids, get_transient( c2c_YearsAgoToday::get_post_ids_cache_key() ) );

		$post = $this->factory->post->create_and_get( array( 'post_status' => 'publish', 'post_date' => $this->get_date( '2021', false ) ) );

		$this->assertEquals( $ids, get_transient( c2c_YearsAgoToday::get_post_ids_cache_key() ) );
	}

	public function test_clear_transient_on_publish__does_not_fire_on_publish_for_today() {
		$ids = [ '15', '16' ];
		set_transient( c2c_YearsAgoToday::get_post_ids_cache_key(), $ids );

		$this->assertEquals( $ids, get_transient( c2c_YearsAgoToday::get_post_ids_cache_key() ) );

		$post = $this->factory->post->create_and_get( array( 'post_status' => 'publish', 'post_date' => $this->get_date() ) );

		$this->assertEquals( $ids, get_transient( c2c_YearsAgoToday::get_post_ids_cache_key() ) );
	}

	public function test_clear_transient_on_publish__when_explicitly_called() {
		$post = $this->factory->post->create_and_get( array( 'post_status' => 'publish', 'post_date' => $this->get_date( '2021' ) ) );
		c2c_YearsAgoToday::get_posts();

		$this->assertEquals( [ $post->ID ], get_transient( c2c_YearsAgoToday::get_post_ids_cache_key() ) );

		c2c_YearsAgoToday::clear_transient_on_publish( $post->ID, $post );

		$this->assertEmpty( get_transient( c2c_YearsAgoToday::get_post_ids_cache_key() ) );
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

		$expected = 'No posts were published on <strong>' . self::get_formatted_date() . '</strong> in any previous year.';

		$this->expectOutputRegex( '~' . preg_quote( $expected ) . '~', c2c_YearsAgoToday::wp_dashboard_years_ago_today() );
	}

	public function test_shows_singular_message_with_single_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = '<strong>1</strong> post has been published on <strong>' . self::get_formatted_date() . '</strong> in a previous year:';

		$this->expectOutputRegex( '~' . preg_quote( $expected ) . '~', c2c_YearsAgoToday::wp_dashboard_years_ago_today() );
	}

	public function test_shows_plural_message_with_multiple_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = '<strong>2</strong> posts have been published on <strong>' . self::get_formatted_date() . '</strong> in previous years:';

		$this->expectOutputRegex( '~' . preg_quote( $expected ) . '~', c2c_YearsAgoToday::wp_dashboard_years_ago_today() );
	}

	public function test_wp_dashboard_years_ago_today__full_ouput() {
		$post1_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$post2_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = sprintf(
			'<div class="years-ago-today-widget"><p><strong>2</strong> posts have been published on <strong>%s</strong> in previous years:</p><section class="years-ago-today-group" aria-labelledby="years-ago-today-year-2014"><h3 id="years-ago-today-year-2014" class="years-ago-today-year" role="heading" aria-level="3">2014</h3><ul class="years-ago-today-posts"><li><a href="%s">%s</a></li>
</ul></section>
<section class="years-ago-today-group" aria-labelledby="years-ago-today-year-2012"><h3 id="years-ago-today-year-2012" class="years-ago-today-year" role="heading" aria-level="3">2012</h3><ul class="years-ago-today-posts"><li><a href="%s">%s</a></li>
</ul></section>
</div>
',
			c2c_YearsAgoToday::get_formatted_date_string(),
			esc_url( get_permalink( $post2_id ) ),
			get_the_title( $post2_id ),
			esc_url( get_permalink( $post1_id ) ),
			get_the_title( $post1_id )
		);

		$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::wp_dashboard_years_ago_today() );
	}

	/*
	 * get_widget_markup()
	 */

	public function test_get_widget_markup_matches_dashboard_output() {
		$post1_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$post2_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = sprintf(
			'<div class="years-ago-today-widget"><p><strong>2</strong> posts have been published on <strong>%s</strong> in previous years:</p><section class="years-ago-today-group" aria-labelledby="years-ago-today-year-2014"><h3 id="years-ago-today-year-2014" class="years-ago-today-year" role="heading" aria-level="3">2014</h3><ul class="years-ago-today-posts"><li><a href="%s">%s</a></li>
</ul></section>
<section class="years-ago-today-group" aria-labelledby="years-ago-today-year-2012"><h3 id="years-ago-today-year-2012" class="years-ago-today-year" role="heading" aria-level="3">2012</h3><ul class="years-ago-today-posts"><li><a href="%s">%s</a></li>
</ul></section>
</div>
',
			c2c_YearsAgoToday::get_formatted_date_string(),
			esc_url( get_permalink( $post2_id ) ),
			get_the_title( $post2_id ),
			esc_url( get_permalink( $post1_id ) ),
			get_the_title( $post1_id )
		);

		$this->assertSame( $expected, c2c_YearsAgoToday::get_widget_markup() );
	}

	/*
	 * shortcode_years_ago_today()
	 */

	public function test_shortcode_is_registered() {
		global $shortcode_tags;

		$this->assertArrayHasKey( 'years-ago-today', $shortcode_tags );
		$this->assertSame( array( 'c2c_YearsAgoToday', 'shortcode_years_ago_today' ), $shortcode_tags['years-ago-today'] );
	}

	public function test_shortcode_outputs_widget_markup() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$expected_fragment = '<strong>1</strong> post has been published on <strong>' . self::get_formatted_date() . '</strong> in a previous year:';

		$output = do_shortcode( '[years-ago-today]' );

		$this->assertStringContainsString( '<h2 class="years-ago-today-title">Years Ago Today</h2>', $output );
		$this->assertStringContainsString( $expected_fragment, $output );
		$this->assertTrue( wp_style_is( 'c2c-years-ago-today', 'enqueued' ) );
	}

	public function test_shortcode_title_attribute() {
		$this->assertStringContainsString(
			'<h2 class="years-ago-today-title">On This Day</h2>',
			do_shortcode( '[years-ago-today title="On This Day"]' )
		);

		$this->assertStringNotContainsString(
			'years-ago-today-title',
			do_shortcode( '[years-ago-today title=""]' )
		);
	}

	/*
	 * register_block()
	 */

	public function test_block_is_registered() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( 'coffee2code/years-ago-today' ) );

		$block = $registry->get_registered( 'coffee2code/years-ago-today' );

		$this->assertSame( array( 'c2c_YearsAgoToday', 'render_block' ), $block->render_callback );
		$this->assertSame( 'c2c-years-ago-today', $block->style );
	}

	public function test_render_block_outputs_widget_markup() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$output = render_block(
			array(
				'blockName' => 'coffee2code/years-ago-today',
				'attrs'     => array(),
			)
		);

		$this->assertStringContainsString( 'years-ago-today-widget', $output );
		$this->assertStringContainsString( '<h2 class="years-ago-today-title">Years Ago Today</h2>', $output );
		$this->assertStringContainsString(
			'<strong>1</strong> post has been published on <strong>' . self::get_formatted_date() . '</strong> in a previous year:',
			$output
		);
		$this->assertTrue( wp_style_is( 'c2c-years-ago-today', 'enqueued' ) );
	}

	public function test_render_block_custom_title_attribute() {
		$output = render_block(
			array(
				'blockName' => 'coffee2code/years-ago-today',
				'attrs'     => array(
					'title' => 'Custom Heading',
				),
			)
		);

		$this->assertStringContainsString( '<h2 class="years-ago-today-title">Custom Heading</h2>', $output );
	}

	public function test_block_has_title_attribute() {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'coffee2code/years-ago-today' );

		$this->assertArrayHasKey( 'title', $block->attributes );
		$this->assertSame( 'string', $block->attributes['title']['type'] );
		$this->assertSame( 'Years Ago Today', $block->attributes['title']['default'] );
	}

	/*
	 * get_post_ids_cache_key()
	 */

	public function test_get_post_ids_cache_key__format() {
		$date = wp_date( 'Ymd' );
		$this->assertMatchesRegularExpression( '/^yat_1_20[0-9]{6}$/', c2c_YearsAgoToday::get_post_ids_cache_key() );
		$this->assertEquals( 'yat_1_' . $date, c2c_YearsAgoToday::get_post_ids_cache_key() );
	}

	/*
	 * query_post_ids()
	 */

	public function test_test_query_post_ids() {
		$post1_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$post2_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		$this->assertEquals( [ $post2_id, $post1_id ], c2c_YearsAgoToday::query_post_ids() );
	}

	public function test_query_post_ids__when_current_year_is_first_year() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		add_filter( 'c2c_years_ago_today-first_published_year', static fn () => wp_date( 'Y' ) );

		$this->assertEquals( wp_date( 'Y' ), c2c_YearsAgoToday::get_first_published_year() );
		$this->assertEmpty( c2c_YearsAgoToday::query_post_ids() );
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
		$this->assertEquals( self::get_formatted_date( wp_date( 'timestamp' ) ), c2c_YearsAgoToday::get_formatted_date_string() );
	}

	public function test_get_formatted_date_string_with_timestamp() {
		$timestamp = wp_date( 'U', '2012-11-12' );

		$this->assertEquals( self::get_formatted_date( $timestamp ), c2c_YearsAgoToday::get_formatted_date_string( $timestamp ) );
	}

	public function test_get_formatted_date_string_with_include_year() {
		$this->assertEquals( self::get_formatted_date( wp_date( 'timestamp' ), true ), c2c_YearsAgoToday::get_formatted_date_string( '', true ) );
	}

	/*
	 * get_first_published_year()
	 */

	public function test_get_first_published_year_with_no_posts() {
		$this->assertEquals( wp_date( 'Y' ), c2c_YearsAgoToday::get_first_published_year() );
	}

	public function test_get_first_published_year_with_posts() {
		$post1_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		$post2_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2013' ) ) );
		$post3_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2011' ) ) );
		$post4_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2010' ), 'post_status' => 'draft' ) );
		$post5_id = $this->factory->post->create( array( 'post_date' => $this->get_date( '2013', false ) ) );

		$this->assertEquals( '2011', c2c_YearsAgoToday::get_first_published_year() );
	}

	public function test_get_first_published_year_ignores_other_post_types() {
		$this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_date'  => $this->get_date( '2010' ),
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015' ) ) );

		wp_cache_delete( c2c_YearsAgoToday::get_first_published_year_cache_key(), c2c_YearsAgoToday::$cache_group );

		$this->assertEquals( '2015', c2c_YearsAgoToday::get_first_published_year() );
	}

	public function test_get_first_published_year_cache_key_varies_by_post_types() {
		$key_default = c2c_YearsAgoToday::get_first_published_year_cache_key();

		add_filter( 'c2c_years_ago_today-post_types', static fn() => array( 'post', 'page' ) );

		$this->assertNotSame( $key_default, c2c_YearsAgoToday::get_first_published_year_cache_key() );
	}

	/*
	 * get_optin_label()
	 */

	public function test_get_optin_label() {
		$this->assertEquals( 'Email me daily about posts published on this day in previous years.', c2c_YearsAgoToday::get_optin_label() );
	}

	public function test_get_optin_label_is_translatable() {
		add_filter( 'gettext_years-ago-today', static function ( $string ) { return "This string is translated."; } );

		$this->assertEquals( 'This string is translated.', c2c_YearsAgoToday::get_optin_label() );

		remove_all_filters( 'gettext_years-ago-today' );
	}

	/*
	 * get_email_subject()
	 */

	public function test_get_email_subject() {
		$this->assertEquals(
			self::$default_email_subject,
			c2c_YearsAgoToday::get_email_subject()
		);
	}

	/*
	 * get_email_body()
	 */

	public function test_get_email_body_with_no_posts() {
		$body = c2c_YearsAgoToday::get_email_body();

		$this->assertEmpty( $body['text'] );
		$this->assertEmpty( $body['html'] );
	}

	public function test_get_email_body_with_no_posts_but_email_forced() {
		add_filter( 'c2c_years_ago_today-email-if-no-posts', '__return_true' );

		$body = c2c_YearsAgoToday::get_email_body( 'list', false );

		$this->assertEquals(
			sprintf(
				'= Years Ago Today on Test Blog =' . "\n\n" . 'No posts were published to the site %1$s on %2$s in any previous year.',
				'Test Blog',
				self::get_formatted_date()
			),
			$body['text']
		);

		$this->assertStringContainsString(
			sprintf(
				'<p>No posts were published to the site %1$s on %2$s in any previous year.</p>',
				'Test Blog',
				self::get_formatted_date()
			),
			$body['html']
		);
	}

	public function test_get_email_body_shows_singular_message_with_single_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = '1 post has been published to the site Test Blog on ' . self::get_formatted_date() . ' in a previous year:';

		$body = c2c_YearsAgoToday::get_email_body( 'list', false );

		$this->assertStringContainsString(
			'= ' . self::$default_title . " =\n\n" . $expected,
			$body['text']
		);

		$this->assertStringContainsString(
			'<h2>' . self::$default_title . '</h2>' . "\n\n" . '<p>' . $expected . '</p>',
			$body['html']
		);
	}

	public function test_get_email_body_whole_email_with_single_matching_past_year_posts() {
		$post_title = 'A blast from the past';
		$post = $this->factory->post->create( array( 'post_title' => $post_title, 'post_date' => $this->get_date( '2012' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$message = '1 post has been published to the site Test Blog on ' . self::get_formatted_date() . ' in a previous year:';

		$text_email  = '= ' . self::$default_title . " =\n\n";
		$text_email .= $message;
		$text_email .= "\n\n== 2012 ==\n";
		$text_email .= "* {$post_title} : " . get_permalink( $post ) . "\n";

		$body = c2c_YearsAgoToday::get_email_body( 'list', false );

		$this->assertEquals(
			$text_email,
			$body['text']
		);

		$html_email  = '<h2>' . self::$default_title . '</h2>' . "\n\n";
		$html_email .= '<p>' . $message . "</p>\n\n";
		$html_email .= "<h3>2012</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post ) . '" rel="noopener noreferrer">' . $post_title . "</a></li>\n</ul>";

		$this->assertStringContainsString(
			$html_email,
			$body['html']
		);
	}

	public function test_get_email_body_shows_plural_message_with_multiple_matching_past_year_posts() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$expected = '2 posts have been published to the site Test Blog on ' . self::get_formatted_date() . ' in previous years:';

		$body = c2c_YearsAgoToday::get_email_body( 'list', false );

		$this->assertStringContainsString(
			'= ' . self::$default_title . " =\n\n" . $expected,
			$body['text']
		);

		$this->assertStringContainsString(
			'<h2>' . self::$default_title . '</h2>' . "\n\n" . '<p>' . $expected . '</p>',
			$body['html']
		);
	}

	public function test_get_email_body__with_multiple_matching_past_year_posts() {
		$post_title1 = 'A blast from the past';
		$post1 = $this->factory->post->create( array( 'post_title' => $post_title1, 'post_date' => $this->get_date( '2012' ) ) );
		$post_title2 = 'Days of future years past';
		$post2 = $this->factory->post->create( array( 'post_title' => $post_title2, 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$message = '2 posts have been published to the site Test Blog on ' . self::get_formatted_date() . ' in previous years:';

		$text_email  = '= ' . self::$default_title . " =\n\n";
		$text_email .= $message;
		$text_email .= "\n\n== 2014 ==\n";
		$text_email .= "* {$post_title2} : " . get_permalink( $post2 ) . "\n";
		$text_email .= "\n\n== 2012 ==\n";
		$text_email .= "* {$post_title1} : " . get_permalink( $post1 ) . "\n";

		$body = c2c_YearsAgoToday::get_email_body( 'list', false );

		$this->assertEquals(
			$text_email,
			$body['text']
		);

		$html_email  = '<h2>' . self::$default_title . '</h2>' . "\n\n";
		$html_email .= '<p>' . $message . "</p>\n\n";
		$html_email .= "<h3>2014</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post2 ) . '" rel="noopener noreferrer">' . $post_title2 . "</a></li>\n</ul>\n";
		$html_email .= "<h3>2012</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post1 ) . '" rel="noopener noreferrer">' . $post_title1 . "</a></li>\n</ul>";

		$this->assertStringContainsString(
			$html_email,
			$body['html']
		);
	}

	public function test_get_email_body__whole_email_with_multiple_matching_past_year_posts() {
		$post_title1 = 'A blast from the past';
		$post1 = $this->factory->post->create( array( 'post_title' => $post_title1, 'post_date' => $this->get_date( '2012' ) ) );
		$post_title2 = 'Days of future years past';
		$post2 = $this->factory->post->create( array( 'post_title' => $post_title2, 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$message = '2 posts have been published to the site Test Blog on ' . self::get_formatted_date() . ' in previous years:';

		$text_email  = '= ' . self::$default_title . " =\n\n";
		$text_email .= $message;
		$text_email .= "\n\n== 2014 ==\n";
		$text_email .= "* {$post_title2} : " . get_permalink( $post2 ) . "\n";
		$text_email .= "\n\n== 2012 ==\n";
		$text_email .= "* {$post_title1} : " . get_permalink( $post1 ) . "\n";

		$body = c2c_YearsAgoToday::get_email_body( 'list', true );

		$this->assertEquals(
			$text_email . self::$text_footer,
			$body['text']
		);

		$html_email  = '<h2>' . self::$default_title . '</h2>' . "\n\n";
		$html_email .= '<p>' . $message . "</p>\n\n";
		$html_email .= "<h3>2014</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post2 ) . '" rel="noopener noreferrer">' . $post_title2 . "</a></li>\n</ul>\n";
		$html_email .= "<h3>2012</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post1 ) . '" rel="noopener noreferrer">' . $post_title1 . "</a></li>\n</ul>";

		$this->assertStringContainsString(
			$html_email,
			$body['html']
		);
	}

	public function test_get_email_body_whole_email_with_multiple_matching_posts_in_a_single_year() {
		$post_title1 = 'A blast from the past';
		$post1 = $this->factory->post->create( array( 'post_title' => $post_title1, 'post_date' => $this->get_date( '2014' ) ) );
		$post_title2 = 'Days of future years past';
		$post2 = $this->factory->post->create( array( 'post_title' => $post_title2, 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$message = '2 posts have been published to the site Test Blog on ' . self::get_formatted_date() . ' in previous years:';

		$text_email  = '= ' . self::$default_title . " =\n\n";
		$text_email .= $message;
		$text_email .= "\n\n== 2014 ==\n";
		$text_email .= "* {$post_title1} : " . get_permalink( $post1 ) . "\n";
		$text_email .= "* {$post_title2} : " . get_permalink( $post2 ) . "\n";

		$body = c2c_YearsAgoToday::get_email_body( 'list', false );

		$this->assertEquals(
			$text_email,
			$body['text']
		);

		$html_email  = '<h2>' . self::$default_title . '</h2>' . "\n\n";
		$html_email .= '<p>' . $message . "</p>\n\n";
		$html_email .= "<h3>2014</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post1 ) . '" rel="noopener noreferrer">' . $post_title1 . "</a></li>\n";
		$html_email .= '<li><a href="' . get_permalink( $post2 ) . '" rel="noopener noreferrer">' . $post_title2 . "</a></li>\n</ul>";

		$this->assertStringContainsString(
			$html_email,
			$body['html']
		);
	}

	public function test_get_email_body_removes_html_from_titles() {
		$post_title1 = 'A <strong>blast</strong> from the past';
		$post1 = $this->factory->post->create( array( 'post_title' => $post_title1, 'post_date' => $this->get_date( '2012' ) ) );
		$post_title2 = 'Days of <em>future</em> years past';
		$post2 = $this->factory->post->create( array( 'post_title' => $post_title2, 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$email  = '= ' . self::$default_title . " =\n\n";
		$email .= '2 posts have been published to the site Test Blog on ' . self::get_formatted_date() . ' in previous years:';
		$email .= "\n\n== 2014 ==\n";
		$email .= "* " . wp_strip_all_tags( $post_title2 ) . " : " . get_permalink( $post2 ) . "\n";
		$email .= "\n\n== 2012 ==\n";
		$email .= "* " . wp_strip_all_tags( $post_title1 ) . " : " . get_permalink( $post1 ) . "\n";

		$body = c2c_YearsAgoToday::get_email_body( 'list', false );

		$this->assertEquals(
			$email,
			$body['text']
		);
	}

	public function test_get_email_body__allows_translation_of_year_headings_for_text_emails() {
		add_filter( 'gettext_years-ago-today', array( $this, 'translate_text' ), 10, 2 );

		$post_title1 = 'A <strong>blast</strong> from the past';
		$post1 = $this->factory->post->create( array( 'post_title' => $post_title1, 'post_date' => $this->get_date( '2012' ) ) );
		$post_title2 = 'Days of <em>future</em> years past';
		$post2 = $this->factory->post->create( array( 'post_title' => $post_title2, 'post_date' => $this->get_date( '2014' ) ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$email  = '= ' . self::$default_title . " =\n\n";
		$email .= '2 posts have been published to the site Test Blog on ' . self::get_formatted_date() . ' in previous years:';
		$email .= "\n\n~~~ < 2014 > ~~~\n";
		$email .= "* " . wp_strip_all_tags( $post_title2 ) . " : " . get_permalink( $post2 ) . "\n";
		$email .= "\n\n~~~ < 2012 > ~~~\n";
		$email .= "* " . wp_strip_all_tags( $post_title1 ) . " : " . get_permalink( $post1 ) . "\n";

		$this->assertEquals(
			$email,
			c2c_YearsAgoToday::get_email_body( 'list', false )['text']
		);
	}

	public function test_get_email_body__uses_wpautop_for_html() {
		add_filter( 'c2c_years_ago_today-email-if-no-posts', '__return_true' );
		add_filter( 'c2c_years_ago_today-email-body-no-posts', static fn() => "This is a paragraph.\n\nThis is another another one.\n\nAnd yet a third paragraph." );

		$html = c2c_YearsAgoToday::get_email_body( 'list', false )['html'];

		$this->assertSame( 3, substr_count( $html, '<p>' ) );
	}

	public function test_get_email_body__includes_excerpt_if_chosen() {
		$author_id = $this->factory->user->create( array( 'role' => 'author', 'display_name' => 'Certain Author' ) );

		$post_title1 = 'A blast from the past';
		$post1 = $this->factory->post->create( array( 'post_title' => $post_title1, 'post_date' => $this->get_date( '2012' ), 'post_author' => $author_id, 'post_excerpt' => 'This is an excerpt of some post content.', 'post_content' => 'This is some post content.' ) );
		$post_title2 = 'Days of future years past';
		$post2 = $this->factory->post->create( array( 'post_title' => $post_title2, 'post_date' => $this->get_date( '2014' ), 'post_author' => $author_id, 'post_excerpt' => 'This is an excerpt of another post content.', 'post_content' => 'This is another post content.' ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$message = '2 posts have been published to the site Test Blog on ' . self::get_formatted_date() . ' in previous years:';

		$body = c2c_YearsAgoToday::get_email_body( 'excerpt', false );

		$text_email  = '= ' . self::$default_title . " =\n\n";
		$text_email .= $message;
		$text_email .= "\n\n== 2014 ==\n";
		$text_email .= "* {$post_title2} : " . get_permalink( $post2 ) . "\n";
		$text_email .= "\n\n== 2012 ==\n";
		$text_email .= "* {$post_title1} : " . get_permalink( $post1 ) . "\n\n\n\n";
		$text_email .= "== Post Excerpts ==\n\n";
		$text_email .= "Excerpts of these posts follows. Formatting and markup have been removed. The full content is available on the site.\n\n\n";
		$text_email .= "==== {$post_title2} : " . get_permalink( $post2 ) . " ====\n";
		$text_email .= sprintf( "Published %s, 2014 by Certain Author\n\n", wp_date( 'F j' ) );
		$text_email .= "This is an excerpt of another post content.\n\n\n";
		$text_email .= "==== {$post_title1} : " . get_permalink( $post1 ) . " ====\n";
		$text_email .= sprintf( "Published %s, 2012 by Certain Author\n\n", wp_date( 'F j' ) );
		$text_email .= "This is an excerpt of some post content.\n\n\n";

		$this->assertEquals(
			$text_email,
			$body['text']
		);

		$h3_style = 'margin-bottom:8px;margin-top:30px;padding-top:30px;color:#eee;border-top:1px solid #eee;';
		$p_style = 'margin-top:0; font-size:smaller;';

		$html_email  = '<h2>' . self::$default_title . '</h2>' . "\n\n";
		$html_email .= '<p>' . $message . "</p>\n\n";
		$html_email .= "<h3>2014</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post2 ) . '" rel="noopener noreferrer">' . $post_title2 . "</a></li>\n</ul>\n";
		$html_email .= "<h3>2012</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post1 ) . '" rel="noopener noreferrer">' . $post_title1 . "</a></li>\n</ul>\n";
		$html_email .= '<h3 style="margin-top:50px;">Post Excerpts</h3>' . "\n";
		$html_email .= '<p style="font-size:smaller;"><em>Excerpts of these posts follows. The full content is available on the site. Note: Email clients may not properly render the formatting.</em></p>' . "\n";
		$html_email .= sprintf( '<h4 style="%s"><a href="%s" rel="noopener noreferrer">%s</a></h4>', esc_attr( $h3_style ), get_permalink( $post2 ), $post_title2 ) . "\n";
		$html_email .= sprintf( '<p style="%s">Published <strong>%s, 2014</strong> by <a href="%s">Certain Author</a></p>', esc_attr( $p_style ), wp_date( 'F j' ), esc_url( get_author_posts_url( $author_id ) ) );
		$html_email .= "\n\n";
		$html_email .= "<p>This is an excerpt of another post content.</p>\n";
		$html_email .= sprintf( '<h4 style="%s"><a href="%s" rel="noopener noreferrer">%s</a></h4>', esc_attr( $h3_style ), get_permalink( $post1 ), $post_title1 ) . "\n";
		$html_email .= sprintf( '<p style="%s">Published <strong>%s, 2012</strong> by <a href="%s">Certain Author</a></p>', esc_attr( $p_style ), wp_date( 'F j' ), esc_url( get_author_posts_url( $author_id ) ) );
		$html_email .= "\n\n";
		$html_email .= "<p>This is an excerpt of some post content.</p>\n";

		$this->assertStringContainsString(
			$html_email,
			$body['html']
		);
	}

	public function test_get_email_body__includes_full_content_if_chosen() {
		$author_id = $this->factory->user->create( array( 'role' => 'author', 'display_name' => 'Certain Author' ) );

		$post_title1 = 'A blast from the past';
		$post1 = $this->factory->post->create( array( 'post_title' => $post_title1, 'post_date' => $this->get_date( '2012' ), 'post_author' => $author_id, 'post_content' => 'This is some post content.' ) );
		$post_title2 = 'Days of future years past';
		$post2 = $this->factory->post->create( array( 'post_title' => $post_title2, 'post_date' => $this->get_date( '2014' ), 'post_author' => $author_id, 'post_content' => 'This is another post content.' ) );
		// Extra non-matching post
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2015', false ) ) );

		$message = '2 posts have been published to the site Test Blog on ' . self::get_formatted_date() . ' in previous years:';

		$text_email  = '= ' . self::$default_title . " =\n\n";
		$text_email .= $message;
		$text_email .= "\n\n== 2014 ==\n";
		$text_email .= "* {$post_title2} : " . get_permalink( $post2 ) . "\n";
		$text_email .= "\n\n== 2012 ==\n";
		$text_email .= "* {$post_title1} : " . get_permalink( $post1 ) . "\n\n\n\n";
		$text_email .= "== Posts ==\n\n";
		$text_email .= "The full content of these posts follows. Formatting and markup have been removed.\n\n\n";
		$text_email .= "==== {$post_title2} : " . get_permalink( $post2 ) . " ====\n";
		$text_email .= sprintf( "Published %s, 2014 by Certain Author\n\n", wp_date( 'F j' ) );
		$text_email .= "This is another post content.\n\n\n";
		$text_email .= "==== {$post_title1} : " . get_permalink( $post1 ) . " ====\n";
		$text_email .= sprintf( "Published %s, 2012 by Certain Author\n\n", wp_date( 'F j' ) );
		$text_email .= "This is some post content.\n\n\n";

		$body = c2c_YearsAgoToday::get_email_body( 'full', false );

		$this->assertEquals(
			$text_email,
			$body['text']
		);

		$h3_style = 'margin-bottom:8px;margin-top:30px;padding-top:30px;color:#eee;border-top:1px solid #eee;';
		$p_style = 'margin-top:0; font-size:smaller;';

		$html_email = '<h2>' . self::$default_title . '</h2>' . "\n\n";
		$html_email .= '<p>' . $message . "</p>\n\n";
		$html_email .= "<h3>2014</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post2 ) . '" rel="noopener noreferrer">' . $post_title2 . "</a></li>\n</ul>\n";
		$html_email .= "<h3>2012</h3>\n";
		$html_email .= '<ul><li><a href="' . get_permalink( $post1 ) . '" rel="noopener noreferrer">' . $post_title1 . "</a></li>\n</ul>\n";
		$html_email .= '<h3 style="margin-top:50px;">Posts</h3>' . "\n";
		$html_email .= '<p style="font-size:smaller;"><em>The full content of these posts follows. Note: Email clients may not properly display the formatting of the posts.</em></p>' . "\n";
		$html_email .= sprintf( '<h4 style="%s"><a href="%s" rel="noopener noreferrer">%s</a></h4>', esc_attr( $h3_style ), esc_url( get_permalink( $post2 ) ), $post_title2 ) . "\n";
		$html_email .= sprintf( '<p style="%s">Published <strong>%s, 2014</strong> by <a href="%s">Certain Author</a></p>', esc_attr( $p_style ), wp_date( 'F j' ), esc_url( get_author_posts_url( $author_id ) ) );
		$html_email .= "\n\n";
		$html_email .= "<p>This is another post content.</p>\n";
		$html_email .= sprintf( '<h4 style="%s"><a href="%s" rel="noopener noreferrer">%s</a></h4>', esc_attr( $h3_style ), esc_url( get_permalink( $post1 ) ), $post_title1 ) . "\n";
		$html_email .= sprintf( '<p style="%s">Published <strong>%s, 2012</strong> by <a href="%s">Certain Author</a></p>', esc_attr( $p_style ), wp_date( 'F j' ), esc_url( get_author_posts_url( $author_id ) ) );
		$html_email .= "\n\n";
		$html_email .= "<p>This is some post content.</p>\n";

		$this->assertStringContainsString(
			$html_email,
			$body['html']
		);
	}

	/*
	 * get_email_footer()
	 */

	public function test_get_email_footer__for_text() {
		$this->assertEquals( self::$text_footer, c2c_YearsAgoToday::get_email_footer( 'text' ) );
	}

	public function test_get_email_footer__invalid_format_treated_as_text() {
		$this->assertEquals( self::$text_footer, c2c_YearsAgoToday::get_email_footer( 'invalid' ) );
	}

	public function test_get_email_footer__for_html() {
		$this->assertEquals( self::$html_footer, c2c_YearsAgoToday::get_email_footer( 'html' ) );
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
			<th scope="row">&quot;Years Ago Today&quot; email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" aria-describedby="years-ago-today-explainer" />
					Email me daily about posts published on this day in previous years.
				</label>
				<p id="years-ago-today-explainer" class="description">If checked, you&#039;ll be sent one email a day that lists posts published on this calendar day in previous years. You can opt out at any time via this checkbox.</p>
				<fieldset id="years-ago-today-content-type" disabled aria-disabled="true"><legend class="screen-reader-text">Email content type</legend>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="list" checked='checked'> List &mdash; <span class="description">Just include the list of post titles, each linked to the post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="excerpt"> Excerpt &mdash; <span class="description">After the list of post titles, include an excerpt for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="full"> Full &mdash; <span class="description">After the list of post titles, include the full content for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=html&amp;force=1">HTML</a></span></label><br>
				</fieldset>
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
			<th scope="row">&quot;Years Ago Today&quot; email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" aria-describedby="years-ago-today-explainer" checked='checked' />
					Email me daily about posts published on this day in previous years.
				</label>
				<p id="years-ago-today-explainer" class="description">If checked, you&#039;ll be sent one email a day that lists posts published on this calendar day in previous years. You can opt out at any time via this checkbox.</p>
				<fieldset id="years-ago-today-content-type"><legend class="screen-reader-text">Email content type</legend>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="list" checked='checked'> List &mdash; <span class="description">Just include the list of post titles, each linked to the post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="excerpt"> Excerpt &mdash; <span class="description">After the list of post titles, include an excerpt for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="full"> Full &mdash; <span class="description">After the list of post titles, include the full content for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=html&amp;force=1">HTML</a></span></label><br>
				</fieldset>
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
			<th scope="row">&quot;Years Ago Today&quot; email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" aria-describedby="years-ago-today-explainer" />
					Email this user daily about posts published on this day in previous years.
				</label>
				<p id="years-ago-today-explainer" class="description">If checked, they&#039;ll be sent one email a day that lists posts published on this calendar day in previous years. They can opt out at any time via this checkbox on their profile.</p>
				<fieldset id="years-ago-today-content-type" disabled aria-disabled="true"><legend class="screen-reader-text">Email content type</legend>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="list" checked='checked'> List &mdash; <span class="description">Just include the list of post titles, each linked to the post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="excerpt"> Excerpt &mdash; <span class="description">After the list of post titles, include an excerpt for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="full"> Full &mdash; <span class="description">After the list of post titles, include the full content for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=html&amp;force=1">HTML</a></span></label><br>
				</fieldset>
			</td>
		</tr>
		</table>

HTML;

		if ( is_multisite() ) {
			$user = get_user_by( 'ID', $user_id );
			// In multisite, admins can't edit users.
			$this->assertEmpty( c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
			// Explicitly allow them to edit users.
			grant_super_admin( $user_id );
			$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
			revoke_super_admin( $user_id );
		} else {
			$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
		}

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
			<th scope="row">&quot;Years Ago Today&quot; email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" aria-describedby="years-ago-today-explainer" checked='checked' />
					Email this user daily about posts published on this day in previous years.
				</label>
				<p id="years-ago-today-explainer" class="description">If checked, they&#039;ll be sent one email a day that lists posts published on this calendar day in previous years. They can opt out at any time via this checkbox on their profile.</p>
				<fieldset id="years-ago-today-content-type"><legend class="screen-reader-text">Email content type</legend>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="list" checked='checked'> List &mdash; <span class="description">Just include the list of post titles, each linked to the post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="excerpt"> Excerpt &mdash; <span class="description">After the list of post titles, include an excerpt for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="full"> Full &mdash; <span class="description">After the list of post titles, include the full content for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=html&amp;force=1">HTML</a></span></label><br>
				</fieldset>
			</td>
		</tr>
		</table>

HTML;

		if ( is_multisite() ) {
			$user = get_user_by( 'ID', $user_id );
			// In multisite, admins can't edit users.
			$this->assertEmpty( c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
			// Explicitly allow them to edit users.
			grant_super_admin( $user_id );
			$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
			revoke_super_admin( $user_id );
		} else {
			$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
		}
	}

	public function test_add_daily_email_optin_checkbox_for_another_user_when_that_user_has_checkbox_checked_and_excerpts_chosen() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		delete_user_option( $user_id, 'c2c_years_ago_today_daily_email_optin' );
		$user2 = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );
		update_user_option( $user2->ID, c2c_YearsAgoToday::$option_name, '1' );
		update_user_option( $user2->ID, c2c_YearsAgoToday::$meta_email_content_pref, 'excerpt' );

		$expected = <<<HTML
		<table class="form-table">
		<tr>
			<th scope="row">&quot;Years Ago Today&quot; email</th>
			<td>
				<label for="c2c_years_ago_today_daily_email_optin">
					<input name="c2c_years_ago_today_daily_email_optin" type="checkbox" id="c2c_years_ago_today_daily_email_optin" value="1" aria-describedby="years-ago-today-explainer" checked='checked' />
					Email this user daily about posts published on this day in previous years.
				</label>
				<p id="years-ago-today-explainer" class="description">If checked, they&#039;ll be sent one email a day that lists posts published on this calendar day in previous years. They can opt out at any time via this checkbox on their profile.</p>
				<fieldset id="years-ago-today-content-type"><legend class="screen-reader-text">Email content type</legend>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="list"> List &mdash; <span class="description">Just include the list of post titles, each linked to the post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=list&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="excerpt" checked='checked'> Excerpt &mdash; <span class="description">After the list of post titles, include an excerpt for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=excerpt&amp;type=html&amp;force=1">HTML</a></span></label><br>
					<label><input type="radio" name="c2c_years_ago_today_email_content" value="full"> Full &mdash; <span class="description">After the list of post titles, include the full content for each post. Preview the email: <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=text&amp;force=1">Text</a> &bull; <a href="http://example.org/wp-admin/profile.php?preview-years-ago-today-email=1&amp;content-type=full&amp;type=html&amp;force=1">HTML</a></span></label><br>
				</fieldset>
			</td>
		</tr>
		</table>

HTML;

		if ( is_multisite() ) {
			$user = get_user_by( 'ID', $user_id );
			// In multisite, admins can't edit users.
			$this->assertEmpty( c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
			// Explicitly allow them to edit users.
			grant_super_admin( $user_id );
			$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
			revoke_super_admin( $user_id );
		} else {
			$this->expectOutputRegex( '~^' . preg_quote( $expected ) . '$~', c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
		}
	}

	public function test_add_daily_email_optin_checkbox_for_another_user_when_current_user_cannot_edit_that_user() {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		$user2 = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );

		$this->assertEmpty( c2c_YearsAgoToday::add_daily_email_optin_checkbox( $user2 ) );
	}

	/*
	 * enqueue_admin_style()
	 */

	 public function test_enqueue_admin_style__when_not_on_admin_index() {
		$key = 'c2c-years-ago-today-admin-css';

		$this->assertFalse( wp_style_is( $key, 'registered' ) );
		$this->assertFalse( wp_style_is( $key, 'enqueued' ) );

		c2c_YearsAgoToday::enqueue_admin_style( 'plugins.php' );

		$this->assertFalse( wp_style_is( $key, 'registered' ) );
		$this->assertFalse( wp_style_is( $key, 'enqueued' ) );
	}

	 public function test_enqueue_admin_style__when_on_admin_index() {
		$key = 'c2c-years-ago-today-admin-css';

		$this->assertFalse( wp_style_is( $key, 'registered' ) );
		$this->assertFalse( wp_style_is( $key, 'enqueued' ) );

		c2c_YearsAgoToday::enqueue_admin_style( 'index.php' );

		$this->assertTrue( wp_style_is( $key, 'registered' ) );
		$this->assertTrue( wp_style_is( $key, 'enqueued' ) );
	}

	public function test_enqueue_admin_style__when_on_profile() {
		$key = 'c2c-years-ago-today-admin-css';

		$this->assertFalse( wp_style_is( $key, 'registered' ) );
		$this->assertFalse( wp_style_is( $key, 'enqueued' ) );

		c2c_YearsAgoToday::enqueue_admin_style( 'profile.php' );

		$this->assertTrue( wp_style_is( $key, 'registered' ) );
		$this->assertTrue( wp_style_is( $key, 'enqueued' ) );
	}

	public function test_enqueue_admin_scripts__when_not_on_admin_index() {
		$key = 'c2c-years-ago-today-admin-js';

		$this->assertFalse( wp_script_is( $key, 'registered' ) );
		$this->assertFalse( wp_script_is( $key, 'enqueued' ) );

		c2c_YearsAgoToday::enqueue_admin_style( 'plugins.php' );

		$this->assertFalse( wp_script_is( $key, 'registered' ) );
		$this->assertFalse( wp_script_is( $key, 'enqueued' ) );
	}

	 public function test_enqueue_admin_scripts__when_on_admin_index() {
		$key = 'c2c-years-ago-today-admin-js';

		$this->assertFalse( wp_script_is( $key, 'registered' ) );
		$this->assertFalse( wp_script_is( $key, 'enqueued' ) );

		c2c_YearsAgoToday::enqueue_admin_style( 'index.php' );

		$this->assertFalse( wp_script_is( $key, 'registered' ) );
		$this->assertFalse( wp_script_is( $key, 'enqueued' ) );
	}

	public function test_enqueue_admin_scripts__when_on_profile() {
		$key = 'c2c-years-ago-today-admin-js';

		$this->assertFalse( wp_script_is( $key, 'registered' ) );
		$this->assertFalse( wp_script_is( $key, 'enqueued' ) );

		c2c_YearsAgoToday::enqueue_admin_style( 'profile.php' );

		$this->assertTrue( wp_script_is( $key, 'registered' ) );
		$this->assertTrue( wp_script_is( $key, 'enqueued' ) );
	}

	/*
	 * Filter: c2c_years_ago_today-email-body-no-posts
	 */

	public function test_filter_email_body_no_posts() {
		add_filter( 'c2c_years_ago_today-email-if-no-posts',  '__return_true' );
		add_filter( 'c2c_years_ago_today-email-body-no-posts', array( $this, 'email_body_no_posts' ) );

		$body = c2c_YearsAgoToday::get_email_body( 'list', false );

		$this->assertEquals(
			sprintf(
				'= ' . self::$default_title . " =\n\n" . 'Sorry, no posts were made on this day (%s) to %s in any prior year.',
				self::get_formatted_date(),
				'Test Blog'
			),
			$body['text']
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

	/*
	 * get_post_types()
	 */

	public function test_get_post_types__default() {
		$this->assertEquals( [ 'post' ], c2c_YearsAgoToday::get_post_types() );
	}

	/*
	 * filter: c2c_years_ago_today-post_types
	 */

	public function test_c2c_years_ago_today_post_types() {
		register_post_type( 'book', [ 'public' => true, 'name' => 'Book' ] );
		add_filter(
			'c2c_years_ago_today-post_types',
			static function ( $post_types ) {
				$post_types[] = 'book';
				return $post_types;
			}
		);

		$this->assertEquals( [ 'post', 'book' ], c2c_YearsAgoToday::get_post_types() );
	}

	public function test_c2c_years_ago_today_post_types__ignores_invalid_post_type() {
		add_filter(
			'c2c_years_ago_today-post_types',
			static function ( $post_types ) {
				$post_types[] = 'story';
				return $post_types;
			}
		);

		$this->assertEquals( [ 'post' ], c2c_YearsAgoToday::get_post_types() );
	}

	public function test_c2c_years_ago_today_post_types__uses_default_with_invalid_filter_value() {
		add_filter( 'c2c_years_ago_today-post_types', '__return_false' );

		$this->assertEquals( [ 'post' ], c2c_YearsAgoToday::get_post_types() );
	}

	/*
	 * get_html_email_image_size()
	 */

	public function test_get_html_email_image_size__default() {
		$this->assertEquals( 'medium', c2c_YearsAgoToday::get_html_email_image_size() );
	}

	public function test_get_html_email_image_size__uses_valid_explicit_size() {
		$this->assertEquals( 'thumbnail', c2c_YearsAgoToday::get_html_email_image_size( 'thumbnail' ) );
	}

	public function test_get_html_email_image_size__uses_default_if_explicit_invalid_size() {
		$this->assertEquals( 'medium', c2c_YearsAgoToday::get_html_email_image_size( 'invalid' ) );
	}

	/*
	 * filter: c2c_years_ago_today-html_email_image_size
	 */

	public function test_filter_c2c_years_ago_today_html_email_image_size__returns_valid_size() {
		add_filter( 'c2c_years_ago_today-html_email_image_size', static fn() => 'thumbnail' );

		$this->assertEquals( 'thumbnail', c2c_YearsAgoToday::get_html_email_image_size() );

		remove_all_filters( 'c2c_years_ago_today-html_email_image_size' );
	}

	public function test_filter_c2c_years_ago_today_html_email_image_size__returns_invalid_size() {
		add_filter( 'c2c_years_ago_today-html_email_image_size', static fn() => 'invalid' );

		$this->assertEquals( 'medium', c2c_YearsAgoToday::get_html_email_image_size() );

		remove_all_filters( 'c2c_years_ago_today-html_email_image_size' );
	}

	/*
	 * get_bcc_batch_size()
	 */

	public function test_get_bcc_batch_size__default() {
		$this->assertEquals( self::$default_bcc_batch_size, c2c_YearsAgoToday::get_bcc_batch_size() );
	}

	/*
	 * filter: c2c_years_ago_today-batch_size
	 */

	 public function test_filter_c2c_years_ago_today_batch_size__honors_valid_size() {
		add_filter( 'c2c_years_ago_today-batch_size', static fn() => 50 );

		$this->assertEquals( 50, c2c_YearsAgoToday::get_bcc_batch_size() );

		remove_all_filters( 'c2c_years_ago_today-batch_size' );
	}

	public function test_filter_c2c_years_ago_today_batch_size__uses_default_when_0() {
		add_filter( 'c2c_years_ago_today-batch_size', '__return_zero' );

		$this->assertEquals( self::$default_bcc_batch_size, c2c_YearsAgoToday::get_bcc_batch_size() );

		remove_all_filters( 'c2c_years_ago_today-batch_size' );
	}

	public function test_filter_c2c_years_ago_today_batch_size__uses_default_when_negative() {
		add_filter( 'c2c_years_ago_today-batch_size', static fn() => -30 );

		$this->assertEquals( self::$default_bcc_batch_size, c2c_YearsAgoToday::get_bcc_batch_size() );

		remove_all_filters( 'c2c_years_ago_today-batch_size' );
	}

	public function test_filter_c2c_years_ago_today_batch_size__uses_default_when_greater_than_100() {
		add_filter( 'c2c_years_ago_today-batch_size', static fn() => 101 );

		$this->assertEquals( self::$default_bcc_batch_size, c2c_YearsAgoToday::get_bcc_batch_size() );

		remove_all_filters( 'c2c_years_ago_today-batch_size' );
	}

	public function test_filter_c2c_years_ago_today_batch_size__uses_default_when_invalid() {
		add_filter( 'c2c_years_ago_today-batch_size', static fn() => 'invalid' );

		$this->assertEquals( self::$default_bcc_batch_size, c2c_YearsAgoToday::get_bcc_batch_size() );

		remove_all_filters( 'c2c_years_ago_today-batch_size' );
	}

	/*
	 * get_bcc_to_email_address()
	 */

	public function test_get_bcc_to_email_address__default() {
		$this->assertEquals( self::$default_bcc_to, c2c_YearsAgoToday::get_bcc_to_email_address() );
	}

	/*
	 * filter: c2c_years_ago_today-to_address
	 */

	public function test_filter_c2c_years_ago_today_to_address__uses_valid_email() {
		$email = 'admin@example.com';
		add_filter( 'c2c_years_ago_today-to_address', static function () use ( $email ) { return $email; } );

		$this->assertEquals( $email, c2c_YearsAgoToday::get_bcc_to_email_address() );

		remove_all_filters( 'c2c_years_ago_today-to_address' );
	}

	public function test_filter_c2c_years_ago_today_to_address__uses_default_if_invalid_email() {
		add_filter( 'c2c_years_ago_today-to_address', static fn() => 'invalid@somewhere' );

		$this->assertEquals( self::$default_bcc_to, c2c_YearsAgoToday::get_bcc_to_email_address() );

		remove_all_filters( 'c2c_years_ago_today-to_address' );
	}

	/*
	 * get_users_to_email_grouped_by_content_type()
	 */

	public function test_get_users_to_email_grouped_by_content_type__when_no_optins() {
		// User with explicit opt-out.
		$user_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => 'user1@example.org' ) );
		update_user_option( $user_id, 'c2c_years_ago_today_daily_email_optin', '0' );

		// User with implicit opt-out.
		$user_id2 = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => 'user2@example.org' ) );

		$this->assertEmpty( c2c_YearsAgoToday::get_users_to_email_grouped_by_content_type() );
	}

	public function test_get_users_to_email_grouped_by_content_type__when_user_has_explicit_content_type_but_not_opted_in() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => 'user1@example.org' ) );
		update_user_option( $user_id, c2c_YearsAgoToday::$meta_email_content_pref, 'excerpt' );

		$this->assertEmpty( c2c_YearsAgoToday::get_users_to_email_grouped_by_content_type() );
	}

	public function test_get_users_to_email_grouped_by_content_type__when_one_optin_with_presumed_default() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => 'user1@example.org' ) );
		wp_set_current_user( $user_id );
		update_user_option( $user_id, 'c2c_years_ago_today_daily_email_optin', '1' );

		$this->assertEquals( [ 'list' => [ 'user1@example.org' ] ], c2c_YearsAgoToday::get_users_to_email_grouped_by_content_type() );
	}

	public function test_get_users_to_email_grouped_by_content_type__when_optins_for_each_type() {
		$user1_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => 'user1@example.org' ) );
		wp_set_current_user( $user1_id );
		update_user_option( $user1_id, c2c_YearsAgoToday::$option_name, '1' );

		$user2_id = $this->factory->user->create( array( 'role' => 'author', 'user_email' => 'user2@example.org' ) );
		wp_set_current_user( $user2_id );
		update_user_option( $user2_id, c2c_YearsAgoToday::$option_name, '1' );
		update_user_option( $user2_id, c2c_YearsAgoToday::$meta_email_content_pref, 'full' );

		$user3_id = $this->factory->user->create( array( 'role' => 'author', 'user_email' => 'user3@example.org' ) );
		wp_set_current_user( $user3_id );
		update_user_option( $user3_id, c2c_YearsAgoToday::$option_name, '1' );
		update_user_option( $user3_id, c2c_YearsAgoToday::$meta_email_content_pref, 'excerpt' );

		$user4_id = $this->factory->user->create( array( 'role' => 'author', 'user_email' => 'user4@example.org' ) );
		wp_set_current_user( $user4_id );
		update_user_option( $user4_id, c2c_YearsAgoToday::$option_name, '1' );
		update_user_option( $user4_id, c2c_YearsAgoToday::$meta_email_content_pref, 'list' );

		$user5_id = $this->factory->user->create( array( 'role' => 'author', 'user_email' => 'user5@example.org' ) );
		wp_set_current_user( $user5_id );
		update_user_option( $user5_id, c2c_YearsAgoToday::$option_name, '0' );
		update_user_option( $user5_id, c2c_YearsAgoToday::$meta_email_content_pref, 'excerpt' );

		$expected = [
			'list' => [ 'user1@example.org', 'user4@example.org' ],
			'excerpt' => [ 'user3@example.org' ],
			'full' => [ 'user2@example.org' ],
		];

		$this->assertEquals( $expected, c2c_YearsAgoToday::get_users_to_email_grouped_by_content_type() );
	}

	/*
	 * option_save()
	 */

	public function test_option_save__saves_content_preference_when_opted_in() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$original_user_id = get_current_user_id();
		$original_post = $_POST;
		wp_set_current_user( $user_id );

		try {
			$_POST[ c2c_YearsAgoToday::$option_name ] = c2c_YearsAgoToday::$enabled_option_value;

			// Test both initial opt-in and changing preferences while already opted in.
			foreach ( array( 'full', 'excerpt', 'list' ) as $preference ) {
				$_POST[ c2c_YearsAgoToday::$meta_email_content_pref ] = $preference;
				c2c_YearsAgoToday::option_save( $user_id );

				$this->assertSame( $preference, get_user_option( c2c_YearsAgoToday::$meta_email_content_pref, $user_id ) );
				$this->assertSame( c2c_YearsAgoToday::$enabled_option_value, get_user_option( c2c_YearsAgoToday::$option_name, $user_id ) );
			}

			unset( $_POST[ c2c_YearsAgoToday::$option_name ] );
			c2c_YearsAgoToday::option_save( $user_id );
			$this->assertFalse( get_user_option( c2c_YearsAgoToday::$option_name, $user_id ) );
		} finally {
			$_POST = $original_post;
			wp_set_current_user( $original_user_id );
		}
	}

	/*
	 * get_user_email_content_pref()
	 */

	public function test_get_user_email_content_pref__implied_default() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->assertEquals( c2c_YearsAgoToday::$email_content_default, c2c_YearsAgoToday::get_user_email_content_pref( $user_id ) );
	}

	public function test_get_user_email_content_pref__valid_value() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		update_user_option( $user_id, c2c_YearsAgoToday::$meta_email_content_pref, 'excerpt' );

		$this->assertEquals( 'excerpt', c2c_YearsAgoToday::get_user_email_content_pref( $user_id ) );
	}

	public function test_get_user_email_content_pref__invalid_value_uses_default() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		update_user_option( $user_id, c2c_YearsAgoToday::$meta_email_content_pref, 'invalid' );

		$this->assertEquals( c2c_YearsAgoToday::$email_content_default, c2c_YearsAgoToday::get_user_email_content_pref( $user_id ) );
	}

	/** This is mostly to acknowledge existing behavior and not that the handling is ideal. */
	public function test_get_user_email_content_pref__invalid_user_uses_default() {
		$this->assertEquals( c2c_YearsAgoToday::$email_content_default, c2c_YearsAgoToday::get_user_email_content_pref( 99999 ) );
	}

	/*
	 * get_html_email()
	 */

	public function test_get_html_email__has_single_html_and_body_tags() {
		$subject = 'Test Subject';
		$body = '<p>Body content</p>';
		$footer = '<p>Footer content</p>';
		$html = c2c_YearsAgoToday::get_html_email($subject, $body, $footer);

		// Only one <html> and one </html>
		$this->assertEquals(1, substr_count($html, '<html>'));
		$this->assertEquals(1, substr_count($html, '</html>'));

		// Only one <body> and one </body>
		$this->assertEquals(1, substr_count($html, '<body style="'));
		$this->assertEquals(1, substr_count($html, '</body>'));

		// </html> is the last tag in the string (ignoring whitespace)
		$this->assertMatchesRegularExpression('/<\/html>\s*$/', $html);
	}

	public function test_get_html_email__has_meta_charset_and_title() {
		$subject = 'Test Subject';
		$body = '<p>Body content</p>';
		$footer = '<p>Footer content</p>';
		$html = c2c_YearsAgoToday::get_html_email($subject, $body, $footer);

		$this->assertMatchesRegularExpression('/<head>.*<meta charset="UTF-8" \/>.*<\/head>/s', $html);
		$this->assertMatchesRegularExpression('/<head>.*<meta http-equiv="Content-Type" content="text\/html; charset=UTF-8" \/>.*<\/head>/s', $html);
		$this->assertMatchesRegularExpression('/<head>.*<title>Test Subject<\/title>.*<\/head>/s', $html);
	}

	public function test_get_html_email__footer_is_inside_body_tag() {
		$subject = 'Test Subject';
		$body = '<p>Body content</p>';
		$footer = '<p id="footer-marker">Footer content</p>';
		$html = c2c_YearsAgoToday::get_html_email( $subject, $body, $footer );

		$footerPos = strpos( $html, $footer );
		$bodyClosePos = strpos( $html, '</body>' );

		$this->assertNotFalse( $footerPos, 'Footer not found in HTML' );
		$this->assertNotFalse( $bodyClosePos, 'No closing </body> tag found' );
		$this->assertLessThan( $bodyClosePos, $footerPos, 'Footer appears after </body>' );
	}

	public function test_get_html_email__is_well_formed() {
		$subject = 'Test Subject';
		$body = '<p>Body content</p>';
		$footer = '<p>Footer content</p>';
		$html = c2c_YearsAgoToday::get_html_email( $subject, $body, $footer );

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $html );
		$errors = libxml_get_errors();
		libxml_clear_errors();

		$this->assertEmpty( $errors, 'HTML is not well-formed: ' . print_r( $errors, true ) );
	}

	/*
	 * get_html_email_template()
	 */

	public function test_get_html_email_template__has_placeholders() {
		$template = $this->ref->invoke( null );

		$this->assertStringContainsString( '{{subject}}', $template );
		$this->assertStringContainsString( '{{body}}', $template );
		$this->assertStringContainsString( '{{footer}}', $template );
	}

	public function test_get_html_email_template__full_html_body() {
		$template = $this->ref->invoke( null );

		$this->assertStringContainsString( '<html>', $template );
		$this->assertStringContainsString( '</html>', $template );
		$this->assertStringContainsString( '<body style="', $template );
		$this->assertStringContainsString( '</body>', $template );
	}

	public function test_get_html_email_template__full_output() {
		$template = $this->ref->invoke( null );

		$expected = <<<HTML
<!DOCTYPE html>
<html>
<head>
	<meta name="viewport" content="width=device-width" />
	<meta charset="UTF-8" />
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>{{subject}}</title>
</head>
<body style="font-family:Arial,sans-serif;font-size:16px;color:#222;background:#fbfbfb;margin:0;padding:0;">
	<div class="container" style="max-width:600px;margin:20px auto;background:#fff;border:1px solid #eee;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.03);padding:32px 24px;">
		{{body}}
		<div class="footer" style="font-size:13px;color:#888;margin-top:48px;border-top:1px solid #eee;padding-top:16px;">{{footer}}</div>
	</div>
</body>
</html>
HTML;

		$this->assertEquals( $expected, $template );
	}

	/*
	 * handle_email_template_preview()
	 */

	/**
	 * Prepares admin profile screen context and preview query args.
	 *
	 * @param array $user_args  Args for user factory create().
	 * @param array $get_args   Additional $_GET query args.
	 */
	private function setup_email_preview_environment( $user_args = array( 'role' => 'subscriber' ), $get_args = array() ) {
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		$user_id = $this->factory->user->create( $user_args );
		wp_set_current_user( $user_id );

		set_current_screen( 'profile' );

		$_GET['preview-years-ago-today-email'] = '1';
		foreach ( $get_args as $key => $value ) {
			$_GET[ $key ] = $value;
		}
	}

	private function cleanup_email_preview_environment() {
		unset( $_GET['preview-years-ago-today-email'], $_GET['content-type'], $_GET['type'], $_GET['force'] );
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
	}

	private function capture_email_preview_output() {
		ob_start();
		try {
			c2c_YearsAgoToday::handle_email_template_preview();
		} finally {
			return ob_get_clean();
		}
	}

	public function test_handle_email_template_preview__bail_if_preview_query_arg_missing() {
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'profile' );

		$this->assertNull( c2c_YearsAgoToday::handle_email_template_preview() );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__bail_if_preview_query_arg_not_one() {
		$this->setup_email_preview_environment();
		$_GET['preview-years-ago-today-email'] = '0';

		$this->assertNull( c2c_YearsAgoToday::handle_email_template_preview() );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__bail_if_not_on_profile_screen() {
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'dashboard' );
		$_GET['preview-years-ago-today-email'] = '1';

		$this->assertNull( c2c_YearsAgoToday::handle_email_template_preview() );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__bail_if_user_not_logged_in() {
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		wp_set_current_user( 0 );
		set_current_screen( 'profile' );
		$_GET['preview-years-ago-today-email'] = '1';

		$this->assertNull( c2c_YearsAgoToday::handle_email_template_preview() );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__subscriber_can_preview_html_list() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$this->setup_email_preview_environment(
			array( 'role' => 'subscriber' ),
			array(
				'content-type' => 'list',
				'type'         => 'html',
			)
		);

		$output = $this->capture_email_preview_output();

		$this->assertStringContainsString( 'This is a preview of the HTML version of the Years Ago Today email.', $output );
		$this->assertStringContainsString( '<strong>Subject:</strong> ' . c2c_YearsAgoToday::get_email_subject(), $output );
		$this->assertStringContainsString( 'Return to profile', $output );
		$this->assertStringContainsString( admin_url( 'profile.php' ), $output );
		$this->assertStringContainsString( self::$default_title, $output );
		$this->assertStringContainsString( 'Note that there may be differences in how the email is displayed by different email clients.', $output );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__subscriber_can_preview_plaintext() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$this->setup_email_preview_environment(
			array( 'role' => 'subscriber' ),
			array(
				'content-type' => 'list',
				'type'         => 'text',
			)
		);

		$output = $this->capture_email_preview_output();

		$this->assertStringContainsString( 'This is a preview of the plain text version of the Years Ago Today email.', $output );
		$this->assertStringContainsString( 'class="text-container"', $output );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__excerpt_content_type_shows_excerpt_notice() {
		$this->setup_email_preview_environment(
			array( 'role' => 'subscriber' ),
			array(
				'content-type' => 'excerpt',
				'type'         => 'html',
			)
		);

		$output = $this->capture_email_preview_output();

		$this->assertStringContainsString( 'The email includes excerpts of each post.', $output );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__invalid_type_defaults_to_html() {
		$this->factory->post->create( array( 'post_date' => $this->get_date( '2012' ) ) );

		$this->setup_email_preview_environment(
			array( 'role' => 'subscriber' ),
			array(
				'content-type' => 'list',
				'type'         => 'invalid',
			)
		);

		$output = $this->capture_email_preview_output();

		$this->assertStringContainsString( 'This is a preview of the HTML version of the Years Ago Today email.', $output );
		$this->assertStringNotContainsString( 'class="text-container"', $output );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__invalid_content_type_uses_user_preference() {
		$this->setup_email_preview_environment( array( 'role' => 'subscriber' ), array( 'type' => 'html' ) );
		update_user_option( get_current_user_id(), c2c_YearsAgoToday::$meta_email_content_pref, 'excerpt' );
		$_GET['content-type'] = 'bogus';

		$output = $this->capture_email_preview_output();

		$this->assertStringContainsString( 'The email includes excerpts of each post.', $output );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__no_posts_without_force_shows_notice() {
		$this->setup_email_preview_environment(
			array( 'role' => 'subscriber' ),
			array(
				'content-type' => 'list',
				'type'         => 'html',
			)
		);

		$output = $this->capture_email_preview_output();

		$this->assertStringContainsString( 'No posts found for today. No email would be sent.', $output );

		$this->cleanup_email_preview_environment();
	}

	public function test_handle_email_template_preview__force_shows_body_when_no_posts() {
		$this->setup_email_preview_environment(
			array( 'role' => 'subscriber' ),
			array(
				'content-type' => 'list',
				'type'         => 'text',
				'force'        => '1',
			)
		);

		$output = $this->capture_email_preview_output();

		$this->assertStringContainsString( 'No posts were published to the site Test Blog on', $output );
		$this->assertStringNotContainsString( 'No posts found for today. No email would be sent.', $output );

		$this->cleanup_email_preview_environment();
	}

}
