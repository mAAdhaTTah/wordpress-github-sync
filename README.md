# WordPress <--> GitHub Sync #
**Contributors:** JamesDiGioia, benbalter   
**Tags:** github, git, version control, content, collaboration, publishing  
**Requires at least:** 3.9  
**Tested up to:** 4.3.1  
**Stable tag:** 1.7.1  
**License:** GPLv2  
**License URI:** http://www.gnu.org/licenses/gpl-2.0.html  

## Description ##

*A WordPress plugin to sync content with a GitHub repository (or Jekyll site)*

[![Build Status](https://scrutinizer-ci.com/g/mAAdhaTTah/wordpress-github-sync/badges/build.png?b=master)](https://scrutinizer-ci.com/g/mAAdhaTTah/wordpress-github-sync/build-status/master) [![Code Coverage](https://scrutinizer-ci.com/g/mAAdhaTTah/wordpress-github-sync/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mAAdhaTTah/wordpress-github-sync/?branch=master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mAAdhaTTah/wordpress-github-sync/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mAAdhaTTah/wordpress-github-sync/?branch=master)

Ever wish you could collaboratively author content for your WordPress site (or expose change history publicly and accept pull requests from your readers)?

Looking to tinker with Jekyll, but wish you could use WordPress's best-of-breed web editing interface instead of Atom? (gasp!)

Well, now you can! Introducing [WordPress <--> GitHub Sync](https://github.com/benbalter/wordpress-github-sync)!

### WordPress <--> GitHub Sync does three things: ###

1. Allows content publishers to version their content in GitHub, exposing "who made what change when" to readers
2. Allows readers to submit proposed improvements to WordPress-served content via GitHub's Pull Request model
3. Allows non-technical writers to draft and edit a Jekyll site in WordPress's best-of-breed editing interface

### WordPress <--> GitHub sync might be able to do some other cool things: ###

* Allow teams to collaboratively write and edit posts using GitHub (e.g., pull requests, issues, comments)
* Allow you to sync the content of two different WordPress installations via GitHub
* Allow you to stage and preview content before "deploying" to your production server

### How it works ###

The sync action is based on two hooks:

1. A per-post sync fired in response to WordPress's `save_post` hook which pushes content to GitHub
2. A sync of all changed files triggered by GitHub's `push` webhook (outbound API call)

## Installation ##

### Using the WordPress Dashboard ###

1. Navigate to the 'Add New' in the plugins dashboard
2. Search for 'WordPress GitHub Sync'
3. Click 'Install Now'
4. Activate the plugin on the Plugin dashboard

### Uploading in WordPress Dashboard ###

1. Download `wordpress-github-sync.zip` from the WordPress plugins repository.
2. Navigate to the 'Add New' in the plugins dashboard
3. Navigate to the 'Upload' area
4. Select `wordpress-github-sync.zip` from your computer
5. Click 'Install Now'
6. Activate the plugin in the Plugin dashboard

### Using FTP ###

1. Download `wordpress-github-sync.zip`
2. Extract the `wordpress-github-sync` directory to your computer
3. Upload the `wordpress-github-sync` directory to the `/wp-content/plugins/` directory
4. Activate the plugin in the Plugin dashboard

### Installing from Source ###

Install the plugin and activate it via WordPress's plugin settings page.

  1. `cd wp-content/plugins`
  2. `git clone https://github.com/benbalter/wordpress-github-sync.git`
  3. `cd wordpress-github-sync && composer install`
  4. Activate the plugin in Wordpress' Dashboard > Plugins > Installed Plugins

### Configuring the plugin ###

1. [Create a personal oauth token](https://github.com/settings/tokens/new) with the `public_repo` scope. If you'd prefer not to use your account, you can create another GitHub account for this. 
2. Configure your GitHub host, repository, secret (defined in the next step),  and OAuth Token on the WordPress <--> GitHub sync settings page within WordPress's administrative interface. Make sure the repository has an initial commit or the export will fail.
3. Create a WebHook within your repository with the provided callback URL and callback secret, using `application/json` as the content type. To set up a webhook on GitHub, head over to the **Settings** page of your repository, and click on **Webhooks & services**. After that, click on **Add webhook**.
4. Click `Export to GitHub` or if you use WP-CLI, run `wp wpghs export all #` from the command line, where # = the user ID you'd like to commit as.

## Frequently Asked Questions ##

### Markdown Support ###

WordPress <--> GitHub Sync exports all posts as `.md` files for better display on GitHub, but all content is exported and imported as its original HTML. To enable writing, importing, and exporting in Markdown, please install and enable [WP-Markdown](https://wordpress.org/plugins/wp-markdown/), and WordPress <--> GitHub Sync will use it to convert your posts to and from Markdown.

You can also activate the Markdown module from [Jetpack](https://wordpress.org/plugins/jetpack/) or the standalone [JP Markdown](https://wordpress.org/plugins/jetpack-markdown/) to save in Markdown and export that version to GitHub.

### Importing from GitHub ###

WordPress <--> GitHub Sync is also capable of importing posts directly from GitHub, without creating them in WordPress before hand. In order to have your post imported into GitHub, add this YAML Frontmatter to the top of your .md document:

    ---
    post_title: 'Post Title'
    layout: post_type_probably_post
    published: true_or_false
    ---
    Post goes here.

and fill it out with the data related to the post you're writing. Save the post and commit it directly to the repository. After the post is added to WordPress, an additional commit will be added to the repository, updating the new post with the new information from the database.

Note that WordPress <--> GitHub Sync will *only* import posts from the `master` branch. Changes on other branches will be ignored.

If WordPress <--> GitHub Sync cannot find the author for a given import, it will fallback to the default user as set on the settings page. **Make sure you set this user before you begin importing posts from GitHub.** Without it set, WordPress <--> GitHub Sync will default to no user being set for the author as well as unknown-author revisions.

### Custom Post Type & Status Support ###

By default, WordPress <--> GitHub Sync only exports published posts and pages. However, it provides a number of [hooks](https://codex.wordpress.org/Plugin_API) in order to customize its functionality. Check out the [wiki](https://github.com/mAAdhaTTah/wordpress-github-sync/wiki) for complete documentation for these actions and filters.

If you want to export additional post types or draft posts, you'll have to hook into the filters `wpghs_whitelisted_post_types` or `wpghs_whitelisted_post_statuses` respectively.

In `wp-content`, create or open the `mu-plugins` folder and create a plugin file there called `wpghs-custom-filters.php`. In it, paste and modify the below code:

    <?php
    /**
     * Plugin Name:  WordPress-GitHub Sync Custom Filters
     * Plugin URI:   https://github.com/benbalter/wordpress-github-sync
     * Description:  Adds support for custom post types and statuses
     * Version:      1.0.0
     * Author:       James DiGioia
     * Author URI:   https://jamesdigioia.com/
     * License:      GPL2
     */
    
    add_filter('wpghs_whitelisted_post_types', function ($supported_post_types) {
      return array_merge($supported_post_types, array(
        // add your custom post types here
        'gistpen'
      ));
    });
    
    add_filter('wpghs_whitelisted_post_statuses', function ($supported_post_statuses) {
      return array_merge($supported_post_statuses, array(
        // additional statuses available: https://codex.wordpress.org/Post_Status
        'draft'
      ));
    });

### Add "Edit|View on GitHub" Link ###

If you want to add a link to your posts on GitHub, there are 4 functions WordPress<-->GitHub Sync makes available for you to use in your themes or as part of `the_content` filter:

* `get_the_github_view_url` - returns the URL on GitHub to view the current post
* `get_the_github_view_link` - returns an anchor tag (`<a>`) with its href set the the view url
* `get_the_github_edit_url` - returns the URL on GitHub to edit the current post
* `get_the_github_edit_link` - returns an anchor tag (`<a>`) with its href set the the edit url

All four of these functions must be used in the loop. If you'd like to retrieve these URLs outside of the loop, instantiate a new `WordPress_GitHub_Sync_Post` object and call `github_edit_url` or `github_view_url` respectively on it:

    // $id can be retrieved from a query or elsewhere
    $wpghs_post = new WordPress_GitHub_Sync_Post( $id );
    $url = $wpghs_post->github_view_url();

If you'd like to include an edit link without modifying your theme directly, you can add one of these functions to `the_content` like so:

    add_filter( 'the_content', function( $content ) {
      if( is_page() || is_single() ) {
        $content .= get_the_github_edit_link();
      }
      return $content;
    }, 1000 );

#### Shortcodes (v >= XXXX) ####

If you wish to add either the bare URL or a link referencing the URL to an individual post, without editing themes, you can add a [shortcode](https://codex.wordpress.org/Shortcode_API) anywhere in your post;

`[wpghs]`

The following optional attributes can also be included in the shortcode
* `target=`
   + `'view'` (default)  the url used will be the *view* URL (`/blob/`). 
   + `'edit'`            the url used will be the *edit* URL (`/edit/`).
* `type=`
   + `'link'` (default)  an anchor tag (`<a>`) with href set to the requested URL will be inserted.
   + `'url'`             the the bare requested URL will be inserted.
* `text=`
   + `''` (default)      link text (where `type='link'`, ignored otherwise) will be set to 'View this post on GitHub'.
   + `'text'`          link text (where `type='link'`, ignored otherwise) will be set to 'text' (the supplied text).

For example, 

`[wpghs target='view' type='link' text='Here is my post on GitHub']` will produce a HTML anchor tag with href set to the 'view' URL of the post on GitHub, and the link text set to 'Here is my post on GitHub', i.e.

`<a href="https://github.com/USERNAME/REPO/blob/master/_posts/YOURPOST.md">Here is my post on GitHub</a>`

Any or all of the attributes can be left out; defaults will take their place.

### Additional Customizations ###

There are a number of other customizations available in WordPress <--> GitHub Sync, including the commit message and YAML front-matter. Want more detail? Check out the [wiki](https://github.com/mAAdhaTTah/wordpress-github-sync/wiki).

### Contributing ###

Found a bug? Want to take a stab at [one of the open issues](https://github.com/mAAdhaTTah/wordpress-github-sync/issues)? We'd love your help!

See [the contributing documentation](CONTRIBUTING.md) for details.

### Prior Art ###

* [WordPress Post Forking](https://github.com/post-forking/post-forking)
* [WordPress to Jekyll exporter](https://github.com/benbalter/wordpress-to-jekyll-exporter)
* [Writing in public, syncing with GitHub](https://konklone.com/post/writing-in-public-syncing-with-github)
