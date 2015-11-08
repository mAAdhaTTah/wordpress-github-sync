<?php
/**
 * API commit model.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Commit
 */
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
	 * @var stdClass|false
	 */
	protected $author;

	/**
	 * Commit committer.
	 *
	 * @var stdClass|false
	 */
	protected $committer;

	/**
	 * Commit parents.
	 *
	 * @var string[]
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
	 * @return stdClass|false
	 */
	public function author() {
		return $this->author;
	}

	/**
	 * Return's the commit author's email.
	 *
	 * @return string
	 */
	public function author_email() {
		if ( isset( $this->author->email ) ) {
			return $this->author->email;
		}

		return '';
	}

	/**
	 * Set's the commit author.
	 *
	 * @param stdClass $author Commit author data.
	 *
	 * @return $this
	 */
	public function set_author( stdClass $author ) {
		$this->author = $author;

		$this->set_to_parent();

		return $this;
	}

	/**
	 * Return the commit committer.
	 *
	 * @return stdClass|false
	 */
	public function committer() {
		return $this->committer;
	}

	/**
	 * Set's the commit committer.
	 *
	 * @param stdClass $committer Committer data.
	 *
	 * @return $this
	 */
	public function set_committer( stdClass $committer ) {
		$this->committer = $committer;

		$this->set_to_parent();

		return $this;
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
	 * @param string $message Commit message.
	 *
	 * @return $this
	 */
	public function set_message( $message ) {
		$this->message = (string) $message;

		$this->set_to_parent();

		return $this;
	}

	/**
	 * Return the commit parents.
	 *
	 * @return string[]
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

		if ( isset( $this->data->tree ) ) {
			return $this->data->tree->sha;
		}

		return '';
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
	 *
	 * @return $this
	 */
	public function set_tree( WordPress_GitHub_Sync_Tree $tree ) {
		$this->tree = $tree;

		return $this;
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
		return 'wpghs' === substr( $this->message, - 5 );
	}

	/**
	 * Transforms the commit into the API
	 * body required to create a new commit.
	 *
	 * @return array
	 */
	public function to_body() {
		$body = array(
			'tree' => $this->tree_sha(),
			'message' => $this->message(),
			'parents' => $this->parents(),
		);

		// @todo set author here
		return $body;
	}

	/**
	 * Interprets the raw data object into commit properties.
	 */
	protected function interpret_data() {
		$this->sha       = isset( $this->data->sha ) ? $this->data->sha : '';
		$this->url       = isset( $this->data->url ) ? $this->data->url : '';
		$this->author    = isset( $this->data->author ) ? $this->data->author : false;
		$this->committer = isset( $this->data->committer ) ? $this->data->committer : false;
		$this->message   = isset( $this->data->message ) ? $this->data->message : '';
		$this->parents   = isset( $this->data->parents ) ? $this->data->parents : array();
	}

	/**
	 * Assigns the current sha to be its parent.
	 */
	protected function set_to_parent() {
		if ( $this->sha ) {
			$this->parents = array( $this->sha );
			$this->sha     = '';
		}
	}
}
