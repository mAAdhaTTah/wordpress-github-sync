<?php

class WordPress_GitHub_Sync_Blob {

	/**
	 * Raw blob data.
	 *
	 * @var stdClass
	 */
	protected $data;

	/**
	 * Blob content.
	 *
	 * @var string
	 */
	protected $content;

	/**
	 * Blob post meta.
	 *
	 * @var array
	 */
	protected $meta;

	/**
	 * Blob sha.
	 *
	 * @var string
	 */
	protected $sha;

	/**
	 * Blob path.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Whether the blob has frontmatter.
	 *
	 * @var boolean
	 */
	protected $frontmatter = false;

	/**
	 * Instantiates a new Blob object.
	 *
	 * @param $data
	 */
	public function __construct( $data ) {
		$this->data = $data;

		$this->interpret_data();
	}

	/**
	 * Returns the formatted/filtered blob content used for import.
	 *
	 * @return string
	 */
	public function content_import() {
		$content = $this->content();

		if ( function_exists( 'wpmarkdown_markdown_to_html' ) ) {
			$content = wpmarkdown_markdown_to_html( $content );
		}

		/**
		 * @todo document filter
		 */
		return apply_filters( 'wpghs_content_import', $content );
	}

	/**
	 * Returns the raw blob content.
	 *
	 * @return string
	 */
	public function content() {
		return $this->content;
	}

	/**
	 * Returns the blob meta.
	 *
	 * @return array
	 */
	public function meta() {
		return $this->meta;
	}

	/**
	 * Returns the blob sha.
	 *
	 * @return string
	 */
	public function sha() {
		return $this->sha;
	}

	/**
	 * Return's the blob path.
	 *
	 * @return string
	 */
	public function path() {
		return $this->path;
	}

	/**
	 * Updates the blob's path.
	 *
	 * @param string $path
	 *
	 * @return WordPress_GitHub_Sync_Blob
	 */
	public function set_path( $path ) {
		$this->path = (string) $path;

		return $this;
	}

	/**
	 * Whether the blob has frontmatter.
	 *
	 * @return bool
	 */
	public function has_frontmatter() {
		return $this->frontmatter;
	}

	/**
	 * Interprets the blob's data into properties.
	 */
	protected function interpret_data() {
		$content = trim( $this->data->content );

		if ( 'base64' === $this->data->encoding ) {
			$content = base64_decode( $content );
		}

		if ( '---' === substr( $content, 0, 3 ) ) {
			$this->frontmatter = true;

			// Break out meta, if present
			preg_match( '/(^---(.*?)---$)?(.*)/ms', $content, $matches );
			$content = array_pop( $matches );

			$meta = cyps_load( $matches[2] );
			if ( isset( $meta['permalink'] ) ) {
				$meta['permalink'] = str_replace( home_url(), '', get_permalink( $meta['permalink'] ) );
			}
		} else {
			$meta = array();
		}

		$this->content = $content;
		$this->meta    = $meta;
		$this->sha     = $this->data->sha;
	}
}
