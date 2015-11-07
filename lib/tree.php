<?php
/**
 * Git commit tree.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Tree
 */
class WordPress_GitHub_Sync_Tree {

	/**
	 * Current tree if retrieved, otherwise, error
	 *
	 * @var stdClass
	 */
	protected $data;

	/**
	 * Tree's sha.
	 *
	 * @var string
	 */
	protected $sha;

	/**
	 * Tree's url.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Blobs keyed by path.
	 *
	 * @var WordPress_GitHub_Sync_Blob[]
	 */
	protected $paths = array();

	/**
	 * Blobs keyed by sha.
	 *
	 * @var WordPress_GitHub_Sync_Blob[]
	 */
	protected $shas = array();

	/**
	 * Whether the tree has changed.
	 *
	 * @var bool
	 */
	protected $changed = false;

	/**
	 * Represents a commit tree.
	 *
	 * @param stdClass $data Raw tree data.
	 */
	public function __construct( stdClass $data ) {
		$this->data = $data;

		$this->interpret_data();
	}

	/**
	 * Returns the tree's raw data.
	 *
	 * @return stdClass
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Return's the tree's sha.
	 *
	 * @return string
	 */
	public function sha() {
		return $this->sha;
	}

	/**
	 * Updates the tree's sha.
	 *
	 * @param string $sha Tree sha.
	 *
	 * @return $this
	 */
	public function set_sha( $sha ) {
		$this->sha = $sha;

		return $this;
	}

	/**
	 * Returns the tree's url.
	 *
	 * @return string
	 */
	public function url() {
		return $this->url;
	}

	/**
	 * Returns the tree's blobs.
	 *
	 * @return WordPress_GitHub_Sync_Blob[]
	 */
	public function blobs() {
		return array_values( $this->paths );
	}

	/**
	 * Sets the tree's blobs to the provided array of blobs.
	 *
	 * @param WordPress_GitHub_Sync_Blob[] $blobs Array of blobs to set to tree.
	 *
	 * @return $this
	 */
	public function set_blobs( array $blobs ) {
		$this->paths = array();
		$this->shas  = array();

		foreach ( $blobs as $blob ) {
			$this->add_blob( $blob );
		}

		return $this;
	}

	/**
	 * Adds the provided blob to the tree.
	 *
	 * @param WordPress_GitHub_Sync_Blob $blob Blob to add to tree.
	 *
	 * @return $this
	 */
	public function add_blob( WordPress_GitHub_Sync_Blob $blob ) {
		$this->paths[ $blob->path() ] = $this->shas[ $blob->sha() ] = $blob;

		return $this;
	}

	/**
	 * Adds the provided post as a blob to the tree.
	 *
	 * @param WordPress_GitHub_Sync_Post $post Post to add to tree.
	 *
	 * @return $this
	 */
	public function add_post_to_tree( WordPress_GitHub_Sync_Post $post ) {
		$blob = $this->get_blob_for_post( $post );

		if (
			! $blob->sha() ||
			$blob->content_import() !== $post->github_content()
		) {
			$this->shas[]  = $this->paths[ $blob->path() ] = $post->to_blob();
			$this->changed = true;

			if ( $blob->sha() ) {
				unset( $this->shas[ $blob->sha() ] );
			}
		}

		return $this;
	}

	/**
	 * Removes the provided post's blob from the tree.
	 *
	 * @param WordPress_GitHub_Sync_Post $post Post to remove from tree.
	 *
	 * @return $this
	 */
	public function remove_post_from_tree( WordPress_GitHub_Sync_Post $post ) {
		if ( isset( $this->shas[ $post->sha() ] ) ) {
			$blob = $this->shas[ $post->sha() ];

			unset( $this->paths[ $blob->path() ] );
			unset( $this->shas[ $post->sha() ] );

			$this->changed = true;
		} else if ( isset( $this->paths[ $post->github_path() ] ) ) {
			unset( $this->paths[ $post->github_path() ] );

			$this->changed = true;
		}

		return $this;
	}

	/**
	 * Retrieves a tree blob for a given path.
	 *
	 * @param string $path Path to retrieve blob by.
	 *
	 * @return false|WordPress_GitHub_Sync_Blob
	 */
	public function get_blob_by_path( $path ) {
		return isset( $this->paths[ $path ] ) ? $this->paths[ $path ] : false;
	}

	/**
	 * Retrieves a tree blob for a given path.
	 *
	 * @param string $sha Sha to retrieve blob by.
	 *
	 * @return false|WordPress_GitHub_Sync_Blob
	 */
	public function get_blob_by_sha( $sha ) {
		return isset( $this->shas[ $sha ] ) ? $this->shas[ $sha ] : false;
	}

	/**
	 * Returns whether the tree has changed.
	 *
	 * @return bool
	 */
	public function is_changed() {
		return $this->changed;
	}

	/**
	 * Formats the tree for an API call body.
	 *
	 * @return array
	 */
	public function to_body() {
		$tree = array();

		foreach ( $this->blobs() as $blob ) {
			$tree[] = $blob->to_body();
		}

		return array( 'tree' => $tree );
	}

	/**
	 * Interprets the Tree from the data.
	 */
	protected function interpret_data() {
		$this->sha = isset( $this->data->sha ) ? $this->data->sha : '';
		$this->url = isset( $this->data->url ) ? $this->data->url : '';
	}

	/**
	 * Returns a blob for the provided post.
	 *
	 * @param WordPress_GitHub_Sync_Post $post Post to retrieve blob for.
	 *
	 * @return WordPress_GitHub_Sync_Blob
	 */
	protected function get_blob_for_post( WordPress_GitHub_Sync_Post $post ) {
		if ( $blob = $this->get_blob_by_sha( $post->sha() ) ) {
			return $blob;
		}

		if ( $blob = $this->get_blob_by_path( $post->github_path() ) ) {
			return $blob;
		}

		return $post->to_blob();
	}
}
