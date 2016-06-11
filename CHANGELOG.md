## Changelog ##

This change log follows the [Keep a Changelog standards]. Versions follows [Semantic Versioning].

### [1.7.0] ###

* Add GitHub link shortcode (props @jonocarroll!)
* Add boot hook (props @kennyfraser!)

### [1.6.1] ###

* Fixed bug where post_meta with the same name as built-in meta keys were getting overwritten

### [1.6.0] ###

* New filters:
    * `wpghs_pre_fetch_all_supported`: Filter the query args before all supported posts are queried.
    * `wpghs_is_post_supported`: Determines whether the post is supported by importing/exporting.
* Bugfix: Set secret to password field. See [#124].
* Bugfix: Fix error when importing branch-deletion webhooks.
* Bugfix: Fix "semaphore is locked" response from webhook. See [#121].
* Bugfix: Correctly display import/export messages in settings page. See [#127].
* Bugfix: Correctly set if post is new only when the matching ID is found in the database.

### [1.5.1] ###

* Added Chinese translation (props @malsony!).
* Updated German translation (props @lsinger!).
* Expire semaphore lock to avoid permanently locked install.

### [1.5.0] ###

* New WP-CLI command:
    * `prime`: Forces WPGHS to fetch the latest commit and save it in the cache.
* New filters:
    * `wpghs_sync_branch`: Branch the WordPress install should sync itself with.
    * `wpghs_commit_msg_tag`: Tag appended to the end of the commit message. Split from message with ` - `. Used to determine if commit has been synced already.
* These two new filters allow you to use WPGHS to keep multiple sites in sync.
    * This is an _advanced feature_. Your configuration may or may not be fully supported. **Use at your own risk.**
* Eliminated some direct database calls in exchange for WP_Query usage.

### [1.4.1] ###

* Fix Database error handling
* Fix bug where WPGHS would interfere with other plugins' AJAX hooks.
* Fix transient key length to <40.

### [1.4.0] ###

* Major rewrite of the plugin internals.
    * *Massively* improved internal architecture.
    * Improved speed.
        * Upgraded caching implementation means updates happen faster.
* Line-endings are now normalize to Unix-style.
    * New filter: `wpghs_line_endings` to set preferred line endings.
* New filter: `wpghs_pre_import_args`
    * Called before post arguments are passed for an imported post.
* New filter: `wpghs_pre_import_meta`
    * Called before post meta is imported from a post.
* BREAKING: Remove reference to global `$wpghs` variable.
    * Use `WordPress_GitHub_Sync::$instance` instead.

### [1.3.4] ###

* Add German translation (props @lsinger).
* Update folder names to default to untranslated.

### [1.3.3] ###

* Fix api bug where API call errors weren't getting kicked up to the calling method.

### [1.3.2] ###

* Fix deleting bug where posts that weren't present in the repo were being added.

### [1.3.1] ###

* Re-add validation of post before exporting.
    * Fixed bug where all post types/statuses were being exported.
* Reverted busted SQL query

### [1.3] ###

* New Feature: Support importing posts from GitHub
* New Feature: Support setting revision and new post users on import.
    * Note: There is a new setting, please selected a default/fallback user and saved the settings.

### [1.2] ###

* New Feature: Support displaying an "Edit|View on GitHub" link.
* Update translation strings and implement pot file generation.
* Redirect user away from settings page page after the import/export process starts.
* Fix autoloader to be PHP 5.2 compatible.

### [1.1.1] ###

* Add WPGHS_Post as param to export content filter.

### [1.1.0] ###

* Add filters for content on import and export.

### [1.0.2] ###

* Hide password-protected posts from being exported to GitHub
* Create post slug if WordPress hasn't created it yet (affects draft exporting)

### [1.0.1] ###

* Remove closure to enable PHP 5.2 compatibility (thanks @pdclark!)

### [1.0.0] ###

* Initial release
* Supports full site sync, Markdown import/export, and custom post type & status support

  [Keep a Changelog standards]: http://keepachangelog.com/
  [Semantic Versioning]: http://semver.org/
  [#124]: https://github.com/mAAdhaTTah/wordpress-github-sync/issues/124
  [#121]: https://github.com/mAAdhaTTah/wordpress-github-sync/issues/121
  [#127]: https://github.com/mAAdhaTTah/wordpress-github-sync/issues/127
  [Unreleased]: https://github.com/mAAdhaTTah/wordpress-github-sync
  [1.7.0]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.7.0
  [1.6.1]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.6.1
  [1.6.0]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.6.0
  [1.5.1]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.5.1
  [1.5.0]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.5.0
  [1.4.1]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.4.1
  [1.4.0]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.4.0
  [1.3.4]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.3.4
  [1.3.3]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.3.3
  [1.3.2]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.3.2
  [1.3.1]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.3.1
  [1.3]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.3
  [1.2]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.2
  [1.1.1]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.1.1
  [1.1.0]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.1.0
  [1.0.2]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.0.2
  [1.0.1]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.0.1
  [1.0.0]: https://github.com/mAAdhaTTah/wordpress-github-sync/releases/tag/1.0.0
