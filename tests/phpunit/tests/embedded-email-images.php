<?php

class Test_Years_Ago_Today_Embedded_Images extends WP_UnitTestCase {

	private $image_path;
	private $image_url;

	public function setUp(): void {
		parent::setUp();
		$upload = wp_upload_bits( 'yat-inline-test.jpg', null, file_get_contents( DIR_TESTDATA . '/images/canola.jpg' ) );
		$this->assertFalse( $upload['error'] );
		$this->image_path = $upload['file'];
		$this->image_url = $upload['url'];
	}

	public function tearDown(): void {
		unlink( $this->image_path );
		parent::tearDown();
	}

	private function embed( $html ) {
		$method = new ReflectionMethod( 'c2c_YearsAgoToday', 'get_embedded_email_images' );
		$method->setAccessible( true );
		return $method->invoke( null, $html );
	}

	public function test_embeds_local_images_once_and_removes_remote_sources() {
		$tag = '<img src="' . esc_url( $this->image_url ) . '" srcset="other.jpg 2x" sizes="100vw" loading="lazy" alt="Photo" width="300">';
		$result = $this->embed( $tag . $tag );

		$this->assertCount( 1, $result['images'] );
		$cid = array_keys( $result['images'] )[0];
		$this->assertSame( 2, substr_count( $result['html'], 'src="cid:' . $cid . '"' ) );
		$this->assertSame( realpath( $this->image_path ), $result['images'][ $cid ]['path'] );
		$this->assertSame( 'image/jpeg', $result['images'][ $cid ]['mime'] );
		$this->assertStringContainsString( 'alt="Photo"', $result['html'] );
		$this->assertStringContainsString( 'width="300"', $result['html'] );
		$this->assertStringNotContainsString( 'srcset=', $result['html'] );
		$this->assertStringNotContainsString( 'sizes=', $result['html'] );
		$this->assertStringNotContainsString( 'loading=', $result['html'] );
	}


	public function test_embeds_protocol_relative_local_images() {
		$url = preg_replace( '/^https?:/', '', $this->image_url );
		$result = $this->embed( '<img src="' . esc_url( $url ) . '">' );

		$this->assertCount( 1, $result['images'] );
		$this->assertStringContainsString( 'src="cid:', $result['html'] );
	}

	public function test_keeps_mismatched_explicit_protocols_and_external_hosts_remote() {
		$scheme = wp_parse_url( $this->image_url, PHP_URL_SCHEME );
		if ( 'https' === $scheme ) {
			$url = 'http' . substr( $this->image_url, strlen( $scheme ) );
		} else {
			$url = 'https' . substr( $this->image_url, strlen( $scheme ) );
		}
		$html = '<img src="' . esc_url( $url ) . '"><img src="//example.org/photo.jpg">';
		$result = $this->embed( $html );

		$this->assertSame( $html, $result['html'] );
		$this->assertSame( array(), $result['images'] );
	}

	public function test_leaves_external_and_missing_images_unchanged() {
		$html = '<img src="https://example.org/photo.jpg"><img src="' . esc_url( $this->image_url . '.missing' ) . '">';
		$result = $this->embed( $html );

		$this->assertSame( $html, $result['html'] );
		$this->assertSame( array(), $result['images'] );
	}

	public function test_does_not_embed_files_outside_uploads() {
		$uploads = wp_upload_dir();
		$outside = dirname( $uploads['basedir'] ) . '/yat-outside.jpg';
		copy( $this->image_path, $outside );
		try {
			$html = '<img src="' . esc_url( $uploads['baseurl'] . '/%2e%2e/yat-outside.jpg' ) . '">';
			$result = $this->embed( $html );
			$this->assertSame( $html, $result['html'] );
			$this->assertSame( array(), $result['images'] );
		} finally {
			unlink( $outside );
		}
	}

