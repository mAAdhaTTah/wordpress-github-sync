<?php

class WordPress_GitHub_Sync_Database_Test extends WordPress_GitHub_Sync_TestCase {

	public function setUp() {
		parent::setUp();
		$this->database = new WordPress_GitHub_Sync_Database( $this->app );
	}

	public function test_should_be_formatted_for_query() {
		$method = new ReflectionMethod( 'WordPress_GitHub_Sync_Database', 'format_for_query' );
		$method->setAccessible( true );

		$this->assertEquals( "'post', 'page'", $method->invoke( $this->database, array( 'post', 'page' ) ) );
	}
}
