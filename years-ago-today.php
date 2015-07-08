<?php
/**
 * Plugin Name: Years Ago Today
 * Version:     1.0
 * Plugin URI:  http://coffee2code.com/wp-plugins/years-ago-today/
 * Author:      Scott Reilly
 * Author URI:  http://coffee2code.com/
 * Text Domain: years-ago-today
 * Domain Path: /lang/
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Description: Admin dashboard widget (and optional daily email) that lists posts published to your site on this day in years past.
 *
 * Compatible with WordPress 4.1 (and probably earlier) through 4.2+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://wordpress.org/plugins/years-ago-today/
 *
 * @package Years_Ago_Today
 * @author  Scott Reilly
 * @version 1.0
 */

/*
 * TODO:
 * - Put CSS in .css file.
 * - Show more info about post in widget, such as author. (Maybe hide by default and show on hover.)
 * - Add cap to control what users can get the daily email?
 * - Add way to filter by author.
 * - Allow post listing template to be overridden.
 */

/*
	Copyright (c) 2015 by Scott Reilly (aka coffee2code)

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
	 * Returns version of the plugin.
	 *
	 * @since 1.0
	 */
	public static function version() {
		return '1.0';
	}

	/**
	 * Hooks actions and filters.
	 *
	 * @since 1.0
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );

		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * Performs initializations on the 'init' action.
	 *
	 * @since 1.0
	 */
	public static function do_init() {
		// Load textdomain.
		load_plugin_textdomain( 'years-ago-today', false, basename( __DIR__ ) );

		// Register hooks.
		add_action( 'wp_dashboard_setup',       array( __CLASS__, 'dashboard_setup' ) );
		add_action( 'posts_where',              array( __CLASS__, 'add_year_clause_to_query' ), 10, 2 );

		// Adds the checkbox to user profiles.
		add_action( 'profile_personal_options', array( __CLASS__, 'add_daily_email_optin_checkbox' ) );

		// Saves the user preference for daily emails.
		add_action( 'personal_options_update',  array( __CLASS__, 'option_save' ) );

		// Register cron task.
		add_action( self::$cron_name,           array( __CLASS__, 'cron_email' ) );

		// TODO: remove
		add_action( 'load-index.php',           array( __CLASS__, 'add_admin_css' ) );
	}

	/**
	 * Handles activation tasks.
	 *
	 * @since 1.0
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::$cron_name ) ) {
			// Schedule the sending of the emails.
			$time = '9:00 am';
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
	 * Sends out daily email.
	 *
	 * @since 1.0
	 */
	public static function cron_email() {
		// Get list of users who want the daily email.
		$users = self::get_users_to_email();

		// If no one wants the email, there's nothing else to do.
		if ( ! $users ) {
			return;
		}

		// Get the list of posts from years ago.
		$query = self::get_posts();

		// If there were no posts, check a filter to see if an email shouldn't be sent.
		if ( ! $query->have_posts() && ! apply_filters( 'c2c_years_ago_today-email-if-no-posts', true ) ) {
			return;
		}  elseif ( ! $query->have_posts() ) {
			$body = apply_filters(
				'c2c_years_ago_today-email-body-no-posts',
				sprintf(
					__( 'No posts were published to the site %s on this day from any past year.', 'years-ago-today' ),
					wp_specialchars_decode( get_option('blogname'), ENT_QUOTES )
				)
			);
		} else {
			// Build out email body.
			$body = sprintf(
				__( 'The following post(s) have been published to the site %s on this day in previous years:', 'wporg' ),
				wp_specialchars_decode( get_option('blogname'), ENT_QUOTES )
			);

			$year = '';
			while ( $query->have_posts() ) : $query->the_post();
				$this_year = get_the_date( 'Y' );
				// Only output the year once.
				if ( $year != $this_year ) {
					$year = $this_year;
					$body .= "\n\n== ${year} ==\n";
				}

				$body .= '* ' . get_the_title() .  ' : ' . esc_url( get_permalink() ) . "\n";
			endwhile;
		}

		// Build subject.
		$subject = sprintf(
			__( '[%s] Years Ago Today daily update', 'years-ago-today' ),
			wp_specialchars_decode( get_option('blogname'), ENT_QUOTES )
		);

		// Send email to each user.
		foreach ( $users as $user ) {
			if ( $user->user_email ) {
				wp_mail( $user->user_email, $subject, $body );
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
			echo '<p>' . __( 'The following post(s) have been published to the site on this day in previous years:', 'wporg' ) . '</p>';
			echo '<ul class="years-ago-today-posts">';
			$year = '';
			while ( $q->have_posts() ) : $q->the_post();
				$this_year = get_the_date( 'Y' );
				// Only output the year once.
				if ( $year != $this_year ) {
					$year = $this_year;
					echo "<li class='years-ago-today-year'><h4>${year}</h4></li>\n";
				}

				the_title( '<li><a href="' . esc_url( get_permalink() ) . '">', '</a></li>' );
			endwhile;
			echo '</ul>';
		else :
			echo '<p>' . __( 'No posts were published on this day from any past year.', 'years-ago-today' ) . '</p>';
		endif;

		echo '</div>';
	}

	/**
	 * Adjusts WHERE clause to include date range when find years ago posts.
	 *
	 * @since 1.0
	 *
	 * @param string   $where The SQL WHERE clause.
	 * @param WP_Query $query The query object.
	 * @return string
	 */
	public static function add_year_clause_to_query( $where, $query ) {
		global $wpdb;

		if ( isset( $query->query_vars['is_years_ago_today'] ) && '1' == $query->query_vars['is_years_ago_today'] ) {

			$first_year = self::get_first_published_year();
			$current_year = mysql2date( 'Y', current_time( 'mysql', 1 ) );

			$years = range( $first_year, $current_year - 1 );
			$now   = current_time( 'timestamp', 1 );
			$month = mysql2date( 'm', $now );
			$day   = mysql2date( 'd', $now );

			// Check data with more performant BETWEEN rather than using DATE()
			// or its variations (YEAR(), MONTH(), DAYOFMONTH()).
			$date_ranges = array();
			foreach ( $years as $year ) {
				$date_ranges[] = $wpdb->prepare(
					'( post_date_gmt BETWEEN %s AND %s )',
					"${year}-${month}-{$day} 00:00:00",
					"${year}-${month}-{$day} 23:59:59"
				);
			}

			if ( $date_ranges ) {
				$where .= ' AND (' . implode( ' OR ', $date_ranges ) . ' ) ';
			}

		}

		return $where;
	}

	/**
	 * Gets the year of the first published post on the site.
	 *
	 * @return string
	 */
	public static function get_first_published_year() {
		global $wpdb;

		// Allow a year to be provided via a filter.
		$first_year = apply_filters( 'c2c_years_ago_ago-first_published_year', false );

		// If not provided via filter, try to get it from the cache.
		if ( false === $first_year ) {
			$first_year = wp_cache_get( 'first_published_year', 'c2c_years_ago_today' );
		}

		// If not in the cache, figure it out.',
		if ( false === $first_year ) {
			// Query for the earlies published year.
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
	 * @param bool            $return_posts True to return just an array of posts, false to return WP_Query object. Default false.
	 * @return array|WP_Query Array is return_posts is true, WP_Query if false.
	 */
	public static function get_posts( $return_posts = false ) {
		$query = new WP_Query( array(
			'post_parent'        => '',
			'post_status'        => array( 'publish' ),
			'post_type'          => array( 'post' ),
			'posts_per_page'     => -1,
			'is_years_ago_today' => 1,
		) );

		return $return_posts ? $query->get_posts() : $query;
	}

	/**
	 * Adds hook to outputs CSS for the display of the Trashed By column if
	 * on the appropriate admin page.
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
	 */
	public static function add_daily_email_optin_checkbox() {
		$checked  = checked( get_user_option( self::$option_name ), self::$enabled_option_value, false );
		$disabled = disabled( true, defined( 'DISABLE_WP_CRON' ) && true === DISABLE_WP_CRON, false );

		?>
		<table class="form-table">
		<tr>
			<th scope="row"><?php _e( '"Years Ago Today" email', 'years-ago-today' ); ?></th>
			<td>
				<label for="<?php echo esc_attr( self::$option_name ); ?>">
					<input name="<?php echo esc_attr( self::$option_name ); ?>" type="checkbox" id="<?php echo esc_attr( self::$option_name ); ?>" value="<?php echo esc_attr( self::$enabled_option_value ); ?>"<?php echo $checked; ?> <?php echo $disabled; ?> />
					<?php _e( 'Email me daily about posts published on this day in years past.', 'years-ago-today' ); ?>
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
	 * @return bool True if the option saved successfully.
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
		echo "<style type='text/css'>
			#dashboard-widgets .years-ago-today-posts h4 {
				font-weight: bold;
			}
			#dashboard-widgets li:not(.years-ago-today-year) {
				margin-left: 30px;
				list-style: initial;
			}
		</style>\n";
	}

} // end c2c_YearsAgoToday

c2c_YearsAgoToday::init();

endif; // end if !class_exists()
