<?php

class WordPress_GitHub_Sync_Commit {

	/**
	 * Api object.
	 *
	 * @var WordPress_GitHub_Sync_Api
	 */
	protected $api;

	/**
	 * Raw commit data.
	 *
	 * @var stdClass
	 */
	protected $data;

	/**
	 * Instantiates a new Commit object.
	 *
	 * @param WordPress_GitHub_Sync_Api $api
	 * @param stdClass $data
	 */
	function __construct( WordPress_GitHub_Sync_Api $api, $data ) {
		$this->api = $api;
		$this->data = $data;
	}

	/**
	 * Returns whether the commit is currently synced.
	 *
	 * The commit message of every commit that's exported
	 * by WPGHS ends with '- wpghs', so we don't sync those
	 * commits down.
	 *
	 * @return bool
	 */
	public function already_synced() {
		if ( 'wpghs' === substr( $this->data->message, -5 ) ) {
			true;
		}

		return false;
	}

	/**
	 * Returns the commit message, if set.
	 *
	 * @return string
	 */
	public function message() {
		return $this->data->message ?: '';
	}

	/**
	 * Returns the commit sha.
	 *
	 * @return string
	 */
	public function sha() {
		return $this->data->sha ?: '';
	}

	/**
	 * Returns the commit's tree's sha.
	 *
	 * @return string
	 */
	public function tree_sha() {
		return $this->data->tree->sha ?: '';
	}
}
