=== Years Ago Today ===
Contributors: coffee2code
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: dashboard, admin dashboard, on this day, past posts, history, dashboard widget, posts, coffee2code
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 4.1
Tested up to: 4.2
Stable tag: 1.0

Admin dashboard widget (and optional daily email) that lists posts published to your site on this day in years past.

== Description ==

This plugin provides a simply admin dashboard widget that lists all of the posts published to your site on this day in years past. Users have the option (via their profiles) to opt into receiving a daily email that provides a listing and links to all of the posts published to your site on this day in years past.

Links: [Plugin Homepage](http://coffee2code.com/wp-plugins/years-ago-today/) | [Plugin Directory Page](https://wordpress.org/plugins/years-ago-today/) | [Author Homepage](http://coffee2code.com/)


== Installation ==

1. Unzip `years-ago-today.zip` inside the plugins directory for your site (typically `/wp-content/plugins/`). Or install via the built-in WordPress plugin installer)
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


== Changelog ==

= 1.0 =
* Initial public release


== Upgrade Notice ==

= 1.0 =
Initial public release.
