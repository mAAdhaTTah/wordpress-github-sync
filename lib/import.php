<?php

/**
 * GitHub Import Manager
 */
class WordPress_GitHub_Sync_Import {

	/**
	 * Application container.
	 *
	 * @var WordPress_GitHub_Sync
	 */
	protected $app;

	/**
	 * Tree object to import.
	 *
	 * @var WordPress_GitHub_Sync_Tree
	 */
	protected $tree;

	/**
	 * Post IDs for posts imported from GitHub.
	 *
	 * @var int[]
	 */
	protected $new_posts = array();

	/**
	 * Posts that needs their revision author set.
	 *
	 * @var int[]
	 */
	protected $updated_posts;

	/**
	 * Initializes a new import manager.
	 *
	 * @param WordPress_GitHub_Sync $app
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app  = $app;
		$this->tree = new WordPress_GitHub_Sync_Tree();
	}

	/**
	 * Returns the IDs of newly added posts.
	 *
	 * @return int[]
	 */
	public function new_posts() {
		return $this->new_posts;
	}

	/**
	 * Returns the newly added posts.
	 *
	 * @return int[]
	 */
	public function updated_posts() {
		return $this->updated_posts;
	}

	/**
	 * Imports a payload
	 *
	 * @param WordPress_GitHub_Sync_Payload $payload
	 *
	 * @return true|WP_Error
	 */
	public function payload( WordPress_GitHub_Sync_Payload $payload ) {
		$commit = $this->app->api()->get_commit( $payload->get_commit_id() );

		if ( is_wp_error( $commit ) ) {
			return new WP_Error(
				'api_error',
				sprintf(
					__( 'Failed getting commit with error: %s', 'wordpress-github-sync' ),
					$commit->get_error_message()
				)
			);
		}

		$this->import_sha( $commit->tree->sha );

		$user = get_user_by( 'email', $payload->get_author_email() );

		if ( ! $user ) {
			// use the default user
			$user = get_user_by( 'id', get_option( 'wpghs_default_user' ) );
		}

		// if we can't find a user and a default hasn't been set,
		// we're just going to set the revision author to 0
		update_option( '_wpghs_export_user_id', $user ? $user->ID : 0 );

		global $wpdb;

		if ( $updated_posts = $this->app->import()->updated_posts() ) {
			foreach ( $updated_posts as $post_id ) {
				$revision = wp_get_post_revision( $post_id );

				if ( ! $revision ) {
					$revision = wp_save_post_revision( $post_id );

					if ( ! $revision || is_wp_error( $revision ) ) {
						// there was a problem saving a new revision
						continue;
					}

					// wp_save_post_revision returns the ID, whereas get_post_revision returns the whole object
					// in order to be consistent, let's make sure we have the whole object before continuing
					$revision = get_post( $revision );
				}

				$wpdb->update(
					$wpdb->posts,
					array(
						'post_author' => (int) get_option( '_wpghs_export_user_id' ),
					),
					array(
						'ID' => $revision->ID,
					),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		// Deleting posts from a payload is the only place
		// we need to search posts by path; another way?
		$removed = array();
		foreach ( $payload->get_commits() as $commit ) {
			$removed = array_merge( $removed, $commit->removed );
		}
		foreach ( array_unique( $removed ) as $path ) {
			$post = new WordPress_GitHub_Sync_Post( $path, $this->app->api() );
			wp_delete_post( $post->id );
		}

		if ( $new_posts = $this->app->import()->new_posts() ) {
			// disable the lock to allow exporting
			// @todo move to semaphore
			// $this->push_lock = false;

			WordPress_GitHub_Sync::write_log(
				sprintf(
					__( 'Updating new posts with IDs: %s', 'wordpress-github-sync' ),
					implode( ', ', $new_posts )
				)
			);

			foreach ( $new_posts as $post_id ) {
				$wpdb->update(
					$wpdb->posts,
					array(
						'post_author' => (int) get_option( '_wpghs_export_user_id' ),
					),
					array(
						'ID' => $post_id,
					),
					array( '%d' ),
					array( '%d' )
				);
			}

			$msg = apply_filters( 'wpghs_commit_msg_new_posts', 'Updating new posts from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')' ) . ' - wpghs';

			$export = new WordPress_GitHub_Sync_Export( $new_posts, $msg );
			$export->run();
		}

		$msg = __( 'Payload processed', 'wordpress-github-sync' );
		WordPress_GitHub_Sync::write_log( $msg );

		return $msg;
	}

	/**
	 * Runs the import process for a provided sha.
	 *
	 * @param string $sha
	 */
	public function import_sha( $sha ) {
		$this->tree->fetch_sha( $sha );

		if ( ! $this->tree->is_ready() ) {
			WordPress_GitHub_Sync::write_log(
				sprintf(
					__( 'Failed getting recursive tree with error: %s', 'wordpress-github-sync' ),
					$this->tree->last_error()
				)
			);

			return;
		}

		foreach ( $this->tree as $blob ) {
			$this->import_blob( $blob );
		}

		WordPress_GitHub_Sync::write_log(
			sprintf(
				__( 'Imported tree %s', 'wordpress-github-sync' ),
				$sha
			)
		);
	}

	/**
	 * Imports a single blob content into matching post.
	 *
	 * @param stdClass $blob
	 */
	protected function import_blob( $blob ) {
		// Break out meta, if present
		preg_match( '/(^---(.*?)---$)?(.*)/ms', $blob->content, $matches );

		$body = array_pop( $matches );

		if ( 3 === count( $matches ) ) {
			$meta = cyps_load( $matches[2] );
			if ( isset( $meta['permalink'] ) ) {
				$meta['permalink'] = str_replace( home_url(), '', get_permalink( $meta['permalink'] ) );
			}
		} else {
			$meta = array();
		}

		if ( function_exists( 'wpmarkdown_markdown_to_html' ) ) {
			$body = wpmarkdown_markdown_to_html( $body );
		}

		$args = array( 'post_content' => apply_filters( 'wpghs_content_import', $body ) );

		if ( ! empty( $meta ) ) {
			if ( array_key_exists( 'layout', $meta ) ) {
				$args['post_type'] = $meta['layout'];
				unset( $meta['layout'] );
			}

			if ( array_key_exists( 'published', $meta ) ) {
				$args['post_status'] = true === $meta['published'] ? 'publish' : 'draft';
				unset( $meta['published'] );
			}

			if ( array_key_exists( 'post_title', $meta ) ) {
				$args['post_title'] = $meta['post_title'];
				unset( $meta['post_title'] );
			}

			if ( array_key_exists( 'ID', $meta ) ) {
				$args['ID'] = $meta['ID'];
				unset( $meta['ID'] );
			}
		}

		$post_id = ! isset( $args['ID'] ) ? wp_insert_post( $args ) : wp_update_post( $args );

		/** @var WordPress_GitHub_Sync_Post $post */
		$post = new WordPress_GitHub_Sync_Post( $post_id, $this->app->api() );
		$post->set_sha( $blob->sha );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		WordPress_GitHub_Sync::write_log(
			sprintf(
				__( 'Updated blob %s', 'wordpress-github-sync' ),
				$blob->sha
			)
		);

		$this->updated_posts[] = $post_id;

		if ( ! isset( $args['ID'] ) ) {
			$this->new_posts[] = $post_id;
		}
	}
}
