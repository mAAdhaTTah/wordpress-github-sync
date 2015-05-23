<?php
/**
 * @group controller
 */
class WordPress_GitHub_Sync_Controller_Test extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->controller = new WordPress_GitHub_Sync_Controller;
	}

	public function test_should_be_formatted_for_query() {
		$method = new ReflectionMethod( 'WordPress_GitHub_Sync_Controller', 'format_for_query' );
		$method->setAccessible( true );

		$this->assertEquals( "'post', 'page'", $method->invoke( $this->controller, array( 'post', 'page' ) ) );
	}
}