	public function test_saves_and_unchecks_embedding_preference() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$original_user = get_current_user_id();
		$original_post = $_POST;
		wp_set_current_user( $user_id );
		try {
			$_POST = array(
				c2c_YearsAgoToday::$option_name => c2c_YearsAgoToday::$enabled_option_value,
				c2c_YearsAgoToday::$meta_email_content_pref => 'full',
				c2c_YearsAgoToday::$meta_email_embed_images => '1',
			);
			c2c_YearsAgoToday::option_save( $user_id );
			$this->assertEquals( 1, get_user_option( c2c_YearsAgoToday::$meta_email_embed_images, $user_id ) );
			$this->assertSame( 'full', c2c_YearsAgoToday::get_user_email_content_pref( $user_id ) );

			unset( $_POST[ c2c_YearsAgoToday::$meta_email_embed_images ] );
			c2c_YearsAgoToday::option_save( $user_id );
			$this->assertFalse( get_user_option( c2c_YearsAgoToday::$meta_email_embed_images, $user_id ) );
			$this->assertSame( c2c_YearsAgoToday::$enabled_option_value, get_user_option( c2c_YearsAgoToday::$option_name, $user_id ) );
		} finally {
			$_POST = $original_post;
			wp_set_current_user( $original_user );
		}
	}

	public function test_send_embeds_matching_content_ids_and_resets_between_sends() {
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		$original_mailer = null;
		if ( isset( $GLOBALS['phpmailer'] ) ) {
			$original_mailer = $GLOBALS['phpmailer'];
		}
		$mailer = new class extends \PHPMailer\PHPMailer\PHPMailer {
			public $messages = array();
			public function send() {
				$this->preSend();
				$this->messages[] = array(
					'html' => $this->Body,
					'plain' => $this->AltBody,
					'mime' => $this->getSentMIMEMessage(),
					'attachments' => $this->getAttachments(),
				);
				return true;
			}
		};
		$GLOBALS['phpmailer'] = $mailer;
		$this->factory->post->create( array(
			'post_status' => 'publish',
			'post_date' => ( wp_date( 'Y' ) - 1 ) . wp_date( '-m-d 12:00:00' ),
			'post_content' => '<p><img src="' . esc_url( $this->image_url ) . '"></p>',
		) );
		delete_transient( c2c_YearsAgoToday::get_post_ids_cache_key() );

		try {
			c2c_YearsAgoToday::send_email_of_type( 'full', array( 'embedded@example.org' ), true );
			c2c_YearsAgoToday::send_email_of_type( 'full', array( 'remote@example.org' ) );
			$this->assertCount( 2, $mailer->messages );
			$embedded = $mailer->messages[0];
			$this->assertCount( 1, $embedded['attachments'] );
			$cid = $embedded['attachments'][0][7];
			$this->assertStringContainsString( 'src="cid:' . $cid . '"', $embedded['html'] );
			$this->assertStringContainsString( 'Content-ID: <' . $cid . '>', $embedded['mime'] );
			$this->assertStringContainsString( 'Content-Disposition: inline;', $embedded['mime'] );
			$this->assertStringContainsString( 'multipart/related;', $embedded['mime'] );
			$this->assertSame( array(), $mailer->messages[1]['attachments'] );
			$this->assertStringNotContainsString( 'cid:', $mailer->messages[1]['html'] );
			$this->assertSame( $embedded['plain'], $mailer->messages[1]['plain'] );
		} finally {
			$GLOBALS['phpmailer'] = $original_mailer;
			delete_transient( c2c_YearsAgoToday::get_post_ids_cache_key() );
		}
	}

	public function test_groups_embedded_and_remote_recipients_separately() {
		$remote = $this->factory->user->create( array( 'user_email' => 'remote@example.org' ) );
		$embedded = $this->factory->user->create( array( 'user_email' => 'embedded@example.org' ) );
		foreach ( array( $remote, $embedded ) as $user_id ) {
			update_user_option( $user_id, c2c_YearsAgoToday::$option_name, c2c_YearsAgoToday::$enabled_option_value );
			update_user_option( $user_id, c2c_YearsAgoToday::$meta_email_content_pref, 'full' );
		}
		update_user_option( $embedded, c2c_YearsAgoToday::$meta_email_embed_images, 1 );

		$this->assertSame( array( 'full' => array( 'remote@example.org', 'embedded@example.org' ) ), c2c_YearsAgoToday::get_users_to_email_grouped_by_content_type() );
		$this->assertSame( array( 'full' => array( 'remote@example.org' ), 'full:embedded' => array( 'embedded@example.org' ) ), c2c_YearsAgoToday::get_users_to_email_grouped_by_content_type( true ) );
	}
}
