<?php

/**
 * @group controller
 */
class WordPress_GitHub_Sync_Controller_Test extends WordPress_GitHub_Sync_TestCase {

	public function setUp() {
		parent::setUp();
		$this->controller = new WordPress_GitHub_Sync_Controller( $this->app );

		$this->semaphore
			->shouldReceive( 'is_open' )
			->once()
			->andReturn( true )
			->byDefault();
		$this->semaphore
			->shouldReceive( 'lock' )
			->once()
			->byDefault();
		$this->semaphore
			->shouldReceive( 'unlock' )
			->once()
			->byDefault();
	}

	public function test_should_fail_pull_if_semaphore_locked() {
		$this->semaphore
			->shouldReceive( 'is_open' )
			->once()
			->andReturn( false );
		$this->semaphore
			->shouldReceive( 'lock' )
			->never();
		$this->semaphore
			->shouldReceive( 'unlock' )
			->never();
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->pull_posts();

		$this->assertFalse( $result );
	}

	public function test_should_fail_if_invalid_secret() {
		$error = new WP_Error( 'invalid_secret', 'Failed to validate secret.' );
		$this->request
			->shouldReceive( 'is_secret_valid' )
			->once()
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->with( $error )
			->once()
			->andReturn( false );

		$result = $this->controller->pull_posts();

		$this->assertFalse( $result );
	}

	public function test_should_fail_if_invalid_payload() {
		$error = new WP_Error( 'invalid_secret', 'Failed to validate payload.' );
		$this->request
			->shouldReceive( 'is_secret_valid' )
			->once()
			->andReturn( true );
		$this->request
			->shouldReceive( 'payload' )
			->once()
			->andReturn( $this->payload );
		$this->payload
			->shouldReceive( 'should_import' )
			->once()
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->with( $error )
			->once()
			->andReturn( false );

		$result = $this->controller->pull_posts();

		$this->assertFalse( $result );
	}

	public function test_should_fail_if_import_payload_fails() {
		$error = new WP_Error( 'invalid_secret', 'Failed to import payload.' );
		$this->request
			->shouldReceive( 'is_secret_valid' )
			->once()
			->andReturn( true );
		$this->request
			->shouldReceive( 'payload' )
			->once()
			->andReturn( $this->payload );
		$this->payload
			->shouldReceive( 'should_import' )
			->once()
			->andReturn( true );
		$this->import
			->shouldReceive( 'payload' )
			->with( $this->payload )
			->once()
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->with( $error )
			->once()
			->andReturn( false );

		$result = $this->controller->pull_posts();

		$this->assertFalse( $result );
	}

	public function test_should_import_payload() {
		$msg = 'Successfully imported payload.';
		$this->request
			->shouldReceive( 'is_secret_valid' )
			->once()
			->andReturn( true );
		$this->request
			->shouldReceive( 'payload' )
			->once()
			->andReturn( $this->payload );
		$this->payload
			->shouldReceive( 'should_import' )
			->once()
			->andReturn( true );
		$this->import
			->shouldReceive( 'payload' )
			->with( $this->payload )
			->once()
			->andReturn( $msg );
		$this->response
			->shouldReceive( 'success' )
			->with( $msg )
			->once()
			->andReturn( true );

		$result = $this->controller->pull_posts();

		$this->assertTrue( $result );
	}

	public function test_should_fail_full_import_if_semaphore_locked() {
		$this->semaphore
			->shouldReceive( 'is_open' )
			->once()
			->andReturn( false );
		$this->semaphore
			->shouldReceive( 'lock' )
			->never();
		$this->semaphore
			->shouldReceive( 'unlock' )
			->never();
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->import_master();

		$this->assertFalse( $result );
	}

	public function test_should_fail_if_cant_retreive_master() {
		$error = new WP_Error( 501, 'Api call failed' );
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( $error )
			->andReturn( false );

		$result = $this->controller->import_master();

		$this->assertFalse( $result );
	}

	public function test_should_fail_if_commit_synced() {
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'already_synced' )
			->once()
			->andReturn( true );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->import_master();

