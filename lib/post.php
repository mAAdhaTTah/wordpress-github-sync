<?php
/**
 * The post object which represents both the GitHub and WordPress post
 */
class WordPress_GitHub_Sync_Post {

	/**
	 * Api object
	 * @var WordPress_GitHub_Sync_Api
	 */
	public $api;

	/**
	 * Post ID
	 * @var integer
	 */
	public $id = 0;

	/**
	 * Path to the file
	 * @var string
	 */
	public $path;

	/**
	 * Post object
	 * @var WP_Post
	 */
	public $post;

	/**
	 * Blacklisted Post Types
	 * @var Array
	 */
	public $blacklisted_post_types = array('attachment', 'revision', 'nav_menu_item');

	/**
	 * Instantiates a new Post object
	 *
	 * $id_or_path - (int|string) either a postID (WordPress) or a path to a file (GitHub)
	 *
	 * Returns the Post object, duh
	 */
	public function __construct( $id_or_path ) {
		$this->api = new WordPress_GitHub_Sync_Api;

		if ( is_numeric( $id_or_path ) ) {
			$this->id = $id_or_path;
		} else {
			$this->path = $id_or_path;
			$this->id = $this->id_from_path();
		}

		$this->post = get_post( $this->id );
	}

	/**
	 * Parse the various parts of a filename from a path
	 *
	 * @todo - CUSTOM FORMAT SUPPORT
	 */
	public function parts_from_path() {
		$directory = trim($this->github_directory(), '/');

		if ( 'post' === $this->type() ) {
			$pattern = sprintf('/%s\/([0-9]{4})-([0-9]{2})-([0-9]{2})-(.*)\.md/', $directory);
		} else {
			$pattern = sprintf('/%s\/(.*)\.md/', $directory);
		}

		preg_match( $pattern, $this->path, $matches );
		return $matches;
	}

	/**
	 * Extract's the post's title from its path
	 */
	public function title_from_path() {
		$matches = $this->parts_from_path();
		if ( 'post' === $this->type() ) {
			return $matches[4];
		}

		return $matches[1];
	}

	/**
	 * Extract's the post's date from its path
	 */
	public function date_from_path() {
		$matches = $this->parts_from_path();
		return $matches[1] . '-' . $matches[2] . '-' . $matches[3] . '00:00:00';
	}

	/**
	 * Determines the post's WordPress ID from its GitHub path
	 * Creates the WordPress post if it does not exist
	 */
	public function id_from_path() {
		global $wpdb;

		$id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wpghs_github_path' AND meta_value = '$this->path'" );

		if ( ! $id ) {
			$title = $this->title_from_path();
			$id = $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_name = '$title'" );
		}

		if ( ! $id ) {
			$id = wp_insert_post( array(
					'post_name' => $this->title_from_path(),
					'post_date' => $this->date_from_path()
				)
			);
		}

		return $id;
	}

	/**
	 * Returns the post type
	 */
	public function type() {
		return $this->post->post_type;
	}

	/**
	 * Is the post type blacklisted?
	 *
	 * If the $page parameter is specified, this function will additionally
	 * check if the query is for one of the pages specified.
	 *
	 * @param string $post_type
	 * @return bool
	 */
	public function is_post_type_blacklisted($post_type = '') {
		if(empty($post_type)) {
			$post_type = $this->type();
		}
		return (bool) in_array($post_type, $this->blacklisted_post_types);
	}

	public function get_blacklisted_values() {
		if (!array($this->blacklisted_post_types)) return '';

		return implode(', ', array_map(function($v) { return "'$v'"; }, $this->blacklisted_post_types ));
	}

	/**
	 * Returns the post name
	 */
	public function name() {
		return $this->post->post_name;
	}

	/**
	 * Combines the 2 content parts for GitHub
	 */
	public function github_content() {
		return $this->front_matter() . $this->post_content();
	}

	/**
	 * The post's YAML frontmatter
	 *
	 * Returns String the YAML frontmatter, ready to be written to the file
	 */
	public function front_matter() {
		return Spyc::YAMLDump( $this->meta(), false, false, true ) . "---\n";
	}

	/**
	 * Returns the post_content
	 *
	 * Markdownify's the content if applicable
	 */
	public function post_content() {
		$content = $this->post->post_content;

		if ( function_exists( 'wpmarkdown_html_to_markdown' ) ) {
			$content = wpmarkdown_html_to_markdown( $content );
		}

		return apply_filters( 'wpghs_content', $content );
	}

