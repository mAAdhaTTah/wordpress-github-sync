<?php

class WordPress_GitHub_Sync_Export_Test extends WordPress_GitHub_Sync_TestCase {

	/**
	 * @var array
	 */
	protected $posts;

	/**
	 * @var string
	 */
	protected $msg = 'Commit message';

	public function setUp() {
		parent::setUp();

		$this->export = new WordPress_GitHub_Sync_Export( $this->app );
		$this->posts  = array( $this->post );
	}

	public function test_should_fail_if_get_master_fails() {
		$error = new WP_Error( 'api_failed', 'Api failed' );
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->posts( $this->posts, $this->msg ) );
	}

	public function test_should_fail_if_get_tree_fails() {
		$sha   = '1234567890qwertyuiop';
		$error = new WP_Error( 'api_failed', 'Api failed' );
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree_sha' )
			->once()
			->andReturn( $sha );
		$this->api
			->shouldReceive( 'get_tree_recursive' )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->posts( $this->posts, $this->msg ) );
	}

	public function test_should_fail_if_create_commit_fails() {
		$sha   = '1234567890qwertyuiop';
		$error = new WP_Error( 'api_failed', 'Api failed' );
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree_sha' )
			->once()
			->andReturn( $sha );
		$this->api
			->shouldReceive( 'get_tree_recursive' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_tree' )
			->once()
			->with( $this->tree );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $this->msg );
		$this->api
			->shouldReceive( 'create_commit' )
			->once()
			->with( $this->commit )
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->posts( $this->posts, $this->msg ) );
	}

	public function test_should_fail_if_cant_retrieve_last_tree() {
		$sha   = '1234567890qwertyuiop';
		$error = new WP_Error( 'api_failed', 'Api failed' );
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree_sha' )
			->once()
			->andReturn( $sha );
		$this->api
			->shouldReceive( 'get_tree_recursive' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_tree' )
			->once()
			->with( $this->tree );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $this->msg );
		$this->api
			->shouldReceive( 'create_commit' )
			->once()
			->with( $this->commit )
			->andReturn( true );
		$this->api
			->shouldReceive( 'last_tree_recursive' )
			->times( 5 )
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->posts( $this->posts, $this->msg ) );
	}

	public function test_should_save_post_shas_on_export() {
		$sha   = '1234567890qwertyuiop';
		$error = new WP_Error( 'api_failed', 'Api failed' );
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree_sha' )
			->once()
			->andReturn( $sha );
		$this->api
			->shouldReceive( 'get_tree_recursive' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_tree' )
			->once()
			->with( $this->tree );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $this->msg );
		$this->api
			->shouldReceive( 'create_commit' )
			->once()
			->with( $this->commit )
			->andReturn( true );
		$this->api
			->shouldReceive( 'last_tree_recursive' )
			->once()
			->andReturn( $this->tree );
		$path = '_posts/2015-10-25-github-path.md';
		$this->post
			->shouldReceive( 'github_path' )
			->once()
			->andReturn( $path );
		$this->tree
			->shouldReceive( 'get_blob_for_path' )
			->with( $path )
			->once()
			->andReturn( $this->blob );
		$this->blob
			->shouldReceive( 'sha' )
			->once()
			->andReturn( $sha );
		$this->database
			->shouldReceive( 'set_post_sha' )
			->with( $this->post, $sha )
			->once();

		$this->assertEquals(
			'Export to GitHub completed successfully.',
			$this->export->posts( $this->posts, $this->msg )
		);
	}

	public function test__should_set_export_user_id() {
		$id = 1;

		$this->export->set_user( $id );

		$this->assertEquals( $id, get_option( '_wpghs_export_user_id' ) );
	}
}
