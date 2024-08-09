<?php
/**
 * Plugin Name: Years Ago Today
 * Version:     1.6
 * Plugin URI:  https://coffee2code.com/wp-plugins/years-ago-today/
 * Author:      Scott Reilly
 * Author URI:  https://coffee2code.com/
 * Text Domain: years-ago-today
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Admin dashboard widget (and optional daily email) that lists posts published to your site on this day in years past.
 *
 * Compatible with WordPress 4.9 through 6.6+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://wordpress.org/plugins/years-ago-today/
 *
 * @package Years_Ago_Today
 * @author  Scott Reilly
 * @version 1.6
 */

/*
	Copyright (c) 2015-2024 by Scott Reilly (aka coffee2code)

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
	 * posts published on that day in years past.
	 *
	 * @var string
	 * @access public
	 */
	public static $option_name = 'c2c_years_ago_today_daily_email_optin';

	/**
	 * Name for the cron task to send out the daily email.
	 *
	 * @var string
	 * @access public
	 */
	public static $cron_name = 'c2c_years_ago_daily_cron';

	/**
	 * Default value for the meta value to indicate the user wants the daily email
	 * of posts published on that day in years past.
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
		return '1.6';
	}

	/**
	 * Hooks actions and filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return false;
		}

		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		// Load textdomain.
		load_plugin_textdomain( 'years-ago-today' );

		/* Register hooks. */

		// Register dashboard widget.
		add_action( 'wp_dashboard_setup',       array( __CLASS__, 'dashboard_setup' ) );

		// Adds the checkbox to user profiles.
		add_action( 'personal_options',         array( __CLASS__, 'add_daily_email_optin_checkbox' ) );

		// Saves the user preference for daily emails.
		add_action( 'personal_options_update',  array( __CLASS__, 'option_save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'option_save' ) );

		// TODO: remove
		add_action( 'load-index.php',           array( __CLASS__, 'add_admin_css' ) );
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
		if ( ! wp_next_scheduled( self::$cron_name ) ) {
			/**
			 * Filters the time of the day that the daily Years Ago Today email is sent.
			 *
			 * @since 1.1.0
			 *
			 * @param string $time The time of day to email the Years Ago Today email to
			 *                     those who have opted-in to it. Default "9:00 am".
			 */
			$time = apply_filters( 'c2c_years_ago_today-email_cron_time', '9:00 am' );
			$timestamp = ( strtotime( $time ) > time() ) ? strtotime( $time ) : strtotime( 'tomorrow ' . $time );

			wp_schedule_event( $timestamp, 'daily', self::$cron_name );
		}
	}

	/**
	 * Handles deactivation tasks.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::$cron_name );

		wp_cache_delete( 'first_published_year', 'c2c_years_ago_today' );
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
			'meta_key'   => $wpdb->get_blog_prefix() . self::$option_name,
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
	 * @param string $time The timestamp to be formatted. Default is the current
	 *                     time's timestamp.
	 * @return string      The timestamp formatted according to the date format
	 *                     string, which by default is "M jS".
	 */
	public static function get_formatted_date_string( $timestamp = '' ) {
		if ( ! $timestamp ) {
			$timestamp = current_time( 'timestamp' );
		}

		/* translators: date string for today */
		return date_i18n( __( 'M jS', 'years-ago-today' ), $timestamp );
	}

	/**
	 * Returns the body of the daily email.
	 *
	 * @since 1.2
	 *
	 * @return string
	 */
	public static function get_email_body() {
		// Get the list of posts from years ago.
		$query = self::get_posts();

		$site_name = wp_specialchars_decode( get_option('blogname'), ENT_QUOTES );

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
					 *                           %1$s on %2$s in any past year.'.
					 */
					apply_filters(
						'c2c_years_ago_today-email-body-no-posts',
						/* translators: 1: name of the site, 2: date string for today */
						__( 'No posts were published to the site %1$s on %2$s in any past year.', 'years-ago-today' )
					),
					$site_name,
					self::get_formatted_date_string()
				);
			}
			// Else don't define an email body.
			else {
				$body = '';
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

			$year = '';
			while ( $query->have_posts() ) :
				$query->the_post();
				$this_year = get_the_date( 'Y' );
				// Only output the year once.
				if ( $year != $this_year ) {
					$year = $this_year;
					$body .= "\n\n== $year ==\n";
				}

				$body .= '* ' . get_the_title() .  ' : ' . esc_url( get_permalink() ) . "\n";
			endwhile;
		}

		return $body;
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
			/* translators: %s: site name in subject for daily email */
			__( '[%s] Years Ago Today daily update', 'years-ago-today' ),
			wp_specialchars_decode( get_option('blogname'), ENT_QUOTES )
		);
	}

	/**
	 * Amends a user-specific footer to an email body.
	 *
	 * Adds an explanation about the email to the recipient. Serves to remind
	 * the user why they are receiving the email, what it is about, and how to
	 * stop it.
	 *
	 * @since 1.2
	 *
	 * @param  int    $user_id The user ID.
	 * @param  string $body.   The email body.
	 * @return string
	 */
	public static function add_user_email_footer( $user_id, $body ) {
		$body .= "\n\n\n-------------------------------\n";
		$body .= sprintf(
			__( 'You received this email because you have opted into receiving a daily email about posts published on this day in years past on the site %s, which is using the Years Ago Today plugin.', 'years-ago-today' ),
			wp_specialchars_decode( get_option('blogname'), ENT_QUOTES )
		);
		$body .= "\n\n";
		$body .= sprintf(
			__( 'If you wish to discontinue receiving these emails, simply log into the site and visit your profile at %s to uncheck the checkbox labeled "Email me daily about posts published on this day in years past."', 'years-ago-today' ),
			get_edit_profile_url( $user_id )
		);
		$body .= "\n";

		return $body;
	}

	/**
	 * Sends out daily email.
	 *
	 * @since 1.0
	 * @todo  Handle large volume of users better, perhaps via chunked BCCs.
	 */
	public static function cron_email() {
		// Get list of users who want the daily email.
		$users = self::get_users_to_email();

		// If no one wants the email, there's nothing else to do.
		if ( ! $users ) {
			return;
		}

		// Get the content of the email.
		$subject = self::get_email_subject();
		$body    = self::get_email_body();

		// If no subject or body for the email, then there's nothing else to do.
		if ( ! $subject || ! $body ) {
			return;
		}

		// Send email to each user.
		foreach ( $users as $user ) {
			if ( $user->user_email ) {
				wp_mail( $user->user_email, $subject, self::add_user_email_footer( $user->ID, $body ) );
			}
		}
	}

	/**
	 * Set up the admin dashboard.
	 *
	 * @since 1.0
	 */
	public static function dashboard_setup() {
		wp_add_dashboard_widget(
			'dashboard_years_ago_today',
			__( 'Years Ago Today', 'years-ago-today' ),
			array( __CLASS__, 'wp_dashboard_years_ago_today' )
		);
	}

	/**
	 * Outputs the admin dashboard.
	 *
	 * @since 1.0
	 */
	public static function wp_dashboard_years_ago_today() {
		echo '<div class="main">';

		$q = self::get_posts();

		if ( $q->have_posts() ) :
			echo '<p>';
			echo sprintf(
				/* translators: 1: number of posts, 2: site name, 2: date string for today */
				_n(
					'<strong>%1$d</strong> post has been published on <strong>%2$s</strong> in a previous year:',
					'<strong>%1$d</strong> posts have been published on <strong>%2$s</strong> in previous years:',
					$q->post_count,
					'years-ago-today'
				),
				$q->post_count,
				self::get_formatted_date_string()
			);
			echo '</p>';
			echo '<ul class="years-ago-today-posts">';
			$year = '';
			while ( $q->have_posts() ) :
				$q->the_post();
				$this_year = get_the_date( 'Y' );
				// Only output the year once.
				if ( $year != $this_year ) {
					$year = $this_year;
					echo "<li class='years-ago-today-year'><h4>$year</h4></li>\n";
				}

				the_title( '<li><a href="' . esc_url( get_permalink() ) . '">', '</a></li>' );
			endwhile;
			echo '</ul>';
		else :
			echo '<p>';
			printf(
				/* translators: %s: date string for today */
				__( 'No posts were published on <strong>%s</strong> from any past year.', 'years-ago-today' ),
				self::get_formatted_date_string()
			);
			echo '</p>';
		endif;

		echo '</div>';
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

		// If not provided via filter, try to get it from the cache.
		if ( false === $first_year ) {
			$first_year = wp_cache_get( 'first_published_year', 'c2c_years_ago_today' );
		}

		// If not in the cache, figure it out.
		if ( false === $first_year ) {
			// Query for the earliest published year.
			$first_year = $wpdb->get_var( "SELECT YEAR(MIN(post_date)) FROM $wpdb->posts WHERE post_status IN ( 'publish', 'private' )" );

			// If nothing was found, assume current year.
			if ( ! $first_year ) {
				$first_year = current_time( 'Y' );
			}

			// Cache the year.
			wp_cache_add( 'first_published_year', $first_year, 'c2c_years_ago_today' );
		}

		return $first_year;
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
		$first_year   = self::get_first_published_year();
		$current_year = mysql2date( 'Y', current_time( 'mysql' ) );

		$years = range( $first_year, $current_year - 1 );
		$month = current_time( 'm' );
		$day   = current_time( 'd' );

		$query = new WP_Query( array(
			'post_parent'    => '',
			'post_status'    => array( 'publish' ),
			'post_type'      => array( 'post' ),
			'posts_per_page' => -1,
			'date_query'     => array(
				'year'  => $years,
				'month' => $month,
				'day'   => $day,
			),
		) );

		return $return_posts ? $query->get_posts() : $query;
	}

	/**
	 * Adds hook to outputs CSS for the display of the Years Ago today widget.
	 *
	 * @since 1.0
	 */
	public static function add_admin_css() {
		add_action( 'admin_head', array( __CLASS__, 'admin_css' ) );
	}

	/**
	 * Adds the checkbox to user profiles to allow them to opt into receiving a
	 * daily email about posts published in years past.
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

		$checked  = checked( get_user_option( self::$option_name, $user->ID ), self::$enabled_option_value, false );
		$disabled = disabled( true, defined( 'DISABLE_WP_CRON' ) && true === DISABLE_WP_CRON, false );
		$label = $is_current_user_profile_page
			? __( 'Email me daily about posts published on this day in years past.', 'years-ago-today' )
			: __( 'Email this user daily about posts published on this day in years past.', 'years-ago-today' );
?>
		<table class="form-table">
		<tr>
			<th scope="row"><?php _e( '"Years Ago Today" email', 'years-ago-today' ); ?></th>
			<td>
				<label for="<?php echo esc_attr( self::$option_name ); ?>">
					<input name="<?php echo esc_attr( self::$option_name ); ?>" type="checkbox" id="<?php echo esc_attr( self::$option_name ); ?>" value="<?php echo esc_attr( self::$enabled_option_value ); ?>"<?php echo $checked; ?><?php echo $disabled; ?> />
					<?php echo $label; ?>
				</label>
			</td>
		</tr>
		</table>
<?php
	}

	/**
	 * Saves value of checkbox to allow user to opt into receiving daily emails
	 * about posts published on this day in years past.
	 *
	 * @since 1.0
	 *
	 * @param  int  $user_id The user ID.
	 * @return bool          True if the option saved successfully.
	 */
	public static function option_save( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( isset( $_POST[ self::$option_name ] ) && self::$enabled_option_value === $_POST[ self::$option_name ] ) {
			return update_user_option( $user_id, self::$option_name, self::$enabled_option_value );
		} else {
			return delete_user_option( $user_id, self::$option_name );
		}
	}

	/**
	 * Outputs CSS.
	 *
	 * @since 1.0
	 */
	public static function admin_css() {
		echo "<style>
			#dashboard-widgets .years-ago-today-posts h4 {
				font-weight: bold;
			}
			#dashboard-widgets .years-ago-today-posts li:not(.years-ago-today-year) {
				margin-left: 30px;
				list-style: initial;
			}
		</style>\n";
	}

} // end c2c_YearsAgoToday

add_action( 'plugins_loaded', array( 'c2c_YearsAgoToday', 'init' ) );
c2c_YearsAgoToday::cron_init();

endif; // end if !class_exists()
