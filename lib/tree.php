<?php

/**
 * Git commit tree.
 */
class WordPress_GitHub_Sync_Tree {

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
	 * Fetches the current tree from GitHub.
	 */
	public function __construct() {
		$this->api  = new WordPress_GitHub_Sync_Api;
		$this->tree = $this->api->last_tree_recursive();
	}

	/**
	 * Checks if the tree is currently ready.
	 *
	 * This will return false if the initial fetch of the tree
	 * returned an error of some sort.
	 *
	 * @return bool
	 */
	public function ready() {
		if ( is_wp_error( $this->tree ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieves the error caused when fetching the tree.
	 *
	 * @return string
	 */
	public function last_error() {
		return $this->tree->get_error_message();
	}

	/**
	 * @param WordPress_GitHub_Sync_Post $post
	 * @param bool $remove
	 */
	public function post_to_tree( $post, $remove = false ) {
		$match = false;

		foreach ( $this->tree as $index => $blob ) {
			if ( ! isset( $blob->sha ) ) {
				continue;
			}

			if ( $blob->sha === $post->sha() ) {
				unset( $this->tree[ $index ] );
				$match = true;

				if ( ! $remove ) {
					$this->tree[] = $this->new_blob( $post, $blob );
				} else {
					$this->changed = true;
				}

				break;
			}
		}

		if ( ! $match ) {
			$this->tree[]  = $this->new_blob( $post );
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
	 * @return bool|stdClass
	 */
	public function get_blob_for_path( $path ) {
		foreach ( $this->tree as $blob ) {
			// this might be a problem if the filename changed since it was set
			// (i.e. post updated in middle mass export)
			// solution?
			if ( $path === $blob->path ) {
				return $blob;
			}
		}

		return false;
	}

	/**
	 * Exports the tree as a new commit with a provided commit message.
	 *
	 * @param string $msg
	 * @return bool|WP_Error false if unchanged, true if success, WP_Error if error
	 */
	public function export( $msg ) {
		if ( ! $this->changed ) {
			return false;
		}

		WordPress_GitHub_Sync::write_log( __( 'Creating the tree.', WordPress_GitHub_Sync::$text_domain ) );
		$tree = $this->api->create_tree( array_values( $this->tree ) );

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		WordPress_GitHub_Sync::write_log( __( 'Creating the commit.', WordPress_GitHub_Sync::$text_domain ) );
		$commit = $this->api->create_commit( $tree->sha, $msg );

		if ( is_wp_error( $commit ) ) {
			return $commit;
		}

		WordPress_GitHub_Sync::write_log( __( 'Setting the master branch to our new commit.',
			WordPress_GitHub_Sync::$text_domain ) );
		$ref = $this->api->set_ref( $commit->sha );

		if ( is_wp_error( $ref ) ) {
			return $ref;
		}

		return true;
	}

}