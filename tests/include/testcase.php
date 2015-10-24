<?php

abstract class WordPress_GitHub_Sync_TestCase extends WP_HTTP_TestCase {

	/**
	 * @var WordPress_GitHub_Sync|Mockery\Mock
	 */
	protected $app;

	/**
	 * @var WordPress_GitHub_Sync_Controller|Mockery\Mock
	 */
	protected $controller;

	/**
	 * @var WordPress_GitHub_Sync_Request|Mockery\Mock
	 */
	protected $request;

	/**
	 * @var WordPress_GitHub_Sync_Import|Mockery\Mock
	 */
	protected $import;

	/**
	 * @var WordPress_GitHub_Sync_Export|Mockery\Mock
	 */
	protected $export;

	/**
	 * @var WordPress_GitHub_Sync_Response|Mockery\Mock
	 */
	protected $response;

	/**
	 * @var WordPress_GitHub_Sync_Payload|Mockery\Mock
	 */
	protected $payload;

	/**
	 * @var WordPress_GitHub_Sync_Api|Mockery\Mock
	 */
	protected $api;

	/**
	 * @var WordPress_GitHub_Sync_Commit|Mockery\Mock
	 */
	protected $commit;

	/**
	 * @var WordPress_GitHub_Sync_Semaphore|Mockery\Mock
	 */
	protected $semaphore;

	/**
	 * @var WordPress_GitHub_Sync_Database|Mockery\Mock
	 */
	protected $database;

	/**
	 * @var WordPress_GitHub_Sync_Post|Mockery\Mock
	 */
	protected $post;

	/**
	 * @var WordPress_GitHub_Sync_Tree|Mockery\Mock
	 */
	protected $tree;

	/**
	 * @var WordPress_GitHub_Sync_Blob|Mockery\Mock
	 */
	protected $blob;

	public function setUp() {
		parent::setUp();

		$this->app        = Mockery::mock( 'WordPress_GitHub_Sync' );
		$this->controller = Mockery::mock( 'WordPress_GitHub_Sync_Controller' );
		$this->request    = Mockery::mock( 'WordPress_GitHub_Sync_Request' );
		$this->import     = Mockery::mock( 'WordPress_GitHub_Sync_Import' );
		$this->export     = Mockery::mock( 'WordPress_GitHub_Sync_Export' );
		$this->response   = Mockery::mock( 'WordPress_GitHub_Sync_Response' );
		$this->payload    = Mockery::mock( 'WordPress_GitHub_Sync_Payload' );
		$this->api        = Mockery::mock( 'WordPress_GitHub_Sync_Api' );
		$this->commit     = Mockery::mock( 'WordPress_GitHub_Sync_Commit' );
		$this->semaphore  = Mockery::mock( 'WordPress_GitHub_Sync_Semaphore' );
		$this->database   = Mockery::mock( 'WordPress_GitHub_Sync_Database' );
		$this->post       = Mockery::mock( 'WordPress_GitHub_Sync_Post' );
		$this->tree       = Mockery::mock( 'WordPress_GitHub_Sync_Tree' );
		$this->blob       = Mockery::mock( 'WordPress_GitHub_Sync_Blob' );

		global $wpghs;
		$wpghs = $this->app;

		$this->app
			->shouldReceive( 'request' )
			->andReturn( $this->request )
			->byDefault();
		$this->app
			->shouldReceive( 'import' )
			->andReturn( $this->import )
			->byDefault();
		$this->app
			->shouldReceive( 'export' )
			->andReturn( $this->export )
			->byDefault();
		$this->app
			->shouldReceive( 'response' )
			->andReturn( $this->response )
			->byDefault();
		$this->app
			->shouldReceive( 'api' )
			->andReturn( $this->api )
			->byDefault();
		$this->app
			->shouldReceive( 'semaphore' )
			->andReturn( $this->semaphore )
			->byDefault();
		$this->app
			->shouldReceive( 'database' )
			->andReturn( $this->database )
			->byDefault();
		$this->app
			->shouldReceive( 'blob' )
			->andReturn( $this->blob )
			->byDefault();
	}

	public function tearDown() {
		Mockery::close();
	}
}
