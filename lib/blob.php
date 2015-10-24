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
	 * Interprets the blob's data into properties.
	 */
	protected function interpret_data() {
		// Break out meta, if present
		preg_match( '/(^---(.*?)---$)?(.*)/ms', $this->data->content, $matches );

		$content = array_pop( $matches );

		if ( 3 === count( $matches ) ) {
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
