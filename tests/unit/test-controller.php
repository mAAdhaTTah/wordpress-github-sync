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
		$this->semaphore
			->shouldReceive( 'lock' )
			->never();
		$this->semaphore
			->shouldReceive( 'unlock' )
			->never();
		$this->request
			->shouldReceive( 'is_secret_valid' )
			->once()
			->andReturn( false );
		$this->response
			->shouldReceive( 'error' )
			->with( Mockery::type( 'WP_Error' ) )
			->once()
			->andReturn( false );

		$result = $this->controller->pull_posts();

		$this->assertFalse( $result );
	}

	public function test_should_fail_if_invalid_payload() {
		$error = new WP_Error( 'invalid_secret', 'Failed to validate payload.' );
		$this->semaphore
			->shouldReceive( 'lock' )
			->never();
		$this->semaphore
			->shouldReceive( 'unlock' )
			->never();
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
			->andReturn( false );
		$this->payload
			->shouldReceive( 'get_commit_id' )
			->once()
			->andReturn( 'commit id' );
		$this->response
			->shouldReceive( 'error' )
			->with( Mockery::type( 'WP_Error' ) )
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

	public function test_should_fail_full_import_if_import_fails() {
		$error = new WP_Error( 'import_fail', 'Import failed.' );
		$this->import
			->shouldReceive( 'master' )
			->andReturn( $error );
		$this->response
			->shouldReceive( 'error' )
			->once()
			->with( $error )
			->andReturn( false );

		$result = $this->controller->import_master();

		$this->assertFalse( $result );
	}

	public function test_should_import_master() {
		$msg = 'Success';
		$this->import
			->shouldReceive( 'master' )
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

	public function test_should_fail_full_export_if_export_fails() {
		$error = new WP_Error( 'export_failed', 'Export failed.' );
		$this->export
			->shouldReceive( 'full' )
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

	public function test_should_should_full_export() {
		$success = 'Export succeeded.';
		$this->export
			->shouldReceive( 'full' )
			->once()
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

	public function test_should_fail_export_post_if_export_fails() {
		$id    = 12345;
		$error = new WP_Error( 'database_failed', 'Database failed.' );
		$this->export
			->shouldReceive( 'update' )
			->with( $id )
			->once()
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
		$success = 'Export succeeded.';
		$this->export
			->shouldReceive( 'update' )
			->once()
			->with( $id )
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

	public function test_should_fail_delete_post_if_export_fails() {
		$id    = 12345;
		$error = new WP_Error( 'database_failed', 'Database failed.' );
		$this->export
			->shouldReceive( 'delete' )
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

	public function test_should_should_delete_post() {
		$id      = 12345;
		$success = 'Export succeeded.';
		$this->export
			->shouldReceive( 'delete' )
			->once()
			->with( $id )
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

