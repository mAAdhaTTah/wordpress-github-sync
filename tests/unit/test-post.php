<?php

class WordPress_GitHub_Sync_Post_Test extends WordPress_GitHub_Sync_TestCase {

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

		$this->fetch
			->shouldReceive( 'repository' )
			->andReturn( 'owner/repo' );
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
		$this->assertStringEndsWith( $this->post->post_content, $post->github_content() );
	}

	public function test_should_build_github_view_url() {
		$post = new WordPress_GitHub_Sync_Post( $this->id, $this->api );

		$this->assertEquals( 'https://github.com/owner/repo/blob/master/_posts/' . get_the_date( 'Y-m-d-', $this->id ) . $this->post->post_name . '.md', $post->github_view_url() );
	}

	public function test_should_build_github_edit_url() {
		$post = new WordPress_GitHub_Sync_Post( $this->id, $this->api );

		$this->assertEquals( 'https://github.com/owner/repo/edit/master/_posts/' . get_the_date( 'Y-m-d-', $this->id ) . $this->post->post_name . '.md', $post->github_edit_url() );
	}

	public function test_should_export_unpublished_to_drafts_folder() {
		$id   = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$post = new WordPress_GitHub_Sync_Post( $id, $this->api );

		$this->assertEquals( '_drafts/', $post->github_directory() );
	}

	public function test_should_export_published_post_to_posts_folder() {
		$id   = $this->factory->post->create();
		$post = new WordPress_GitHub_Sync_Post( $id, $this->api );

		$this->assertEquals( '_posts/', $post->github_directory() );
	}

	public function test_should_export_published_page_to_pages_folder() {
		$id   = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$post = new WordPress_GitHub_Sync_Post( $id, $this->api );

		$this->assertEquals( '_pages/', $post->github_directory() );
	}

	public function test_should_export_published_unknown_post_type_to_root() {
		global $wp_version;

		if ( version_compare( $wp_version, '4.0', '<' ) && is_multisite() ) {
			$this->markTestSkipped( "Can't create post with unregistered type in Multisite v3.9." );
		}

		$id   = $this->factory->post->create( array( 'post_type' => 'widget' ) );
		$post = new WordPress_GitHub_Sync_Post( $id, $this->api );

		$this->assertEquals( '', $post->github_directory() );
	}

	public function test_should_export_published_post_type_to_plural_folder() {
		register_post_type( 'widget', array(
			'labels' => array( 'name' => 'Widgets' ),
		) );
		$id   = $this->factory->post->create( array( 'post_type' => 'widget' ) );
		$post = new WordPress_GitHub_Sync_Post( $id, $this->api );

		$this->assertEquals( '_widgets/', $post->github_directory() );
	}
}

