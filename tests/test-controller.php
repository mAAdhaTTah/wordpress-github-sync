<?php

/**
 * @group controller
 */
class WordPress_GitHub_Sync_Controller_Test extends WP_UnitTestCase {

	/**
	 * @var WordPress_GitHub_Sync|Mockery\Mock
	 */
	protected $app;

	/**
	 * @var WordPress_GitHub_Sync_Request|Mockery\Mock
	 */
	protected $request;

	/**
	 * @var WordPress_GitHub_Sync_Import|Mockery\Mock
	 */
	protected $import;

	/**
	 * @var WordPress_GitHub_Sync_Response|Mockery\Mock
	 */
	protected $response;

	/**
	 * @var WordPress_GitHub_Sync_Payload|Mockery\Mock
	 */
	protected $payload;

	/**
	 * @var WordPress_GitHub_Sync_Controller
	 */
	protected $controller;

	public function setUp() {
		parent::setUp();
		$this->app      = Mockery::mock( 'WordPress_GitHub_Sync' );
		$this->request  = Mockery::mock( 'WordPress_GitHub_Sync_Request' );
		$this->import   = Mockery::mock( 'WordPress_GitHub_Sync_Import' );
		$this->response = Mockery::mock( 'WordPress_GitHub_Sync_Response' );
		$this->payload  = Mockery::mock( 'WordPress_GitHub_Sync_Payload' );

		$this->app
			->shouldReceive( 'request' )
			->andReturn( $this->request )
			->byDefault();
		$this->app
			->shouldReceive( 'import' )
			->andReturn( $this->import )
			->byDefault();
		$this->app
			->shouldReceive( 'response' )
			->andReturn( $this->response )
			->byDefault();

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

	public function test_should_fail_if_import_fails() {
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

	public function test_should_be_formatted_for_query() {
		$method = new ReflectionMethod( 'WordPress_GitHub_Sync_Controller', 'format_for_query' );
		$method->setAccessible( true );

		$this->assertEquals( "'post', 'page'", $method->invoke( $this->controller, array( 'post', 'page' ) ) );
	}
}

