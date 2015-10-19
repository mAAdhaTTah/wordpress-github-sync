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
	 * Queries a post and returns it if it's supported.
	 *
	 * @param $post_id
	 * @return WP_Error|WordPress_GitHub_Sync_Post
	 */
	public function id( $post_id ) {
		$post = new WordPress_GitHub_Sync_Post( $post_id, $this->app->api() );

		if ( ! $this->is_post_supported( $post ) ) {
			return new WP_Error( 'unsupported_post', __( 'Post is not supported.', 'wordpress-github-sync' ) ); // @todo better message
		}

		return $post;
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

	/**
	 * Verifies that both the post's status & type
	 * are currently whitelisted
	 *
	 * @param  WordPress_GitHub_Sync_Post $post post to verify
	 *
	 * @return boolean                          true if supported, false if not
	 */
	protected function is_post_supported( WordPress_GitHub_Sync_Post $post ) {
		// @todo this logic can be simplified
		if ( wp_is_post_revision( $post->id ) || wp_is_post_autosave( $post->id ) ) {
			return false;
		}

		if ( ! in_array( $post->status(), $this->get_whitelisted_post_statuses() ) ) {
			return false;
		}

		if ( ! in_array( $post->type(), $this->get_whitelisted_post_types() ) ) {
			return false;
		}

		if ( $post->has_password() ) {
			return false;
		}

		return true;
	}
}
