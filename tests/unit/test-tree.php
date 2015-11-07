<?php

/**
 * @group models
 */
class WordPress_GitHub_Sync_Tree_Test extends WordPress_GitHub_Sync_TestCase {
	public function setUp() {
		parent::setUp();
	}

	public function test_should_interpret_data() {
		$data      = new stdClass;
		$data->sha = '1234567890qwertyuiop';
		$data->url = 'https://api.github.com/trees';

		$tree = new WordPress_GitHub_Sync_Tree( $data );

		$this->assertSame( $data, $tree->get_data() );
		$this->assertSame( $data->sha, $tree->sha() );
		$this->assertSame( $data->url, $tree->url() );
		$this->assertEmpty( $tree->blobs() );
	}

	public function test_should_construct_empty() {
		$tree = new WordPress_GitHub_Sync_Tree( new stdClass );

		$this->assertSame( '', $tree->sha() );
		$this->assertSame( '', $tree->url() );
		$this->assertEmpty( $tree->blobs() );
	}

	public function test_should_set_sha() {
		$sha = '1234567890qwertyuiop';
		$tree = new WordPress_GitHub_Sync_Tree( new stdClass );

		$tree->set_sha( $sha );

		$this->assertSame( $sha, $tree->sha() );
	}

	public function test_should_set_array_of_blobs() {
		$this->blob
			->shouldReceive( 'path' )
			->once()
			->andReturn( '_posts/2015-10-31-new-post.md' );
		$this->blob
			->shouldReceive( 'sha' )
			->once()
			->andReturn( '1234567890wertyuiop' );
		$tree  = new WordPress_GitHub_Sync_Tree( new stdClass );
		$blobs = $tree->set_blobs( array( $this->blob ) )->blobs();

		$this->assertCount( 1, $blobs );
		$this->assertSame( $this->blob, $blobs[0] );
	}

	public function test_should_add_post_to_tree() {
		$tree = new WordPress_GitHub_Sync_Tree( new stdClass );

		$post_id = $this->factory->post->create();
		$post    = new WordPress_GitHub_Sync_Post( $post_id, $this->api );
		$tree->add_post_to_tree( $post );

		$this->assertCount( 1, $tree->blobs() );
		$this->assertTrue( $tree->is_changed() );

		$body = $tree->to_body();

		$this->assertArrayHasKey( 'tree', $body );
		$this->assertCount( 1, $body['tree'] );
	}

	public function test_should_update_post_by_sha() {
		$sha  = '1234567890qwertyuiop';
		$path = '_posts/2015-10-31-new-post.md';
		$this->blob
			->shouldReceive( 'sha' )
			->andReturn( $sha );
		$this->blob
			->shouldReceive( 'path' )
			->andReturn( $path );
		$this->blob
			->shouldReceive( 'content_import' )
			->andReturn( 'Old content' );
		$tree = new WordPress_GitHub_Sync_Tree( new stdClass );
		$tree->add_blob( $this->blob );

		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, '_sha', $sha );
		$post = new WordPress_GitHub_Sync_Post( $post_id, $this->api );
		$tree->add_post_to_tree( $post );

		$this->assertCount( 1, $blobs = $tree->blobs() );
		$this->assertNotSame( $this->blob, $blobs[0] );
		$this->assertTrue( $tree->is_changed() );

		$body = $tree->to_body();

		$this->assertArrayHasKey( 'tree', $body );
		$this->assertCount( 1, $body['tree'] );
	}

	public function test_should_update_post_by_path() {
		$sha = '1234567890qwertyuiop';
		$this->blob
			->shouldReceive( 'sha' )
			->andReturn( $sha );
		$post_id = $this->factory->post->create();
		$post    = new WordPress_GitHub_Sync_Post( $post_id, $this->api );
		$this->blob
			->shouldReceive( 'path' )
			->andReturn( $post->github_path() );
		$this->blob
			->shouldReceive( 'content_import' )
			->andReturn( 'Old content' );
		$tree = new WordPress_GitHub_Sync_Tree( new stdClass );
		$tree->add_blob( $this->blob );

		$tree->add_post_to_tree( $post );

		$this->assertCount( 1, $blobs = $tree->blobs() );
		$this->assertNotSame( $this->blob, $blobs[0] );
		$this->assertTrue( $tree->is_changed() );

		$body = $tree->to_body();

		$this->assertArrayHasKey( 'tree', $body );
		$this->assertCount( 1, $body['tree'] );
	}

	public function test_should_not_update_post_if_unchanged() {
		$sha = '1234567890qwertyuiop';
		$this->blob
			->shouldReceive( 'sha' )
			->andReturn( $sha );
		$post_id = $this->factory->post->create();
		$post    = new WordPress_GitHub_Sync_Post( $post_id, $this->api );
		$this->blob
			->shouldReceive( 'path' )
			->andReturn( $post->github_path() );
		$this->blob
			->shouldReceive( 'content_import' )
			->andReturn( $post->github_content() );
		$tree = new WordPress_GitHub_Sync_Tree( new stdClass );
		$tree->add_blob( $this->blob );

		$tree->add_post_to_tree( $post );

		$this->assertCount( 1, $blobs = $tree->blobs() );
		$this->assertSame( $this->blob, $blobs[0] );
		$this->assertFalse( $tree->is_changed() );

		$this->blob
			->shouldReceive( 'to_body' )
			->andReturn( new stdClass );
		$body = $tree->to_body();

		$this->assertArrayHasKey( 'tree', $body );
		$this->assertCount( 1, $body['tree'] );
	}

	public function test_should_remove_post_by_sha() {
		$sha  = '1234567890qwertyuiop';
		$path = '_posts/2015-10-31-new-post.md';
		$this->blob
			->shouldReceive( 'sha' )
			->andReturn( $sha );
		$this->blob
			->shouldReceive( 'path' )
			->andReturn( $path );
		$tree = new WordPress_GitHub_Sync_Tree( new stdClass );
		$tree->add_blob( $this->blob );

		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, '_sha', $sha );
		$post = new WordPress_GitHub_Sync_Post( $post_id, $this->api );
		$tree->remove_post_from_tree( $post );

		$this->assertCount( 0, $blobs = $tree->blobs() );
		$this->assertTrue( $tree->is_changed() );

		$body = $tree->to_body();

		$this->assertArrayHasKey( 'tree', $body );
		$this->assertCount( 0, $body['tree'] );
	}

	public function test_should_remove_post_by_path() {
		$sha = '1234567890qwertyuiop';
		$this->blob
			->shouldReceive( 'sha' )
			->andReturn( $sha );
		$post_id = $this->factory->post->create();
		$post    = new WordPress_GitHub_Sync_Post( $post_id, $this->api );
		$this->blob
			->shouldReceive( 'path' )
			->andReturn( $post->github_path() );
		$tree = new WordPress_GitHub_Sync_Tree( new stdClass );
		$tree->add_blob( $this->blob );

		$tree->remove_post_from_tree( $post );

		$this->assertCount( 0, $blobs = $tree->blobs() );
		$this->assertTrue( $tree->is_changed() );

		$body = $tree->to_body();

		$this->assertArrayHasKey( 'tree', $body );
		$this->assertCount( 0, $body['tree'] );
	}
}
