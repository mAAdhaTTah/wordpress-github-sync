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
		$directory = trim( $this->github_directory(), '/' );

		if ( 'post' === $this->type() ) {
			$pattern = sprintf( '/%s\/([0-9]{4})-([0-9]{2})-([0-9]{2})-(.*)\.md/', $directory );
		} else {
			$pattern = sprintf( '/%s\/(.*)\.md/', $directory );
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
	 * Returns the post type
	 */
	public function status() {
		return $this->post->post_status;
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
		} else if ( class_exists( 'WPCom_Markdown' ) ) {
			$wpcomMd = WPCom_Markdown::get_instance();
			if ( $wpcomMd->is_markdown( $this->post->ID ) ) {
				$content = $this->post->post_content_filtered;
			}
		}

		return $content;
	}

	/**
	 * Retrieves or calculates the proper GitHub path for a given post
	 *
	 * Returns (string) the path relative to repo root
	 */
	public function github_path() {
		$path = $this->github_directory() . $this->github_filename();

		update_post_meta( $this->id, '_wpghs_github_path', $path );

		return $path;
	}

	/**
	 * Get GitHub directory based on post
	 */
	public function github_directory() {
		if ( 'publish' !== $this->status() ) {
			return apply_filters( 'wpghs_directory_unpublished', '_drafts/', $this );
		}

		$obj = get_post_type_object( $this->type() );

		if ( ! $obj ) {
			return '';
		}

		$name = strtolower( $obj->labels->name );

		if ( ! $name ) {
			return '';
		}

		return apply_filters( 'wpghs_directory_published', '_' . strtolower( $name ) . '/', $this );
	}

	/**
	 * Build GitHub filename based on post
	 */
	public function github_filename() {
		if ( 'post' === $this->type() ) {
			$filename = get_the_time( 'Y-m-d-', $this->id ) . $this->name() . '.md';
		} else {
			$filename = $this->name() . '.md';
		}

		return apply_filters( 'wpghs_filename', $filename, $this );
	}

	/**
	* Retrieve post type directory from blob path
	* @param string $path
	* @return string
	*/
	public function get_directory_from_path( $path ) {
		$directory = explode( '/',$path );
		$directory = count( $directory ) > 0 ? $directory[0] : '';

		return $directory;
	}


	/**
	 * Determines the last author to modify the post
	 *
	 * Returns Array an array containing the author name and email
	 */
	public function last_modified_author() {
		if ( $last_id = get_post_meta( $this->id, '_edit_last', true ) ) {
			$user = get_userdata( $last_id );

			if ( $user ) {
				return array( 'name' => $user->display_name, 'email' => $user->user_email );
			}
		}

		return array();
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

			// @todo could we eliminate this by calling down the full tree and searching it
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
	public function set_sha( $sha ) {
		update_post_meta( $this->id, '_sha', $sha );
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
			'permalink'    => get_permalink( $this->post ),
			'published'    => 'publish' === $this->status() ? true : false,
		);

		//convert traditional post_meta values, hide hidden values
		foreach ( get_post_custom( $this->id ) as $key => $value ) {

			if ( '_' === substr( $key, 0, 1 ) ) {
				continue;
			}

			$meta[ $key ] = $value;

		}

		return apply_filters( 'wpghs_post_meta', $meta, $this );
	}
}
