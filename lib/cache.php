<?php
/**
 * The cache object which reads and writes the GitHub api data
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Cache
 */
class WordPress_GitHub_Sync_Cache {

	/**
	 * Cached blobs.
	 *
	 * @var array
	 */
	protected $blobs = array();

	/**
	 * Cached trees.
	 *
	 * @var array
	 */
	protected $trees = array();

	/**
	 * Cached commits.
	 *
	 * @var array
	 */
	protected $commits = array();

	/**
	 * Clean out previous version of API data.
	 *
	 * This old data failed to save frequently, as it was too much data to
	 * hold in a single MySQL row. While the idea of maintaining this data
	 * permanently in the database seemed ideal, given that the response for
	 * a given sha of a given type will never change, it was too much information.
	 * Transients are much more appropriate for this type of data, and even if we lose it,
	 * it can still be refetched from the GitHub API.
	 *
	 * The structure of this object, including the name `open` for the singleton
	 * method, is a holdover from this original implementation, where we would save
	 * to the the database on the WordPress's shutdown hook with a `close` method.
	 * All of this no longer exists, but we should be good WordPress citizens
	 * and delete the data we left behind, since it was large and we're no longer
	 * using it.
	 */
	public function __construct() {
		// Clear out previously saved information.
		if ( get_option( '_wpghs_api_cache' ) ) {
			delete_option( '_wpghs_api_cache' );
		}
	}

	/**
	 * Fetch commit from cache by sha.
	 *
	 * @param string $sha Commit sha to fetch from cache.
	 *
	 * @return false|WordPress_GitHub_Sync_Commit
	 */
	public function fetch_commit( $sha ) {
		$commit = $this->get( 'commits', $sha );

		if ( $commit instanceof WordPress_GitHub_Sync_Commit ) {
			return $commit;
		}

		return false;
	}

	/**
	 * Save commit to cache by sha.
	 *
	 * @param string                       $sha Commit sha to cache by.
	 * @param WordPress_GitHub_Sync_Commit $commit Commit to cache.
	 *
	 * @return WordPress_GitHub_Sync_Commit
	 */
	public function set_commit( $sha, WordPress_GitHub_Sync_Commit $commit ) {
		return $this->save( 'commits', $sha, $commit, 0 );
	}

	/**
	 * Fetch tree from cache by sha.
	 *
	 * @param string $sha Tree sha to fetch from cache.
	 *
	 * @return false|WordPress_GitHub_Sync_Tree
	 */
	public function fetch_tree( $sha ) {
		$tree = $this->get( 'trees', $sha );

		if ( $tree instanceof WordPress_GitHub_Sync_Tree ) {
			return $tree;
		}

		return false;
	}


	/**
	 * Save tree to cache by sha.
	 *
	 * @param string                     $sha Tree sha to cache by.
	 * @param WordPress_GitHub_Sync_Tree $tree Tree to cache.
	 *
	 * @return WordPress_GitHub_Sync_Tree
	 */
	public function set_tree( $sha, WordPress_GitHub_Sync_Tree $tree ) {
		return $this->save( 'trees', $sha, $tree, DAY_IN_SECONDS * 3 );
	}

	/**
	 * Fetch tree from cache by sha.
	 *
	 * @param string $sha Blob sha to fetch from cache.
	 *
	 * @return false|WordPress_GitHub_Sync_Blob
	 */
	public function fetch_blob( $sha ) {
		$blob = $this->get( 'blobs', $sha );

		if ( $blob instanceof WordPress_GitHub_Sync_Blob ) {
			return $blob;
		}

		return false;
	}

	/**
	 * Save blob to cache by sha.
	 *
	 * @param string                     $sha Blob sha to cache by.
	 * @param WordPress_GitHub_Sync_Blob $blob Blob to cache.
	 *
	 * @return WordPress_GitHub_Sync_Blob
	 */
	public function set_blob( $sha, WordPress_GitHub_Sync_Blob $blob ) {
		return $this->save( 'blobs', $sha, $blob, 3 * DAY_IN_SECONDS );
	}

	/**
	 * Retrieve data from previous api calls by sha.
	 *
	 * @param string $type Object type to retrieve from cache.
	 * @param string $sha Object sha to retrieve from cache.
	 *
	 * @return stdClass|false response object if cached, false if not
	 */
	protected function get( $type, $sha ) {
		if ( isset( $this->{$type}[ $sha ] ) ) {
			return $this->{$type}[ $sha ];
		}

		if ( $data = get_transient( $this->cache_id( $type, $sha ) ) ) {
			return $this->{$type}[ $sha ] = $data;
		}

		return false;
	}

	/**
	 * Save data from api call by sha.
	 *
	 * @param string $type Object type.
	 * @param string $sha Object sha to cache by.
	 * @param object $data Object to cache.
	 * @param string $time Length of time to cache object for.
	 *
	 * @return mixed
	 */
	protected function save( $type, $sha, $data, $time ) {
		$this->{$type}[ $sha ] = $data;

		set_transient( $this->cache_id( $type, $sha ), $data, $time );

		return $data;
	}

	/**
	 * Generates the cache id for a given type & sha.
	 *
	 * @param string $type Object type.
	 * @param string $sha Object sha.
	 *
	 * @return string
	 */
	protected function cache_id( $type, $sha ) {
		return 'wpghs_' . md5( $type . '_' . $sha );
	}
}
