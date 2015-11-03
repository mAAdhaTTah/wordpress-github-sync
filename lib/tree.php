<?php

/**
 * Git commit tree.
 */
class WordPress_GitHub_Sync_Tree implements Iterator {

	/**
	 * Whether the tree has changed.
	 *
	 * @var bool
	 */
	protected $changed = false;

	/**
	 * Current tree if retrieved, otherwise, error
	 *
	 * @var stdClass
	 */
	protected $data;

	/**
	 * Blobs keyed by path.
	 *
	 * @var array<string $path, WordPress_GitHub_Sync_Blob $blob>
	 */
	protected $paths;

	/**
	 * Blobs keyed by sha.
	 *
	 * @var array<string $sha, WordPress_GitHub_Sync_Blob $blob>
	 */
	protected $shas;

	/**
	 * Current position in the loop.
	 *
	 * @var int
	 */
	protected $position;

	/**
	 * Current blob in the loop.
	 *
	 * @var stdClass
	 */
	protected $current;

	/**
	 * Represents a commit tree.
	 *
	 * @param stdClass $data
	 */
	public function __construct( stdClass $data ) {
		$this->data = $data;

		$this->sha = $this->data->sha;
	}

	/**
	 * Returns the tree's raw data.
	 *
	 * @return array
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
	 * @param WordPress_GitHub_Sync_Blob[] $blobs
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
	 * @param WordPress_GitHub_Sync_Blob $blob
	 *
	 * @return $this
	 */
	public function add_blob( WordPress_GitHub_Sync_Blob $blob ) {
		$this->paths[ $blob->path() ] = $this->shas[ $blob->sha() ] = $blob;

		return $this;
	}

	/**
	 * Manipulates the tree for a given post.
	 *
	 * If remove is true, removes the provided post from the current true.
	 * If false or nothing is provided, adds or updates the tree
	 * with the provided post.
	 *
	 * @param WordPress_GitHub_Sync_Post $post
	 * @param bool $remove
	 *
	 * @todo split into post_{to/from}_tree instead of param toggle; easier to understand
	 */
	public function add_post_to_tree( $post, $remove = false ) {
		$match = false;

		foreach ( $this->data as $index => $blob ) {
			if ( ! isset( $blob->sha ) ) {
				continue;
			}

			if ( $blob->sha === $post->sha() ) {
				unset( $this->data[ $index ] );
				$match = true;

				if ( ! $remove ) {
					$this->data[] = $this->new_blob( $post, $blob );
				} else {
					$this->changed = true;
				}

				break;
			}
		}

		if ( ! $match && ! $remove ) {
			$this->data[]  = $this->new_blob( $post );
			$this->changed = true;
		}
	}

	/**
	 * Combines a post and (potentially) a blob.
	 *
	 * If no blob is provided, turns post into blob.
	 *
	 * If blob is provided, compares blob to post
	 * and updates blob data based on differences.
	 *
	 * @param WordPress_GitHub_Sync_Post $post
	 * @param bool|stdClass $blob
	 *
	 * @return array
	 */
	public function new_blob( $post, $blob = false ) {
		if ( ! $blob ) {
			$blob = $this->blob_from_post( $post );
		} else {
			unset( $blob->url );
			unset( $blob->size );

			if ( $blob->path !== $post->github_path() ) {
				$blob->path    = $post->github_path();
				$this->changed = true;
			}

			$blob_data = $this->api->get_blob( $blob->sha );

			if ( base64_decode( $blob_data->content ) !== $post->github_content() ) {
				unset( $blob->sha );
				$blob->content = $post->github_content();
				$this->changed = true;
			}
		}

		return $blob;
	}

	/**
	 * Creates a blob with the data required for the tree.
	 *
	 * @param WordPress_GitHub_Sync_Post $post
	 *
	 * @return stdClass
	 */
	public function blob_from_post( $post ) {
		$blob = new stdClass;

		$blob->path    = $post->github_path();
		$blob->mode    = '100644';
		$blob->type    = 'blob';
		$blob->content = $post->github_content();

		return $blob;
	}

	/**
	 * Retrieves a tree blob for a given path.
	 *
	 * @param string $path
	 *
	 * @return bool|WordPress_GitHub_Sync_Blob
	 */
	public function get_blob_for_path( $path ) {
		return isset( $this->paths[ $path ] ) ? $this->paths[ $path ] : false;
	}

	/**
	 * Return the current element.
	 *
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return WordPress_GitHub_Sync_Blob
	 */
	public function current() {
		return $this->current;
	}

	/**
	 * Move forward to next element.
	 *
	 * @link http://php.net/manual/en/iterator.next.php
	 */
	public function next() {
		$this->position++;
	}

	/**
	 * Return the key of the current element
	 *
	 * @link http://php.net/manual/en/iterator.key.php
	 * @return int|null int on success, or null on failure.
	 */
	public function key() {
		if ( $this->valid() ) {
			return $this->position;
		}

		return null;
	}

	/**
	 * Checks if current position is valid
	 *
	 * @link http://php.net/manual/en/iterator.valid.php
	 * @return boolean true on success, false on failure.
	 */
	public function valid() {
		global $wpdb;

		$blobs = $this->blobs();

		while ( isset( $blobs[ $this->position ] ) ) {
			$blob = $blobs[ $this->position ];

			// Skip the repo's readme
			if ( 'readme' === strtolower( substr( $blob->path(), 0, 6 ) ) ) {
//				WordPress_GitHub_Sync::write_log( __( 'Skipping README', 'wordpress-github-sync' ) );
				$this->next();

				continue;
			}

			// If the blob sha already matches a post, then move on
			// @todo this doesn't belong here
			$id = $wpdb->get_var(
				"SELECT post_id FROM $wpdb->postmeta
				WHERE meta_key = '_sha' AND meta_value = '{$blob->sha()}'"
			);

			if ( $id ) {
//				WordPress_GitHub_Sync::write_log(
//					sprintf(
//						__( 'Already synced blob %s', 'wordpress-github-sync' ),
//						$blob->path()
//					)
//				);
				$this->next();

				continue;
			}

			if ( ! $blob->has_frontmatter() ) {
//				WordPress_GitHub_Sync::write_log(
//					sprintf(
//						__( 'No front matter on blob %s', 'wordpress-github-sync' ),
//						$blob->path()
//					)
//				);
				$this->next();

				continue;
			}

			$this->current = $blob;

			return true;
		}

		return false;
	}

	/**
	 * Rewind the Iterator to the first element
	 *
	 * @link http://php.net/manual/en/iterator.rewind.php
	 */
	public function rewind() {
		$this->position = 0;
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
		return array( 'tree' => $this->data );
	}
}
