<?php
/**
 * Controller object manages tree retrieval, manipulation and publishing
 */
class WordPress_GitHub_Sync_Controller {

	/**
	 * Api object
	 * @var WordPress_GitHub_Sync_Api
	 */
	public $api;

	/**
	 * Currently whitelisted post types & statuses
	 * @var  array
	 */
	protected $whitelisted_post_types = array( 'post', 'page' );
	protected $whitelisted_post_statuses = array( 'publish' );

	/**
	 * Whether any posts have changed
	 * @var boolean
	 */
	public $changed = false;

	/**
	 * Array of posts to export
	 * @var array
	 */
	public $posts = array();

	/**
	 * Array representing new tre
	 * @var array
	 */
	public $tree = array();

	/**
	 * Commit message
	 * @var string
	 */
	public $msg = '';

	/**
	 * Instantiates a new Controller object
	 *
	 * $posts - array of post IDs to export
	 */
	public 	function __construct() {
		$this->api = new WordPress_GitHub_Sync_Api;
	}

	/**
	 * Reads the Webhook payload and syncs posts as necessary
	 */
	public function pull($payload) {
		if ( strtolower( $payload->repository->full_name ) !== strtolower( $this->api->repository() ) ) {
			$msg = strtolower( $payload->repository->full_name ) . __( ' is an invalid repository.', WordPress_GitHub_Sync::$text_domain );
			WordPress_GitHub_Sync::write_log( $msg );
			return array(
				'result'  => 'error',
				'message' => $msg,
			);
		}

		// the last term in the ref is the branch name
		$refs = explode( '/', $payload->ref );
		$branch = array_pop( $refs );

		if ( 'master' !== $branch ) {
			$msg = __( 'Not on the master branch.', WordPress_GitHub_Sync::$text_domain );
			WordPress_GitHub_Sync::write_log( $msg );
			return array(
				'result'  => 'error',
				'message' => $msg,
			);
		}

		// We add wpghs to commits we push out, so we shouldn't pull them in again
		if ( 'wpghs' === substr( $payload->head_commit->message, -5 ) ) {
			$msg = __( 'Already synced this commit.', WordPress_GitHub_Sync::$text_domain );
			WordPress_GitHub_Sync::write_log( $msg );
			return array(
				'result'  => 'error',
				'message' => $msg,
			);
		}

		$commit = $this->api->get_commit( $payload->head_commit->id );

		if ( is_wp_error( $commit ) ) {
			$msg = __( 'Failed getting commit with error: ', WordPress_GitHub_Sync::$text_domain ) . $commit->get_error_message();
			WordPress_GitHub_Sync::write_log( $msg );
			return array(
				'result'  => 'error',
				'message' => $msg,
			);
		}

		$this->import_tree( $commit->tree->sha );

		// Deleting posts from a payload is the only place
		// we need to search posts by path; another way?
		$removed = array();
		foreach ( $payload->commits as $commit ) {
			$removed  = array_merge( $removed,  $commit->removed );
		}
		foreach ( array_unique( $removed ) as $path ) {
			$post = new WordPress_GitHub_Sync_Post( $path );
			wp_delete_post( $post->id );
		}

		$msg = __( 'Payload processed', WordPress_GitHub_Sync::$text_domain );
		WordPress_GitHub_Sync::write_log( $msg );

		return array(
			'result'  => 'success',
			'message' => $msg,
		);
	}

	/**
	 * Imports posts from the current master branch
	 */
	public function import_master() {
		$commit = $this->api->last_commit();

		if ( is_wp_error( $commit ) ) {
			WordPress_GitHub_Sync::write_log( __( 'Failed getting last commit with error: ', WordPress_GitHub_Sync::$text_domain ) . $commit->get_error_message() );
			return;
		}

		if ( 'wpghs' === substr( $commit->message, -5 ) ) {
			WordPress_GitHub_Sync::write_log( __( 'Already synced this commit.', WordPress_GitHub_Sync::$text_domain ) );
			return;
		}

		$this->import_tree( $commit->tree->sha );
	}

	/**
	 * Imports posts from a given tree sha
	 */
	public function import_tree($sha) {
		$tree = $this->api->get_tree_recursive( $sha );

		if ( is_wp_error( $tree ) ) {
			WordPress_GitHub_Sync::write_log( __( 'Failed getting recursive tree with error: ', WordPress_GitHub_Sync::$text_domain ) . $tree->get_error_message() );
			return;
		}

		foreach ( $tree as $blob ) {
			$this->import_blob( $blob );
		}

		WordPress_GitHub_Sync::write_log( __( 'Imported tree ', WordPress_GitHub_Sync::$text_domain ) . $sha );
	}

