<?php
/**
 * The cache object which reads and writes the GitHub api data
 */
class WordPress_GitHub_Sync_Cache {

	/**
	 * Endpoints to cache
	 */
	protected $blobs = array();
	protected $trees = array();
	protected $commits = array();

	/**
	 * Object instance
	 * @var self
	 */
	public static $instance;

	/**
	 * Retrieves the api cache data and loads it into the object
	 */
	public function __construct() {
		$cache = get_option( '_wpghs_api_cache', array() );

		if ( ! empty( $cache ) ) {
			$this->blobs = $cache['blobs'];
			$this->trees = $cache['trees'];
			$this->commits = $cache['commits'];
		}

		add_action( 'shutdown', array( $this, 'close' ) );
	}

	/**
	 * Retrieve data from previous api calls by sha
	 */
	public function get( $type, $sha ) {
		return isset( $this->{$type}[ $sha ] ) ? $this->{$type}[ $sha ] : false;
	}

	/**
	 * Save data from api call by sha
	 */
	public function save( $type, $sha, $data ) {
		$this->{$type}[ $sha ] = $data;

		return $data;
	}

	/**
	 * Saves the cache data right before the object is destroyed
	 */
	public function close() {
		$cache = array(
			'blobs' => $this->blobs,
			'trees' => $this->trees,
			'commits' => $this->commits,
		);

		update_option( '_wpghs_api_cache', $cache );
	}

	/**
	 * Initializes or retrieves the cache object
	 * @return Cache object
	 */
	public static function open() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
}
