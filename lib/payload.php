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
	 * Payload error.
	 *
	 * @var null|string
	 */
	protected $error = null;

	/**
	 * WordPress_GitHub_Sync_Payload constructor.
	 *
	 * @param WordPress_GitHub_Sync $app      Application container.
	 * @param string                $raw_data Raw request data.
	 */
	public function __construct( WordPress_GitHub_Sync $app, $raw_data ) {
		$this->app  = $app;
		$this->data = $this->get_payload_from_raw_response( $raw_data );

		if ( null === $this->data ) {
			switch ( json_last_error() ) {
				case JSON_ERROR_DEPTH:
					$this->error = __( 'Maximum stack depth exceeded', 'wp-github-sync' );
					break;
				case JSON_ERROR_STATE_MISMATCH:
					$this->error = __( 'Underflow or the modes mismatch', 'wp-github-sync' );
					break;
				case JSON_ERROR_CTRL_CHAR:
					$this->error = __( 'Unexpected control character found', 'wp-github-sync' );
					break;
				case JSON_ERROR_SYNTAX:
					$this->error = __( 'Syntax error, malformed JSON', 'wp-github-sync' );
					break;
				case JSON_ERROR_UTF8:
					$this->error = __( 'Malformed UTF-8 characters, possibly incorrectly encoded', 'wp-github-sync' );
					break;
				default:
					$this->error = __( 'Unknown error', 'wp-github-sync' );
					break;
			}
		}
	}


	/**
	 * Attempts to get the JSON decoded string.
	 *
	 * @param string $raw_data A raw string from php://input
	 *
	 * @see    WordPress_GitHub_Sync_Request::read_raw_data()
	 *
	 * @return Object|null An object from JSON Decode or false if failure.
	 *
	 * @author JayWood <jjwood2004@gmail.com>
	 */
	private function get_payload_from_raw_response( $raw_data ) {

		/*
		 * Try this the old way first, despite this not working in some servers. Assuming there's a flag
		 * at the Nginx or Apache level that auto-parses encoded strings.
		 */
		$maybe_decoded = json_decode( $raw_data );
		if ( null !== $maybe_decoded ) {
			return $maybe_decoded;
		}

		/*
		 * GitHub returns a raw string with Action and Payload keys by default, we have to parse that string
		 * using parse_str() and then grab the payload.
		 */
		parse_str( $raw_data, $decoded_data );

		if ( ! isset( $decoded_data['payload'] ) ) {
			return null;
		}

		return json_decode( $decoded_data['payload'] );
	}

	/**
	 * Returns whether payload should be imported.
	 *
	 * @return bool
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
			throw new Exception( __( 'Sync branch not set. Filter `wpghs_sync_branch` misconfigured.', 'wp-github-sync' ) );
		}

		if ( $sync_branch !== $payload_branch ) {
			return false;
		}

		// We add a tag to commits we push out, so we shouldn't pull them in again.
		$tag = apply_filters( 'wpghs_commit_msg_tag', 'wpghs' );

		if ( ! $tag ) {
			throw new Exception( __( 'Commit message tag not set. Filter `wpghs_commit_msg_tag` misconfigured.', 'wp-github-sync' ) );
		}

		if ( $tag === substr( $this->message(), -1 * strlen( $tag ) ) ) {
			return false;
		}

		if ( ! $this->get_commit_id() ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the sha of the head commit.
	 *
	 * @return string
	 */
	public function get_commit_id() {
		return $this->data->head_commit ? $this->data->head_commit->id : null;
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

	/**
	 * Return whether the payload has an error.
	 *
	 * @return bool
	 */
	public function has_error() {
		return $this->error !== null;
	}

	/**
	 * Return the payload error string.
	 *
	 * @return string|null
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * Returns the payload's commit message.
	 *
	 * @return string
	 */
	protected function message() {
		return $this->data->head_commit->message;
	}
}
