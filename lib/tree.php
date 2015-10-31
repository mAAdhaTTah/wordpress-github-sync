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
	 * @var WordPress_GitHub_Sync_Api
	 */
	protected $api;

	/**
	 * Current tree if retrieved, otherwise, error
	 *
	 * @var array
	 */
	protected $data;

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
	 * @param WordPress_GitHub_Sync_Api $api Api object.
	 * @param array $data
	 */
	public function __construct( WordPress_GitHub_Sync_Api $api, $data ) {
		$this->api = $api;
		$this->data = $data;
	}

	/**
	 * Returns the
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
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
	 * @todo split into post_{to/from}_tree instead of param toggle; easier to understand
	 */
	public function post_to_tree( $post, $remove = false ) {
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
		foreach ( $this->data as $blob ) {
			// this might be a problem if the filename changed since it was set
			// (i.e. post updated in middle mass export)
			// solution?
			if ( $path === $blob->path ) {
				// @todo this is a stdClass; should be Blob
				return $blob;
			}
		}

		return false;
	}

	/**
	 * Return the current element.
	 *
	 * @link http://php.net/manual/en/iterator.current.php
	 * @return stdClass
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

		while ( isset( $this->data[ $this->position ] ) ) {
			$blob = $this->data[ $this->position ];

			// Skip the repo's readme
			if ( 'readme' === strtolower( substr( $blob->path, 0, 6 ) ) ) {
				WordPress_GitHub_Sync::write_log( __( 'Skipping README', 'wordpress-github-sync' ) );
				$this->next();

				continue;
			}

			// If the blob sha already matches a post, then move on
			$id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sha' AND meta_value = '$blob->sha'" );
			if ( $id ) {
				WordPress_GitHub_Sync::write_log(
					sprintf(
						__( 'Already synced blob %s', 'wordpress-github-sync' ),
						$blob->path
					)
				);
				$this->next();

				continue;
			}

			$blob = $this->api->get_blob( $blob->sha );

			if ( is_wp_error( $blob ) ) {
				WordPress_GitHub_Sync::write_log(
					sprintf(
						__( 'Failed getting blob with error: %s', 'wordpress-github-sync' ),
						$blob->get_error_message()
					)
				);
				$this->next();

				continue;
			}

			$content = base64_decode( $blob->content );

			// If it doesn't have YAML frontmatter, then move on
			if ( '---' !== substr( $content, 0, 3 ) ) {
				WordPress_GitHub_Sync::write_log(
					sprintf(
						__( 'No front matter on blob %s', 'wordpress-github-sync' ),
						$blob->sha
					)
				);
				$this->next();

				continue;
			}

			$blob->content = $content;
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
