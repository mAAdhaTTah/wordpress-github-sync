<?php

class WordPress_GitHub_Sync_Request {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Raw request data.
	 *
	 * @var string
	 */
	protected $raw_data;

	/**
	 * WordPress_GitHub_Sync_Request constructor.
	 *
	 * @param WordPress_GitHub_Sync $app
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Validates the header's secret.
	 *
	 * @return true|WP_Error
	 */
	public function is_secret_valid() {
		$headers = $this->headers();

		$this->raw_data = file_get_contents( 'php://input' );

		// validate secret
		$hash = hash_hmac( 'sha1', $this->raw_data, $this->secret() );
		if ( 'sha1=' . $hash !== $headers['X-Hub-Signature'] ) {
			return new WP_Error( 'invalid_headers', __( 'Failed to validate secret.', 'wordpress-github-sync' ) );
		}

		return true;
	}

	/**
	 * Returns a payload object for the given request.
	 *
	 * @return WordPress_GitHub_Sync_Payload
	 */
	public function payload() {
		return new WordPress_GitHub_Sync_Payload( $this->app->api(), $this->raw_data );
	}

	/**
	 * Cross-server header support.
	 *
	 * Returns an array of the request's headers.
	 *
	 * @return array
	 */
	protected function headers() {
		if ( function_exists( 'getallheaders' ) ) {
			return getallheaders();
		}

		// Nginx and pre 5.4 workaround
		// http://www.php.net/manual/en/function.getallheaders.php
		$headers = array();
		foreach ( $_SERVER as $name => $value ) {
			if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
				$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
			}
		}

		return $headers;
	}

	/**
	 * Returns the Webhook secret
	 *
	 * @return string
	 */
	protected function secret() {
		return get_option( 'wpghs_secret' );
	}
}
