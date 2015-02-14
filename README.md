# WordPress <--> GitHub Sync

*A WordPress plugin to sync content with a GitHub repository (or Jekyll site)*

[![Build Status](https://travis-ci.org/benbalter/wordpress-github-sync.svg)](https://travis-ci.org/benbalter/wordpress-github-sync)

Ever wish you could collaboratively author content for your WordPress site (or expose change history publicly and accept pull requests from your readers)?

Looking to tinker with Jekyll, but wish you could use WordPress's best-of-breed web editing interface instead of Atom? (gasp!)

Well, now you can! Introducing [WordPress <--> GitHub Sync](https://github.com/benbalter/wordpress-github-sync)! :sparkles:

## WordPress <--> GitHub Sync does three things:

1. Allows content publishers to version their content in GitHub, exposing "who made what change when" to readers

2. Allows readers to submit proposed improvements to WordPress-served content via GitHub's Pull Request model

3. Allows non-technical writers to draft and edit a Jekyll site in WordPress's best-of-breed editing interface

## WordPress <--> GitHub sync might be able to do some other cool things:

* Allow teams to collaboratively write and edit posts using GitHub (e.g., pull requests, issues, comments)

* Allow you to sync the content of two different WordPress installations via GitHub

* Allow you to stage and preview content before "deploying" to your production server

## How it works

The sync action is based on two hooks:

1. A per-post sync fired in response to WordPress's `save_post` hook which pushes content to GitHub

2. A sync of all changed files trigged by GitHub's `push` webhook (outbound API call)

## Setup

**:warning: Still a work in progress, but [we'd love your help](CONTRIBUTING.md) making it better. :warning:**

### Installing the WordPress plugin

Install the plugin and activate it via WordPress's plugin settings page.

  1. `cd wp-content/plugins`
  2. `git clone https://github.com/benbalter/wordpress-github-sync.git`
  3. `cd wordpress-github-sync && composer install`
  4. Activate the plugin in Wordpress' Dashboard > Plugins > Installed Plugins

### Configuring the plugin

1. [Create a personal oauth token](https://github.com/settings/tokens/new) with the `public_repo` scope. If you'd prefer not to use your account, you can create another GitHub account for this. 
2. Configure your GitHub host, repository, secret (defined in the next step),  and OAuth Token on the WordPress <--> GitHub sync settings page within WordPress's administrative interface
3. Create a WebHook within your repository with the provided callback URL and callback secret, using `application/json` as the content type. To set up a webhook on GitHub, head over to the **Settings** page of your repository, and click on **Webhooks & services**. After that, click on **Add webhook**.

### Markdown Support

WordPress <--> GitHub Sync exports all posts as `.md` files for better display on GitHub, but all content is exported and imported as its original HTML. To enable writing, importing, and exporting in Markdown, please install and enable [WP-Markdown](https://wordpress.org/plugins/wp-markdown/), and WordPress <--> GitHub Sync will use it to convert your posts to and from Markdown.

## Contributing

Found a bug? Want to take a stab at [one of the open issues](https://github.com/benbalter/wordpress-github-sync/issues)? We'd love your help!

See [the contributing documentation](CONTRIBUTING.md) for details.

## Prior Art

* [WordPress Post Forking](https://github.com/post-forking/post-forking)
* [WordPress to Jekyll exporter](https://github.com/benbalter/wordpress-to-jekyll-exporter)
* [Writing in public, syncing with GitHub](https://konklone.com/post/writing-in-public-syncing-with-github)
