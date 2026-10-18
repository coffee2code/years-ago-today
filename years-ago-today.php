<?php
/**
 * Plugin Name: Years Ago Today
 * Version:     2.0
 * Plugin URI:  https://coffee2code.com/wp-plugins/years-ago-today/
 * Author:      Scott Reilly
 * Author URI:  https://coffee2code.com/
 * Text Domain: years-ago-today
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Lists posts published to your site on this day in previous years via admin dashboard widget, block, shortcode, and/or optional daily email.
 *
 * Compatible with WordPress 5.6 through 7.0+, and PHP through at least 8.5+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://wordpress.org/plugins/years-ago-today/
 *
 * @package Years_Ago_Today
 * @author  Scott Reilly
 * @version 2.0
 */

/*
	Copyright (c) 2015-2026 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'c2c_YearsAgoToday' ) ) :

class c2c_YearsAgoToday {

	/**
	 * Meta key name for flag to indicate if user has opted into being notified of
	 * posts published on that day in previous years.
	 *
	 * @var string
	 * @access public
	 */
	public static $option_name = 'c2c_years_ago_today_daily_email_optin';

	/**
	 * User meta key name for user's choice of email content type.
	 *
	 * @since 2.0
	 * @var string
	 * @access public
	 */
	public static $meta_email_content_pref = 'c2c_years_ago_today_email_content';

	/**
	 * User meta key for embedding uploaded images in HTML emails.
	 *
	 * @var string
	 */
	public static $meta_email_embed_images = 'c2c_years_ago_today_email_embed_images';

	/**
	 * The default email content type.
	 *
	 * One of either 'list', 'excerpt', or 'full'. See `get_email_content_types()` for actual acceptable values.
	 *
	 * @since 2.0
	 * @var string
	 * @access public
	 */
	public static $email_content_default = 'list';

	/**
	 * Name for the cron task to send out the daily email.
	 *
	 * @var string
	 * @access public
	 */
	public static $cron_name = 'c2c_years_ago_daily_cron';

	/**
	 * Name for the cache group for caching plugin values.
	 *
	 * @var string
	 * @access public
	 */
	public static $cache_group = 'c2c_years_ago_today';

	/**
	 * Default value for the meta value to indicate the user wants the daily email
	 * of posts published on that day in previous years.
	 *
	 * @var string
	 * @access public
	 */
	public static $enabled_option_value = '1';

	/**
	 * Prevents instantiation.
	 *
	 * @since 1.2
	 */
	private function __construct() {}

	/**
	 * Prevents unserializing an instance.
	 *
	 * @since 1.2
	 * @since 1.5 Changed to public.
	 */
	public function __wakeup() {}

	/**
	 * Returns version of the plugin.
	 *
	 * @since 1.0
	 */
	public static function version() {
		return '2.0';
	}

	/**
	 * Hooks actions and filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
		if ( is_multisite() ) {
			wp_cache_add_global_groups( self::$cache_group );
		}

		/* Register hooks. */

		// Register dashboard widget.
		add_action( 'wp_dashboard_setup',       array( __CLASS__, 'dashboard_setup' ) );

		// Adds the checkbox to user profiles.
		add_action( 'personal_options',         array( __CLASS__, 'add_daily_email_optin_checkbox' ) );

		// Saves the user preference for daily emails.
		add_action( 'personal_options_update',  array( __CLASS__, 'option_save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'option_save' ) );

		// Enqueue CSS and JS in the admin.
		add_action( 'admin_enqueue_scripts',    array( __CLASS__, 'enqueue_admin_style' ) );

		// Maybe clear transients when a post gets published.
		add_action( 'save_post',                array( __CLASS__, 'clear_transient_on_publish' ), 10, 2 );

		// Handle previewing the email template when viewing user profile page.
		add_action( 'load-profile.php',         array( __CLASS__, 'handle_email_template_preview' ) );

		// Register shortcode and block for front-end display.
		add_action( 'init',                     array( __CLASS__, 'register_shortcode' ) );
		add_action( 'init',                     array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Initializes things necessary for the cron job.
	 *
	 * @since 1.5
	 */
	public static function cron_init() {
		// Register cron task.
		add_action( self::$cron_name, array( __CLASS__, 'cron_email' ) );
	}

	/**
	 * Handles activation tasks.
	 *
	 * @since 1.0
	 */
	public static function activate() {
		// Bail if cron task is already scheduled.
		if ( wp_next_scheduled( self::$cron_name ) ) {
			return;
		}

		$default_time_string = '9:00 am';

		/**
		 * Filters the time of the day that the daily Years Ago Today email is sent.
		 *
		 * @since 2.0
		 *
		 * @param string $time The time of day to email the Years Ago Today email to
		 *                     those who have opted-in to it. Default "9:00 am".
		 */
		$time_string = apply_filters( 'c2c_years_ago_today-email_cron_time', $default_time_string );

		// Get the site’s TZ.
		$tz = wp_timezone();

		// Parse the time in that TZ.
		$dt = date_create_immutable( $time_string, $tz );

		// Use default if the string couldn’t be parsed for some reason.
		if ( false === $dt ) {
			$dt = new DateTimeImmutable( $default_time_string, $tz );
		}

		// If the desired send time has already passed today, schedule for tomorrow.
		if ( $dt->getTimestamp() <= time() ) {
			$dt = $dt->modify( '+1 day' );
		}

		wp_schedule_event( $dt->getTimestamp(), 'daily', self::$cron_name );
	}

	/**
	 * Handles deactivation tasks.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::$cron_name );

		wp_cache_delete( self::get_first_published_year_cache_key(), self::$cache_group );
	}

	/**
	 * Clears post-related transients when a post gets published.
	 *
	 * @since 2.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function clear_transient_on_publish( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$today_md = wp_date( 'm-d' );
		$post_md  = wp_date( 'm-d', strtotime( $post->post_date ) );

		$current_year = (int) wp_date( 'Y' );
		$post_year    = (int) wp_date( 'Y', strtotime( $post->post_date ) );

		if ( $post_md === $today_md && $post_year < $current_year ) {
			delete_transient( self::get_post_ids_cache_key() );
		}
	}

	/**
	 * Returns list of all users who have opted into receiving daily email.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public static function get_users_to_email() {
		global $wpdb;

		$query = new WP_User_Query( array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_key'   => $wpdb->get_blog_prefix() . self::$option_name,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_value' => self::$enabled_option_value,
		) );

		return $query->results;
	}

	/**
	 * Formats a timestamp according to the date format string to be used when
	 * referring to the given day.
	 *
	 * @since 1.3.0
	 *
	 * @param string $timestamp    The timestamp to be formatted. Default is the current
	 *                             time's timestamp.
	 * @param bool   $include_year Whether to include the year in the date format string.
	 *                             Default false.
	 * @return string The timestamp formatted according to the date format string, which
	 *                is either "F j, Y" or "F jS" depending on whether the year is included.
	 */
	public static function get_formatted_date_string( $timestamp = '', $include_year = false ) {
		if ( ! $timestamp ) {
			$timestamp = current_time( 'timestamp' );
		}

		$date_format = $include_year
			/* translators: date format string for today, including year */
			? __( 'F j, Y', 'years-ago-today' )
			/* translators: date format string for today, without year */
			: __( 'F jS', 'years-ago-today' );

			return date_i18n( $date_format, $timestamp );
	}

	/**
	 * Returns the full HTML for the HTML part of an email with the given content
	 * inserted into appropriate locations.
	 *
	 * @since 2.0
	 *
	 * @param string $subject The email subject.
	 * @param string $body    The HTML content for the body of the email.
	 * @param string $footer  The HTML content for the footer of the email.
	 * @return string
	 */
	public static function get_html_email( $subject, $body, $footer ) {
		$template = self::get_html_email_template();
		return str_replace(
			[ '{{subject}}', '{{body}}', '{{footer}}' ],
			[ $subject, $body, $footer ],
			$template
		);
	}

	/**
	 * Returns the HTML email template with placeholders for subject, body, and footer.
	 *
	 * The placeholders are:
	 * - {{subject}}
	 * - {{body}}
	 * - {{footer}}
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	private static function get_html_email_template() {
		return '<!DOCTYPE html>
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
</html>';
	}

	/**
	 * Returns the body of the daily email.
	 *
	 * @since 1.2
	 * @since 2.0 Changed return value format so HTML body can be generated and returned alongside plaintext.
	 *
	 * @param string $content_type The email content type. See `get_email_content_types()` for
	 *                             acceptable values. Default 'list'.
	 * @param bool   $include_footer Whether to include the footer in the email. Default true.
	 * @return array Associative array consisting of 'text' and 'html' keys with the plain-text
	 *               and HTML email bodies, respectively.
	 */
	public static function get_email_body( $content_type = 'list', $include_footer = true ) {
		// Get the list of posts from years ago.
		$query = self::get_posts();

		$site_name = html_entity_decode( wp_kses( get_option( 'blogname' ), array() ), ENT_QUOTES );

		/* translators: %s: site name */
		$head = sprintf( __( 'Years Ago Today on %s', 'years-ago-today' ), $site_name );
		$html_head = '<h2>' . $head . '</h2>';

		// If there are no posts to include in the email.
		if ( ! $query->have_posts() ) {
			/**
			 * Filters if the daily Years Ago Today email is sent out on days that don't
			 * have any published posts in prior years.
			 *
			 * @since 1.0.0
			 *
			 * @param string $send Send daily email if there are no posts? Default false.
			 */
			$send_email_when_no_posts = (bool) apply_filters( 'c2c_years_ago_today-email-if-no-posts', false );

			// Define an email body if sending email despite not having posts.
			if ( $send_email_when_no_posts ) {
				$body = sprintf(
					/**
					 * Filters the email body for daily Years Ago Today email when no posts
					 * have been published in prior years.
					 *
					 * @since 1.0.0
					 *
					 * @param string $email_body The body of the email. Use "%1$s" as a
					 *                           placeholder for site name and "%2$s" for date.
					 *                           Default 'No posts were published to the site
					 *                           %1$s on %2$s in any previous year.'.
					 */
					apply_filters(
						'c2c_years_ago_today-email-body-no-posts',
						/* translators: 1: name of the site, 2: date string for today */
						__( 'No posts were published to the site %1$s on %2$s in any previous year.', 'years-ago-today' )
					),
					$site_name,
					self::get_formatted_date_string()
				);
				$html_body = wpautop( $body );
			}
			// Else don't define an email body.
			else {
				$body = $html_body = '';
			}
		}
		// Else there are posts to include in the email.
		else {
			// Build out email body.
			$body = sprintf(
				/* translators: 1: number of posts, 2: site name, 3: date string for today */
				_n(
					'%1$d post has been published to the site %2$s on %3$s in a previous year:',
					'%1$d posts have been published to the site %2$s on %3$s in previous years:',
					$query->post_count,
					'years-ago-today'
				),
				$query->post_count,
				$site_name,
				self::get_formatted_date_string()
			);

			$html_body = wpautop( esc_html( $body ) );

			// Output the list of posts.
			$year = '';
			$open_ul = false;
			while ( $query->have_posts() ) :
				$query->the_post();
				$this_year = wp_date( 'Y', strtotime( get_post_field( 'post_date' ) ) );
				// Only output the year once.
				if ( $year !== $this_year ) {
					$year = $this_year;
					if ( $open_ul ) {
						$html_body .= '</ul>';
						$open_ul = false;
					}

					/* translators: %d: 4-digit year. */
					$body .= "\n\n" . sprintf( __( '== %d ==', 'years-ago-today' ), (int) $year ) . "\n";
					$html_body .= "\n<h3>" . $year . "</h3>\n<ul>";
					$open_ul = true;
				}
				$body .= '* ' . wp_kses( get_the_title(), array() ) . ' : ' . esc_url_raw( get_permalink() ) . "\n";
				$html_body .= '<li><a href="' . esc_url( get_permalink() ) . '" rel="noopener noreferrer">' . esc_html( get_the_title() ) . "</a></li>\n";
			endwhile;

			if ( $open_ul ) {
				$html_body .= "</ul>\n";
			}

			// Optionally output excerpts or full contents of posts.
			if ( in_array( $content_type, array( 'excerpt', 'full' ) ) ) {
				$query->rewind_posts();

				$body .= "\n\n\n";
				$html_body .= '<h3 style="margin-top:50px;">';
				if ( 'excerpt' === $content_type ) {
					$heading = __( 'Post Excerpts', 'years-ago-today' );
					$body .= '== ' . $heading . " ==\n\n";
					$html_body .= $heading;
				} elseif ( 'full' === $content_type ) {
					$heading = __( 'Posts', 'years-ago-today' );
					$body .= '== ' . $heading . " ==\n\n";
					$html_body .= $heading;
				}
				$html_body .= "</h3>\n";
				$html_body .= '<p style="font-size:smaller;"><em>';
				if ( 'excerpt' === $content_type ) {
					$body .= __( 'Excerpts of these posts follows. Formatting and markup have been removed. The full content is available on the site.', 'years-ago-today' ) . "\n\n\n";
					$html_body .= __( 'Excerpts of these posts follows. The full content is available on the site. Note: Email clients may not properly render the formatting.', 'years-ago-today' );
				} elseif ( 'full' === $content_type ) {
					$body .= __( 'The full content of these posts follows. Formatting and markup have been removed.', 'years-ago-today' ) . "\n\n\n";
					$html_body .= __( 'The full content of these posts follows. Note: Email clients may not properly display the formatting of the posts.', 'years-ago-today' );
				}
				$html_body .= "</em></p>\n";

				while ( $query->have_posts() ) {
					$query->the_post();

					// Include post heading.
					$body .= '==== ' . wp_kses( get_the_title(), array() ) . ' : ' . esc_url_raw( get_permalink() ) . " ====\n";
					/* translators: 1: the publication date, 2: the post author */
					$body .= wp_kses( sprintf( _x( 'Published %1$s by %2$s', 'plain text email post info', 'years-ago-today' ), get_the_date(), get_the_author() ), array() ) . "\n\n";

					$html_body .= '<h4 style="margin-bottom:8px;margin-top:30px;padding-top:30px;color:#eee;border-top:1px solid #eee;"><a href="' . esc_url( get_permalink() ) . '" rel="noopener noreferrer">' . esc_html( get_the_title() ) . "</a></h4>\n";
					$html_body .= '<p style="margin-top:0; font-size:smaller;">';
					$html_body .= sprintf(
						/* translators: 1: the publication date, 2: the post author linked to their post archive */
						wp_kses( _x( 'Published %1$s by %2$s', 'HTML email post info', 'years-ago-today' ), array() ),
						'<strong>' . wp_kses( get_the_date(), array() ) . '</strong>',
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
							wp_kses( get_the_author(), array() )
						)
					);
					$html_body .= "</p>\n\n";

					// Include post content.
					if ( 'excerpt' === $content_type ) {
						$body .= wp_strip_all_tags( get_the_excerpt() );
						$html_body .= wp_kses_post( wpautop( get_the_excerpt() ) );
					} elseif ( 'full' === $content_type ) {
						$body .= wp_strip_all_tags( self::get_resized_content() );
						$html_body .= wp_kses_post( self::get_resized_content() );
					}

					$body .= "\n\n\n";
				}
			}

			wp_reset_postdata();
		}

		if ( $body ) {
			$body = '= ' . $head . " =\n\n" . $body;
		}

		if ( $body && $include_footer ) {
			$body .= self::get_email_footer( 'text' );
		}

		if ( $html_body ) {
			$html_body = self::get_html_email(
				self::get_email_subject(),
				$html_head . "\n\n" . $html_body,
				$include_footer ? self::get_email_footer( 'html' ) : ''
			);
		}

		return array(
			'text' => $body,
			'html' => $html_body,
		);

	}

	/**
	 * Returns the desired size for images within an HTML email.
	 *
	 * @since 2.0
	 *
	 * @param string $size A valid image size. Default 'medium'.
	 * @return string
	 */
	public static function get_html_email_image_size( $size = 'medium' ) {
		$default_size = 'medium';

		/**
		 * Filters the desired size for images within an HTML email.
		 *
		 * @since 2.0
		 *
		 * @param string $size A valid image size. Default 'medium'.
		 */
		$size = apply_filters(
			'c2c_years_ago_today-html_email_image_size',
			is_string( $size ) ? $size : $default_size
		);

		// Cache valid sizes in a static variable.
		static $valid_sizes = null;
		if ( null === $valid_sizes ) {
			// Fall back to default if an invalid size.
			$valid_sizes = array_merge( array( 'full' ), get_intermediate_image_sizes() );
		}

		if ( ! in_array( $size, $valid_sizes, true ) ) {
			$size = $default_size;
		}

		return $size;
	}

	/**
	 * Replaces image file references with reduced-sized versions.
	 *
	 * @since 2.0
	 *
	 * @param string $size The size for resized images. Default 'medium'.
	 * @return string The post content with images resized.
	 */
	private static function get_resized_content( $size = 'medium' ) {
		$size = self::get_html_email_image_size( $size );

		$content = apply_filters( 'the_content', get_the_content() );
		if ( '' === trim( $content ) ) {
			return $content;
		}

		// Build a DOM document - tolerant mode prevents fatal errors on bad markup.
		$dom = new DOMDocument();
		@$dom->loadHTML(
			mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ),
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			$src = $img->getAttribute( 'src' );

			if ( ! $src ) {
				continue;
			}

			// Get an attachment ID based on URL and cache results.
			$key = strtok( $src, '?' ); // Strip ?query=string for stable look-up.
			$cache_key     = md5( $key );
			$attachment_id = wp_cache_get( $cache_key, 'yat_url_to_id' );
			if ( false === $attachment_id ) {
				$attachment_id = attachment_url_to_postid( $key );

				// Store 0 for “not found” so repeated externals are skipped quickly.
				wp_cache_set( $cache_key, $attachment_id ? $attachment_id : 0, 'yat_url_to_id' );
			}

			// Skip if an external image or not an attachment.
			if ( ! $attachment_id ) {
				continue;
			}

			// Skip if the requested size can't be fetched.
			$img_data = wp_get_attachment_image_src( $attachment_id, $size );
			if ( ! $img_data ) {
				continue;
			}

			// Limit 'srcset' values to sizes <= max width and one retina-supportive size > max_width but <= 2*max_width to prevent mail clients unnecessarily downloading larger sizes.
			$max_width   = $img_data[1];               // width of chosen rendition
			$srcset_trim = static function ( $sources ) use ( $max_width ) {
				$kept = array();
				$high_density = null;

				foreach ( $sources as $w => $src ) {
					// Keep any smaller size.
					if ( (int) $w <= $max_width ) {
						$kept[ $w ] = $src;
					}
					// Only keep the first 2x+ image as the retina companion.
					// Note: This simple approach may not yield the perfect HiDPI version available, but it is sufficient.
					elseif ( ! $high_density && $w <= $max_width * 2 ) {
						$high_density = true;
						$kept[ $w ]   = $src;
					}
				}

				// If trimming would leave only one candidate, fall back so srcset survives.
				return ( count( $kept ) >= 2 ) ? $kept : $sources;
			};
			add_filter( 'wp_calculate_image_srcset', $srcset_trim, 10, 1 );

			// Persist attributes to regenerated `img` tag.
			$attrs = array( 'class' => $img->getAttribute( 'class' ) );

			if ( $img->hasAttribute( 'alt' ) ) {
				$alt = trim( $img->getAttribute( 'alt' ) );

				if ( $alt ) {
					$attrs['alt'] = $alt;
				} else {
					// Empty alt means purely decorative: mark as presentation.
					$attrs['role'] = 'presentation';
				}
			}

			$style = $img->hasAttribute( 'style' ) ? $img->getAttribute( 'style' ) : '';

			// Add border:0 if not already present.
			if ( false === stripos( $style, 'border' ) ) {
				$style = trim( $style . ';border:0;' );
			}

			// Avoid saving an empty style attribute; max-width is added later.
			if ( $style ) {
				$attrs['style'] = $style;
			}

			// Responsive sources for clients that support them.
			$img_html = wp_get_attachment_image(
				$attachment_id,
				$size,
				false,
				$attrs
			);

			remove_filter( 'wp_calculate_image_srcset', $srcset_trim );

			// Import the generated, fully featured `img` into the DOM.
			$frag = $dom->createDocumentFragment();
			$frag->appendXML( $img_html );
			$img->parentNode->replaceChild( $frag, $img );
		}

		// Force all images to max-width:100% for narrow screens.
		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			$style = $img->getAttribute( 'style' );
			if ( false === stripos( $style, 'max-width' ) ) {
				$img->setAttribute( 'style', trim( $style . ';max-width:100%;height:auto;' ) );
			}
		}

		return $dom->saveHTML();
	}

	/**
	 * Returns the translated string used for the label of the setting to opt into daily emails.
	 *
	 * This is its own getter because the string is used on the settings page and in the email footer.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function get_optin_label() {
		return __( 'Email me daily about posts published on this day in previous years.', 'years-ago-today' );
	}

	/**
	 * Returns the subject line for the daily email.
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	public static function get_email_subject() {
		return sprintf(
			/* translators: 1: site name in subject for daily email, 2: date string for today */
			__( '[%1$s] Years Ago Today - %2$s', 'years-ago-today' ),
			html_entity_decode( wp_kses( get_option( 'blogname' ), array() ), ENT_QUOTES ),
			self::get_formatted_date_string( '', true )
		);
	}

	/**
	 * Returns the footer used for all emails.
	 *
	 * Contains an explanation about the email to the recipient. Serves to remind the
	 * user why they are receiving the email, what it is about, and how to stop it.
	 *
	 * @since 1.2
	 * @since 2.0 Remove `$user_id` and `$html_body` arguments and simply return the footer.
	 *
	 * @param string $type The format for the footer. Either 'text' or 'html'. Default 'text'.
	 * @return string
	 */
	public static function get_email_footer( $format = 'text' ) {
		if ( 'html' !== $format ) {
			$format = 'text';
		}

		$footer = '';

		$is_html = ( 'html' === $format );

		$footer .= $is_html
			? '<p>'
			: "\n\n\n-------------------------------\n";

		$opt_in_explanation = sprintf(
			/* translators: %s: site name */
			__( 'You received this email because you have opted into receiving a daily email about posts published on this day in previous years to the site %s, which is using the Years Ago Today plugin.', 'years-ago-today' ),
			wp_specialchars_decode( get_option('blogname'), ENT_QUOTES )
		);
		if ( $is_html ) {
			$opt_in_explanation = esc_html( $opt_in_explanation );
		}
		$footer .= $opt_in_explanation;

		$footer .= $is_html
			? "</p>\n<p>"
			: "\n\n";

		$unsubscribe_url = admin_url( 'profile.php' );

		$unsubscribe_instructions = sprintf(
			/* translators: 1: URL to user profile on the site, 2: the label for the opt-in checkbox in the user's profile */
			__( 'If you wish to discontinue receiving these emails, simply log into the site and visit your profile at %1$s to uncheck the checkbox labeled "%2$s"', 'years-ago-today' ),
			$unsubscribe_url,
			self::get_optin_label()
		);
		// If HTML, convert URL to a link and escape the instructions.
		if ( $is_html ) {
			$unsubscribe_instructions = str_replace(
				esc_html( $unsubscribe_url ),
				'<a href="' . esc_url( $unsubscribe_url ) . '">' . esc_html( $unsubscribe_url ) . '</a>',
				esc_html( $unsubscribe_instructions )
			);
		}
		$footer .= $unsubscribe_instructions;

		$footer .= $is_html
			? "</p>\n"
			: "\n";

		return $footer;
	}

	/**
	 * Returns the number of people to email per batch.
	 *
	 * @since 2.0
	 *
	 * @return int The batch size. Default 40.
	 */
	public static function get_bcc_batch_size() {
		$default = 40;

		/**
		 * Filters the maximum number of recipients per outgoing batch.
		 *
		 * Some mail servers cap the length of the Bcc header; 40 keeps us well
		 * below common limits (~ 2 kB).
		 *
		 * @since 2.0
		 *
		 * @param int $size Batch size. Default 40.
		 */
		$batch_size = (int) apply_filters( 'c2c_years_ago_today-batch_size', $default );

		if ( 0 >= $batch_size || 100 < $batch_size ) {
			$batch_size = $default;
		}

		return $batch_size;
	}

	/**
	 * Returns the 'To:' email address used for Bcc-batched emails.
	 *
	 * @since 2.0
	 *
	 * @return string The email address. Default 'noreply@{site-domain}'.
	 */
	public static function get_bcc_to_email_address() {
		$default = 'noreply@' . wp_parse_url( home_url(), PHP_URL_HOST );

		/**
		 * Filters the “To:” address used for batched mails.
		 *
		 * Most MTAs require a non-empty “To:”.
		 *
		 * @since 2.0
		 *
		 * @param string $to The To: email address. Default 'noreply@{site-domain}'.
		 */
		$raw_email = (string) apply_filters( 'c2c_years_ago_today-to_address', $default );

		$email = sanitize_email( $raw_email );

		return is_email( $email ) ? $email : $default;
	}

	/**
	 * Returns an associative array of email content types and the email addresses
	 * of users who have opted into emails of each content type.
	 *
	 * @param bool $group_by_image_pref Also separate recipients who prefer embedded images.
	 * @return array
	 */
	public static function get_users_to_email_grouped_by_content_type( $group_by_image_pref = false ) {
		$users = self::get_users_to_email();

		// Bail if no one has opted into getting an email.
		if ( ! $users ) {
			return array();
		}

		// Group all recipient addresses according to their desired email content type.
		$emails = array();
		foreach ( $users as $user ) {
			if ( is_email( $user->user_email ) ) {
				$type = self::get_user_email_content_pref( $user->ID );
				if ( $group_by_image_pref && get_user_option( self::$meta_email_embed_images, $user->ID ) ) {
					$type .= ':embedded';
				}
				if ( ! isset( $emails[ $type ] ) ) {
					$emails[ $type ] = array();
				}
				$emails[ $type ][] = $user->user_email;
			}
		}

		return $emails;
	}

	/**
	 * Sends out daily email.
	 *
	 * @since 1.0
	 */
	public static function cron_email() {
		// Get list of users who want the daily email and bail if there aren't any.
		$emails = self::get_users_to_email_grouped_by_content_type( true );
		if ( ! $emails ) {
			return;
		}

		// Mail each email content type to its associated users.
		foreach ( array_keys( $emails ) as $type ) {
			$parts = explode( ':', $type );
			self::send_email_of_type( $parts[0], $emails[ $type ], isset( $parts[1] ) );
		}
	}

	/**
	 * Sends out the given email type to the specified users.
	 *
	 * Note: This presumes that the email addresses provided are opted into the given email.
	 *
	 * @since 2.0
	 *
	 * @param string   $type   The email content type.
	 * @param string[] $emails The already-verified email addresses that should be emailed for the content type.
	 * @param bool     $embed_images Embed images from this site's uploads. Default false.
	 * @return int Count of the number of users emailed.
	 */
	public static function send_email_of_type( $type, $emails, $embed_images = false ) {
		// Bail if no one to email.
		if ( ! $emails ) {
			return 0;
		}

		// Headers included for every email that don't change per batch.
		$default_headers = array(
			'Content-type: text/html; charset=UTF-8', // PHPMailer would normally set this due to isHTML(true), but be explicit for testing.
			'List-Unsubscribe: <' . esc_url_raw( admin_url( 'profile.php' ) ) . '>',
		);

		// Get the subject of the email and bail if there isn't one.
		$subject = self::get_email_subject();
		if ( ! $subject ) {
			return 0;
		}

		$batch_size = self::get_bcc_batch_size();
		$batch_to_address = self::get_bcc_to_email_address();

		// Get the email body parts and bail if there is no plaintext body (which can happen if there are no posts to email about).
		$body  = self::get_email_body( $type );
		if ( ! $body['text'] ) {
			return 0;
		}

		$plain = wp_kses( $body['text'], array() );
		$html  = empty( $body['html'] )
			? wpautop( esc_html( $plain ) )
			: $body['html'];

		if ( $embed_images ) {
			$embedded = self::get_embedded_email_images( $html );
		} else {
			$embedded = array( 'html' => $html, 'images' => array() );
		}

		$mailer_hook = static function ( $phpmailer ) use ( $embedded, $plain ) {
			$phpmailer->isHTML( true );
			$phpmailer->Body    = $embedded['html'];
			$phpmailer->AltBody = $plain;
			foreach ( $embedded['images'] as $cid => $image ) {
				$phpmailer->addEmbeddedImage( $image['path'], $cid, basename( $image['path'] ), 'base64', $image['mime'] );
			}
		};

		// Chunk and send.
		foreach ( array_chunk( $emails, $batch_size ) as $chunk ) {
			$headers = $default_headers;

			// Forego bcc-batched emailing if only 1 user.
			if ( 1 === count( $chunk ) ) {
				$to_address = $chunk[0];
			} else {
				$to_address = $batch_to_address;
				$headers[] = 'Bcc: ' . implode( ', ', $chunk );
			}

			add_action( 'phpmailer_init', $mailer_hook, 10, 1 );
			wp_mail( $to_address, $subject, $html, $headers );
			remove_action( 'phpmailer_init', $mailer_hook, 10 );
		}

		return count( $emails );
	}

	/**
	 * Rewrites local upload image URLs as Content-ID references for email.
	 * Unavailable files and external images retain their original URLs.
	 *
	 * @param string $html Email HTML.
	 * @return array HTML and images keyed by Content-ID.
	 */
	private static function get_embedded_email_images( $html ) {
		$uploads = wp_upload_dir();
		$base_path = realpath( $uploads['basedir'] );
		$base_url = trailingslashit( $uploads['baseurl'] );
		$images = array();

		if ( ! $base_path ) {
			return array( 'html' => $html, 'images' => $images );
		}

		$html = preg_replace_callback( '/<img\b[^>]*>/i', static function ( $match ) use ( $base_url, $base_path, &$images ) {
			$dom = new DOMDocument();
			@$dom->loadHTML( '<?xml encoding="UTF-8">' . $match[0], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			$img = $dom->getElementsByTagName( 'img' )->item( 0 );
			if ( ! $img ) {
				return $match[0];
			}
			$src = $img->getAttribute( 'src' );
			if ( '//' === substr( $src, 0, 2 ) ) {
				$src = wp_parse_url( $base_url, PHP_URL_SCHEME ) . ':' . $src;
			}
			if ( 0 !== strpos( $src, $base_url ) ) {
				return $match[0];
			}

			$relative = rawurldecode( strtok( substr( $src, strlen( $base_url ) ), '?#' ) );
			$path = realpath( $base_path . '/' . $relative );
			if ( ! $path || 0 !== strpos( $path, $base_path . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) ) {
				return $match[0];
			}
			$info = @getimagesize( $path );
			if ( ! $info || ! in_array( $info['mime'], array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
				return $match[0];
			}

			$cid = 'yat-' . md5( $path ) . '@yat';
			$images[ $cid ] = array( 'path' => $path, 'mime' => $info['mime'] );
			$img->setAttribute( 'src', 'cid:' . $cid );
			$img->removeAttribute( 'srcset' );
			$img->removeAttribute( 'sizes' );
			$img->removeAttribute( 'loading' );
			return $dom->saveHTML( $img );
		}, $html );

		return array( 'html' => $html, 'images' => $images );
	}

	/**
	 * Set up the admin dashboard.
	 *
	 * @since 1.0
	 */
	public static function dashboard_setup() {
		wp_add_dashboard_widget(
			'dashboard_years_ago_today',
			_x( 'Years Ago Today', 'Title of the dashboard widget', 'years-ago-today' ),
			array( __CLASS__, 'wp_dashboard_years_ago_today' )
		);
	}

	/**
	 * Outputs the admin dashboard.
	 *
	 * @since 1.0
	 */
	public static function wp_dashboard_years_ago_today() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is escaped during generation.
		echo self::get_widget_markup();
	}

	/**
	 * Returns the default title for the front-end block and shortcode listing.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function get_default_display_title() {
		return _x( 'Years Ago Today', 'Title displayed above the front-end listing', 'years-ago-today' );
	}

	/**
	 * Returns markup for the Years Ago Today listing (dashboard widget, block, shortcode).
	 *
	 * @since 2.0
	 *
	 * @param string|null $title Optional. Title to display above the listing. Null omits a title
	 *                          (e.g. admin dashboard, which has its own widget title). Default null.
	 * @return string
	 */
	public static function get_widget_markup( $title = null ) {
		return '<div class="years-ago-today-widget">' . self::get_widget_title_markup( $title ) . self::get_widget_inner_markup() . "</div>\n";
	}

	/**
	 * Returns markup for the listing title.
	 *
	 * @since 2.0
	 *
	 * @param string|null $title Title text. Null omits the title element.
	 * @return string
	 */
	public static function get_widget_title_markup( $title = null ) {
		if ( null === $title || '' === $title ) {
			return '';
		}

		return sprintf(
			'<h2 class="years-ago-today-title">%s</h2>' . "\n",
			esc_html( $title )
		);
	}

	/**
	 * Returns the inner markup for the Years Ago Today listing (without a wrapper element).
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function get_widget_inner_markup() {
		$q = self::get_posts();

		ob_start();

		// Output and return if no posts were published.
		if ( ! $q->have_posts() ) {
			printf(
				'<p>%s</p>',
				wp_kses(
					sprintf(
						/* translators: %s: date string for today */
						__( 'No posts were published on <strong>%s</strong> in any previous year.', 'years-ago-today' ),
						esc_html( self::get_formatted_date_string() )
					),
					array( 'strong' => array() )
				)
			);
			wp_reset_postdata();
			return ob_get_clean();
		}

		// Print summary.
		printf(
			'<p>%s</p>',
			wp_kses(
				sprintf(
					/* translators: 1: number of posts, 2: date string for today */
					_n(
						'<strong>%1$d</strong> post has been published on <strong>%2$s</strong> in a previous year:',
						'<strong>%1$d</strong> posts have been published on <strong>%2$s</strong> in previous years:',
						(int) $q->post_count,
						'years-ago-today'
					),
					(int) $q->post_count,
					esc_html( self::get_formatted_date_string() )
				),
				array( 'strong' => array() )
			)
		);

		// Group posts by year.
		$year = '';
		$open = false;

		while ( $q->have_posts() ) :
			$q->the_post();
			$this_year = wp_date( 'Y', strtotime( get_post_field( 'post_date' ) ) );

			if ( $this_year !== $year ) {
				if ( $open ) {
					echo "</ul></section>\n";
				}

				$year = $this_year;

				printf(
					'<section class="years-ago-today-group" aria-labelledby="years-ago-today-year-%1$d">' .
					'<h3 id="years-ago-today-year-%1$d" class="years-ago-today-year" role="heading" aria-level="3">%1$d</h3>' .
					'<ul class="years-ago-today-posts">',
					(int) $year
				);

				$open = true;
			}

			echo '<li>';
			the_title( '<a href="' . esc_url( get_permalink() ) . '">', '</a>' );
			echo "</li>\n";

		endwhile;

		if ( $open ) {
			echo "</ul></section>\n";
		}

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Registers the Years Ago Today shortcode.
	 *
	 * @since 2.0
	 */
	public static function register_shortcode() {
		add_shortcode( 'years-ago-today', array( __CLASS__, 'shortcode_years_ago_today' ) );
	}

	/**
	 * Handles output for the Years Ago Today shortcode.
	 *
	 * @since 2.0
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed content (unused).
	 * @param string $tag     Shortcode tag.
	 * @return string
	 */
	public static function shortcode_years_ago_today( $atts, $content = '', $tag = '' ) {
		$atts = shortcode_atts(
			array(
				'title' => self::get_default_display_title(),
			),
			$atts,
			'years-ago-today'
		);

		self::enqueue_public_style();

		return self::get_widget_markup( $atts['title'] );
	}

	/**
	 * Registers the Years Ago Today block.
	 *
	 * @since 2.0
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		self::register_public_style();

		$supports = array(
			'html' => false,
		);

		if ( self::supports_php_only_blocks() ) {
			$supports['autoRegister'] = true;
		}

		register_block_type(
			'coffee2code/years-ago-today',
			array(
				'title'           => __( 'Years Ago Today', 'years-ago-today' ),
				'description'     => __( 'Lists posts published on this day in previous years.', 'years-ago-today' ),
				'category'        => 'widgets',
				'icon'            => 'calendar',
				'keywords'        => array(
					__( 'on this day', 'years-ago-today' ),
					__( 'history', 'years-ago-today' ),
					__( 'archive', 'years-ago-today' ),
				),
				'attributes'      => array(
					'title' => array(
						'label'   => __( 'Title', 'years-ago-today' ),
						'type'    => 'string',
						'default' => self::get_default_display_title(),
					),
				),
				'render_callback' => array( __CLASS__, 'render_block' ),
				'style'           => 'c2c-years-ago-today',
				'supports'        => $supports,
			)
		);
	}

	/**
	 * Renders the Years Ago Today block.
	 *
	 * @since 2.0
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block instance.
	 * @return string
	 */
	public static function render_block( $attributes, $content, $block ) {
		self::enqueue_public_style();

		$attributes = wp_parse_args(
			$attributes,
			array(
				'title' => self::get_default_display_title(),
			)
		);

		$markup = self::get_widget_title_markup( $attributes['title'] ) . self::get_widget_inner_markup();

		if ( function_exists( 'get_block_wrapper_attributes' ) && $block instanceof WP_Block ) {
			return sprintf(
				'<div %1$s>%2$s</div>',
				wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'years-ago-today-widget' ) ) ),
				$markup
			);
		}

		return self::get_widget_markup( $attributes['title'] );
	}

	/**
	 * Returns if PHP-only block registration is supported.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	public static function supports_php_only_blocks() {
		global $wp_version;

		return version_compare( $wp_version, '7.0', '>=' );
	}

	/**
	 * Registers the public CSS stylesheet.
	 *
	 * @since 2.0
	 */
	public static function register_public_style() {
		wp_register_style(
			'c2c-years-ago-today',
			plugins_url( 'assets/css/public.css', __FILE__ ),
			array(),
			self::version()
		);
	}

	/**
	 * Enqueues the public CSS stylesheet.
	 *
	 * @since 2.0
	 */
	public static function enqueue_public_style() {
		self::register_public_style();
		wp_enqueue_style( 'c2c-years-ago-today' );
	}

	/**
	 * Gets the year of the first published post on the site.
	 *
	 * @return string
	 */
	public static function get_first_published_year() {
		global $wpdb;

		/**
		 * Filters the year of the earliest published post.
		 *
		 * By default this is false, which causes the plugin to determine the earliest
		 * year via a database query. The queried value does get cached, though may
		 * not persist depending on your site setup. This filter can be used to
		 * prevent the need for the query or to set a year later than the earliest
		 * published year (in case you'd prefer not to feature or be reminded of the
		 * early years).
		 *
		 * @since 1.0.0
		 *
		 * @param string|false The year for the earlier published post. A value of
		 *                     `false` forces the actual value to be queried from the
		 *                     database. Default false.
		 */
		$first_year = apply_filters( 'c2c_years_ago_today-first_published_year', false );

		$cache_key = self::get_first_published_year_cache_key();

		// If not provided via filter, try to get it from the cache.
		if ( false === $first_year ) {
			$first_year = wp_cache_get( $cache_key, self::$cache_group );
		}

		// If not in the cache, figure it out.
		if ( false === $first_year ) {
			$post_types     = self::get_post_types();
			$placeholders   = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Aggregate query; placeholders are for post types.
			$first_year = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Aggregate query; placeholders are for post types.
					"SELECT YEAR(MIN(post_date)) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($placeholders)",
					...$post_types
				)
			);

			// If nothing was found, assume current year.
			if ( ! $first_year ) {
				$first_year = wp_date( 'Y' );
			}

			// Cache the year.
			wp_cache_add( $cache_key, $first_year, self::$cache_group );
		}

		return $first_year;
	}

	/**
	 * Returns the cache key for the first published year value.
	 *
	 * The key incorporates the included post types so a change via the
	 * 'c2c_years_ago_today-post_types' filter does not yield a stale year.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function get_first_published_year_cache_key() {
		$post_types = self::get_post_types();
		sort( $post_types );

		return 'first_published_year_' . get_current_blog_id() . '_' . md5( implode( ',', $post_types ) );
	}

	/**
	 * Returns the post types that should be included in the widget.
	 *
	 * @since 2.0
	 *
	 * @return string[]
	 */
	public static function get_post_types() {
		$default = array( 'post' );

		/**
		 * Filters the post types that get included in Years Ago Today widgets.
		 *
		 * @since 2.0
		 *
		 * @param string[] $post_types The included post types. Default `['post']`.
		 */
		$post_types = (array) apply_filters( 'c2c_years_ago_today-post_types', $default );

		// Verify returned post types.
		$post_types = array_filter(
			$post_types,
			static function ( $pt ) {
				return is_string( $pt ) && post_type_exists( $pt );
			}
		);

		return $post_types ?: $default;
	}

	/**
	 * Generates and returns the cache key for today's post IDs.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public static function get_post_ids_cache_key() {
		// Unique per-site prefix + today's date.
		return 'yat_' . get_current_blog_id() . '_' . wp_date( 'Ymd' );
	}

	/**
	 * Returns the post IDs for posts published today.
	 *
	 * @since 2.0
	 *
	 * @return int[] Array of post IDs.
	 */
	public static function query_post_ids() {
		$first_year   = (int) self::get_first_published_year();
		$current_year = (int) wp_date( 'Y' );

		// Bail if this is the site's first year.
		if ( $first_year >= $current_year ) {
			return array();
		}

		$years = range( $first_year, $current_year - 1 );

		$q = new WP_Query( array(
			'fields'         => 'ids',
			'post_status'    => array( 'publish' ),
			'post_type'      => self::get_post_types(),
			'posts_per_page' => -1,
			'date_query'     => array(
				'year'  => $years,
				'month' => wp_date( 'm' ),
				'day'   => wp_date( 'd' ),
			),
		) );

		return $q->posts;
	}

	/**
	 * Returns the query object after a years ago post query, or the posts that
	 * were found.
	 *
	 * @since 1.0
	 *
	 * @param bool $return_posts Return array of queried posts or the WP_Query
	 *                           object? True to return array posts, false to
	 *                           return WP_Query object. Default false.
	 * @return array|WP_Query    Array if return_posts is true, WP_Query if false.
	 */
	public static function get_posts( $return_posts = false ) {
		if ( false === ( $ids = get_transient( self::get_post_ids_cache_key() ) ) ) {
			$ids = self::query_post_ids();
			set_transient( self::get_post_ids_cache_key(), $ids, 15 * MINUTE_IN_SECONDS );
		}

		// Bail early if there are no posts.
		if ( ! $ids ) {
			return $return_posts ? array() : new WP_Query( array( 'post__in' => array( 0 ) ) );
		}

		$query_args = array(
			'post__in'       => $ids,
			'post_status'    => array( 'publish' ),
			'orderby'        => 'post_date',
			'order'          => 'DESC',
			'posts_per_page' => -1,
		);

		if ( $return_posts ) {
			return get_posts( $query_args );
		}

		$query_args['post_type'] = self::get_post_types();

		return new WP_Query( $query_args );
	}

	/**
	 * Enqueues admin CSS and JS when on appropriate pages.
	 *
	 * @since 2.0
	 */
	public static function enqueue_admin_style( $hook_suffix ) {
		// Bail if not on the dashboard or profile pages.
		if ( ! in_array( $hook_suffix, array( 'index.php', 'profile.php' ) ) ) {
			return;
		}

		wp_enqueue_style(
			'c2c-years-ago-today-admin-css',
			plugins_url( 'assets/css/admin.css', __FILE__ ),
			array(),
			self::version()
		);

		// Only enqueue JS on the profile page.
		if ( 'profile.php' === $hook_suffix ) {
			$js_id = 'c2c-years-ago-today-admin-js';

			// Register script.
			wp_register_script( $js_id, plugins_url( 'assets/js/admin.js', __FILE__ ), array(), self::version(), true );

			// Localize script.
			wp_localize_script( $js_id, 'c2c_years_ago_today', array(
				'option_id' => esc_js( self::$option_name ),
			) );

			// Enqueue script.
			wp_enqueue_script( $js_id );
		}
	}

	/**
	 * Returns an array of the recognized email content types.
	 *
	 * @since 2.0
	 *
	 * @param bool $types_only Return the types only? Default true.
	 * @return string[] If `$types_only is true, then returns an array of just the types, else
	 *                  returns an associative array with keys of the types and values of description.
	 */
	public static function get_email_content_types( $types_only = true ) {
		$formats = array(
			'list'    => __( 'Just include the list of post titles, each linked to the post.', 'years-ago-today' ),
			'excerpt' => __( 'After the list of post titles, include an excerpt for each post.', 'years-ago-today' ),
			'full'    => __( 'After the list of post titles, include the full content for each post.', 'years-ago-today' ),
		);
		return $types_only ? array_keys( $formats ) : $formats;
	}

	/**
	 * Adds the checkbox to user profiles to allow them to opt into receiving a
	 * daily email about posts published in previous years.
	 *
	 * @since 1.0
	 * @since 1.4 Added $user arg.
	 *
	 * @param WP_User $user The user whose options are being shown.
	 */
	public static function add_daily_email_optin_checkbox( $user ) {
		$current_user = wp_get_current_user();
		$is_current_user_profile_page = ( $user->ID === $current_user->ID );

		// Only show on current user's own profile, or other user profiles if current
		// user has appropriate capabilities.
		if ( ! $is_current_user_profile_page && ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$is_checked = get_user_option( self::$option_name, $user->ID );
		// Disable the input if cron is disabled (since emails won't go out) and unit tests aren't running (since cron is disabled for tests).
		$is_disabled = defined( 'DISABLE_WP_CRON' ) && ( true === DISABLE_WP_CRON ) && ( ! defined( 'WP_RUNNING_TESTS' ) || ! WP_RUNNING_TESTS );

		$label = $is_current_user_profile_page
			? self::get_optin_label()
			: __( 'Email this user daily about posts published on this day in previous years.', 'years-ago-today' );

		echo "\t\t<table class=\"form-table\">\n";
		echo "\t\t<tr>\n";
		echo "\t\t\t" . '<th scope="row">' . esc_html__( '"Years Ago Today" email', 'years-ago-today' ) . "</th>\n";
		echo "\t\t\t<td>\n";
		echo "\t\t\t\t" . sprintf( '<label for="%s">', esc_attr( self::$option_name ) ) . "\n";
		echo "\t\t\t\t\t" . sprintf(
			'<input name="%s" type="checkbox" id="%s" value="%s" aria-describedby="years-ago-today-explainer"%s%s />',
			esc_attr( self::$option_name ),
			esc_attr( self::$option_name ),
			esc_attr( self::$enabled_option_value ),
			checked( $is_checked, self::$enabled_option_value, false ),
			disabled( $is_disabled, true, false )
		) . "\n";
		echo "\t\t\t\t\t" . esc_html( $label ) . "\n";
		echo "\t\t\t\t</label>\n";
		echo "\t\t\t\t<p id=\"years-ago-today-explainer\" class=\"description\">";
		$is_current_user_profile_page
			? esc_html_e( 'If checked, you\'ll be sent one email a day that lists posts published on this calendar day in previous years. You can opt out at any time via this checkbox.', 'years-ago-today' )
			: esc_html_e( 'If checked, they\'ll be sent one email a day that lists posts published on this calendar day in previous years. They can opt out at any time via this checkbox on their profile.', 'years-ago-today' );
		echo "</p>\n";

		$is_opted_in = (bool) get_user_option( self::$option_name, $user->ID );

		echo "\t\t\t\t" . '<fieldset id="years-ago-today-content-type"' . ( $is_opted_in ? '' : ' disabled aria-disabled="true"' ) . '>';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Email content type', 'years-ago-today' ) . "</legend>\n";

		foreach ( self::get_email_content_types( false ) as $mode => $desc ) {
			$preview_links = sprintf(
				/* translators: 1: link to preview text version of email, 2: link to preview HTML version of email */
				__( 'Preview the email: %1$s &bull; %2$s', 'years-ago-today' ),
				'<a href="' .
					esc_url( add_query_arg( array(
						'preview-years-ago-today-email' => '1',
						'content-type' => $mode,
						'type' => 'text',
						'force' => '1'
					), admin_url( 'profile.php' ) ) ) . '">' . esc_html__( 'Text', 'years-ago-today' ) . '</a>',
				'<a href="' .
					esc_url( add_query_arg( array(
						'preview-years-ago-today-email' => '1',
						'content-type' => $mode,
						'type' => 'html',
						'force' => '1'
					), admin_url( 'profile.php' ) ) ) . '">' . esc_html__( 'HTML', 'years-ago-today' ) . '</a>'
			);
			printf(
				"\t\t\t\t\t" . '<label><input type="radio" name="%1$s" value="%2$s"%3$s> %4$s &mdash; %5$s</label><br>' . "\n",
				esc_attr( self::$meta_email_content_pref ),
				esc_attr( $mode ),
				checked( $mode, self::get_user_email_content_pref( $user->ID ), false ),
				esc_html( ucfirst( $mode ) ),
				'<span class="description">' . esc_html( $desc ) . ' ' . wp_kses( $preview_links, array( 'a' => array( 'href' => array() ) ) ) . '</span>'
			);
		}

		printf(
			'<p><label><input type="checkbox" name="%1$s" value="1"%2$s> %3$s</label><br><span class="description">%4$s</span></p>',
			esc_attr( self::$meta_email_embed_images ),
			checked( (bool) get_user_option( self::$meta_email_embed_images, $user->ID ), true, false ),
			esc_html__( 'Embed images in HTML emails', 'years-ago-today' ),
			esc_html__( 'Include photos uploaded to this site in the email so they can display without downloading remote images. This increases the email size. External images still load remotely.', 'years-ago-today' )
		);

		echo "\t\t\t\t</fieldset>\n";

		echo "\t\t\t</td>\n";
		echo "\t\t</tr>\n";
		echo "\t\t</table>\n";
	}

	/**
	 * Saves value of checkbox to allow user to opt into receiving daily emails
	 * about posts published on this day in previous years.
	 *
	 * @since 1.0
	 *
	 * @param  int  $user_id The user ID.
	 * @return void|false False if the current user cannot edit this user.
	 */
	public static function option_save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core already verifies nonces, but also the value is only used for a comparison.
		if ( isset( $_POST[ self::$option_name ] ) && self::$enabled_option_value === sanitize_text_field( wp_unslash( $_POST[ self::$option_name ] ) ) ) {
			update_user_option( $user_id, self::$option_name, self::$enabled_option_value );
		} else {
			delete_user_option( $user_id, self::$option_name );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core already verifies nonces, but also the value is only used for a comparison.
		if ( isset( $_POST[ self::$meta_email_content_pref ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core already verifies nonces, but also the value is only used for a comparison.
			$value = sanitize_key( wp_unslash( $_POST[ self::$meta_email_content_pref ] ) );
			if ( in_array( $value, self::get_email_content_types(), true ) ) {
				update_user_option( $user_id, self::$meta_email_content_pref, $value );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core already verifies profile nonces.
		if ( isset( $_POST[ self::$meta_email_embed_images ] ) && '1' === wp_unslash( $_POST[ self::$meta_email_embed_images ] ) ) {
			update_user_option( $user_id, self::$meta_email_embed_images, 1 );
		} else {
			delete_user_option( $user_id, self::$meta_email_embed_images );
		}
	}

	/**
	 * Returns the user's preference for email content type.
	 *
	 * Note: This does not verify whether the user has opted into getting an email,
	 * just what type of email they would get if they were to be emailed.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id The user ID.
	 * @return string
	 */
	public static function get_user_email_content_pref( $user_id ) {
		$pref = get_user_option( self::$meta_email_content_pref, $user_id );

		// Unset the preference if it isn't valid (so that the default can be used).
		if ( ! in_array( $pref, self::get_email_content_types() ) ) {
			$pref = '';
		}

		return $pref ?: self::$email_content_default;
	}

	/**
	 * Handles the email template preview.
	 *
	 * @since 2.0
	 */
	public static function handle_email_template_preview() {
		$query_key = 'preview-years-ago-today-email';

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// Bail if not on the email template preview page.
		if (
			! is_admin() ||
			! is_user_logged_in() ||
			! current_user_can( 'read' ) ||
			! isset( $_GET[ $query_key ] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Value is only checked for presence.
			'1' !== $_GET[ $query_key ] ||    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Value is only used for a comparison.
			! $screen ||
			'profile' !== $screen->id
		) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is only used for a comparison.
		$content_type = $_GET['content-type'] ?? self::$email_content_default;
		if ( ! in_array( $content_type, self::get_email_content_types(), true ) ) {
			$content_type = self::get_user_email_content_pref( get_current_user_id() );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is only used for a comparison.
		$type = $_GET['type'] ?? 'html';
		if ( ! in_array( $type, array( 'text', 'html' ), true ) ) {
			$type = 'html';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is only checked for presence.
		$force = $_GET['force'] ?? false;
		if ( $force ) {
			add_filter( 'c2c_years_ago_today-email-if-no-posts', '__return_true' );
		}

		// Styles.
		echo '<style>
			body {
				background-color: #fbfbfb;
			}
			p {
				max-width: 600px;
				margin: 30px auto;
			}
			p.text-container {
				white-space: pre-wrap;
				background-color: #fff;
				border: 1px solid #eee;
				border-radius: 6px;
				padding: 32px 24px;
			}
			p.header {
				font-style: italic;
			}
			p.header, p.footer {
				text-align: center;
			}
			p.footer {
				margin-top: 50px;
			}
		</style>';

		// Provide context for the email preview.
		echo '<p class="header">';
		echo esc_html( sprintf(
			/* translators: 1: type of email (plain text or HTML), 2: sentence describing content type (if excerpt or full) */
			__( 'This is a preview of the %1$s version of the Years Ago Today email. %2$s', 'years-ago-today' ),
			( $type === 'text' ? __( 'plain text', 'years-ago-today' ) : __( 'HTML', 'years-ago-today' ) ),
			( $content_type !== 'list' ? sprintf(
				/* translators: %s: type of content (excerpts or full content) */
				__( 'The email includes %s of each post.', 'years-ago-today' ),
				( $content_type === 'excerpt' ? __( 'excerpts', 'years-ago-today' ) : __( 'full content', 'years-ago-today' ) )
			) : '' )
		) );
		echo '</p>';

		// Get the email body.
		$body = self::get_email_body( $content_type )[ $type ];
		if ( 'text' === $type ) {
			$body = '<p class="text-container">' . $body . '</p>';
		}

		echo '<p class="header"><strong>' . esc_html__( 'Subject:', 'years-ago-today' ) . '</strong> ' . esc_html( self::get_email_subject() ) . '</p>';

		if ( $body ) {
			echo wp_kses_post( $body );
		} else {
			echo "<p><em>" . esc_html__( 'No posts found for today. No email would be sent.', 'years-ago-today' ) . "</em></p>";
		}

		echo '<p class="header">';
		esc_html_e( 'Note that there may be differences in how the email is displayed by different email clients.', 'years-ago-today' );
		echo '</p>';

		printf(
			'<p class="footer"><a href="%s">%s</a></p>',
			esc_url( admin_url( 'profile.php' ) ),
			esc_html__( 'Return to profile', 'years-ago-today' )
		);

		if ( defined( 'WP_RUNNING_TESTS' ) && WP_RUNNING_TESTS ) {
			return;
		}

		die;
	}

} // end c2c_YearsAgoToday

add_action( 'plugins_loaded', array( 'c2c_YearsAgoToday', 'init' ) );
register_activation_hook( __FILE__, array( 'c2c_YearsAgoToday', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'c2c_YearsAgoToday', 'deactivate' ) );
c2c_YearsAgoToday::cron_init();

endif; // end if !class_exists()
