## Changelog ##

This change log follows the [Keep a Changelog standards]. Versions follows [Semantic Versioning].

### [Unreleased] ###

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
  [Unreleased]: https://github.com/benbalter/wordpress-github-sync
  [1.3.4]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.3.4
  [1.3.3]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.3.3
  [1.3.2]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.3.2
  [1.3.1]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.3.1
  [1.3]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.3
  [1.2]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.2
  [1.1.1]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.1.1
  [1.1.0]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.1.0
  [1.0.2]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.0.2
  [1.0.1]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.0.1
  [1.0.0]: https://github.com/benbalter/wordpress-github-sync/releases/tag/1.0.0
