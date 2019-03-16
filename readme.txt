=== Years Ago Today ===
Contributors: coffee2code
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: dashboard, admin dashboard, on this day, past posts, history, dashboard widget, posts, coffee2code
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 4.6
Tested up to: 5.1
Stable tag: 1.2.2

Admin dashboard widget (and optional daily email) that lists posts published to your site on this day in years past.

== Description ==

This plugin provides a simply admin dashboard widget that lists all of the posts published to your site on this day in years past. Users have the option (via their profiles) to opt into receiving a daily email that provides a listing and links to all of the posts published to your site on this day in years past.

Links: [Plugin Homepage](http://coffee2code.com/wp-plugins/years-ago-today/) | [Plugin Directory Page](https://wordpress.org/plugins/years-ago-today/) | [GitHub](https://github.com/coffe2code/years-ago-today/) | [Author Homepage](http://coffee2code.com/)


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


== Changelog ==

= () =
* New: Add CHANGELOG.md file and move all but most recent changelog entries into it
* Change: Initialize plugin on 'plugins_loaded' action instead of on load
* Change: Merge `do_init()` into `init()`
* Change: Split paragraph in README.md's "Support" section into two
* Change: Note compatibility through WP 5.1+
* Change: Update copyright date (2019)
* Change: Update License URI to be HTTPS

= 1.2.2 (2017-11-08) =
* New: Add README.md
* Change: Add GitHub link to readme
* Change: Note compatibility through WP 4.9+
* Change: Update copyright date (2018)
* Change: Minor whitespace tweaks in unit test bootstrap

= 1.2.1 (2017-05-09) =
* Fix: Properly constrain CSS `li` styling to apply only to plugin's dashboard widget and not any other dashboard widgets

= Full changelog is available in [CHANGELOG.md](CHANGELOG.md). =


== Upgrade Notice ==

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
