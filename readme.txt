=== Years Ago Today ===
Contributors: coffee2code
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: dashboard, admin dashboard, on this day, past posts, history, dashboard widget, posts, coffee2code
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 4.6
Tested up to: 5.4
Stable tag: 1.3.2

Admin dashboard widget (and optional daily email) that lists posts published to your site on this day in years past.

== Description ==

This plugin provides a simply admin dashboard widget that lists all of the posts published to your site on this day in years past. Users have the option (via their profiles) to opt into receiving a daily email that provides a listing and links to all of the posts published to your site on this day in years past.

Links: [Plugin Homepage](https://coffee2code.com/wp-plugins/years-ago-today/) | [Plugin Directory Page](https://wordpress.org/plugins/years-ago-today/) | [GitHub](https://github.com/coffee2code/years-ago-today/) | [Author Homepage](https://coffee2code.com/)


== Installation ==

1. Install via the built-in WordPress plugin installer. Or download and unzip `years-ago-today.zip` inside the plugins directory for your site (typically `wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' admin menu in WordPress
3. View the widget on your admin dashboard.
4. (Optional.) To sign up for a daily email that lists posts published that day, go to your profile, set the checkbox for '"Years Ago Today" email', and then press the button to update your profile.


== Screenshots ==

1. A screenshot of the admin dashboard showing posts published on the current day in past years.
2. A screenshot of the admin dashboard when no posts were published on the current day in any past year.
3. Profile option for opting into receiving a daily email of posts published on the current day in past years.


== Frequently Asked Questions ==

= If multiple posts were made on this day in a past year, will they be listed? =

Yes.

= Are posts published today included? =

No, only posts made for any year before the current year.

= Can I filter the widget to only show my posts (or only posts for a particular author)? =

Not yet. This functionality is expected in a future update.

= Why is the checkbox for '"Years Ago Today" email' in my profile disabled? =

Your site has its cron system disabled (via the `DISABLE_WP_CRON` constant) which means scheduled events (such as this plugin's daily emails) won't be handled by WordPress.

= I opted into the daily email, but why haven't I gotten it? =

The cron system for WordPress (which handles scheduled events, such as the schedule daily email) requires site traffic to trigger close to its scheduled time, so low traffic sites may not see events fire at a consistent time. It's also possible cron has been disabled by the site (see previous question).


== Hooks ==

The plugin exposes four filters for hooking. Code using this filter should ideally be put into a mu-plugin or site-specific plugin (which is beyond the scope of this readme to explain). Less ideally, you could put them in your active theme's functions.php file.

**c2c_years_ago_today-email_cron_time (filter)**

The 'c2c_years_ago_today-email_cron_time' hook allows you to customize the time of day to email the Years Ago Today email to those who have opted-in to it. By default this is "9:00 am".

Arguments:

* $time (string) : The time of day to email the Years Ago Today email to those who have opted-in to it. Default "9:00 am".

Example:

`
// Send daily Years Ago Today emails at end of day.
add_filter( 'c2c_years_ago_today-email_cron_time', function ( $time ) { return "6:00 pm"; } );
`

**c2c_years_ago_today-email-if-no-posts (filter)**

The 'c2c_years_ago_today-email-if-no-posts' filter is used to override whether the daily Years Ago Today email is sent out on days that don't have any posts in prior years. By default this value is false, meaning no email is sent in such circumstances.

Arguments:

* $send (boolean) : Send the daily Years Ago Today email on days that have no prior year posts? Default false.

Example:

`
// Send daily Years Ago Today email even if there aren't any posts posted in prior years.
add_filter( 'c2c_years_ago_today-email-if-no-posts', '__return_true' );
`

**c2c_years_ago_today-email-body-no-posts (filter)**

The 'c2c_years_ago_today-email-body-no-posts' filter is used to the content of the body of the daily Years Ago Today email when it is sent on days that had no posts in prior years. 

Arguments:

* $text (string) : The content of the email. You can optionally include "%1$s" as a placeholder for the site name and "%2$s" as a placeholder for the date. Default 'No posts were published to the site %1$s on <strong>%2$s</strong> in any past year.'.

Example:

`
// Define custom email text for daily Years Ago Today email when there are no posts to list.
add_filter( 'c2c_years_ago_today-email-body-no-posts', function ( $text ) { return "Sorry, no posts were made on this day in any prior year."; } );
`

**c2c_years_ago_today-first_published_year (filter)**

The 'c2c_years_ago_today-first_published_year' filter allows specifying the year of the earliest published post. By default this is false, which causes the plugin to determine the earliest year via a database query. The queried value does get cached, though may not persist depending on your site setup. This filter can be used to prevent the need for the query or to set a year later than the earliest published year (in case you'd prefer not to feature or be reminded of the early years).

Arguments:

* $year (string|false) : The year of the first published post. False indicates the year should be queried for. Default false.

Example:

`
// Set the earliest published post year for Years Ago Today.
add_filter( 'c2c_years_ago_today-first_published_year', function ( $year ) { return '2009'; } );
`


== Changelog ==

= 1.3.2 (2019-11-16) =
* Fix: Fix incorrect date query handling
* Change: Note compatibility through WP 5.3+
* Change: Update copyright date (2020)

= 1.3.1 (2019-06-10) =
* Fix: Fix incorect handling of timestamp argument when supplied to `get_formatted_date_string()` (bug not triggered within plugin itself)
* Change: Update unit test install script and bootstrap to use latest WP unit test repo
* Change: Note compatibility through WP 5.2+
* Change: Add link to CHANGELOG.md in README.md
* Change: Remove 'Domain Path' plugin header
* Fix: Correct typo in GitHub URL

= 1.3 (2019-03-17) =
* New: Add and use `get_formatted_date_string()` to format the date string used when referring to a given day
* New: Add unit tests for untested hooks
* New: Add CHANGELOG.md file and move all but most recent changelog entries into it
* New: Add "Hooks" section to readme.txt to document hooks provided by the plugin
* New: Add inline documentation for all hooks
* Change: Initialize plugin on 'plugins_loaded' action instead of on load
* Change: Merge `do_init()` into `init()`
* Change: Do placeholder substitutions of site name and day strings after `c2c_years_ago_today-email-body-no-posts` filter is run, so those using the hook have those values available
* Change: Reformat conditional logic handling in `get_email_body()` for improved readability
* Change: Allow date strings to be translated in a plugin-specific way
* CHange: Cast return value of `c2c_years_ago_today-email-if-no-posts` filter as boolean
* Change: Split paragraph in README.md's "Support" section into two
* Change: Note compatibility through WP 5.1+
* Change: Update copyright date (2019)
* Change: Update License URI to be HTTPS

_Full changelog is available in [CHANGELOG.md](CHANGELOG.md)._


== Upgrade Notice ==

= 1.3.2 =
Bugfix update: fixed bug causing unrelated posts to be listed, noted compatibility through WP 5.3+, updated copyright date (2020)

= 1.3.1 =
Trivial update: modernized unit tests, noted compatibility through WP 5.2+

= 1.3 =
Recommended update: tweaked plugin initialization process, minor filter and string translation improvements, created CHANGELOG.md to store historical changelog outside of readme.txt, noted compatibility through WP 5.1+, updated copyright date (2019)

= 1.2.2 =
Trivial update: noted compatibility through WP 4.9+; added README.md; added GitHub link to readme; updated copyright date (2018)

= 1.2.1 =
Minor bugfix update: Prevent admin dashboard CSS styling from applying to other dashboard widgets

= 1.2 =
Minor update: added footer text to daily emails, show today's date and number of posts in dashboard widget and email, use separate singular and plural strings, compatibility is now WP 4.6-4.7+, updated copyright date (2017), and more

= 1.1 =
Recommended update: bugfix for posts published the day after past todays sometimes being included; adjustments to utilize language packs; minor unit test tweaks; noted compatibility through WP 4.4+; and updated copyright date

= 1.0.1 =
Minor bugfixes: Default to not sending email on days without past posts, as originally intended; locate lang files in proper sub-directory; noted compatibility through WP 4.3+

= 1.0 =
Initial public release.
