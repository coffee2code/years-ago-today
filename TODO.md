# TODO

The following list comprises ideas, suggestions, and known issues, all of which are in consideration for possible implementation in future releases.

***This is not a roadmap or a task list.*** Just because something is listed does not necessarily mean it will ever actually get implemented. Some might be bad ideas. Some might be impractical. Some might either not benefit enough users to justify the effort or might negatively impact too many existing users. Or I may not have the time to devote to the task.

* Move CSS into a .css file
* Show more info about post in widget, such as author (if on multi-author site). (Maybe hide by default and show on hover/focus.)
* Add capability to control what users can get the daily email?
* Add way to filter by author
* Allow post listing template to be overridden
* Add widget for front-end use (!! MULTIPLE REQUESTS !!)
* In `get_first_published_year()`/`get_posts()`:
  - Don't use hardcoded post statuses of 'publish' and 'private'. Include latter only if user has relevant caps.
  - Make the statuses filterable for custom post status support
  - Make the post types filterable (and expand default to public post types)
* In `cron_email()`, improve handling for sending large number of emails
* Unit tests: Add tests for `cron_email()`, `option_save()`
* bcc: chunks of the email list if of sufficient size (or always) instead of individual email submissions
* Widget could allow specifying a specific date to list posts from that given date
* Send emails as multipart with HTML and text parts

Feel free to make your own suggestions or champion for something already on the list (via the [plugin's support forum on WordPress.org](https://wordpress.org/support/plugin/years-ago-today/) or on [GitHub](https://github.com/coffee2code/years-ago-today/) as an issue or PR).