	/**
	 * Retrieves or calculates the proper GitHub path for a given post
	 *
	 * Returns (string) the path relative to repo root
	 */
	public function github_path() {
		return $this->github_directory() . $this->github_filename();
	}

	/**
	 * Get GitHub directory based on post
	 */
	public function github_directory() {
		$directory = '';

		if (!$this->is_post_type_blacklisted()) {
			$obj = get_post_type_object($this->type());
			$name = strtolower($obj->labels->name);
			if($name) {
				$directory = '_' . strtolower($obj->labels->name) . '/';
			}
		}

		return $directory;
	}

	/**
	 * Build GitHub filename based on post
	 */
	public function github_filename() {
		$filename = '';

		if ( 'post' === $this->type() ) {
			$filename = get_the_time( 'Y-m-d-', $this->id ) . $this->name() . '.md';
		} elseif (!$this->is_post_type_blacklisted()) {
			$filename = $this->name() . '.md';
		}

		return $filename;
	}

	/**
	* Retrieve post type directory from blob path
	* @param string $path
	* @return string
	*/
	 public function get_directory_from_path($path) {
		$directory = explode('/',$path);
		$directory = count($directory) > 0 ? $directory[0] : '';

		return $directory;
	}

	/**
	 * Retrieve post type from blob path
	 * @param string $path
	 * @return string
	 */
	public function get_type_from_path($path) {
		global $wp_post_types;

		$directory = $this->get_directory_from_path($path);
		if ($directory) {
			// remove the underscore
			$directory = substr($directory, 1);

			foreach ($wp_post_types as $key => $pt) {
				if ( strtolower($pt->labels->name) === $directory ) {
					return $key;
				}
			}
		}

			// default post type
		return 'post';
	}

	/**
	 * Retrieve post name from blob path
	 * @param string $path
	 * @return string
	 */
	public function get_name_from_path($path) {
		$post_type = $this->get_type_from_path($path);
		$directory = $this->get_directory_from_path($path);

		if ( 'post' === $post_type ) {
			$pattern = sprintf('/%s\/([0-9]{4})-([0-9]{2})-([0-9]{2})-(.*)\.md/', $directory);
			$match_index = 4;
		} else {
			$pattern = sprintf('/%s\/(.*)\.md/', $directory);
			$match_index = 1;
		}

		preg_match( $pattern, $path, $matches );
		return $matches[$match_index];
	}


	/**
	 * Determines the last author to modify the post
	 *
	 * Returns Array an array containing the author name and email
	 */
	public function last_modified_author() {
		if ( $last_id = get_post_meta( $this->id, '_edit_last', true ) ) {
			$user = get_userdata( $last_id );
			if ( ! $user ) {
				return array();
			}
			return array( 'name' => $user->display_name, 'email' => $user->user_email );
		} else {
			return array();
		}
	}

	/**
	 * The post's sha
	 * Cached as post meta, or will make a live call if need be
	 *
	 * Returns String the sha1 hash
	 */
	public function sha() {
		$sha = get_post_meta( $this->id, '_sha', true );

		// If we've done a full export and we have no sha
		// then we should try a live check to see if it exists
		if ( ! $sha && 'yes' === get_option( '_wpghs_fully_exported' ) ) {
			$data = $this->api->remote_contents( $this );

			if ( ! is_wp_error( $data ) ) {
				update_post_meta( $this->id, '_sha', $data->sha );
				$sha = $data->sha;
			}
		}

		// if the sha still doesn't exist, then it's empty
		if ( ! $sha ) {
			$sha = '';
		}

		return $sha;
	}

	/**
	 * Save the sha to post
	 */
	public function set_sha($sha, $post_id = 0) {
		if ( 0 === $post_id ) {
			$post_id = $this->id;
		}

		update_post_meta( $post_id, '_sha', $sha );
	}

	/**
	 * The post's metadata
	 *
	 * Returns Array the post's metadata
	 */
	public function meta() {

		$meta = array(
			'ID'           => $this->post->ID,
			'post_title'   => get_the_title( $this->post ),
			'author'       => get_userdata( $this->post->post_author )->display_name,
			'post_date'    => $this->post->post_date,
			'post_excerpt' => $this->post->post_excerpt,
			'layout'       => get_post_type( $this->post ),
			'permalink'    => get_permalink( $this->post )
		);

		//convert traditional post_meta values, hide hidden values
		foreach ( get_post_custom( $this->id ) as $key => $value ) {

			if ( '_' === substr( $key, 0, 1 ) ) {
				continue;
			}

			$meta[ $key ] = $value;

		}

		return $meta;

	}
}
