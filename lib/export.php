<?php

/**
 * GitHub Export Manager.
 */
class WordPress_GitHub_Sync_Export {

	/**
	 * Option key for export user.
	 */
	const EXPORT_USER_OPTION = '_wpghs_export_user_id';

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
	 * Updates all of the current posts in the database on master.
	 *
	 * @return string|WP_Error
	 */
	public function full() {
		$posts = $this->app->database()->fetch_all_supported();

		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		$master = $this->app->api()->last_commit();

		if ( is_wp_error( $master ) ) {
			return $master;
		}

		foreach ( $posts as $post ) {
			$master->tree()->post_to_tree( $post );
		}

		$master->set_message(
			apply_filters(
				'wpghs_commit_msg_full',
				sprintf(
					'Full export from WordPress at %s (%s)',
					site_url(),
					get_bloginfo( 'name' )
				)
			) . ' - wpghs'
		);

		$result = $this->app->api()->create_commit( $master );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->update_shas( $posts );
	}

	/**
	 * Updates the provided post ID in master.
	 *
	 * @param $post_id
	 *
	 * @return string|WP_Error
	 */
	public function update( $post_id ) {
		$post = $this->app->database()->fetch_by_id( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$master = $this->app->api()->last_commit();

		if ( is_wp_error( $master ) ) {
			return $master;
		}

		$master->tree()->post_to_tree( $post );
		$master->set_message(
			apply_filters(
				'wpghs_commit_msg_single',
				sprintf(
					'Syncing %s from WordPress at %s (%s)',
					$post->github_path(),
					site_url(),
					get_bloginfo( 'name' )
				),
				$post
			) . ' - wpghs'
		);

		$result = $this->app->api()->create_commit( $master );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->update_shas( array( $post ) );
	}

	/**
	 * Deletes a provided post ID from master.
	 *
	 * @param $post_id
	 *
	 * @return string} WP_Error
	 */
	public function delete( $post_id ) {
		$post = $this->app->database()->fetch_by_id( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$master = $this->app->api()->last_commit();

		if ( is_wp_error( $master ) ) {
			return $master;
		}

		$master->tree()->post_to_tree( $post, true );
		$master->set_message(
			apply_filters(
				'wpghs_commit_msg_delete',
				sprintf(
					'Deleting %s via WordPress at %s (%s)',
					$post->github_path(),
					site_url(),
					get_bloginfo( 'name' )
				),
				$post
			) . ' - wpghs'
		);

		$result = $this->app->api()->create_commit( $master );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return __( 'Export to GitHub completed successfully.', 'wordpress-github-sync' );
	}

	/**
	 * Use the new tree to save sha data
	 * for all the updated posts.
	 *
	 * @param WordPress_GitHub_Sync_Post[] $posts
	 * @param WordPress_GitHub_Sync_Tree $tree
	 *
	 * @return string|WP_Error
	 */
	protected function update_shas( array $posts ) {
		$master   = $this->app->api()->last_commit();
		$attempts = 1;

		while ( is_wp_error( $master ) && $attempts < 5 ) {
			$master = $this->app->api()->last_commit();
			$attempts++;
		}

		if ( is_wp_error( $master ) ) {
			// @todo throw a big warning! not having the latest shas is BAD
			return $master;
		}

//		WordPress_GitHub_Sync::write_log( __( 'Saving the shas.', 'wordpress-github-sync' ) );
		foreach ( $posts as $post ) {
			$blob = $master->tree()->get_blob_for_path( $post->github_path() );

			if ( $blob ) {
				$this->app->database()->set_post_sha( $post, $blob->sha() );
			}
		}

		return __( 'Export to GitHub completed successfully.', 'wordpress-github-sync' );
	}

	/**
	 * Saves the export user to the database.
	 *
	 * @param $user_id
	 *
	 * @return bool
	 */
	public function set_user( $user_id ) {
		return update_option( self::EXPORT_USER_OPTION, (int) $user_id );
	}
}