		$this->assertFalse( $result );
	}

	public function test_should_fail_if_commit_import_fails() {
		$error = new WP_Error( 'import_failed', 'Import failed.' );
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'already_synced' )
			->once()
			->andReturn( false );
		$this->import
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->import_master();

		$this->assertFalse( $result );
	}

	public function test_should_import_commit() {
		$msg = 'Success';
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'already_synced' )
			->once()
			->andReturn( false );
		$this->import
			->shouldReceive( 'commit' )
			->with( $this->commit )
			->andReturn( $msg );
		$this->response
			->shouldReceive( 'success' )
			->once()
			->with( $msg )
			->andReturn( true );

		$result = $this->controller->import_master();

		$this->assertTrue( $result );
	}

	public function test_should_fail_full_export_if_semaphore_locked() {
		$this->semaphore
			->shouldReceive( 'is_open' )
			->once()
			->andReturn( false );
		$this->semaphore
			->shouldReceive( 'lock' )
			->never();
		$this->semaphore
			->shouldReceive( 'unlock' )
			->never();
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->export_all();

		$this->assertFalse( $result );
	}

	public function test_should_fail_full_export_if_database_fails() {
		$error = new WP_Error( 'database_failed', 'Database failed.' );
		$this->database
			->shouldReceive( 'fetch_all_supported' )
			->once()
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->export_all();

		$this->assertFalse( $result );
	}

	public function test_should_fail_full_export_if_export_fails() {
		$posts = array();
		$msg   = 'Commit msg';
		$error = new WP_Error( 'database_failed', 'Database failed.' );
		add_filter( 'wpghs_commit_msg_full', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_all_supported' )
			->once()
			->andReturn( $posts );
		$this->export
			->shouldReceive( 'posts' )
			->once()
			->with( $posts, $msg . ' - wpghs' )
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->export_all();

		$this->assertFalse( $result );
	}

	public function test_should_should_full_export() {
		$posts   = array();
		$msg     = 'Commit msg';
		$success = 'Export succeeded.';
		add_filter( 'wpghs_commit_msg_full', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_all_supported' )
			->once()
			->andReturn( $posts );
		$this->export
			->shouldReceive( 'posts' )
			->once()
			->with( $posts, $msg . ' - wpghs' )
			->andReturn( $success );
		$this->response
			->shouldReceive( 'success' )
			->once()
			->andReturn( true );

		$result = $this->controller->export_all();

		$this->assertTrue( $result );
	}

	public function test_should_fail_export_post_if_semaphore_locked() {
		$id = 12345;
		$this->semaphore
			->shouldReceive( 'is_open' )
			->once()
			->andReturn( false );
		$this->semaphore
			->shouldReceive( 'lock' )
			->never();
		$this->semaphore
			->shouldReceive( 'unlock' )
			->never();
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->export_post( $id );

		$this->assertFalse( $result );
	}

	public function test_should_fail_export_post_if_database_fails() {
		$id    = 12345;
		$error = new WP_Error( 'database_failed', 'Database failed.' );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->once()
			->with( $id )
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->export_post( $id );

		$this->assertFalse( $result );
	}

	public function test_should_fail_export_post_if_export_fails() {
		$id    = 12345;
		$msg   = 'Commit msg';
		$error = new WP_Error( 'database_failed', 'Database failed.' );
		add_filter( 'wpghs_commit_msg_single', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->once()
			->with( $id )
			->andReturn( $this->post );
		$this->post
			->shouldReceive( 'github_path' )
			->andReturn( '' );
		$this->export
			->shouldReceive( 'post' )
			->once()
			->with( $this->post, $msg . ' - wpghs' )
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->export_post( $id );

		$this->assertFalse( $result );
	}

	public function test_should_should_export_post() {
		$id      = 12345;
		$msg     = 'Commit msg';
		$success = 'Export succeeded.';
		add_filter( 'wpghs_commit_msg_single', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->once()
			->with( $id )
			->andReturn( $this->post );
		$this->post
			->shouldReceive( 'github_path' )
			->andReturn( '' );
		$this->export
			->shouldReceive( 'post' )
			->once()
			->with( $this->post, $msg . ' - wpghs' )
			->andReturn( $success );
		$this->response
			->shouldReceive( 'success' )
			->once()
			->andReturn( true );

		$result = $this->controller->export_post( $id );

		$this->assertTrue( $result );
	}

	public function test_should_fail_delete_post_if_semaphore_locked() {
		$id = 12345;
		$this->semaphore
			->shouldReceive( 'is_open' )
			->once()
			->andReturn( false );
		$this->semaphore
			->shouldReceive( 'lock' )
			->never();
		$this->semaphore
			->shouldReceive( 'unlock' )
			->never();
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->delete_post( $id );

		$this->assertFalse( $result );
	}

	public function test_should_fail_delete_post_if_database_fails() {
		$id    = 12345;
		$error = new WP_Error( 'database_failed', 'Database failed.' );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->once()
			->with( $id )
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->delete_post( $id );

		$this->assertFalse( $result );
	}

	public function test_should_fail_delete_post_if_export_fails() {
		$id    = 12345;
		$msg   = 'Commit msg';
		$error = new WP_Error( 'database_failed', 'Database failed.' );
		add_filter( 'wpghs_commit_msg_delete', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->once()
			->with( $id )
			->andReturn( $this->post );
		$this->post
			->shouldReceive( 'github_path' )
			->andReturn( '' );
		$this->export
			->shouldReceive( 'delete' )
			->once()
			->with( $this->post, $msg . ' - wpghs' )
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( Mockery::type( 'WP_Error' ) )
			->andReturn( false );

		$result = $this->controller->delete_post( $id );

		$this->assertFalse( $result );
	}

	public function test_should_should_delete_post() {
		$id      = 12345;
		$msg     = 'Commit msg';
		$success = 'Export succeeded.';
		add_filter( 'wpghs_commit_msg_delete', function () use ( $msg ) {
			return $msg;
		} );
		$this->database
			->shouldReceive( 'fetch_by_id' )
			->once()
			->with( $id )
			->andReturn( $this->post );
		$this->post
			->shouldReceive( 'github_path' )
			->andReturn( '' );
		$this->export
			->shouldReceive( 'delete' )
			->once()
			->with( $this->post, $msg . ' - wpghs' )
			->andReturn( $success );
		$this->response
			->shouldReceive( 'success' )
			->once()
			->with( $success )
			->andReturn( true );

		$result = $this->controller->delete_post( $id );

		$this->assertTrue( $result );
	}
}

