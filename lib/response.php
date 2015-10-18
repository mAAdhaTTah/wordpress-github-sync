<?php

class WordPress_GitHub_Sync_Response {

	/**
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * WordPress_GitHub_Sync_Response constructor.
	 *
	 * @param WordPress_GitHub_Sync $app
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Writes to the log and returns the error response.
	 *
	 * @param WP_Error $error
	 * @return false
	 */
	public function error( WP_Error $error ) {
		WordPress_GitHub_Sync::write_log( $error->get_error_message() );
		// @todo back-compat this, only 4.1+ works
		wp_send_json_error( $error );

		return false;
	}

	/**
	 * Writes to the log and returns the success response.
	 *
	 * @param string $result
	 * @return true
	 */
	public function success( $result ) {
		WordPress_GitHub_Sync::write_log( $result );
		wp_send_json_success( $result );

		return true;
	}
}
