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
	 * Initializes a new import manager.
	 *
	 * @param WordPress_GitHub_Sync $app
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Imports a payload.
	 *
	 * @param WordPress_GitHub_Sync_Payload $payload
	 *
	 * @return string|WP_Error
	 */
	public function payload( WordPress_GitHub_Sync_Payload $payload ) {
		/** @var false|WP_Error $error */
		$error = false;

		$result = $this->commit( $this->app->api()->fetch()->commit( $payload->get_commit_id() ) );

		if ( is_wp_error( $result ) ) {
			$error = $result;
		}

		$removed = array();
		foreach ( $payload->get_commits() as $commit ) {
			$removed = array_merge( $removed, $commit->removed );
		}
		foreach ( array_unique( $removed ) as $path ) {
			$result = $this->app->database()->delete_post_by_path( $path );

			if ( is_wp_error( $result ) ) {
				if ( $error ) {
					$error->add( $result->get_error_code(), $result->get_error_message() );
				} else {
					$error = $result;
				}
			}
		}

		if ( $error ) {
			return $error;
		}

		return __( 'Payload processed', 'wordpress-github-sync' );
	}

	/**
	 * Imports the latest commit on the master branch.
	 *
	 * @return string|WP_Error
	 */
	public function master() {
		return $this->commit( $this->app->api()->fetch()->master() );
	}

	/**
	 * Imports a provided commit into the database.
	 *
	 * @param WordPress_GitHub_Sync_Commit|WP_Error $commit
	 *
	 * @return string|WP_Error
	 */
	protected function commit( $commit ) {
		if ( is_wp_error( $commit ) ) {
			return $commit;
		}

		if ( $commit->already_synced() ) {
			return new WP_Error( 'commit_synced', __( 'Already synced this commit.', 'wordpress-github-sync' ) );
		}

		$posts = array();
		$new   = array();

		foreach ( $commit->tree() as $blob ) {
			$posts[] = $post = $this->blob_to_post( $blob );

			if ( $post->is_new() ) {
				$new[] = $post;
			}
		}

		$result = $this->app->database()->save_posts( $posts, $commit->author_email() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $new ) {
			$result = $this->app->export()->posts(
				$new,
				apply_filters(
					'wpghs_commit_msg_new_posts',
					sprintf(
						'Updating new posts from WordPress at %s (%s)',
						site_url(),
						get_bloginfo( 'name' )
					)
				) . ' - wpghs'
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $posts;
	}

	/**
	 * Imports a single blob content into matching post.
	 *
	 * @param WordPress_GitHub_Sync_Blob $blob
	 *
	 * @return WordPress_GitHub_Sync_Post
	 */
	protected function blob_to_post( WordPress_GitHub_Sync_Blob $blob ) {
		$args = array( 'post_content' => $blob->content_import() );
		$meta = $blob->meta();

		if ( $meta ) {
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

		$meta['_sha'] = $blob->sha();

		$post = new WordPress_GitHub_Sync_Post( $args, $this->app->api() );
		$post->set_meta( $meta );

		return $post;
	}
}
