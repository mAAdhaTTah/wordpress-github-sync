<?php

class WordPress_GitHub_Sync_Database {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Currently whitelisted post types.
	 *
	 * @var array
	 */
	protected $whitelisted_post_types = array( 'post', 'page' );

	/**
	 * Currently whitelisted post statuses.
	 *
	 * @var array
	 */
	protected $whitelisted_post_statuses = array( 'publish' );

	/**
	 * Instantiates a new Database object.
	 *
	 * @param WordPress_GitHub_Sync $app Application container.
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Queries the database for all of the supported posts.
	 *
	 * @return WordPress_GitHub_Sync_Post[]|WP_Error
	 */
	public function all_supported() {
		global $wpdb;

		$post_statuses = $this->format_for_query( $this->get_whitelisted_post_statuses() );
		$post_types    = $this->format_for_query( $this->get_whitelisted_post_types() );

		$post_ids = $wpdb->get_col(
			"SELECT ID FROM $wpdb->posts WHERE
			post_status IN ( $post_statuses ) AND
			post_type IN ( $post_types )"
		);

		if ( ! $post_ids ) {
			return new WP_Error(
				'no_results',
				__( 'Querying for supported posts returned no results.', 'wordpress-github-sync' )
			);
		}

		$results = array();
		foreach ( $post_ids as $post_id ) {
			$results[] = new WordPress_GitHub_Sync_Post( $post_id, $this->app->api() );
		}

		return $results;
	}

	/**
	 * Returns the list of post type permitted.
	 *
	 * @return array
	 */
	protected function get_whitelisted_post_types() {
		return apply_filters( 'wpghs_whitelisted_post_types', $this->whitelisted_post_types );
	}

	/**
	 * Returns the list of post status permitted.
	 *
	 * @return array
	 */
	protected function get_whitelisted_post_statuses() {
		return apply_filters( 'wpghs_whitelisted_post_statuses', $this->whitelisted_post_statuses );
	}

	/**
	 * Formats a whitelist array for a query
	 *
	 * @param  array $whitelist
	 *
	 * @return string Whitelist formatted for query
	 */
	protected function format_for_query( $whitelist ) {
		foreach ( $whitelist as $key => $value ) {
			$whitelist[ $key ] = "'$value'";
		}

		return implode( ', ', $whitelist );
	}
}
