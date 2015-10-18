<?php

/**
 * @group controller
 */
class WordPress_GitHub_Sync_Controller_Test extends WordPress_GitHub_Sync_TestCase {

	public function setUp() {
		parent::setUp();
		$this->controller = new WordPress_GitHub_Sync_Controller( $this->app );
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

	public function test_should_fail_if_cant_retreive_master() {
		$error = new WP_Error( 501, 'Api call failed' );
		$this->api
			->shouldReceive( 'last_commit' )
			->once()
			->andReturn( $error );
		$this->response
			->shouldReceive( 'log' )
			->with( $error )
			->once();

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
			->shouldReceive( 'log' )
			->once();

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
			->shouldReceive( 'log' )
			->once();

		$result = $this->controller->import_master();

		$this->assertFalse( $result );
	}

	public function test_should_import_commit() {
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
			->andReturn( true );
		$this->response
			->shouldReceive( 'log' )
			->once();

		$result = $this->controller->import_master();

		$this->assertTrue( $result );
	}

	public function test_should_be_formatted_for_query() {
		$method = new ReflectionMethod( 'WordPress_GitHub_Sync_Controller', 'format_for_query' );
		$method->setAccessible( true );

		$this->assertEquals( "'post', 'page'", $method->invoke( $this->controller, array( 'post', 'page' ) ) );
	}
}

