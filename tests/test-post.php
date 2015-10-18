<?php

class WordPress_GitHub_Sync_Post_Test extends WordPress_GitHub_Sync_Base_TestCase {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var WP_Post
	 */
	protected $post;

	public function setUp() {
		parent::setUp();
		update_option( 'wpghs_repository', 'owner/repo' );
		$this->id   = $this->factory->post->create();
		$this->post = get_post( $this->id );

		$this->api
			->shouldReceive('repository')
			->andReturn('owner/repo');
	}

	public function test_should_return_correct_directory() {
		$post = new WordPress_GitHub_Sync_Post( $this->id, $this->api );

		$this->assertEquals( '_posts/', $post->github_directory() );
	}

	public function test_should_get_post_name() {
		$post = new WordPress_GitHub_Sync_Post( $this->id, $this->api );

		$this->assertEquals( get_post( $this->id )->post_name, $post->name() );
	}

	public function test_should_build_github_content() {
		$post = new WordPress_GitHub_Sync_Post( $this->id, $this->api );

		$this->assertStringStartsWith( '---', $post->github_content() );
		$this->assertStringEndsWith( 'Post content 1', $post->github_content() );
	}

	public function test_should_build_github_view_url() {
		$post = new WordPress_GitHub_Sync_Post( $this->id, $this->api );

		$this->assertEquals( 'https://github.com/owner/repo/blob/master/_posts/' . get_the_date( 'Y-m-d-', $this->id ) . $this->post->post_name . '.md', $post->github_view_url() );
	}

	public function test_should_build_github_edit_url() {
		$post = new WordPress_GitHub_Sync_Post( $this->id, $this->api );

		$this->assertEquals( 'https://github.com/owner/repo/edit/master/_posts/' . get_the_date( 'Y-m-d-', $this->id ) . $this->post->post_name . '.md', $post->github_edit_url() );
	}
}

