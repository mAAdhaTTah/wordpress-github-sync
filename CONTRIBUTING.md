# How to contribute

## Ways to contribute

1. Grab [an open issue] and [submit a pull request].
1. Try the plugin out on a test server and [report any issues you find].
1. Let us know [what features you'd love to see].
1. Participate in the [discussion forums].
1. Help improve [the documentation].
1. Translate the plugin [to another language].

## Submit a pull request

Want to propose a change? Great! We could use the help. Here's how:

1. Fork the project.
1. Create a descriptively named feature branch.
1. Commit your changes.
1. Submit a pull request.

For more information see [GitHub flow] and [Contributing to Open Source].

This project uses [Composer] for dependency management, and there are a few scripts you use to help you along:

* `composer test` will run PHPUnit for you, making sure all your tests pass.
* `composer sniff` will run the code-sniffer for you, making sure you're adhering to the [WordPress Coding Standards]

In order to the the plugin unit tests, you'll need [MySQL] installed on your development machine. Before running `composer test`, run:

```bash
bash bin/install-wp-tests.sh <database_name> <username> <password>
```

where `<username>` and `<password>` are for the root MySQL user. A new database will be created matching `<database_name>`, if it doesn't exist. This database will be deleted every time the tests are run, so `wordpress_test` is commonly used as the database name. 

If you're opening a pull request with a new feature, please include unit tests. If you don't know how to write unit tests, open the PR anyway; we'll be glad to help you out.

  [an open issue]: https://github.com/benbalter/wordpress-github-sync/issues
  [submit a pull request]: #submit-a-pull-request
  [report any issues you find]: https://github.com/benbalter/wordpress-github-sync/issues/new
  [what features you'd love to see]: https://github.com/benbalter/wordpress-github-sync/issues/new
  [discussion forums]: https://github.com/benbalter/wordpress-github-sync/issues
  [the documentation]: https://github.com/benbalter/wordpress-github-sync/blob/master/README.md
  [to another language]: https://github.com/benbalter/wordpress-github-sync/tree/master/languages
  [GitHub flow]: https://guides.github.com/introduction/flow/
  [Contributing to Open Source]: https://guides.github.com/activities/contributing-to-open-source/
  [Composer]: https://getcomposer.org/
  [WordPress Coding Standards]: https://make.wordpress.org/core/handbook/best-practices/coding-standards/php/
  [MySQL]: https://www.mysql.com/
