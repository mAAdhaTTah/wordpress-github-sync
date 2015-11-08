<?php
/**
 * Fetch API client class.
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Fetch_Client
 */
class WordPress_GitHub_Sync_Fetch_Client extends WordPress_GitHub_Sync_Base_Client {

	/**
	 * Retrieve the last commit in the repository
	 *
	 * @return WordPress_GitHub_Sync_Commit|WP_Error
	 */
	public function master() {
		$data = $this->call( 'GET', $this->reference_endpoint() );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return $this->commit( $data->object->sha );
	}

	/**
	 * Retrieves a commit by sha from the GitHub API
	 *
	 * @param string $sha Sha for commit to retrieve.
	 *
	 * @return WordPress_GitHub_Sync_Commit|WP_Error
	 */
	public function commit( $sha ) {
		if ( $cache = $this->app->cache()->fetch_commit( $sha ) ) {
			return $cache;
		}

		$data = $this->call( 'GET', $this->commit_endpoint() . '/' . $sha );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$commit = new WordPress_GitHub_Sync_Commit( $data );
		$tree   = $this->tree_recursive( $commit->tree_sha() );

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$commit->set_tree( $tree );

		return $this->app->cache()->set_commit( $sha, $commit );
	}

	/**
	 * Calls the content API to get the post's contents and metadata
	 *
	 * Returns Object the response from the API
	 *
	 * @param WordPress_GitHub_Sync_Post $post Post to retrieve remote contents for.
	 *
	 * @return mixed
	 */
	public function remote_contents( $post ) {
		return $this->call( 'GET', $this->content_endpoint() . $post->github_path() );
	}

	/**
	 * Retrieves a tree by sha recursively from the GitHub API
	 *
	 * @param string $sha Commit sha to retrieve tree from.
	 *
	 * @return WordPress_GitHub_Sync_Tree|WP_Error
	 */
	protected function tree_recursive( $sha ) {
		if ( $cache = $this->app->cache()->fetch_tree( $sha ) ) {
			return $cache;
		}

		$data = $this->call( 'GET', $this->tree_endpoint() . '/' . $sha . '?recursive=1' );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		foreach ( $data->tree as $index => $thing ) {
			// We need to remove the trees because
			// the recursive tree includes both
			// the subtrees as well the subtrees' blobs.
			if ( 'tree' === $thing->type ) {
				unset( $data->tree[ $index ] );
			}
		}

		$tree = new WordPress_GitHub_Sync_Tree( $data );
		$tree->set_blobs( $this->blobs( $data->tree ) );

		return $this->app->cache()->set_tree( $sha, $tree );
	}

	/**
	 * Generates blobs for recursive tree blob data.
	 *
	 * @param stdClass[] $blobs Array of tree blob data.
	 *
	 * @return WordPress_GitHub_Sync_Blob[]
	 */
	protected function blobs( array $blobs ) {
		$results = array();

		foreach ( $blobs as $blob ) {
			$obj = $this->blob( $blob );

			if ( ! is_wp_error( $obj ) ) {
				$results[] = $obj;
			}
		}

		return $results;
	}

	/**
	 * Retrieves the blob data for a given sha
	 *
	 * @param stdClass $blob Tree blob data.
	 *
	 * @return WordPress_GitHub_Sync_Blob|WP_Error
	 */
	protected function blob( $blob ) {
		if ( $cache = $this->app->cache()->fetch_blob( $blob->sha ) ) {
			return $cache;
		}

		$data = $this->call( 'GET', $this->blob_endpoint() . '/' . $blob->sha );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$data->path = $blob->path;
		$obj = new WordPress_GitHub_Sync_Blob( $data );

		return $this->app->cache()->set_blob( $obj->sha(), $obj );
	}
}
