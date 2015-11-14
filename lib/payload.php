<?php
/**
 * GitHub Webhook payload.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Payload
 */
class WordPress_GitHub_Sync_Payload {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Payload data.
	 *
	 * @var stdClass
	 */
	protected $data;

	/**
	 * WordPress_GitHub_Sync_Payload constructor.
	 *
	 * @param WordPress_GitHub_Sync $app      Application container.
	 * @param string                $raw_data Raw request data.
	 */
	public function __construct( WordPress_GitHub_Sync $app, $raw_data ) {
		$this->app  = $app;
		$this->data = json_decode( $raw_data );
	}

	/**
	 * Returns whether payload should be imported.
	 *
	 * @return bool|WP_Error
	 */
	public function should_import() {
		// @todo how do we get this without importing the whole api object just for this?
		if ( strtolower( $this->data->repository->full_name ) !== strtolower( $this->app->api()->fetch()->repository() ) ) {
			return false;
		}

		// The last term in the ref is the payload_branch name.
		$refs   = explode( '/', $this->data->ref );
		$payload_branch = array_pop( $refs );
		$sync_branch = apply_filters( 'wpghs_sync_branch', 'master' );

		if ( ! $sync_branch ) {
			throw new Exception( __( 'Sync branch not set. Filter `wpghs_sync_branch` misconfigured.', 'wordpress-github-sync' ) );
		}

		if ( $sync_branch !== $payload_branch ) {
			return new WP_Error(
				'invalid_branch',
				sprintf(
					__( 'Not on branch %s.', 'wordpress-github-sync' ),
					$sync_branch
				)
			);
		}

		// We add wpghs to commits we push out, so we shouldn't pull them in again.
		if ( 'wpghs' === substr( $this->data->head_commit->message, -5 ) ) {
			return new WP_Error( 'synced_commit', __( 'Already synced this commit.', 'wordpress-github-sync' ) );
		}

		return true;
	}

	/**
	 * Returns the sha of the head commit.
	 *
	 * @return string
	 */
	public function get_commit_id() {
		return $this->data->head_commit->id;
	}

	/**
	 * Returns the email address for the commit author.
	 *
	 * @return string
	 */
	public function get_author_email() {
		return $this->data->head_commit->author->email;
	}

	/**
	 * Returns array commits for the payload.
	 *
	 * @return array
	 */
	public function get_commits() {
		return $this->data->commits;
	}

	/**
	 * Returns the repository's full name.
	 *
	 * @return string
	 */
	public function get_repository_name() {
		return $this->data->repository->full_name;
	}
}
