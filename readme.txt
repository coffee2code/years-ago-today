=== Years Ago Today ===
Contributors: coffee2code
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: dashboard, admin, on this day, history, coffee2code
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 4.9
Tested up to: 6.6
Stable tag: 1.6

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

= Does this plugin include unit tests? =

Yes. The tests are not packaged in the release .zip file or included in plugins.svn.wordpress.org, but can be found in the [plugin's GitHub repository](https://github.com/coffee2code/years-ago-today/).


== Developer Documentation ==

Developer documentation can be found in [DEVELOPER-DOCS.md](https://github.com/coffee2code/years-ago-today/blob/master/DEVELOPER-DOCS.md). That documentation covers the numerous hooks provided by the plugin. Those hooks are listed below to provide an overview of what's available.

* `c2c_years_ago_today-email_cron_time` : Customize the time of day to email the Years Ago Today email to those who have opted-in to it. By default this is "9:00 am".
* `c2c_years_ago_today-email-if-no-posts` : Override whether the daily Years Ago Today email is sent out on days that don't have any posts in prior years. By default this value is false, meaning no email is sent in such circumstances.
* `c2c_years_ago_today-email-body-no-posts` : Customize the content of the body of the daily Years Ago Today email when it is sent on days that had no posts in prior years. 
* `c2c_years_ago_today-first_published_year` : Explicitly define the earliest year to be considered when finding earlier published posts.


== Changelog ==

= 1.6 (2024-08-09) =
* Fix: Convert use of deprecated string interpolation syntax to prevent notice under PHP8.2. Props Simounet.
* Change: Note compatibility through WP 6.6+
* Change: Update copyright date (2024)
* New: Add `.gitignore` file
* Change: Remove development and testing-related files from release packaging
* Unit tests:
    * Hardening: Prevent direct web access to `bootstrap.php`
    * Allow tests to run against current versions of WordPress
    * New: Add `composer.json` for PHPUnit Polyfill dependency
    * Change: In bootstrap, store path to plugin file in a constant
    * Change: In bootstrap, add backcompat for PHPUnit pre-v6.0

= 1.5.1 (2023-06-11) =
* Change: Note compatibility through WP 6.3+
* Change: Update copyright date (2023)
* New: Add link to DEVELOPER-DOCS.md in README.md

= 1.5 (2022-10-12) =
Highlights:

* This minor release improves cron handling, removes the little HTML used from emails, prevents a PHP8 warning, adds DEVELOPER-DOCS.md, notes compatibility through WP 6.0+, and reorganizes unit test files.

Details:

* Fix: Make `__wakeup()` public to prevent PHP8 warnings. Props Simounet, koolinus.
* Change: Move cron initialization into new `cron_init()`
* Change: Register cron task earlier. Props Simounet.
* Change: Remove HTML from email. Props Simounet.
* New: Add DEVELOPER-DOCS.md and move hooks documentation into it
* Change: Pare plugin tags down to 5
* Change: Note compatibility through WP 6.0+
* Change: Update copyright date (2022)
* Unit tests:
    * Change: Restructure unit test directories
        * Change: Move `phpunit/` into `tests/phpunit/`
        * Change: Move `phpunit/bin/` into `tests/`
    * Change: Remove 'test-' prefix from unit test file
    * Change: In bootstrap, store path to plugin file constant

_Full changelog is available in [CHANGELOG.md](https://github.com/coffee2code/years-ago-today/blob/master/CHANGELOG.md)._


== Upgrade Notice ==

= 1.6 =
Minor update: Prevented deprecation notice under PHP8.2, noted compatibility through WP 6.6+, removed unit tests from release packaging, and updated copyright date (2024)

= 1.5.1 =
Trivial update: noted compatibility through WP 6.3+ and updated copyright date (2023)

= 1.5 =
Minor update: improved cron handling, discontinued HTML in emails, prevented a PHP8 warning, added DEVELOPER-DOCS.md, noted compatibility through WP 6.0+, and reorganized unit test files.

= 1.4 =
Minor update: added ability for admins to edit the value of the setting for other users, noted compatibility through WP 5.7+., and updated copyright date (2021).

= 1.3.4 =
Trivial update: Removed some extra spaces from output markup, restructured unit test file structure, expanded unit test coverage, and noted compatibility through WP 5.5+.

= 1.3.3 =
Trivial update: added TODO.md file, updated a few URLs to be HTTPS, expanded unit testing, and noted compatibility through WP 5.4+

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
