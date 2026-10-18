<?php
/**
 * Unit tests for c2c_YearsAgoToday::cron_email().
 *
 * Uses the "pre_wp_mail" filter to capture every call to `wp_mail()` and short-
 * circuit actual delivery, so the suite runs without a mail server.
 *
 * @covers c2c_YearsAgoToday::cron_email
 */
class Test_Years_Ago_Today_Cron_Email extends WP_UnitTestCase {

	/** @var array[] Each captured wp_mail() call. */
	protected $sent = array();

	/* ----------------------------------------------------------------------
	 * setUp / tearDown
	 * --------------------------------------------------------------------*/

	 public function setUp(): void {
		parent::setUp();

		// Intercept every wp_mail() call.
		add_filter( 'pre_wp_mail', array( $this, 'intercept_mail' ), 10, 2 );

		// Predictable noreply address.
		add_filter( 'c2c_years_ago_today-to_address', static fn() => 'noreply@example.org' );

		// Send an email even if there are no posts to list.
		add_filter( 'c2c_years_ago_today-email-if-no-posts', '__return_true' );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'intercept_mail' ), 10 );
		remove_all_filters( 'c2c_years_ago_today-to_address' );
		remove_filter( 'gettext_years-ago-today', array( $this, 'translate_text' ) );

		$this->sent = array();
		parent::tearDown();
	}

	/* ----------------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------------*/

	 public function translate_text( $translation, $text ) {
		if ( '[%1$s] Years Ago Today - %2$s' === $text ) {
			$translation = '';
		}

		return $translation;
	}

	/** Capture + short-circuit wp_mail(). */
	public function intercept_mail( $null, $atts ) {
		// $atts is an associative array with keys: to, subject, message, headers, attachments
		$this->sent[] = $atts;
		return true; // stops real send
	}

	/** Create a user opted-in to Years Ago Today emails. */
	protected function create_subscribed_user( $email ) {
		$uid = $this->factory->user->create( array( 'user_email' => $email ) );
		update_user_option(
			$uid,
			c2c_YearsAgoToday::$option_name,
			c2c_YearsAgoToday::$enabled_option_value
		);
		return $uid;
	}

	/* ----------------------------------------------------------------------
	 * Tests
	 * --------------------------------------------------------------------*/

	public function test_no_subscribers_sends_nothing() {
		c2c_YearsAgoToday::cron_email();
		$this->assertCount( 0, $this->sent );
	}

	public function test_no_past_posts_sends_nothing_by_default() {
		// Undo the override to always send an email.
		remove_all_filters( 'c2c_years_ago_today-email-if-no-posts' );

		$this->create_subscribed_user( 'alice@example.org' );

		c2c_YearsAgoToday::cron_email();
		$this->assertCount( 0, $this->sent );
	}

	public function test_single_subscriber_direct_send() {
		$this->create_subscribed_user( 'alice@example.org' );

		c2c_YearsAgoToday::cron_email();

		$this->assertCount( 1, $this->sent );
		$mail = $this->sent[0];
		$this->assertSame( 'alice@example.org', $mail['to'] );
		$header_blob = implode( "\n", (array) $mail['headers'] );
		$this->assertStringNotContainsString( 'Bcc:', $header_blob );
		$this->assertStringContainsString( 'List-Unsubscribe:', $header_blob );
	}

	public function test_multi_subscriber_default_batch() {
		$emails = array(
			'alice@example.org',
			'bob@example.org',
			'carl@example.org',
			'dan@example.org',
			'eva@example.org',
		);
		array_map( array( $this, 'create_subscribed_user' ), $emails );

		c2c_YearsAgoToday::cron_email();

		$this->assertCount( 1, $this->sent );
		$mail = $this->sent[0];

		// Neutral To: address.
		$this->assertSame( 'noreply@example.org', $mail['to'] );

		$header_blob = implode( "\n", (array) $mail['headers'] );
		foreach ( $emails as $e ) {
			$this->assertStringContainsString( $e, $header_blob );
		}
		$this->assertStringContainsString( 'Bcc:',              $header_blob );
		$this->assertStringContainsString( 'List-Unsubscribe:', $header_blob );
	}

	public function test_custom_batch_size_splits_email() {
		array_map( array( $this, 'create_subscribed_user' ), array_map( static function ( $name ) { return "{$name}@example.org"; }, range( 'a', 'e' ) ) );

		add_filter( 'c2c_years_ago_today-batch_size', fn() => 2 );

		c2c_YearsAgoToday::cron_email();

		remove_all_filters( 'c2c_years_ago_today-batch_size' );

		$this->assertCount( 3, $this->sent );

		// First two mails must contain Bcc with exactly 2 addresses.
		for ( $i = 0; $i < 2; $i ++ ) {
			$hdr = implode( "\n", (array) $this->sent[ $i ]['headers'] );
			$this->assertSame( 2, substr_count( $hdr, '@' ) );
			$this->assertStringContainsString( 'Bcc:', $hdr );
		}
	}

	public function test_multipart_headers_present() {
		$this->create_subscribed_user( 'alice@example.org' );
		c2c_YearsAgoToday::cron_email();

		$mail = $this->sent[0];
		$hdr  = implode( "\n", (array) $mail['headers'] );

		$this->assertStringContainsString( 'Content-type: text/html', $hdr );
		$this->assertStringContainsString( '<html>',                  $mail['message'] );
		$this->assertStringContainsString( '</html>',                 $mail['message'] );
	}

	public function test_subject() {
		$this->create_subscribed_user( 'alice@example.org' );
		c2c_YearsAgoToday::cron_email();

		$mail = $this->sent[0];

		$this->assertEquals( '[Test Blog] Years Ago Today - ' . wp_date( 'F j, Y' ), $mail['subject'] );
	}

	public function test_multipart_bodies_are_populated() {
		// Temporarily disable the short-circuit.
		remove_filter( 'pre_wp_mail', array( $this, 'intercept_mail' ), 10 );

		// Simulate the short-circuit to capture the same data, but without aborting.
		add_filter( 'wp_mail', function ( $atts ) {
			$this->sent[] = $atts;
			return $atts;
		}, 10, 1 );

		$user_id = $this->create_subscribed_user( 'alice@example.org' );

		$captured = array();
		$spy      = static function ( $phpmailer ) use ( &$captured ) {
			$captured[] = array(
				'html' => $phpmailer->Body,
				'text' => $phpmailer->AltBody,
			);

			// Prevent any attempt to actually send.
			$phpmailer->clearAllRecipients();
		};

		// Hook after the helper’s own hook to capture the final values.
		add_action( 'phpmailer_init', $spy, 20, 1 );

		c2c_YearsAgoToday::cron_email();

		remove_action( 'phpmailer_init', $spy, 20 );
		// Restore short-circuit.
		add_filter( 'pre_wp_mail', array( $this, 'intercept_mail' ), 10, 2 );

		$this->assertNotEmpty( $captured, 'No PHPMailer instance captured' );

		$mail = $captured[0];

		// HTML part should look like HTML.
		$this->assertStringContainsString( '<html>',  $mail['html'] );
		$this->assertStringContainsString( '</html>', $mail['html'] );

		// Plain-text should *not* contain any tags.
		$this->assertStringNotContainsString( '<html>', $mail['text'] );
		$this->assertStringNotContainsString( '</html>', $mail['text'] );

		// Both parts should contain the footer opt-out.
		$footer_snippet = 'wish to discontinue receiving these emails';
		$this->assertStringContainsString( $footer_snippet, $mail['html'] );
		$this->assertStringContainsString( $footer_snippet, $mail['text'] );
	}

	/*
	 * send_email_of_type()
	 */

	 public function test_send_email_of_type__when_no_one_to_email() {
		$this->assertEquals( 0, c2c_YearsAgoToday::send_email_of_type( 'list', [] ) );
	}

	public function test_send_email_of_type__when_no_subject() {
		add_filter( 'gettext_years-ago-today', array( $this, 'translate_text' ), 10, 2 );

		$this->assertEquals( 0, c2c_YearsAgoToday::send_email_of_type( 'list', [ 'text@example.org' ] ) );
	}

	public function test_send_email_of_type__when_nothing_to_email() {
		// Undo the override to always send an email.
		remove_all_filters( 'c2c_years_ago_today-email-if-no-posts' );

		$this->assertEquals( 0, c2c_YearsAgoToday::send_email_of_type( 'list', [ 'text@example.org' ] ) );
	}

	public function test_send_email_of_type__when_something_to_email() {
		$this->assertEquals( 1, c2c_YearsAgoToday::send_email_of_type( 'list', [ 'text@example.org' ] ) );
	}

	public function test_send_email_of_type__when_something_to_email_multiple_addresses() {
		$this->assertEquals( 3, c2c_YearsAgoToday::send_email_of_type( 'list', [ 'text1@example.org', 'text2@example.org', 'text3@example.org' ] ) );
	}


	public function test_send_email_of_type__failed_transport_returns_zero() {
		$blocked = static function () { return false; };
		add_filter( 'pre_wp_mail', $blocked, 20 );
		try {
			$this->assertSame( 0, c2c_YearsAgoToday::send_email_of_type( 'list', array( 'text@example.org' ) ) );
		} finally {
			remove_filter( 'pre_wp_mail', $blocked, 20 );
		}
	}

	public function test_send_email_of_type__counts_only_successful_batches() {
		$batch_size = static function () { return 1; };
		$transport = static function ( $result, $args ) { return 'text2@example.org' !== $args['to']; };
		add_filter( 'c2c_years_ago_today-batch_size', $batch_size );
		add_filter( 'pre_wp_mail', $transport, 20, 2 );
		try {
			$this->assertSame( 2, c2c_YearsAgoToday::send_email_of_type( 'list', array( 'text1@example.org', 'text2@example.org', 'text3@example.org' ) ) );
		} finally {
			remove_filter( 'c2c_years_ago_today-batch_size', $batch_size );
			remove_filter( 'pre_wp_mail', $transport, 20 );
		}
	}

	public function test_send_email_of_type__when_invalid_email_content_type() {
		$this->assertEquals( 1, c2c_YearsAgoToday::send_email_of_type( 'invalid', [ 'text@example.org' ] ) );
	}

}
