# Wordpress GitHub Sync

*A WordPress plugin to sync content with a GitHub repository (or Jekyll site)*

**:warning: Still a work in progress, but [we'd love your help](https://github.com/benbalter/wordpress-github-sync/issues) :warning:**

## WordPress GitHub Sync does three things:

1. Allows content publishers to version their content in GitHub, exposing "who made what change when" to readers

2. Allows readers to submit proposed improvements to WordPress-served content via GitHub's Pull Request model

3. Allows non-technical writers to draft and edit a Jekyll site in WordPress's best-of-breed editing interface

## How it works

The sync action is based on two hooks:

1. A per-post sync fired in respone to WordPress's `save_post` hook which pushes content to GitHub

2. A sync of all changed files trigged by GitHub's `push` webhook (outbound API call)

## Setup

1. Install the plugin and activate it via WordPress's plugin settings page
2. [Create a personal oauth token](https://github.com/settings/tokens/new) with the `public_repo` scope (you can also create a bot account for this, if you'd prefer)
3. Configure your GitHub host, repository, secret, and OAuth Token on the WordPress <--> GitHub sync settings page within WordPress's administrative interface
4. Create a WebHook within you repository with the provided callback URL and callback secret
