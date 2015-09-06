<?php

/**
 * GitHub Import Manager
 */
class WordPress_GitHub_Sync_Import {

	/**
	 * Initializes a new import manager.
	 */
	public function __construct() {
		$this->tree = new WordPress_GitHub_Sync_Tree();
	}

	/**
	 * Runs the import process for a provided sha.
	 *
	 * @param string $sha
	 */
	public function run( $sha ) {
		$this->tree->fetch_sha( $sha );

		if ( ! $this->tree->ready() ) {
			WordPress_GitHub_Sync::write_log( __( 'Failed getting recursive tree with error: ',
					WordPress_GitHub_Sync::$text_domain ) . $this->tree->last_error() );

			return;
		}

		foreach ( $this->tree as $blob ) {
			$this->import_blob( $blob );
		}

		WordPress_GitHub_Sync::write_log( __( 'Imported tree ', WordPress_GitHub_Sync::$text_domain ) . $sha );
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

		if ( ! isset( $args['ID'] ) ) {
			// @todo create a revision when we add revision author support
			$post_id = wp_insert_post( $args );
		} else {
			$post_id = wp_update_post( $args );
		}

		/** @var WordPress_GitHub_Sync_Post $post */
		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$post->set_sha( $blob->sha );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		WordPress_GitHub_Sync::write_log( __( 'Updated blob ', WordPress_GitHub_Sync::$text_domain ) . $blob->sha );
	}

}