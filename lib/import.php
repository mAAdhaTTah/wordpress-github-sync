<?php
/**
 * GitHub Import Manager
 *
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Import
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
	 * @param WordPress_GitHub_Sync $app Application container.
	 */
	public function __construct( WordPress_GitHub_Sync $app ) {
		$this->app = $app;
	}

	/**
	 * Imports a payload.
	 *
	 * @param WordPress_GitHub_Sync_Payload $payload GitHub payload object.
	 *
	 * @return string|WP_Error
	 */
	public function payload( WordPress_GitHub_Sync_Payload $payload ) {
		/**
		 * Whether there's an error during import.
		 *
		 * @var false|WP_Error $error
		 */
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

		return __( 'Payload processed', 'wp-github-sync' );
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
	 * @param WordPress_GitHub_Sync_Commit|WP_Error $commit Commit to import.
	 *
	 * @return string|WP_Error
	 */
	protected function commit( $commit ) {
		if ( is_wp_error( $commit ) ) {
			return $commit;
		}

		if ( $commit->already_synced() ) {
			return new WP_Error( 'commit_synced', __( 'Already synced this commit.', 'wp-github-sync' ) );
		}

		$posts = array();
		$new   = array();

		foreach ( $commit->tree()->blobs() as $blob ) {
			if ( ! $this->importable_blob( $blob ) ) {
				continue;
			}

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
			$result = $this->app->export()->new_posts( $new );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $posts;
	}

	/**
	 * Checks whether the provided blob should be imported.
	 *
	 * @param WordPress_GitHub_Sync_Blob $blob Blob to validate.
	 *
	 * @return bool
	 */
	protected function importable_blob( WordPress_GitHub_Sync_Blob $blob ) {
		global $wpdb;

		// Skip the repo's readme.
		if ( 'readme' === strtolower( substr( $blob->path(), 0, 6 ) ) ) {
			return false;
		}

		// If the blob sha already matches a post, then move on.
		if ( ! is_wp_error( $this->app->database()->fetch_by_sha( $blob->sha() ) ) ) {
			return false;
		}

		if ( ! $blob->has_frontmatter() ) {
			return false;
		}

		return true;
	}

	/**
	 * Imports a single blob content into matching post.
	 *
	 * @param WordPress_GitHub_Sync_Blob $blob Blob to transform into a Post.
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

			if ( array_key_exists( 'post_date', $meta ) ) {
				$args['post_date'] = $meta['post_date'];
				$args['post_date_gmt'] = get_gmt_from_date( $meta['post_date'] );
				unset( $meta['post_date'] );
			}
		}

		$meta['_sha'] = $blob->sha();

		$post = new WordPress_GitHub_Sync_Post( $args, $this->app->api() );
		$post->set_meta( $meta );

		return $post;
	}
}
