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
		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				__( 'Semaphore is locked, import/export already in progress.', 'wordpress-github-sync' )
			) );
		}

		if ( ! $this->app->request()->is_secret_valid() ) {
			return $this->app->response()->error( new WP_Error(
				'invalid_headers',
				__( 'Failed to validate secret.', 'wordpress-github-sync' )
			) );
		}

		$payload = $this->app->request()->payload();

		if ( ! $payload->should_import() ) {
			return $this->app->response()->error( new WP_Error(
				'invalid_repo',
				sprintf(
					__( '%s is an invalid repository.', 'wordpress-github-sync' ),
					strtolower( $payload->get_repository_name() )
				)
			) );
		}

		$this->app->semaphore()->lock();
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
		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				__( 'Semaphore is locked, import/export already in progress.', 'wordpress-github-sync' )
			) );
		}

		$this->app->semaphore()->lock();
		$result = $this->app->import()->master();
		$this->app->semaphore()->unlock();

		if ( is_wp_error( $result ) ) {
			return $this->app->response()->error( $result );
		}

		return $this->app->response()->success( $result );
	}

	/**
	 * Export all the posts in the database to GitHub.
	 *
	 * @return boolean
	 */
	public function export_all() {
		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				__( 'Semaphore is locked, import/export already in progress.', 'wordpress-github-sync' )
			) );
		}

		$this->app->semaphore()->lock();
		$result = $this->app->export()->full();
		$this->app->semaphore()->unlock();

		// Maybe move option updating out of this class/upgrade message display?
		if ( is_wp_error( $result ) ) {
			update_option( '_wpghs_export_error', $result->get_error_message() );

			return $this->app->response()->error( $result );
		} else {
			update_option( '_wpghs_export_complete', 'yes' );
			update_option( '_wpghs_fully_exported', 'yes' );

			return $this->app->response()->success( $result );
		}
	}

	/**
	 * Exports a single post to GitHub by ID.
	 *
	 * Called on the save_post hook.
	 *
	 * @param int $post_id Post ID
	 *
	 * @return boolean
	 */
	public function export_post( $post_id ) {
		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				__( 'Semaphore is locked, import/export already in progress.', 'wordpress-github-sync' )
			) );
		}

		$this->app->semaphore()->lock();
		$result = $this->app->export()->update( $post_id );
		$this->app->semaphore()->unlock();

		if ( is_wp_error( $result ) ) {
			return $this->app->response()->error( $result );
		}

		return $this->app->response()->success( $result );
	}

	/**
	 * Removes the post from the tree.
	 *
	 * Called the delete_post hook.
	 *
	 * @param int $post_id Post ID
	 *
	 * @return boolean
	 */
	public function delete_post( $post_id ) {
		if ( ! $this->app->semaphore()->is_open() ) {
			return $this->app->response()->error( new WP_Error(
				'semaphore_locked',
				__( 'Semaphore is locked, import/export already in progress.', 'wordpress-github-sync' )
			) );
		}

		$this->app->semaphore()->lock();
		$result = $this->app->export()->delete( $post_id );
		$this->app->semaphore()->unlock();

		if ( is_wp_error( $result ) ) {
			return $this->app->response()->error( $result );
		}

		return $this->app->response()->success( $result );
	}
}
