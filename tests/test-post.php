<?php

class WordPress_GitHub_Sync_Post_Test extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	protected $id;

	public function setUp() {
		parent::setUp();
		$this->id = $this->factory->post->create();
	}

	public function test_should_return_correct_directory() {
		$post = new WordPress_GitHub_Sync_Post( $this->id );

		$this->assertEquals( '_posts/', $post->github_directory() );
	}

	public function test_should_get_post_name() {
		$post = new WordPress_GitHub_Sync_Post( $this->id );

		$this->assertEquals( get_post( $this->id )->post_name, $post->name() );
	}

	public function test_should_build_github_content() {
		$post = new WordPress_GitHub_Sync_Post( $this->id );

		$this->assertStringStartsWith( '---', $post->github_content() );
		$this->assertStringEndsWith( 'Post content 1', $post->github_content() );
	}
}

