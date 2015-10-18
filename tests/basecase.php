<?php

class WordPress_GitHub_Sync_Base_TestCase extends WP_HTTP_TestCase {

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

	public function setUp() {
		parent::setUp();

		$this->app        = Mockery::mock( 'WordPress_GitHub_Sync' );
		$this->controller = Mockery::mock( 'WordPress_GitHub_Sync_Controller' );
		$this->request    = Mockery::mock( 'WordPress_GitHub_Sync_Request' );
		$this->import     = Mockery::mock( 'WordPress_GitHub_Sync_Import' );
		$this->response   = Mockery::mock( 'WordPress_GitHub_Sync_Response' );
		$this->payload    = Mockery::mock( 'WordPress_GitHub_Sync_Payload' );
		$this->api        = Mockery::mock( 'WordPress_GitHub_Sync_Api' );
		$this->commit     = Mockery::mock( 'WordPress_GitHub_Sync_Commit' );

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
			->shouldReceive( 'response' )
			->andReturn( $this->response )
			->byDefault();
		$this->app
			->shouldReceive( 'api' )
			->andReturn( $this->api )
			->byDefault();
	}
}