	/**
	 * Imports a single blob content into matching post
	 */
	public function import_blob($blob) {
		global $wpdb;

		// Skip the repo's readme
		if ( 'readme' === strtolower( substr( $blob->path, 0, 6 ) ) ) {
			WordPress_GitHub_Sync::write_log( __( 'Skipping README', WordPress_GitHub_Sync::$text_domain ) );
			return;
		}

		// If the blob sha already matches a post, then move on
		$id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_sha' AND meta_value = '$blob->sha'" );
		if ( $id ) {
			WordPress_GitHub_Sync::write_log( __( 'Already synced blob ', WordPress_GitHub_Sync::$text_domain ) . $blob->path );
			return;
		}

		$blob = $this->api->get_blob( $blob->sha );

		if ( is_wp_error( $blob ) ) {
			WordPress_GitHub_Sync::write_log( __( 'Failed getting blob with error: ', WordPress_GitHub_Sync::$text_domain ) . $blob->get_error_message() );
			return;
		}

		$content = base64_decode( $blob->content );

		// If it doesn't have YAML frontmatter, then move on
		if ( '---' !== substr( $content, 0, 3 ) ) {
			WordPress_GitHub_Sync::write_log( __( 'No front matter on blob ', WordPress_GitHub_Sync::$text_domain ) . $blob->sha );
			return;
		}

		// Break out meta, if present
		preg_match( '/(^---(.*?)---$)?(.*)/ms', $content, $matches );

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

		if ( ! isset($args['ID']) ) {
			// @todo create a revision when we add revision author support
			$post_id = wp_insert_post( $args );
		} else {
			$post_id = wp_update_post( $args );
		}

		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$post->set_sha( $blob->sha );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		WordPress_GitHub_Sync::write_log( __( 'Updated blob ', WordPress_GitHub_Sync::$text_domain ) . $blob->sha );
	}

	/**
	 * Export all the posts in the database to GitHub
	 */
	public function export_all() {
		global $wpdb;

		if ( $this->locked() ) {
			return;
		}

		$post_statuses = $this->format_for_query( $this->get_whitelisted_post_statuses() );
		$post_types    = $this->format_for_query( $this->get_whitelisted_post_types() );

		$post_ids = $wpdb->get_col(
			"SELECT ID FROM $wpdb->posts WHERE
			post_status IN ( $post_statuses ) AND
			post_type IN ( $post_types )"
		);

		$msg = apply_filters( 'wpghs_commit_msg_full',
				'Full export from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')' ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_ids, $msg );
		$export->run();
	}

	/**
	 * Exports a single post to GitHub by ID
	 */
	public function export_post( $post_id ) {
		if ( $this->locked() ) {
			return;
		}

		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$msg  = apply_filters( 'wpghs_commit_msg_single',
				'Syncing ' . $post->github_path() . ' from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')',
				$post ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_id, $msg );
		$export->run();
	}

	/**
	 * Removes the post from the tree
	 */
	public function delete_post( $post_id ) {
		if ( $this->locked() ) {
			return;
		}

		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$msg  = apply_filters( 'wpghs_commit_msg_delete',
				'Deleting ' . $post->github_path() . ' via WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ')',
				$post ) . ' - wpghs';

		$export = new WordPress_GitHub_Sync_Export( $post_id, $msg );
		$export->run( true );
	}

	/**
	 * Check if we're clear to call the api
	 */
	public function locked() {
		global $wpghs;

		if ( ! $this->api->oauth_token() || ! $this->api->repository() || $wpghs->push_lock ) {
			return true;
		}

		return false;
	}

	/**
	 * Formats a whitelist array for a query
	 *
	 * @param  array $whitelist
	 * @return string            Whitelist formatted for query
	 */
	protected function format_for_query( $whitelist ) {
		foreach( $whitelist as $key => $value ) {
			$whitelist[ $key ] = "'$value'";
		}
		return implode( ', ', $whitelist );
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
	 * Verifies that both the post's status & type
	 * are currently whitelisted
	 *
	 * @param  WordPress_GitHub_Sync_Post  $post  post to verify
	 * @return boolean                            true if supported, false if not
	 */
	protected function is_post_supported( $post ) {
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
