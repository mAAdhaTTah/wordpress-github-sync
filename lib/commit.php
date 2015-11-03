<?php

class WordPress_GitHub_Sync_Commit {

	/**
	 * Raw commit data.
	 *
	 * @var stdClass
	 */
	protected $data;

	/**
	 * Commit sha.
	 *
	 * @var string
	 */
	protected $sha;

	/**
	 * Commit message.
	 *
	 * @var string
	 */
	protected $message = '';

	/**
	 * Commit tree.
	 *
	 * @var WordPress_GitHub_Sync_Tree
	 */
	protected $tree;

	/**
	 * Commit api url.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Commit author.
	 *
	 * @var stdClass
	 */
	protected $author;

	/**
	 * Commit committer.
	 *
	 * @var stdClass
	 */
	protected $committer;

	/**
	 * Commit parents.
	 *
	 * @var stdClass[]
	 */
	protected $parents;

	/**
	 * Instantiates a new Commit object.
	 *
	 * @param stdClass $data Raw commit data.
	 */
	public function __construct( stdClass $data ) {
		$this->data = $data;

		$this->interpret_data();
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
	 * Returns the commit sha.
	 *
	 * @return string
	 */
	public function sha() {
		return $this->sha;
	}

	/**
	 * Return the commit's API url.
	 *
	 * @return string
	 */
	public function url() {
		return $this->url;
	}

	/**
	 * Return the commit author.
	 *
	 * @return stdClass
	 */
	public function author() {
		return $this->author;
	}

	/**
	 * Return the commit committer.
	 *
	 * @return stdClass
	 */
	public function committer() {
		return $this->committer;
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
	 * Set's the commit message;
	 *
	 * @param string $message
	 *
	 * @return $this
	 */
	public function set_message( $message ) {
		$this->message = (string) $message;

		return $this;
	}

	/**
	 * Return the commit parents.
	 *
	 * @return stdClass[]
	 */
	public function parents() {
		return $this->parents;
	}

	/**
	 * Returns the commit's tree's sha.
	 *
	 * @return string
	 */
	public function tree_sha() {
		if ( $this->tree ) {
			return $this->tree->sha();
		}

		return $this->data->tree->sha;
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

	/**
	 * Interprets the raw data object into commit properties.
	 */
	protected function interpret_data() {
		$this->sha = $this->data->sha ?: '';
		$this->url = $this->data->url ?: '';
		$this->author = $this->data->author ?: new stdClass;
		$this->committer = $this->data->committer ?: new stdClass;
		$this->message = $this->data->message ?: '';
		$this->parents = $this->data->parents ?: array();
	}
}
