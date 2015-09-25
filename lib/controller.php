<?php

/**
 * Controller object manages tree retrieval, manipulation and publishing
 */
class WordPress_GitHub_Sync_Controller {

	/**
	 * Api object
	 * @var WordPress_GitHub_Sync_Api
	 */
	public $api;

	/**
	 * Currently whitelisted post types & statuses
	 * @var  array
	 */
	protected $whitelisted_post_types = array( 'post', 'page' );
	protected $whitelisted_post_statuses = array( 'publish' );

	/**
	 * Whether any posts have changed
	 * @var boolean
	 */
	public $changed = false;

	/**
	 * Array of posts to export
	 * @var array
	 */
	public $posts = array();

	/**
	 * Array representing new tre
	 * @var array
	 */
	public $tree = array();

	/**
	 * Commit message
	 * @var string
	 */
	public $msg = '';

	/**
	 * Instantiates a new Controller object
	 *
	 * $posts - array of post IDs to export
	 */
	public function __construct() {
		$this->api = new WordPress_GitHub_Sync_Api;
	}

	/**
	 * Reads the Webhook payload and syncs posts as necessary
	 *
	 * @param stdClass $payload
	 *
	 * @return array
	 */
	public function pull( $payload ) {
		if ( strtolower( $payload->repository->full_name ) !== strtolower( $this->api->repository() ) ) {
			$msg = strtolower( $payload->repository->full_name ) . __( ' is an invalid repository.', 'wordpress-github-sync' );
			WordPress_GitHub_Sync::write_log( $msg );

			return array(
				'result'  => 'error',
				'message' => $msg,
			);
		}

		// the last term in the ref is the branch name
		$refs   = explode( '/', $payload->ref );
		$branch = array_pop( $refs );

		if ( 'master' !== $branch ) {
			$msg = __( 'Not on the master branch.', 'wordpress-github-sync' );
			WordPress_GitHub_Sync::write_log( $msg );

			return array(
				'result'  => 'error',
				'message' => $msg,
			);
		}

		// We add wpghs to commits we push out, so we shouldn't pull them in again
		if ( 'wpghs' === substr( $payload->head_commit->message, -5 ) ) {
			$msg = __( 'Already synced this commit.', 'wordpress-github-sync' );
			WordPress_GitHub_Sync::write_log( $msg );

			return array(
				'result'  => 'error',
				'message' => $msg,
			);
		}

		$commit = $this->api->get_commit( $payload->head_commit->id );

		if ( is_wp_error( $commit ) ) {
			$msg = sprintf( __( 'Failed getting commit with error: %s', 'wordpress-github-sync' ), $commit->get_error_message() );
			WordPress_GitHub_Sync::write_log( $msg );

			return array(
				'result'  => 'error',
				'message' => $msg,
			);
		}

		$import = new WordPress_GitHub_Sync_Import();
		$import->run( $commit->tree->sha );

		$user = get_user_by( 'email', $payload->head_commit->author->email );

		if ( ! $user ) {
			// use the first user registered in the DB
			// @todo do we want to do this?
			$user_id = 0;

			while ( ! $user ) {
				$user_id++;

				$user = get_user_by( 'id', $user_id );
			}
		}

		update_option( '_wpghs_export_user_id', $user->ID );

		global $wpdb;

		if ( $updated_posts = $import->updated_posts() ) {
			foreach ( $updated_posts as $post_id ) {
				$revision = wp_get_post_revision( $post_id );

				if ( ! $revision ) {
					$revision = wp_save_post_revision( $post_id );

					if ( ! $revision || is_wp_error( $revision ) ) {
						// there was a problem saving a new revision
						continue;
					}

					// wp_save_post_revision returns the ID, whereas get_post_revision returns the whole object
					// in order to be consistent, let's make sure we have the whole object before continuing
					$revision = get_post( $revision );
				}

				$wpdb->update(
					$wpdb->posts,
					array(
						'post_author' => $user->ID,
					),
					array(
						'ID' => $revision->ID,
					),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		// Deleting posts from a payload is the only place
		// we need to search posts by path; another way?
		$removed = array();
		foreach ( $payload->commits as $commit ) {
			$removed = array_merge( $removed, $commit->removed );
		}
		foreach ( array_unique( $removed ) as $path ) {
			$post = new WordPress_GitHub_Sync_Post( $path );
			wp_delete_post( $post->id );
		}

		if ( $new_posts = $import->new_posts() ) {
			// disable the lock to allow exporting
			global $wpghs;
			$wpghs->push_lock = false;

			WordPress_GitHub_Sync::write_log(
				sprintf(
					__( 'Updating new posts with IDs: %s', 'wordpress-github-sync' ),
					implode( ', ', $new_posts )
				)
			);

			foreach ( $new_posts as $post_id ) {
				$wpdb->update(
					$wpdb->posts,
					array(
						'post_author' => $user->ID,
					),
					array(
						'ID' => $post_id,
					),
					array( '%d' ),
					array( '%d' )
				);
			}

			$msg = apply_filters( 'wpghs_commit_msg_new_posts', 'Updating new posts from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')' ) . ' - wpghs';

			$export = new WordPress_GitHub_Sync_Export( $new_posts, $msg );
			$export->run();
		}

		$msg = __( 'Payload processed', 'wordpress-github-sync' );
		WordPress_GitHub_Sync::write_log( $msg );

		return array(
			'result'  => 'success',
			'message' => $msg,
		);
	}

	/**
	 * Imports posts from the current master branch
	 */
	public function import_master() {
		$commit = $this->api->last_commit();

		if ( is_wp_error( $commit ) ) {
			WordPress_GitHub_Sync::write_log(
				sprintf(
					__( 'Failed getting last commit with error: %s', 'wordpress-github-sync' ),
					$commit->get_error_message()
				)
			);

			return;
		}

		if ( 'wpghs' === substr( $commit->message, - 5 ) ) {
			WordPress_GitHub_Sync::write_log( __( 'Already synced this commit.', 'wordpress-github-sync' ) );

			return;
		}

		$import = new WordPress_GitHub_Sync_Import();
		$import->run( $commit->tree->sha );
	}

	/**
	 * Export all the posts in the database to GitHub
	 */
	public function export_all() {
		global $wpdb;

		if ( $this->locked() ) {
			WordPress_GitHub_Sync::write_log( __( 'Export locked. Terminating.', 'wordpress-github-sync' ) );
			return;
		}

		$post_statuses = $this->format_for_query( $this->get_whitelisted_post_statuses() );
		$post_types    = $this->format_for_query( $this->get_whitelisted_post_types() );

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT ID FROM %s WHERE
				post_status IN ( %s ) AND
				post_type IN ( %s )'
				,
				$wpdb->posts,
				$post_statuses,
				$post_types
			)
		);

		$msg = apply_filters( 'wpghs_commit_msg_full', 'Full export from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')' ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_ids, $msg );
		$export->run();
	}

	/**
	 * Exports a single post to GitHub by ID
	 *
	 * @param int $post_id
	 */
	public function export_post( $post_id ) {
		if ( $this->locked() ) {
			return;
		}

		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$msg  = apply_filters( 'wpghs_commit_msg_single', 'Syncing ' . $post->github_path() . ' from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')', $post ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_id, $msg );
		$export->run();
	}

	/**
	 * Removes the post from the tree
	 *
	 * @param int $post_id
	 */
	public function delete_post( $post_id ) {
		if ( $this->locked() ) {
			return;
		}

		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$msg  = apply_filters( 'wpghs_commit_msg_delete', 'Deleting ' . $post->github_path() . ' via WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')', $post ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_id, $msg );
		$export->run( true );
	}

	/**
	 * Check if we're clear to call the api
	 */
	public function locked() {
		global $wpghs;

		if ( ! $this->api->oauth_token() || ! $this->api->repository() || $wpghs->push_lock ) {
			return true;
		}

		return false;
	}

	/**
	 * Formats a whitelist array for a query
	 *
	 * @param  array $whitelist
	 *
	 * @return string            Whitelist formatted for query
	 */
	protected function format_for_query( $whitelist ) {
		foreach ( $whitelist as $key => $value ) {
			$whitelist[ $key ] = "'$value'";
		}

		return implode( ', ', $whitelist );
	}

	/**
	 * Returns the list of post type permitted.
	 *
	 * @return array
	 */
	protected function get_whitelisted_post_types() {
		return apply_filters( 'wpghs_whitelisted_post_types', $this->whitelisted_post_types );
	}

	/**
	 * Returns the list of post status permitted.
	 *
	 * @return array
	 */
	protected function get_whitelisted_post_statuses() {
		return apply_filters( 'wpghs_whitelisted_post_statuses', $this->whitelisted_post_statuses );
	}

	/**
	 * Verifies that both the post's status & type
	 * are currently whitelisted
	 *
	 * @param  WordPress_GitHub_Sync_Post $post post to verify
	 *
	 * @return boolean                            true if supported, false if not
	 */
	protected function is_post_supported( $post ) {
		if ( ! in_array( $post->status(), $this->get_whitelisted_post_statuses() ) ) {
			return false;
		}

		if ( ! in_array( $post->type(), $this->get_whitelisted_post_types() ) ) {
			return false;
		}

		if ( $post->has_password() ) {
			return false;
		}

		return true;
	}
}
