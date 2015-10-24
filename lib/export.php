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

		$tree = $this->app->api()->get_tree_recursive( $commit->tree_sha() );

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		foreach ( $posts as $post ) {
			$tree->post_to_tree( $post );
		}

		$commit->set_tree( $tree );
		$commit->set_message( $msg );

		$result = $this->app->api()->create_commit( $commit );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$tree     = $this->app->api()->last_tree_recursive();
		$attempts = 1;

		while ( is_wp_error( $tree ) && $attempts < 5 ) {
			$tree = $this->app->api()->last_tree_recursive();
			$attempts ++;
		}

		if ( is_wp_error( $tree ) ) {
			// @todo throw a big warning! not having the latet shas is BAD
			return $tree;
		}

//		WordPress_GitHub_Sync::write_log( __( 'Saving the shas.', 'wordpress-github-sync' ) );
		$this->save_post_shas( $posts, $tree );

		return __( 'Export to GitHub completed successfully.', 'wordpress-github-sync' );
	}

	/**
	 * Use the new tree to save sha data
	 * for all the updated posts.
	 *
	 * @param WordPress_GitHub_Sync_Post[] $posts
	 * @param WordPress_GitHub_Sync_Tree $tree
	 */
	public function save_post_shas( array $posts, WordPress_GitHub_Sync_Tree $tree ) {
		foreach ( $posts as $post ) {
			$blob = $tree->get_blob_for_path( $post->github_path() );

			if ( $blob ) {
				$this->app->database()->set_post_sha( $post, $blob->sha() );
			}
		}
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
