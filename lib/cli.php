<?php
/**
 * WP_CLI Commands
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_CLI
 */
class WordPress_GitHub_Sync_CLI extends WP_CLI_Command {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Grab the Application container on instantiation.
	 */
	public function __construct() {
		$this->app = WordPress_GitHub_Sync::$instance;
	}

	/**
	 * Exports an individual post
	 * all your posts to GitHub
	 *
	 * ## OPTIONS
	 *
	 * <post_id|all>
	 * : The post ID to export or 'all' for full site
	 *
	 * <user_id>
	 * : The user ID you'd like to save the commit as
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpghs export all 1
	 *     wp wpghs export 1 1
	 *
	 * @synopsis <post_id|all> <user_id>
	 *
	 * @param array $args Command arguments.
	 */
	public function export( $args ) {
		list( $post_id, $user_id ) = $args;

		if ( ! is_numeric( $user_id ) ) {
			WP_CLI::error( __( 'Invalid user ID', 'wp-github-sync' ) );
		}

		$this->app->export()->set_user( $user_id );

		if ( 'all' === $post_id ) {
			WP_CLI::line( __( 'Starting full export to GitHub.', 'wp-github-sync' ) );
			$this->app->controller()->export_all();
		} elseif ( is_numeric( $post_id ) ) {
			WP_CLI::line(
				sprintf(
					__( 'Exporting post ID to GitHub: %d', 'wp-github-sync' ),
					$post_id
				)
			);
			$this->app->controller()->export_post( (int) $post_id );
		} else {
			WP_CLI::error( __( 'Invalid post ID', 'wp-github-sync' ) );
		}
	}

	/**
	 * Imports the post in your GitHub repo
	 * into your WordPress blog
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user ID you'd like to save the commit as
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpghs import 1
	 *
	 * @synopsis <user_id>
	 *
	 * @param array $args Command arguments.
	 */
	public function import( $args ) {
		list( $user_id ) = $args;

		if ( ! is_numeric( $user_id ) ) {
			WP_CLI::error( __( 'Invalid user ID', 'wp-github-sync' ) );
		}

		update_option( '_wpghs_export_user_id', (int) $user_id );

		WP_CLI::line( __( 'Starting import from GitHub.', 'wp-github-sync' ) );

		$this->app->controller()->import_master();
	}

	/**
	 * Fetches the provided sha or the repository's
	 * master branch and caches it.
	 *
	 * ## OPTIONS
	 *
	 * <user_id>
	 * : The user ID you'd like to save the commit as
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpghs prime --branch=master
	 *     wp wpghs prime --sha=<commit_sha>
	 *
	 * @synopsis [--sha=<commit_sha>] [--branch]
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command associated arguments.
	 */
	public function prime( $args, $assoc_args ) {
		if ( isset( $assoc_args['branch'] ) ) {
			WP_CLI::line( __( 'Starting branch import.', 'wp-github-sync' ) );

			$commit = $this->app->api()->fetch()->master();

			if ( is_wp_error( $commit ) ) {
				WP_CLI::error(
					sprintf(
						__( 'Failed to import and cache branch with error: %s', 'wp-github-sync' ),
						$commit->get_error_message()
					)
				);
			} else {
				WP_CLI::success(
					sprintf(
						__( 'Successfully imported and cached commit %s from branch.', 'wp-github-sync' ),
						$commit->sha()
					)
				);
			}
		} else if ( isset( $assoc_args['sha'] ) ) {
			WP_CLI::line( 'Starting sha import.' );

			$commit = $this->app->api()->fetch()->commit( $assoc_args['sha'] );

			WP_CLI::success(
				sprintf(
					__( 'Successfully imported and cached commit %s.', 'wp-github-sync' ),
					$commit->sha()
				)
			);
		} else {
			WP_CLI::error( 'Invalid fetch.' );
		}
	}
}
