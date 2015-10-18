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
		$this->log( $error );

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
		$this->log( $result );

		wp_send_json_success( $result );

		return true;
	}

	/**
	 * Writes a log message.
	 *
	 * Can extract a message from WP_Error object.
	 *
	 * @param string|WP_Error $msg Message to log.
	 */
	public function log( $msg ) {
		if ( is_wp_error( $msg ) ) {
			$msg = $msg->get_error_message();
		}

		WordPress_GitHub_Sync::write_log( $msg );
	}
}
