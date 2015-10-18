<?php

/**
 * Controller object manages tree retrieval, manipulation and publishing
 */
class WordPress_GitHub_Sync_Controller {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	public $app;

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
	 * @param WordPress_GitHub_Sync $app
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Webhook callback as triggered from GitHub push.
	 *
	 * Reads the Webhook payload and syncs posts as necessary.
	 *
	 * @return boolean
	 */
	public function pull_posts() {
		if ( is_wp_error( $error = $this->app->semaphore()->is_open() ) ) {
			return $this->app->response()->error( $error );
		}

		$this->app->semaphore()->lock();

		if ( is_wp_error( $error = $this->app->request()->is_secret_valid() ) ) {
			$this->app->semaphore()->unlock();

			return $this->app->response()->error( $error );
		}

		$payload = $this->app->request()->payload();

		if ( is_wp_error( $error = $payload->should_import() ) ) {
			$this->app->semaphore()->unlock();

			return $this->app->response()->error( $error );
		}

		$result = $this->app->import()->payload( $payload );

		$this->app->semaphore()->unlock();

		if ( is_wp_error( $result ) ) {
			return $this->app->response()->error( $result );
		}

		return $this->app->response()->success( $result );
	}

	/**
	 * Imports posts from the current master branch.
	 *
	 * @return boolean
	 */
	public function import_master() {
		if ( is_wp_error( $error = $this->app->semaphore()->is_open() ) ) {
			return $this->app->response()->error( $error );
		}

		$this->app->semaphore()->lock();

		$commit = $this->app->api()->last_commit();

		if ( is_wp_error( $commit ) ) {
			$this->app->semaphore()->unlock();
			$this->app->response()->log( $commit );

			return false;
		}

		if ( $commit->already_synced() ) {
			$this->app->semaphore()->unlock();
			$this->app->response()->log(
				new WP_Error( 'commit_synced', __( 'Already synced this commit.', 'wordpress-github-sync' ) )
			);

			return false;
		}

		$result = $this->app->import()->commit( $commit );

		$this->app->semaphore()->unlock();
		$this->app->response()->log( $result );

		return is_wp_error( $result ) ? false : true;
	}

	/**
	 * Export all the posts in the database to GitHub
	 */
	public function export_all() {
		global $wpdb;

		if ( $this->app->semaphore()->is_open() ) {
			WordPress_GitHub_Sync::write_log( __( 'Export locked. Terminating.', 'wordpress-github-sync' ) );
			return;
		}

		$post_statuses = $this->format_for_query( $this->get_whitelisted_post_statuses() );
		$post_types    = $this->format_for_query( $this->get_whitelisted_post_types() );

		$post_ids = $wpdb->get_col(
			"SELECT ID FROM $wpdb->posts WHERE
			post_status IN ( $post_statuses ) AND
			post_type IN ( $post_types )"
		);

		$msg = apply_filters( 'wpghs_commit_msg_full', 'Full export from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')' ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_ids, $msg );
		$export->run();
	}

	/**
	 * Exports a single post to GitHub by ID.
	 *
	 * Called on the save_post hook.
	 *
	 * @param int $post_id Post ID
	 */
	public function export_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || $this->app->semaphore()->is_open() ) {
			return;
		}

		$post = new WordPress_GitHub_Sync_Post( $post_id );

		if ( ! $this->is_post_supported( $post ) ) {
			return;
		}

		$msg  = apply_filters( 'wpghs_commit_msg_single', 'Syncing ' . $post->github_path() . ' from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')', $post ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_id, $msg );
		$export->run();
	}

	/**
	 * Removes the post from the tree
	 *
	 * Called the delete_post hook.
	 *
	 * @param int $post_id Post ID
	 */
	public function delete_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || $this->app->semaphore()->is_open() ) {
			return;
		}

		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$msg  = apply_filters( 'wpghs_commit_msg_delete', 'Deleting ' . $post->github_path() . ' via WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')', $post ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_id, $msg );
		$export->run( true );
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
	 * @return boolean                          true if supported, false if not
	 */
	protected function is_post_supported( WordPress_GitHub_Sync_Post $post ) {
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
