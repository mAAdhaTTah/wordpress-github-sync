<?php
/**
 * WP_CLI Commands
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_CLI
 */
class WordPress_GitHub_Sync_CLI {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Wire up controller object on init
	 *
	 * @param WordPress_GitHub_Sync $app Application container.
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
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
			WP_CLI::error( __( 'Invalid user ID', 'wordpress-github-sync' ) );
		}

		$this->app->export()->set_user( $user_id );

		if ( 'all' === $post_id ) {
			WP_CLI::line( __( 'Starting full export to GitHub.', 'wordpress-github-sync' ) );
			$this->app->controller()->export_all();
		} elseif ( is_numeric( $post_id ) ) {
			WP_CLI::line(
				sprintf(
					__( 'Exporting post ID to GitHub: %d', 'wordpress-github-sync' ),
					$post_id
				)
			);
			$this->app->controller()->export_post( (int) $post_id );
		} else {
			WP_CLI::error( __( 'Invalid post ID', 'wordpress-github-sync' ) );
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
			WP_CLI::error( __( 'Invalid user ID', 'wordpress-github-sync' ) );
		}

		update_option( '_wpghs_export_user_id', (int) $user_id );

		WP_CLI::line( __( 'Starting import from GitHub.', 'wordpress-github-sync' ) );

		$this->app->controller()->import_master();
	}
}
