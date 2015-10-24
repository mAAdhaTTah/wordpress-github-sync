<?php

class WordPress_GitHub_Sync_Commit {

	/**
	 * Raw commit data.
	 *
	 * @var stdClass
	 */
	protected $data;

	/**
	 * Commit tree.
	 *
	 * @var WordPress_GitHub_Sync_Tree
	 */
	protected $tree;

	/**
	 * Commit message.
	 *
	 * @var string
	 */
	protected $message;

	/**
	 * Commit's tree sha.
	 *
	 * @var string
	 */
	protected $tree_sha;

	/**
	 * Instantiates a new Commit object.
	 *
	 * @param stdClass $data Raq commit data.
	 */
	public function __construct( stdClass $data ) {
		$this->data = $data;

		$this->message = $this->data->message ?: '';
		$this->sha = $this->data->sha ?: '';
		$this->tree_sha = $this->data->tree->sha ?: '';
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
		if ( 'wpghs' === substr( $this->message, -5 ) ) {
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
		return $this->message;
	}

	/**
	 * Returns the commit sha.
	 *
	 * @return string
	 */
	public function sha() {
		return $this->sha;
	}

	/**
	 * Returns the commit's tree's sha.
	 *
	 * @return string
	 */
	public function tree_sha() {
		return $this->tree_sha;
	}

	/**
	 * Return's the commit's tree.
	 *
	 * @return WordPress_GitHub_Sync_Tree
	 */
	public function tree() {
		return $this->tree;
	}

	/**
	 * Set the commit's tree.
	 *
	 * @param WordPress_GitHub_Sync_Tree $tree New tree for commit.
	 */
	public function set_tree( WordPress_GitHub_Sync_Tree $tree ) {
		$this->tree = $tree;
	}
}
