<?php

/**
 * GitHub Export Manager.
 */
class WordPress_GitHub_Sync_Export {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Initializes a new export manager.
	 *
	 * @param WordPress_GitHub_Sync $app
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Exports provided posts.
	 *
	 * @param WordPress_GitHub_Sync_Post[] $posts
	 * @param string $msg
	 *
	 * @return string|WP_Error
	 */
	public function posts( array $posts, $msg ) {
		$commit = $this->app->api()->last_commit();

		if ( is_wp_error( $commit ) ) {
			return $commit;
		}

		$tree = $this->app->api()->get_tree_recursive( $commit->tree_sha()  );

		foreach ( $posts as $post ) {
			$tree->post_to_tree( $post );
		}

		$result = $tree->export( $msg );

		if ( ! $result ) {
			$this->no_change();

			return new WP_Error;
		}

		if ( is_wp_error( $result ) ) {
			$this->error( $result );

			return new WP_Error;
		}

		$tree = $this->app->api()->last_tree_recursive();

		if ( is_wp_error( $tree ) ) {
			// @todo warning b/c shas aren't saved? try again?
			return $tree;
		}

		WordPress_GitHub_Sync::write_log( __( 'Saving the shas.', 'wordpress-github-sync' ) );
		$this->save_post_shas( $posts, $tree );

		$this->success();

		return true;
	}

	/**
	 * Runs the export process.
	 *
	 * Passing in true will delete all the posts provided in the constructor.
	 *
	 * @param bool|false $delete
	 */
	public function run( $delete = false ) {
		$this->tree->fetch_last();

		if ( ! $this->tree->is_ready() ) {
			WordPress_GitHub_Sync::write_log(
				sprintf(
					__( 'Failed getting tree with error: %s', 'wordpress-github-sync' ),
					$this->tree->last_error()
				)
			);

			return;
		}

		WordPress_GitHub_Sync::write_log( __( 'Building the tree.', 'wordpress-github-sync' ) );
		foreach ( $this->ids as $post_id ) {
			$post = new WordPress_GitHub_Sync_Post( $post_id );
			$this->tree->post_to_tree( $post, $delete );
		}

		$result = $this->tree->export( $this->msg );

		if ( ! $result ) {
			$this->no_change();

			return;
		}

		if ( is_wp_error( $result ) ) {
			$this->error( $result );

			return;
		}

		$this->tree->fetch_last();

		// @todo what if we fail?
		if ( $this->tree->is_ready() ) {
			WordPress_GitHub_Sync::write_log( __( 'Saving the shas.', 'wordpress-github-sync' ) );
			$this->save_post_shas();
		}

		$this->success();
	}

	/**
	 * Writes out the results of an unchanged export
	 */
	public function no_change() {
		update_option( '_wpghs_export_complete', 'yes' );
		WordPress_GitHub_Sync::write_log( __( 'There were no changes, so no additional commit was added.', 'wordpress-github-sync' ), 'warning' );
	}

	/**
	 * Writes out the results of an error and saves the data
	 *
	 * @param WP_Error $result
	 */
	public function error( $result ) {
		update_option( '_wpghs_export_error', $result->get_error_message() );
		WordPress_GitHub_Sync::write_log(
			sprintf(
				__( 'Error exporting to GitHub. Error: %s', 'wordpress-github-sync' ),
				$result->get_error_message()
			),
			'error'
		);
	}

	/**
	 * Use the new tree to save sha data
	 * for all the updated posts.
	 *
	 * @param WordPress_GitHub_Sync_Post[] $posts
	 * @param WordPress_GitHub_Sync_Tree $tree
	 */
	public function save_post_shas( $posts, $tree) {
		foreach ( $posts as $post ) {
			$blob = $tree->get_blob_for_path( $post->github_path() );

			if ( $blob ) {
				$post->set_sha( $blob->sha );
			} else {
				WordPress_GitHub_Sync::write_log(
					sprintf(
						__( 'No sha matched for post ID %d', 'wordpress-github-sync' ),
						$post->id
					)
				);
			}
		}
	}

	/**
	 * Writes out the results of a successful export
	 */
	public function success() {
		update_option( '_wpghs_export_complete', 'yes' );
		update_option( '_wpghs_fully_exported', 'yes' );
		WordPress_GitHub_Sync::write_log( __( 'Export to GitHub completed successfully.', 'wordpress-github-sync' ), 'success' );
	}

	/**
	 * Saves the export user to the database.
	 *
	 * @param $user_id
	 *
	 * @return bool
	 */
	public function set_user( $user_id ) {
		return update_option( '_wpghs_export_user_id', (int) $user_id );
	}

}
