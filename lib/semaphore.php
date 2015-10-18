<?php

class WordPress_GitHub_Sync_Semaphore {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Locked when receiving payload
	 * @var boolean
	 */
	public $push_lock = false;

	/**
	 * Instantiates a new Semaphore object.
	 *
	 * @param WordPress_GitHub_Sync $app
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Checks if the Semaphore is open.
	 *
	 * Fails to report it's open if the the Api class can't make a call
	 * or the push lock has been enabled.
	 *
	 * @return true|WP_Error
	 */
	public function is_open() {
		if ( is_wp_error( $error = $this->app->api()->can_call() ) ) {
			return $error;
		}

		if ( $this->push_lock ) {
			return new WP_Error(
				'semaphore_locked',
				__( 'Semaphore is locked, import/export already in progress.', 'wordpress-github-sync' )
			);
		}

		return true;
	}

	/**
	 * Enables the push lock.
	 */
	public function lock() {
		$this->push_lock = true;
	}

	/**
	 * Disables the push lock.
	 */
	public function unlock() {
		$this->push_lock = false;
	}
}
