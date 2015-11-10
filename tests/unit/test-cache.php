<?php

class WordPress_GitHub_Sync_Cache_Test extends WordPress_GitHub_Sync_TestCase {

	/**
	 * @var string
	 */
	protected $sha;

	public function setUp() {
		parent::setUp();

		update_option( '_wpghs_api_cache', 'data' );
		$this->api_cache = new WordPress_GitHub_Sync_Cache;
		$this->sha       = '1234567890qwertyuiop';
	}

	public function test_should_set_and_fetch_commit_from_memory() {
		$this->api_cache->set_commit( $this->sha, $this->commit );

		$commit = $this->api_cache->fetch_commit( $this->sha );

		$this->assertSame( $this->commit, $commit );
	}

	public function test_should_set_and_fetch_tree_from_memory() {
		$this->api_cache->set_tree( $this->sha, $this->tree );

		$tree = $this->api_cache->fetch_tree( $this->sha );

		$this->assertSame( $this->tree, $tree );
	}

	public function test_should_set_and_fetch_blob_from_memory() {
		$this->api_cache->set_blob( $this->sha, $this->blob );

		$blob = $this->api_cache->fetch_blob( $this->sha );

		$this->assertSame( $this->blob, $blob );
	}

	public function test_should_set_and_fetch_commit_from_cache() {
		$data                    = new stdClass;
		$data->message           = 'Commit message';
		$this->commit->fake_data = $data;
		set_transient( 'wpghs_' . md5( 'commits_' . $this->sha ), $this->commit );

		$commit = $this->api_cache->fetch_commit( $this->sha );

		$this->assertSame( $data->message, $commit->fake_data->message );
	}

	public function test_should_set_and_fetch_tree_from_cache() {
		$data                  = new stdClass;
		$data->blobs           = array( $this->blob );
		$this->tree->fake_data = $data;
		set_transient( 'wpghs_' . md5( 'trees_' . $this->sha ), $this->tree );

		$tree = $this->api_cache->fetch_tree( $this->sha );

		$this->assertCount( 1, $tree->fake_data->blobs );
		$this->assertEquals( $this->blob, $tree->fake_data->blobs[0] );
	}

	public function test_should_set_and_fetch_blob_from_cache() {
		$data                  = new stdClass;
		$data->content         = 'Post content';
		$this->blob->fake_data = $data;
		set_transient( 'wpghs_' . md5( 'blobs_' . $this->sha ), $this->blob );

		$blob = $this->api_cache->fetch_blob( $this->sha );

		$this->assertSame( $data->content, $blob->fake_data->content );
	}

	public function test_should_return_false_if_cant_fetch_commit() {
		$this->assertFalse( $this->api_cache->fetch_commit( $this->sha ) );
	}

	public function test_should_return_false_if_cant_fetch_tree() {
		$this->assertFalse( $this->api_cache->fetch_tree( $this->sha ) );
	}

	public function test_should_return_false_if_cant_fetch_blob() {
		$this->assertFalse( $this->api_cache->fetch_blob( $this->sha ) );
	}

	public function tearDown() {
		parent::tearDown();

		$this->assertFalse( get_option( '_wpghs_api_cache' ) );
	}
}
