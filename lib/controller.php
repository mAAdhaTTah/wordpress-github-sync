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
		// @TODO: check if we moved this post from one directory to another, so that we need to update the post type.
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
			$meta = spyc_load( $matches[2] );
			if ( isset( $meta['permalink'] ) ) {
				$meta['permalink'] = str_replace( home_url(), '', get_permalink( $meta['permalink'] ) );
			}
		} else {
			$meta = array();
		}

		if ( function_exists( 'wpmarkdown_markdown_to_html' ) ) {
			$body = wpmarkdown_markdown_to_html( $body );
		}

		$args = array( 'post_content' => $body );

		if ( ! empty( $meta ) ) {
			$args['post_type'] = $meta['layout'];
			unset( $meta['layout'] );

			$args['post_status'] = true === $meta['published'] ? 'publish' : 'draft';
			unset( $meta['published'] );

			if ( array_key_exists( 'post_title', $meta ) ) {
				$args['post_title'] = isset( $meta['post_title'] ) ? sanitize_text_field( $meta['post_title'] ) : '';
				unset( $meta['post_title'] );
			}

			if ( isset( $meta['ID'] ) ) {
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
		$post_types = $this->format_for_query( $this->get_whitelisted_post_types() );

		$posts = $wpdb->get_col(
			"SELECT ID FROM $wpdb->posts WHERE
			post_status IN ( $post_statuses ) AND
			post_type IN ( $post_types )"
		);

		$this->msg = 'Full export from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ') - wpghs';

		$this->get_tree();

		WordPress_GitHub_Sync::write_log( __( 'Building the tree.', WordPress_GitHub_Sync::$text_domain ) );
		foreach ( $posts as $post_id ) {
			$this->posts[] = $post_id;
			$post = new WordPress_GitHub_Sync_Post( $post_id );
			$this->post_to_tree( $post );
		}

		$this->finalize();
	}

	/**
	 * Exports a single post to GitHub by ID
	 */
	public function export_post($post_id) {
		if ( $this->locked() ) {
			return;
		}

		$this->posts[] = $post_id;
		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$this->msg = 'Syncing ' . $post->github_path() . ' from WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ') - wpghs';

		$this->get_tree();

		WordPress_GitHub_Sync::write_log( __( 'Building the tree.', WordPress_GitHub_Sync::$text_domain ) );
		$this->post_to_tree( $post );

		$this->finalize();
	}

	/**
	 * Removes the post from the tree
	 */
	public function delete_post($post_id) {
		if ( $this->locked() ) {
			return;
		}

		$this->posts[] = $post_id;
		$post = new WordPress_GitHub_Sync_Post( $post_id );
		$this->msg = 'Deleting ' . $post->github_path() . ' via WordPress at ' . site_url() . ' (' . get_bloginfo( 'name' ) . ') - wpghs';

		$this->get_tree();

		WordPress_GitHub_Sync::write_log( __( 'Building the tree.', WordPress_GitHub_Sync::$text_domain ) );

		$this->post_to_tree( $post, true );

		$this->finalize();
	}

	/**
	 * Takes the next post off the top of the list
	 * and exports it to the new GitHub tree
	 */
	public function post_to_tree($post, $remove = false) {
		$match = false;

		if ( ! $this->is_post_supported( $post ) ) {
			return;
		}

		foreach ( $this->tree as $index => $blob ) {
			if ( ! isset( $blob->sha ) ) {
				continue;
			}

			if ( $blob->sha === $post->sha() ) {
				unset($this->tree[ $index ]);
				$match = true;

				if ( ! $remove ) {
					$this->tree[] = $this->new_blob( $post, $blob );
				} else {
					$this->changed = true;
				}

				break;
			}
		}

		if ( ! $match ) {
			$this->tree[] = $this->new_blob( $post );
			$this->changed = true;
		}
	}

	/**
	 * After all the blobs are saved,
	 * create the tree, commit, and adjust master ref
	 */
	public function finalize() {
		if ( ! $this->changed ) {
			$this->no_change();
			return;
		}

		WordPress_GitHub_Sync::write_log( __( 'Creating the tree.', WordPress_GitHub_Sync::$text_domain ) );
		$tree = $this->api->create_tree( array_values( $this->tree ) );

		if ( is_wp_error( $tree ) ) {
			$this->error( $tree );
			return;
		}

		WordPress_GitHub_Sync::write_log( __( 'Creating the commit.', WordPress_GitHub_Sync::$text_domain ) );
		$commit = $this->api->create_commit( $tree->sha, $this->msg );

		if ( is_wp_error( $commit ) ) {
			$this->error( $commit );
			return;
		}

		WordPress_GitHub_Sync::write_log( __( 'Setting the master branch to our new commit.', WordPress_GitHub_Sync::$text_domain ) );
		$ref = $this->api->set_ref( $commit->sha );

		if ( is_wp_error( $ref ) ) {
			$this->error( $ref );
			return;
		}

		$rtree = $this->api->last_tree_recursive();

		WordPress_GitHub_Sync::write_log( __( 'Saving the shas.', WordPress_GitHub_Sync::$text_domain ) );
		$this->save_post_shas( $rtree );

		$this->success();
	}

	/**
	 * Combines a post and (potentially) a blob
	 *
	 * If no blob is provided, turns post into blob
	 *
	 * If blob is provided, compares blob to post
	 * and updates blob data based on differences
	 */
	public function new_blob( $post, $blob = array() ) {
		if ( empty( $blob ) ) {
			$blob = $this->blob_from_post( $post );
		} else {
			unset($blob->url);
			unset($blob->size);

			if ( $blob->path !== $post->github_path() ) {
				$blob->path = $post->github_path();
				$this->changed = true;
			}

			$blob_data = $this->api->get_blob( $blob->sha );

			if ( base64_decode( $blob_data->content ) !== $post->github_content() ) {
				unset($blob->sha);
				$blob->content = $post->github_content();
				$this->changed = true;
			}
		}

		return $blob;
	}

	/**
	 * Creates a blob with the data required for the tree
	 */
	public function blob_from_post($post) {
		$blob = new stdClass;

		$blob->path = $post->github_path();
		$blob->mode = '100644';
		$blob->type = 'blob';
		$blob->content = $post->github_content();

		return $blob;
	}

	/**
	 * Use the new tree to save sha data
	 * for all the updated posts
	 */
	public function save_post_shas($tree) {
		foreach ( $this->posts as $post_id ) {
			$post = new WordPress_GitHub_Sync_Post( $post_id );
			$match = false;

			foreach ( $tree as $blob ) {
				// this might be a problem if the filename changed since it was set
				// (i.e. post updated in middle mass export)
				// solution?
				if ( $post->github_path() === $blob->path ) {
					$post->set_sha( $blob->sha );
					$match = true;
					break;
				}
			}

			if ( ! $match ) {
				WordPress_GitHub_Sync::write_log( __( 'No sha matched for post ID ', WordPress_GitHub_Sync::$text_domain ) . $post_id );
			}
		}
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
	 * Writes out the results of an unchanged export
	 */
	public function no_change() {
		update_option( '_wpghs_export_complete', 'yes' );
		WordPress_GitHub_Sync::write_log( __( 'There were no changes, so no additional commit was added.', WordPress_GitHub_Sync::$text_domain ), 'warning' );
	}

	/**
	 * Writes out the results of a successful export
	 */
	public function success() {
		update_option( '_wpghs_export_complete', 'yes' );
		update_option( '_wpghs_fully_exported', 'yes' );
		WordPress_GitHub_Sync::write_log( __( 'Export to GitHub completed successfully.', WordPress_GitHub_Sync::$text_domain ), 'success' );
	}

	/**
	 * Writes out the results of an error and saves the data
	 */
	public function error($result) {
		update_option( '_wpghs_export_error', $result->get_error_message() );
		WordPress_GitHub_Sync::write_log( __( 'Error exporting to GitHub. Error: ', WordPress_GitHub_Sync::$text_domain ) . $result->get_error_message(), 'error' );
	}

	/**
	 * Retrieve the saved tree we're building
	 * or get the latest tree from the repo
	 */
	public function get_tree() {
		if ( ! empty($this->tree) ) {
			return;
		}

		$tree = $this->api->last_tree_recursive();

		if ( is_wp_error( $tree ) ) {
			WordPress_GitHub_Sync::write_log( __( 'Failed getting tree with error: ', WordPress_GitHub_Sync::$text_domain ) . $tree->get_error_message() );
			return;
		}

		$this->tree = $tree;
	}

	/**
	 * Formats a whitelist array for a query
	 *
	 * @param  array $whitelist
	 * @return string            Whitelist formatted for query
	 */
	protected function format_for_query( $whitelist ) {
		return implode(', ', array_map( function( $v ) {
			return "'$v'";
		}, $whitelist ) );
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
	 * @param  WPGHS_Post  $post  post to verify
	 * @return boolean            true if supported, false if not
	 */
	protected function is_post_supported( $post ) {
		if ( ! in_array( $post->status(), $this->get_whitelisted_post_statuses() ) ) {
			return false;
		}

		if ( ! in_array( $post->type(), $this->get_whitelisted_post_types() ) ) {
			return false;
		}

		return true;
	}
}
