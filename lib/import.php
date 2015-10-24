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
		$commit = $this->app->api()->get_commit( $payload->get_commit_id() );

		if ( is_wp_error( $commit ) ) {
			return $commit;
		}

		$this->commit( $commit );

		$removed = array();
		foreach ( $payload->get_commits() as $commit ) {
			$removed = array_merge( $removed, $commit->removed );
		}
		foreach ( array_unique( $removed ) as $path ) {
			$this->app->database()->delete_post_path( $path );
		}

		return __( 'Payload processed', 'wordpress-github-sync' );
	}

	/**
	 * Imports a provided commit into the database.
	 *
	 * @param WordPress_GitHub_Sync_Commit $commit
	 *
	 * @return string|WP_Error
	 */
	public function commit( WordPress_GitHub_Sync_Commit $commit ) {
		$tree = $this->app->api()->get_tree_recursive( $commit->tree_sha() );

		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$posts = array();

		foreach ( $tree as $blob ) {
			$posts[] = $this->blob_to_post( $blob );
		}

		// Filter now, because we can't tell what's new after we've saved.
		$new = $this->filter_new( $posts );

		$this->app->database()->save_posts( $posts, $commit->author_email() );

		if ( $new ) {
			$this->app->export()->posts(
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

	/**
	 * Filters an array of WPGHS_Posts to return only the new posts.
	 *
	 * @param WordPress_GitHub_Sync_Post[] $posts
	 *
	 * @return WordPress_GitHub_Sync_Post[]
	 */
	protected function filter_new( $posts ) {
		$new = array();

		foreach ( $posts as $post ) {
			if ( $post->is_new() ) {
				$new[] = $post;
			}
		}

		return $new;
	}
}
