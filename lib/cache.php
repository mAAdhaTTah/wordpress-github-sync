<?php

/**
 * The cache object which reads and writes the GitHub api data
 */
class WordPress_GitHub_Sync_Cache {

	/**
	 * Endpoint types to cache.
	 */
	protected $blobs = array();
	protected $trees = array();
	protected $commits = array();

	/**
	 * Object instance.
	 * @var self
	 */
	protected static $instance;

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
	protected function __construct() {
		// clear out previously saved information
		if ( get_option( '_wpghs_api_cache' ) ) {
			delete_option( '_wpghs_api_cache' );
		}
	}

	/**
	 * Retrieve data from previous api calls by sha.
	 *
	 * @param string $type
	 * @param string $sha
	 *
	 * @return stdClass|false response object if cached, false if not
	 */
	public function get( $type, $sha ) {
		if ( isset( $this->{$type}[ $sha ] ) ) {
			return $this->{$type}[ $sha ];
		}

		if ( $data = get_transient( $this->cache_id( $type, $sha ) ) ) {
			$this->{$type}[ $sha ] = $data;

			return $data;
		}

		return false;
	}

	/**
	 * Save data from api call by sha.
	 *
	 * @param string $type
	 * @param string $sha
	 * @param mixed $data
	 *
	 * @return mixed
	 */
	public function save( $type, $sha, $data ) {
		$this->{$type}[ $sha ] = $data;

		set_transient( $this->cache_id( $type, $sha ), $data );

		return $data;
	}

	/**
	 * Generates the cache id for a given type & sha.
	 *
	 * @param string $type
	 * @param string $sha
	 *
	 * @return string
	 */
	protected function cache_id( $type, $sha ) {
		return '_wpghs_' . $type . '_' . $sha;
	}

	/**
	 * Initializes or retrieves the cache object.
	 *
	 * @return self
	 */
	public static function open() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
}
