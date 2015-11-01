<?php

/**
 * @group api
 */
class WordPress_GitHub_Sync_Api_Test extends WordPress_GitHub_Sync_TestCase {
	public function setUp() {
		parent::setUp();

		$this->api = new WordPress_GitHub_Sync_Api( $this->app );
	}

	public function test_should_load_fetch() {
		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Fetch_Client', $this->api->fetch() );
	}

	public function test_should_load_persist() {
		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Persist_Client', $this->api->persist() );
	}
}

