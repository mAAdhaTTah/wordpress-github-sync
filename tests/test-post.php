<?php

class WordPress_GitHub_Sync_Post_Test extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		$this->id = $this->factory->post->create();
	}

	function test_should_get_id_by_path() {
		$path = 'thepath';
		update_post_meta( $this->id, '_wpghs_github_path', $path );

		$post = new WordPress_GitHub_Sync_Post( $path );

		$this->assertEquals( $this->id, $post->id );
	}
}

