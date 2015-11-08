<?php

/**
 * @group managers
 */
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
		$this->post
			->shouldReceive( 'status' )
			->andReturn( 'publish' );
	}

	public function test_should_fail_full_export_if_database_fails() {
		$error = new WP_Error( 'db_fail', 'Database failed.' );
		$this->database
			->shouldReceive( 'fetch_all_supported' )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->full() );
	}

	public function test_should_fail_full_export_if_cant_get_last_commit() {
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$this->database
			->shouldReceive( 'fetch_all_supported' )
			->once()
			->andReturn( array( $this->post ) );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->full() );
	}

	public function test_should_fail_full_export_if_cant_create_new_commit() {
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$msg   = 'Commit message';
		add_filter( 'wpghs_commit_msg_full', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_all_supported' )
			->once()
			->andReturn( array( $this->post ) );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'add_post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $msg . ' - wpghs' );
		$this->persist
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->full() );
	}

	public function test_should_fail_full_export_if_cant_retrieve_new_commit() {
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$msg   = 'Commit message';
		add_filter( 'wpghs_commit_msg_full', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_all_supported' )
			->once()
			->andReturn( array( $this->post ) );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'add_post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $msg . ' - wpghs' );
		$this->persist
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->once()
			->andReturn( 'Success' );
		$this->fetch
			->shouldReceive( 'master' )
			->times( 5 )
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->full() );
	}

	public function test_should_successfully_full_export() {
		$msg = 'Commit message';
		add_filter( 'wpghs_commit_msg_full', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_all_supported' )
			->once()
			->andReturn( array( $this->post ) );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree' )
			->twice()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'add_post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $msg . ' - wpghs' );
		$this->persist
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->once()
			->andReturn( 'Success' );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$path = '_posts/2015-10-25-github-path.md';
		$sha  = '1234567890qwertyuiop';
		$this->post
			->shouldReceive( 'github_path' )
			->once()
			->andReturn( $path );
		$this->tree
			->shouldReceive( 'get_blob_by_path' )
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

		$this->assertEquals( 'Export to GitHub completed successfully.', $this->export->full() );
	}

	public function test_should_fail_update_export_if_database_fails() {
		$id    = 123456789;
		$error = new WP_Error( 'db_fail', 'Database failed.' );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->update( $id ) );
	}

	public function test_should_fail_update_export_if_cant_get_last_commit() {
		$id    = 123456789;
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $this->post );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->update( $id ) );
	}

	public function test_should_fail_update_export_if_cant_create_new_commit() {
		$id    = 123456789;
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$msg   = 'Commit message';
		add_filter( 'wpghs_commit_msg_single', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $this->post );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'add_post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $msg . ' - wpghs' );
		$path = '_posts/2015-10-25-github-path.md';
		$this->post
			->shouldReceive( 'github_path' )
			->once()
			->andReturn( $path );
		$this->persist
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->update( $id ) );
	}

	public function test_should_fail_update_export_if_cant_retrieve_new_commit() {
		$id    = 123456789;
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$msg   = 'Commit message';
		add_filter( 'wpghs_commit_msg_single', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $this->post );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'add_post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $msg . ' - wpghs' );
		$path = '_posts/2015-10-25-github-path.md';
		$this->post
			->shouldReceive( 'github_path' )
			->once()
			->andReturn( $path );
		$this->persist
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->once()
			->andReturn( 'Success' );
		$this->fetch
			->shouldReceive( 'master' )
			->times( 5 )
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->update( $id ) );
	}

	public function test_should_successfully_update_export() {
		$id  = 123456789;
		$msg = 'Commit message';
		add_filter( 'wpghs_commit_msg_single', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $this->post );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree' )
			->twice()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'add_post_to_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $msg . ' - wpghs' );
		$this->persist
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->once()
			->andReturn( 'Success' );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$sha  = '1234567890qwertyuiop';
		$path = '_posts/2015-10-25-github-path.md';
		$this->post
			->shouldReceive( 'github_path' )
			->twice()
			->andReturn( $path );
		$this->tree
			->shouldReceive( 'get_blob_by_path' )
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

		$this->assertEquals( 'Export to GitHub completed successfully.', $this->export->update( $id ) );
	}

	public function test_should_fail_export_new_posts_if_cant_get_last_commit() {
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$this->fetch
				->shouldReceive( 'master' )
				->once()
				->andReturn( $error );

		$this->assertEquals( $error, $this->export->new_posts( array( $this->post ) ) );
	}

	public function test_should_fail_export_new_posts_if_cant_create_new_commit() {
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$msg   = 'Commit message';
		add_filter( 'wpghs_commit_msg_new_posts', function () use ( $msg ) {
			return $msg;
		} );
		$this->fetch
				->shouldReceive( 'master' )
				->once()
				->andReturn( $this->commit );
		$this->commit
				->shouldReceive( 'tree' )
				->once()
				->andReturn( $this->tree );
		$this->tree
				->shouldReceive( 'add_post_to_tree' )
				->once()
				->with( $this->post );
		$this->commit
				->shouldReceive( 'set_message' )
				->once()
				->with( $msg . ' - wpghs' );
		$path = '_posts/2015-10-25-github-path.md';
		$this->persist
				->shouldReceive( 'commit' )
				->with( $this->commit )
				->once()
				->andReturn( $error );

		$this->assertEquals( $error, $this->export->new_posts( array( $this->post ) ) );
	}

	public function test_should_fail_export_new_posts_if_cant_retrieve_new_commit() {
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$msg   = 'Commit message';
		add_filter( 'wpghs_commit_msg_new_posts', function () use ( $msg ) {
			return $msg;
		} );
		$this->fetch
				->shouldReceive( 'master' )
				->once()
				->andReturn( $this->commit );
		$this->commit
				->shouldReceive( 'tree' )
				->once()
				->andReturn( $this->tree );
		$this->tree
				->shouldReceive( 'add_post_to_tree' )
				->once()
				->with( $this->post );
		$this->commit
				->shouldReceive( 'set_message' )
				->once()
				->with( $msg . ' - wpghs' );
		$path = '_posts/2015-10-25-github-path.md';
		$this->persist
				->shouldReceive( 'commit' )
				->with( $this->commit )
				->once()
				->andReturn( 'Success' );
		$this->fetch
				->shouldReceive( 'master' )
				->times( 5 )
				->andReturn( $error );

		$this->assertEquals( $error, $this->export->new_posts( array( $this->post ) ) );
	}

	public function test_should_successfully_export_new_posts() {
		$id  = 123456789;
		$msg = 'Commit message';
		add_filter( 'wpghs_commit_msg_new_posts', function () use ( $msg ) {
			return $msg;
		} );
		$this->fetch
				->shouldReceive( 'master' )
				->once()
				->andReturn( $this->commit );
		$this->commit
				->shouldReceive( 'tree' )
				->twice()
				->andReturn( $this->tree );
		$this->tree
				->shouldReceive( 'add_post_to_tree' )
				->once()
				->with( $this->post );
		$this->commit
				->shouldReceive( 'set_message' )
				->once()
				->with( $msg . ' - wpghs' );
		$this->persist
				->shouldReceive( 'commit' )
				->with( $this->commit )
				->once()
				->andReturn( 'Success' );
		$this->fetch
				->shouldReceive( 'master' )
				->once()
				->andReturn( $this->commit );
		$sha  = '1234567890qwertyuiop';
		$path = '_posts/2015-10-25-github-path.md';
		$this->post
				->shouldReceive( 'github_path' )
				->once()
				->andReturn( $path );
		$this->tree
				->shouldReceive( 'get_blob_by_path' )
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

		$this->assertEquals( 'Export to GitHub completed successfully.', $this->export->new_posts( array( $this->post ) ) );
	}

	public function test_should_fail_delete_export_if_database_fails() {
		$id    = 123456789;
		$error = new WP_Error( 'db_fail', 'Database failed.' );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->delete( $id ) );
	}

	public function test_should_fail_delete_export_if_cant_get_last_commit() {
		$id    = 123456789;
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $this->post );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->delete( $id ) );
	}

	public function test_should_fail_delete_export_if_cant_create_new_commit() {
		$id    = 123456789;
		$error = new WP_Error( 'api_fail', 'API failed.' );
		$msg   = 'Commit message';
		add_filter( 'wpghs_commit_msg_delete', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $this->post );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'remove_post_from_tree' )
			->once()
			->with( $this->post );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $msg . ' - wpghs' );
		$path = '_posts/2015-10-25-github-path.md';
		$this->post
			->shouldReceive( 'github_path' )
			->once()
			->andReturn( $path );
		$this->persist
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->export->delete( $id ) );
	}

	public function test_should_successfully_delete_export() {
		$id  = 123456789;
		$msg = 'Commit message';
		add_filter( 'wpghs_commit_msg_delete', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->with( $id )
			->once()
			->andReturn( $this->post );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'tree' )
			->once()
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'remove_post_from_tree' )
			->once()
			->with( $this->post );
		$path = '_posts/2015-10-25-github-path.md';
		$this->post
			->shouldReceive( 'github_path' )
			->once()
			->andReturn( $path );
		$this->commit
			->shouldReceive( 'set_message' )
			->once()
			->with( $msg . ' - wpghs' );
		$this->persist
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->once()
			->andReturn( 'Success' );

		$this->assertEquals( 'Export to GitHub completed successfully.', $this->export->delete( $id ) );
	}

	public function test_should_set_export_user_id() {
		$id = 1;

		$this->export->set_user( $id );

		$this->assertEquals( $id, get_option( '_wpghs_export_user_id' ) );
	}
}
