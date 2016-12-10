<?php
/**
 * Base API client class.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Base_Client
 */
class WordPress_GitHub_Sync_Base_Client {

	const TOKEN_OPTION_KEY = 'wpghs_oauth_token';
	const REPO_OPTION_KEY = 'wpghs_repository';
	const HOST_OPTION_KEY = 'wpghs_host';

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Instantiates a new Api object.
	 *
	 * @param WordPress_GitHub_Sync $app Application container.
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Generic GitHub API interface and response handler
	 *
	 * @param string $method HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $body Request body.
	 *
	 * @return stdClass|WP_Error
	 */
	protected function call( $method, $endpoint, $body = array() ) {
		if ( is_wp_error( $error = $this->can_call() ) ) {
			return $error;
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'token ' . $this->oauth_token(),
			),
		);

		if ( 'GET' !== $method ) {
			$args['body'] = function_exists( 'wp_json_encode' ) ?
				wp_json_encode( $body ) :
				json_encode( $body );
		}

		$response = wp_remote_request( $endpoint, $args );
		$status   = wp_remote_retrieve_header( $response, 'status' );
		$body     = json_decode( wp_remote_retrieve_body( $response ) );

		if ( '2' !== substr( $status, 0, 1 ) && '3' !== substr( $status, 0, 1 ) ) {
			return new WP_Error(
				strtolower( str_replace( ' ', '_', $status ) ),
				sprintf(
					__( 'Method %s to endpoint %s failed with error: %s', 'wp-github-sync' ),
					$method,
					$endpoint,
					$body && $body->message ? $body->message : 'Unknown error'
				)
			);
		}

		return $body;
	}

	/**
	 * Validates whether the Api object can make a call.
	 *
	 * @return true|WP_Error
	 */
	protected function can_call() {
		if ( ! $this->oauth_token() ) {
			return new WP_Error(
				'missing_token',
				__( 'WordPress-GitHub-Sync needs an auth token. Please update your settings.', 'wp-github-sync' )
			);
		}

		$repo = $this->repository();

		if ( ! $repo ) {
			return new WP_Error(
				'missing_repository',
				__( 'WordPress-GitHub-Sync needs a repository. Please update your settings.', 'wp-github-sync' )
			);
		}

		$parts = explode( '/', $repo );

		if ( 2 !== count( $parts ) ) {
			return new WP_Error(
				'malformed_repository',
				__( 'WordPress-GitHub-Sync needs a properly formed repository. Please update your settings.', 'wp-github-sync' )
			);
		}

		return true;
	}

	/**
	 * Returns the repository to sync with
	 *
	 * @return string
	 */
	public function repository() {
		return (string) get_option( self::REPO_OPTION_KEY );
	}

	/**
	 * Returns the user's oauth token
	 *
	 * @return string
	 */
	public function oauth_token() {
		return (string) get_option( self::TOKEN_OPTION_KEY );
	}

	/**
	 * Returns the GitHub host to sync with (for GitHub Enterprise support)
	 */
	public function api_base() {
		return get_option( self::HOST_OPTION_KEY );
	}

	/**
	 * API endpoint for the master branch reference
	 */
	public function reference_endpoint() {
		$sync_branch = apply_filters( 'wpghs_sync_branch', 'master' );

		if ( ! $sync_branch ) {
			throw new Exception( __( 'Sync branch not set. Filter `wpghs_sync_branch` misconfigured.', 'wp-github-sync' ) );
		}

		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/git/refs/heads/' . $sync_branch;

		return $url;
	}

	/**
	 * Api to get and create commits
	 */
	public function commit_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/git/commits';

		return $url;
	}

	/**
	 * Api to get and create trees
	 */
	public function tree_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/git/trees';

		return $url;
	}

	/**
	 * Builds the proper blob API endpoint for a given post
	 *
	 * Returns String the relative API call path
	 */
	public function blob_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/git/blobs';

		return $url;
	}

	/**
	 * Builds the proper content API endpoint for a given post
	 *
	 * Returns String the relative API call path
	 */
	public function content_endpoint() {
		$url = $this->api_base() . '/repos/';
		$url = $url . $this->repository() . '/contents/';

		return $url;
	}
}
