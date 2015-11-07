<?php

/**
 * @group models
 * @group api
 */
class WordPress_GitHub_Sync_Commit_Test extends WordPress_GitHub_Sync_Base_Client_Test {

	public function setUp() {
		parent::setUp();
	}

	public function test_should_interpret_data() {
		$data                = new stdClass;
		$data->sha           = '1234567890qwertyuiop';
		$data->url           = 'https://api.github.com/path/to/endpoint';
		$data->author        = new stdClass;
		$data->author->email = 'jamesorodig@gmail.com';
		$data->committer     = new stdClass;
		$data->message       = 'Commit message';
		$data->parents       = array( '0987654321poiuytrewq' );
		$data->tree          = new stdClass;
		$data->tree->sha     = 'zxcvbnmasdfghjklpoiuytrewq1234567876543';

		$commit = new WordPress_GitHub_Sync_Commit( $data );

		$this->assertSame( $data->sha, $commit->sha() );
		$this->assertSame( $data->url, $commit->url() );
		$this->assertSame( $data->author, $commit->author() );
		$this->assertSame( $data->author->email, $commit->author_email() );
		$this->assertSame( $data->committer, $commit->committer() );
		$this->assertSame( $data->message, $commit->message() );
		$this->assertSame( $data->parents, $commit->parents() );
		$this->assertSame( $data->tree->sha, $commit->tree_sha() );

		$body = $commit->to_body();

		$this->assertSame( $data->tree->sha, $body['tree'] );
		$this->assertSame( $data->message, $body['message'] );
		$this->assertSame( $data->parents, $body['parents'] );
	}

	public function test_should_return_empty_commit() {
		$commit = new WordPress_GitHub_Sync_Commit( new stdClass );

		$this->assertSame( '', $commit->sha() );
		$this->assertSame( '', $commit->url() );
		$this->assertFalse( $commit->author() );
		$this->assertSame( '', $commit->author_email() );
		$this->assertFalse( $commit->committer() );
		$this->assertSame( '', $commit->message() );
		$this->assertEmpty( $commit->parents() );
		$this->assertSame( '', $commit->tree_sha() );
	}

	public function test_should_be_unsynced_without_wpghs() {
		$commit = new WordPress_GitHub_Sync_Commit( new stdClass );
		$commit->set_message( 'Commit message.' );

		$this->assertFalse( $commit->already_synced() );
	}

	public function test_should_be_synced_with_wpghs() {
		$commit = new WordPress_GitHub_Sync_Commit( new stdClass );
		$commit->set_message( 'Commit message. - wpghs' );

		$this->assertTrue( $commit->already_synced() );
	}

	public function test_should_set_and_retrieve_tree() {
		$commit = new WordPress_GitHub_Sync_Commit( new stdClass );
		$commit->set_tree( $this->tree );
		$sha = 'qwsadjflkajsdf';
		$this->tree
			->shouldReceive( 'sha' )
			->andReturn( $sha );

		$this->assertSame( $this->tree, $commit->tree() );
		$this->assertSame( $sha, $commit->tree_sha() );
	}

	public function test_should_set_self_to_parent_on_message_update() {
		$sha       = '1233567890fghjkaisdfasd';
		$data      = new stdClass;
		$data->sha = $sha;

		$commit = new WordPress_GitHub_Sync_Commit( $data );
		$commit->set_message( 'New message.' );

		$this->assertCount( 1, $parents = $commit->parents() );
		$this->assertSame( $sha, $parents[0] );
	}

	public function test_should_set_self_to_parent_on_author_update() {
		$sha       = '1233567890fghjkaisdfasd';
		$data      = new stdClass;
		$data->sha = $sha;

		$commit = new WordPress_GitHub_Sync_Commit( $data );
		$commit->set_author( new stdClass );

		$this->assertCount( 1, $parents = $commit->parents() );
		$this->assertSame( $sha, $parents[0] );
	}

	public function test_should_set_self_to_parent_on_committer_update() {
		$sha       = '1233567890fghjkaisdfasd';
		$data      = new stdClass;
		$data->sha = $sha;

		$commit = new WordPress_GitHub_Sync_Commit( $data );
		$commit->set_committer( new stdClass );

		$this->assertCount( 1, $parents = $commit->parents() );
		$this->assertSame( $sha, $parents[0] );
	}
}
