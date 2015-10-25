<?php

/**
 * @group database
 */
class WordPress_GitHub_Sync_Database_Test extends WordPress_GitHub_Sync_TestCase {

	public function setUp() {
		parent::setUp();
		$this->database = new WordPress_GitHub_Sync_Database( $this->app );
	}

	public function test_should_return_error_if_no_post_found() {
		$this->assertInstanceOf( 'WP_Error', $error = $this->database->fetch_all_supported() );
		$this->assertEquals( 'no_results', $error->get_error_code() );
	}

	public function test_should_only_fetch_published() {
		$this->factory->post->create( array(
			'post_status' => 'publish',
		) );
		$this->factory->post->create( array(
			'post_status' => 'draft',
		) );

		$result = $this->database->fetch_all_supported();

		$this->assertInternalType( 'array', $result );
		$this->assertCount( 1, $result );
	}

	public function test_should_only_fetch_page_and_post() {
		$this->factory->post->create( array( 'post_type' => 'post' ) );
		$this->factory->post->create( array( 'post_type' => 'page' ) );
		$this->factory->post->create( array( 'post_type' => 'gistpen' ) );

		$result = $this->database->fetch_all_supported();

		$this->assertInternalType( 'array', $result );
		$this->assertCount( 2, $result );
	}

	public function test_should_return_error_when_fetching_revision() {
		$post_id = $this->factory->post->create( array(
			'post_type'   => 'revision',
			'post_parent' => 1,
		) );

		$this->assertInstanceOf( 'WP_Error', $error = $this->database->fetch_by_id( $post_id ) );
		$this->assertEquals( 'unsupported_post', $error->get_error_code() );
	}

	public function test_should_return_error_when_fetching_unsupported_status() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );

		$this->assertInstanceOf( 'WP_Error', $error = $this->database->fetch_by_id( $post_id ) );
		$this->assertEquals( 'unsupported_post', $error->get_error_code() );
	}

	public function test_should_return_error_when_fetching_unsupported_type() {
		$post_id = $this->factory->post->create( array( 'post_type' => 'gistpen' ) );

		$this->assertInstanceOf( 'WP_Error', $error = $this->database->fetch_by_id( $post_id ) );
		$this->assertEquals( 'unsupported_post', $error->get_error_code() );
	}

	public function test_should_return_error_when_fetching_post_with_password() {
		$post_id = $this->factory->post->create( array( 'post_password' => 'password' ) );

		$this->assertInstanceOf( 'WP_Error', $error = $this->database->fetch_by_id( $post_id ) );
		$this->assertEquals( 'unsupported_post', $error->get_error_code() );
	}

	public function test_should_fetch_by_id() {
		$post_id = $this->factory->post->create();

		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Post', $post = $this->database->fetch_by_id( $post_id ) );
		$this->assertEquals( $post_id, $post->id );
	}
}
