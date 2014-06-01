# Wordpress GitHub Sync

*A WordPress plugin to sync content with a GitHub repository (or Jekyll site)*

## WordPress GitHub Sync does three things:

1. Allows content publishers to version their content in GitHub, exposing "who made what change when" to readers

2. Allows readers to submit proposed improvements to WordPress-served content via GitHub's Pull Request model

3. Allows non-technical writers to draft and edit a Jekyll site in WordPress's best-of-breed editing interface

## How it works

The sync action is based on two hooks:

1. A per-post sync fired in respone to WordPress's `save_post` hook which pushes content to GitHub

2. A sync of all changed files trigged by GitHub's `push` webhook (outbound API call)

