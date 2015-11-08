<?php

/**
 * @group api
 * @group models
 */
class WordPress_GitHub_Sync_Blob_Test extends WordPress_GitHub_Sync_Base_Client_Test {

	/**
	 * @var stdClass
	 */
	protected $blob_data;

	/**
	 * @var array {
	 *     @var string utf8
	 *     @var string base84
	 * }
	 */
	protected $content = array(
		'base64' => '',
		'utf8'   => '',
	);

	public function setUp() {
		parent::setUp();

		$this->blob_data           = new stdClass;
		$this->blob_data->sha      = '1234567890qwertyuiop';
		$this->blob_data->path     = '_posts/2015-10-31-new-post.md';
		$this->blob_data->content  = base64_encode( <<<MD
---
post_title: 'New Post'
permalink: >
  http://example.org/2015/11/02/hello-world/
---
Post content.
MD
		);
		$this->blob_data->encoding = 'base64';

		$this->blob = new WordPress_GitHub_Sync_Blob( $this->blob_data );

		$this->content['utf8'] = <<<MD
---
post_title: 'New Post Title'
---
New post content.
MD;
		$this->content['base64'] = base64_encode( $this->content['utf8'] );
	}

	public function test_should_create_empty_blob() {
		$this->blob = new WordPress_GitHub_Sync_Blob( new stdClass );

		$this->assertSame( '', $this->blob->sha() );
		$this->assertSame( '', $this->blob->path() );
		$this->assertSame( '', $this->blob->content() );
		$this->assertSame( '', $this->blob->content_import() );
		$this->assertEmpty( $this->blob->meta() );
		$this->assertFalse( $this->blob->has_frontmatter() );
	}

	public function test_should_interpret_data() {
		$this->assertSame( $this->blob_data->sha, $this->blob->sha() );
		$this->assertSame( $this->blob_data->path, $this->blob->path() );
		$this->assertSame( base64_decode( $this->blob_data->content ), $this->blob->content() );
		$this->assertSame( 'Post content.', $this->blob->content_import() );
		$this->assertTrue( $this->blob->has_frontmatter() );
		$this->assertArrayHasKey( 'post_title', $meta = $this->blob->meta() );
		$this->assertSame( 'New Post', $meta['post_title'] );
		$this->assertArrayHasKey( 'permalink', $meta );
		$this->assertSame( '/2015/11/02/hello-world/', $meta['permalink'] );
	}

	public function test_should_generate_body_with_sha() {
		$body = $this->blob->to_body();

		$this->assertInstanceOf( 'stdClass', $body );
		$this->assertSame( '100644', $body->mode );
		$this->assertSame( 'blob', $body->type );
		$this->assertSame( $this->blob_data->path, $body->path );
		$this->assertSame( $this->blob_data->sha, $body->sha );
	}

	public function test_should_generate_body_with_content() {
		unset( $this->blob_data->sha );
		$this->blob = new WordPress_GitHub_Sync_Blob( $this->blob_data );

		$body = $this->blob->to_body();

		$this->assertInstanceOf( 'stdClass', $body );
		$this->assertSame( '100644', $body->mode );
		$this->assertSame( 'blob', $body->type );
		$this->assertSame( $this->blob_data->path, $body->path );
		$this->assertSame( base64_decode( $this->blob_data->content ), $body->content );
	}

	public function test_should_set_base64_content() {
		$this->blob->set_content( $this->content['base64'], true );

		$this->assertSame( $this->content['utf8'], $this->blob->content() );
	}

	public function test_should_set_utf8_content() {
		$this->blob->set_content( $this->content['utf8'], false );

		$this->assertSame( $this->content['utf8'], $this->blob->content() );
	}
}

function wpmarkdown_markdown_to_html( $content ) {
	return $content;
}