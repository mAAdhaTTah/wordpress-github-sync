<?php
/**
 * Interfaces with the GitHub API
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Api
 */
class WordPress_GitHub_Sync_Api {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * GitHub fetch client.
	 *
	 * @var WordPress_GitHub_Sync_Fetch_Client
	 */
	protected $fetch;

	/**
	 * Github persist client.
	 *
	 * @var WordPress_GitHub_Sync_Persist_Client
	 */
	protected $persist;

	/**
	 * Instantiates a new Api object.
	 *
	 * @param WordPress_GitHub_Sync $app Application container.
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Lazy-load fetch client.
	 *
	 * @return WordPress_GitHub_Sync_Fetch_Client
	 */
	public function fetch() {
		if ( ! $this->fetch ) {
			$this->fetch = new WordPress_GitHub_Sync_Fetch_Client( $this->app );
		}

		return $this->fetch;
	}

	/**
	 * Lazy-load persist client.
	 *
	 * @return WordPress_GitHub_Sync_Persist_Client
	 */
	public function persist() {
		if ( ! $this->persist ) {
			$this->persist = new WordPress_GitHub_Sync_Persist_Client( $this->app );
		}

		return $this->persist;
	}
}
