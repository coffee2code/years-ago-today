=== Years Ago Today ===
Contributors: coffee2code, audrey
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: dashboard, admin, on this day, history, coffee2code
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 5.6
Tested up to: 7.0
Stable tag: 2.0

Lists posts published to your site on this day in previous years via admin dashboard widget, block, shortcode, and/or optional daily email.

== Description ==

This plugin provides a simply admin dashboard widget that lists all of the posts published to your site on this day in previous years. The same listing can be shown on the front-end via the "Years Ago Today" block (WordPress 7.0+) or the `[years-ago-today]` shortcode. Users have the option (via their profiles) to opt into receiving a daily email that provides a listing and links to all of the posts published to your site on this day in previous years.

Links: [Plugin Homepage](https://coffee2code.com/wp-plugins/years-ago-today/) | [Plugin Directory Page](https://wordpress.org/plugins/years-ago-today/) | [GitHub](https://github.com/coffee2code/years-ago-today/) | [Author Homepage](https://coffee2code.com/)


== Installation ==

1. Install via the built-in WordPress plugin installer. Or download and unzip `years-ago-today.zip` inside the plugins directory for your site (typically `wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' admin menu in WordPress
3. View the widget on your admin dashboard.
4. (Optional.) To show the same listing on the front-end, insert the "Years Ago Today" block (WordPress 7.0+) or add the `[years-ago-today]` shortcode to a post or page. Both output a title which can be overridden or omitted.
5. (Optional.) To sign up for a daily email that lists posts published that day, go to your profile, set the checkbox for '"Years Ago Today" email', choose the style of email you wish to receive, optionally enable embedding uploaded images in HTML emails, and then press the "Update Profile" button to update your profile.


== Screenshots ==

1. The admin dashboard showing posts published on the current day in previous years.
2. The admin dashboard when no posts were published on the current day in any previous year.
3. Profile option for opting into receiving a daily email of posts published on the current day in previous years. Also shown are links for previewing each style of email.
4. Preview of the HTML email with the default basic listing of posts by year.
5. Preview of the HTML email, but with full post content included.
6. Preview of the plain text email.

Email previews include a date picker, arrows to skip to dates with posts from previous years, and a button to send a test email to your own address. If today has no matching posts, a date with posts is selected automatically. Test emails use the previewed content style and your saved image preference.


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

= Is this plugin compatible with multisite? =

Yes.

= Does this plugin include unit tests? =

Yes. The tests are not packaged in the release .zip file or included in plugins.svn.wordpress.org, but can be found in the [plugin's GitHub repository](https://github.com/coffee2code/years-ago-today/).


== Developer Documentation ==

Developer documentation can be found in [DEVELOPER-DOCS.md](https://github.com/coffee2code/years-ago-today/blob/master/DEVELOPER-DOCS.md). That documentation covers the numerous hooks provided by the plugin. Those hooks are listed below to provide an overview of what's available.

* `c2c_years_ago_today-email_cron_time` : Customize the time of day to email the Years Ago Today email to those who have opted-in to it. By default this is "9:00 am".
* `c2c_years_ago_today-email-if-no-posts` : Override whether the daily Years Ago Today email is sent out on days that don't have any posts in prior years. By default this value is false, meaning no email is sent in such circumstances.
* `c2c_years_ago_today-email-body-no-posts` : Customize the content of the body of the daily Years Ago Today email when it is sent on days that had no posts in prior years. 
* `c2c_years_ago_today-first_published_year` : Explicitly define the earliest year to be considered when finding earlier published posts.
* `c2c_years_ago_today-post_types` : Customize the post types included for consideration. Default `['post']`.
* `c2c_years_ago_today-batch_size` : Customize the number of daily emails sent per batch. Default 40.
* `c2c_years_ago_today-to_address` : Customize the email address specified for the "To:" field of batched emails. Default "noreply@{site-domain}".


== Changelog ==

= 2.0 (2026-06-04) =
Highlights:

This recommended release adds a block, adds a shortcode, adds an email previewer, adds support for additional post types via filter, improves performance and sending of emails, prevents translations from containing unintended markup, notes compatibility through WP 7.0+ and PHP 8.5+, drops compatibility with versions of WP older than 5.6, and much more.

Details:

* New: Add front-end block and `[years-ago-today]` shortcode to display the same listing as the admin dashboard widget
    * New: Add `get_widget_markup()` and `get_widget_inner_markup()` to centralize listing markup generation
    * New: Register PHP-only block `coffee2code/years-ago-today` on WordPress 7.0+
    * New: Add public CSS for block and shortcode output
* New: Send full HTML of posts in the email alongside plain text version. Fixes #5. Props akirk.
    * New: Extract HTML email generation logic out from `get_email_body()` into new `get_html_email()`
    * New: Add `get_html_email_template()` to return the template used for HTML emails, with placeholders for subject, body, and footer
    * New: Add `get_resized_content()` to rewrite image tags in post content to use smaller-sized versions
    * New: Add `get_html_email_image_size()` to get image size used in HTML emails
    * New: Add filter 'c2c_years_ago_today-html_email_image_size' to customize image size used in HTML emails
    * Change: Move amending of footer into `get_email_body()` and add optional arg to disable that behavior
* New: Add email previewer to preview variations of both plain text and HTML emails
* New: Add support for including additional post types via new 'c2c_years_ago_today-post_types' filter
* Change: Add date to email subject
* Change: Add optional `$include_year` argument to `get_formatted_date_string()`, allowing for full date with year
* Fix: Restore proper registration of activation/deactivation hooks. Fixes #4. Props akirk.
* Fix: Ensure timing of emails is consistent with site's timezone rather than the server's
* Change: Improve performance of `get_posts()`
    * Change: Cache post IDs and only query for IDs when more is needed
    * New: Add `clear_transient_on_publish()`, `get_post_ids_cache_key()`, and `query_post_ids()`
    * New: Hook 'save_post' to clear transient when backporting a post to the current day in any past year
* Change: Improve the sending of emails
    * Change: Group users together (via BCC:) and email them in batches rather than individually when sending to multiple users
    * New: Add `get_bcc_batch_size()` to get number of people to email per BCC: batch
    * New: Add filter 'c2c_years_ago_today-batch_size' to customize the number of people to email per BCC: batch
    * New: Add `get_bcc_to_email_address()` to get the 'To:' email address used for Bcc-batched emails
    * New: Add filter 'c2c_years_ago_today-to_address' to customize the 'To:' email address used for Bcc-batched emails
    * New: Extract email handling logic out from `cron_email()` into new `send_email_of_type()`
    * New: Extract email address grouping logic out from `cron_email()` into new `get_users_to_email_grouped_by_content_type()`
* Change: Output full month instead of 3-letter abbreviations
* Change: Revamp dashboard output, more semantically putting each year within `section` tags
* Change: Align `get_first_published_year()` query with `get_posts()`
* Change: Incorporate a hash of post types into the first published year cache key
* Fix: Properly clear the first published year cache entry on deactivation by using the correct cache key
* Change: Ensure list bullets are consistently visible in all dark mode schemes
* New: Add descriptive help text to profile setting for accessibility and clarity
* New: Enqueue JS on profile pages
* New: Enqueue separate admin CSS file rather than outputting into `head`
* Change: Also enqueue CSS on profile pages
* Change: Change caching to support multisite usage
* Hardening: Prevent translated and generated strings from containing unintended markup
* Hardening: Prevent PHP warnings from any potential malicious form submission field value
* Change: Update plugin description
* Change: Use consistent phrasing across UI and documentation
* Change: Rename `add_user_email_footer()` to `get_email_footer()` and remove arguments and just return the footer based on a format ('text' or 'html')
* Change: Add context to translation of dashboard widget title to differentiate it from plugin's name
* Change: Allow year headings in plain text emails to be translated
* Change: Remove `is_admin()` guard from `init()`, which isn't strictly necessary and impedes unit tests
* Change: Update uses of `current_time()` and `mysql2date()` to more modern `wp_date()`
* New: Add `get_optin_label()` to centralize the optin label since it is used in two places
* Change: Discontinue use of HEREDOC syntax
* Change: Discontinue unnecessary explicit loading of textdomain
* Change: Add missing translator comments for strings with one placeholder
* Change: Prevent unwarranted PHPCS complaints
* Fix: Fix translator string that referenced an extraneous placeholder
* Change: Note compatibility through WP 7.0+
* Change: Drop compatibilities with versions of WP older than 5.6
* Change: Note compatibility through PHP 8.5+
* Change: Update copyright date (2026)
* Change: Add new screenshots and update existing screenshots

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

_Full changelog is available in [CHANGELOG.md](https://github.com/coffee2code/years-ago-today/blob/master/CHANGELOG.md)._


== Upgrade Notice ==

= 2.0 =
Major update: Added block, shortcode, email previewer; send emails as HTML (optionally including full posts or excerpts); improved performance and emails; added support for more post types; fixed bugs; now supporting WP 5.6-7.0+ and PHP through 8.5+; updated copyright date (2026); and much more

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
