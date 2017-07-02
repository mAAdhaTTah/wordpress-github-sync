<?php
/**
 * The post object which represents both the GitHub and WordPress post
 * @package WordPress_GitHub_Sync
 */

/**
 * Class WordPress_GitHub_Sync_Post
 */
class WordPress_GitHub_Sync_Post {

	/**
	 * Api object
	 *
	 * @var WordPress_GitHub_Sync_Api
	 */
	public $api;

	/**
	 * Post ID
	 * @var integer
	 */
	public $id = 0;

	/**
	 * Post object
	 * @var WP_Post
	 */
	public $post;

	/**
	 * Post args.
	 *
	 * @var array
	 */
	protected $args;

	/**
	 * Post meta.
	 *
	 * @var array
	 */
	protected $meta;

	/**
	 * Whether the post has been saved.
	 *
	 * @var bool
	 */
	protected $new = true;

	/**
	 * Instantiates a new Post object
	 *
	 * @param int|array                 $id_or_args Either a post ID or an array of arguments.
	 * @param WordPress_GitHub_Sync_Api $api API object.
	 *
	 * @todo remove database operations from this method
	 */
	public function __construct( $id_or_args, WordPress_GitHub_Sync_Api $api ) {
		$this->api = $api;

		if ( is_numeric( $id_or_args ) ) {
			$this->id   = (int) $id_or_args;
			$this->post = get_post( $this->id );
			$this->new  = false;
		}

		if ( is_array( $id_or_args ) ) {
			$this->args = $id_or_args;

			if ( isset( $this->args['ID'] ) ) {
				$this->post = get_post( $this->args['ID'] );

				if ( $this->post ) {
					$this->id  = $this->post->ID;
					$this->new = false;
				} else {
					unset( $this->args['ID'] );
				}
			}
		}
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
	 * Returns true if the post has a password
	 * @return bool
	 */
	public function has_password() {
		return ! empty( $this->post->post_password );
	}

	/**
	 * Combines the 2 content parts for GitHub
	 */
	public function github_content() {
		$content = $this->front_matter() . $this->post_content();
		$ending  = apply_filters( 'wpghs_line_endings', "\n" );

		return preg_replace( '~(*BSR_ANYCRLF)\R~', $ending, $content );
	}

	/**
	 * The post's YAML frontmatter
	 *
	 * Returns String the YAML frontmatter, ready to be written to the file
	 */
	public function front_matter() {
		return "---\n" . spyc_dump( $this->meta() ) . "---\n";
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
			if ( WPCom_Markdown::get_instance()->is_markdown( $this->post->ID ) ) {
				$content = $this->post->post_content_filtered;
			}
		}

		return apply_filters( 'wpghs_content_export', $content, $this );
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
	 *
	 * @return string
	 */
	public function github_directory() {
		if ( 'publish' !== $this->status() ) {
			return apply_filters( 'wpghs_directory_unpublished', '_drafts/', $this );
		}

		$name = '';

		switch ( $this->type() ) {
			case 'post':
				$name = 'posts';
				break;
			case 'page':
				$name = 'pages';
				break;
			default:
				$obj = get_post_type_object( $this->type() );

				if ( $obj ) {
					$name = strtolower( $obj->labels->name );
				}

				if ( ! $name ) {
					$name = '';
				}
		}

		if ( $name ) {
			$name = '_' . $name . '/';
		}

		return apply_filters( 'wpghs_directory_published', $name, $this );
	}

	/**
	 * Build GitHub filename based on post
	 */
	public function github_filename() {
		if ( 'post' === $this->type() ) {
			$filename = get_the_time( 'Y-m-d-', $this->id ) . $this->get_name() . '.md';
		} else {
			$filename = $this->get_name() . '.md';
		}

		$filename = sanitize_file_name( $filename );

		return apply_filters( 'wpghs_filename', $filename, $this );
	}

	/**
	 * Returns a post slug we can use in the GitHub filename
	 *
	 * @return string
	 */
	protected function get_name() {
		if ( '' !== $this->name() ) {
			return $this->name();
		}

		return get_the_title( $this->post );
	}

	/**
	 * Returns the URL for the post on GitHub.
	 *
	 * @return string
	 */
	public function github_view_url() {
		return 'https://github.com/' . $this->api->fetch()->repository() . '/blob/master/' . $this->github_path();
	}

	/**
	 * Returns the URL for the post on GitHub.
	 *
	 * @return string
	 */
	public function github_edit_url() {
		return 'https://github.com/' . $this->api->fetch()->repository() . '/edit/master/' . $this->github_path();
	}

	/**
	 * Retrieve post type directory from blob path.
	 *
	 * @param string $path Path string.
	 *
	 * @return string
	 */
	public function get_directory_from_path( $path ) {
		$directory = explode( '/', $path );
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
		// then we should try a live check to see if it exists.
		if ( ! $sha && 'yes' === get_option( '_wpghs_fully_exported' ) ) {

			// @todo could we eliminate this by calling down the full tree and searching it
			$data = $this->api->fetch()->remote_contents( $this );

			if ( ! is_wp_error( $data ) ) {
				update_post_meta( $this->id, '_sha', $data->sha );
				$sha = $data->sha;
			}
		}

		// if the sha still doesn't exist, then it's empty
		if ( ! $sha || is_wp_error( $sha ) ) {
			$sha = '';
		}

		return $sha;
	}

	/**
	 * Save the sha to post
	 *
	 * @param string $sha
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
			'author'       => ( $author = get_userdata( $this->post->post_author ) ) ? $author->display_name : '',
			'post_excerpt' => $this->post->post_excerpt,
			'layout'       => get_post_type( $this->post ),
			'permalink'    => get_permalink( $this->post ),
			'published'    => 'publish' === $this->status() ? true : false,
		);

		// only include the post date if the post is published
		if ( $meta['published'] ) {
			$meta['post_date'] = $this->post->post_date;
		}

		return apply_filters( 'wpghs_post_meta', $meta, $this );
	}

	/**
	 * Returns whether the Post has been saved in the DB yet.
	 *
	 * @return bool
	 */
	public function is_new() {
		return $this->new;
	}

	/**
	 * Sets the Post's meta.
	 *
	 * @param array $meta
	 */
	public function set_meta( $meta ) {
		$this->meta = $meta;
	}

	/**
	 * Returns the Post's arguments.
	 *
	 * @return array
	 */
	public function get_args() {
		return $this->args;
	}

	/**
	 * Returns the Post's meta.
	 *
	 * @return array
	 */
	public function get_meta() {
		return $this->meta;
	}

	/**
	 * Sets the Post's WP_Post object.
	 *
	 * @param WP_Post $post
	 *
	 * @return $this
	 */
	public function set_post( WP_Post $post ) {
		$this->post = $post;
		$this->id   = $post->ID;

		return $this;
	}

	/**
	 * Transforms the Post into a Blob.
	 *
	 * @return WordPress_GitHub_Sync_Blob
	 */
	public function to_blob() {
		$data = new stdClass;

		$data->path    = $this->github_path();
		$data->content = $this->github_content();

		return new WordPress_GitHub_Sync_Blob( $data );
	}
}
