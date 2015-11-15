<?php

/**
 * @group database
 */
class WordPress_GitHub_Sync_Database_Test extends WordPress_GitHub_Sync_TestCase {

	public function setUp() {
		parent::setUp();
		$this->database = new WordPress_GitHub_Sync_Database( $this->app );
		register_post_type( 'gistpen', array() );
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

	public function test_should_return_error_if_cant_find_sha() {
		$sha = '1234567890qwertyuiop';
		$this->factory->post->create();

		$this->assertInstanceOf( 'WP_Error', $error = $this->database->fetch_by_sha( $sha ) );
		$this->assertEquals( 'sha_not_found', $error->get_error_code() );
	}

	public function test_should_fetch_by_sha() {
		$sha     = '1234567890qwertyuiop';
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, '_sha', $sha );

		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Post', $post = $this->database->fetch_by_sha( $sha ) );
		$this->assertEquals( $post_id, $post->id );
	}

	public function test_should_save_new_post_with_provided_user() {
		$email   = 'test@test.com';
		$user_id = $this->factory->user->create( array( 'user_email' => $email, 'role' => 'administrator' ) );
		$sha     = '1234567890qwertyuiop';
		/** @var WP_Post $result_post */
		$result_post = '';
		$this->post
			->shouldReceive( 'is_new' )
			->twice()
			->andReturn( true );
		$this->post
			->shouldReceive( 'get_args' )
			->once()
			->andReturn( array(
				'post_content' => 'Post content',
				'post_title'   => 'Post title',
			) );
		$this->post
			->shouldReceive( 'get_meta' )
			->once()
			->andReturn( array( '_sha' => $sha ) );
		$this->post
			->shouldReceive( 'set_post' )
			->once()
			->with( Mockery::on( function ( $argument ) use ( &$result_post ) {
				$result_post = $argument;

				return $argument instanceof WP_Post;
			} ) );

		$this->database->save_posts( array( $this->post ), $email );

		$this->assertEquals( $user_id, $result_post->post_author );
		$this->assertEquals( $sha, get_post_meta( $result_post->ID, '_sha', true ) );
		$this->assertCount( 1, $revisions = wp_get_post_revisions( $result_post->ID ) );

		$revision = array_shift( $revisions );
		$this->assertEquals( $user_id, $revision->post_author );
	}

	public function test_should_save_new_post_with_default_user() {
		$email   = 'randomemailaddress@example.com';
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		update_option( 'wpghs_default_user', (int) $user_id );
		$this->factory->user->create( array( 'role' => 'administrator' ) );
		$sha = '1234567890qwertyuiop';
		/** @var WP_Post $result_post */
		$result_post = '';
		$this->post
			->shouldReceive( 'is_new' )
			->twice()
			->andReturn( true );
		$this->post
			->shouldReceive( 'get_args' )
			->once()
			->andReturn( array(
				'post_content' => 'Post content',
				'post_title'   => 'Post title',
			) );
		$this->post
			->shouldReceive( 'get_meta' )
			->once()
			->andReturn( array( '_sha' => $sha ) );
		$this->post
			->shouldReceive( 'set_post' )
			->once()
			->with( Mockery::on( function ( $argument ) use ( &$result_post ) {
				$result_post = $argument;

				return $argument instanceof WP_Post;
			} ) );

		$this->database->save_posts( array( $this->post ), $email );

		$this->assertEquals( $user_id, $result_post->post_author );
		$this->assertEquals( $sha, get_post_meta( $result_post->ID, '_sha', true ) );
		$this->assertCount( 1, $revisions = wp_get_post_revisions( $result_post->ID ) );

		$revision = array_shift( $revisions );
		$this->assertEquals( $user_id, $revision->post_author );
	}

	public function test_should_save_new_post_with_no_user() {
		$email   = 'randomemailaddress@example.com';
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$sha     = '1234567890qwertyuiop';
		/** @var WP_Post $result_post */
		$result_post = '';
		$this->post
			->shouldReceive( 'is_new' )
			->twice()
			->andReturn( true );
		$this->post
			->shouldReceive( 'get_args' )
			->once()
			->andReturn( array(
				'post_content' => 'Post content',
				'post_title'   => 'Post title',
			) );
		$this->post
			->shouldReceive( 'get_meta' )
			->once()
			->andReturn( array( '_sha' => $sha ) );
		$this->post
			->shouldReceive( 'set_post' )
			->once()
			->with( Mockery::on( function ( $argument ) use ( &$result_post ) {
				$result_post = $argument;

				return $argument instanceof WP_Post;
			} ) );

		$this->database->save_posts( array( $this->post ), $email );

		$this->assertEquals( 0, $result_post->post_author );
		$this->assertEquals( $sha, get_post_meta( $result_post->ID, '_sha', true ) );
		$this->assertCount( 1, $revisions = wp_get_post_revisions( $result_post->ID ) );

		$revision = array_shift( $revisions );
		$this->assertEquals( 0, $revision->post_author );
	}

	public function test_should_update_latest_post_revision_with_provided_user() {
		$email   = 'test@test.com';
		$user_id = $this->factory->user->create( array( 'user_email' => $email, 'role' => 'administrator' ) );
		$post_id = $this->factory->post->create();
		// create a revision for existing post
		wp_update_post( array( 'ID' => $post_id ), true );
		$sha = '1234567890qwertyuiop';
		/** @var WP_Post $result_post */
		$result_post = '';
		$this->post
			->shouldReceive( 'is_new' )
			->twice()
			->andReturn( false );
		$this->post
			->shouldReceive( 'get_args' )
			->once()
			->andReturn( array(
				'post_content' => 'New post content',
				'post_title'   => 'New post title',
				'ID'           => $post_id,
			) );
		$this->post
			->shouldReceive( 'get_meta' )
			->once()
			->andReturn( array( '_sha' => $sha ) );
		$this->post
			->shouldReceive( 'set_post' )
			->once()
			->with( Mockery::on( function ( $argument ) use ( &$result_post ) {
				$result_post = $argument;

				return $argument instanceof WP_Post;
			} ) );

		$this->database->save_posts( array( $this->post ), $email );

		$this->assertEquals( 0, $result_post->post_author );
		$this->assertEquals( $sha, get_post_meta( $result_post->ID, '_sha', true ) );
		$this->assertCount( 2, $revisions = wp_get_post_revisions( $result_post->ID ) );

		$revision = array_shift( $revisions );
		$this->assertEquals( $user_id, $revision->post_author );
		$this->assertEquals( $result_post->post_content, $revision->post_content );
		$revision = array_shift( $revisions );
		$this->assertEquals( 0, $revision->post_author );
	}

	public function test_should_update_latest_post_revision_with_default_user() {
		$email   = 'randomemailaddress@example.com';
		$post_id = $this->factory->post->create();
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		update_option( 'wpghs_default_user', (int) $user_id );
		$this->factory->user->create( array( 'role' => 'administrator' ) );
		// create a revision for existing post
		wp_update_post( array( 'ID' => $post_id ) );
		$sha = '1234567890qwertyuiop';
		/** @var WP_Post $result_post */
		$result_post = '';
		$this->post
			->shouldReceive( 'is_new' )
			->twice()
			->andReturn( false );
		$this->post
			->shouldReceive( 'get_args' )
			->once()
			->andReturn( array(
				'post_content' => 'New post content',
				'post_title'   => 'New post title',
				'ID'           => $post_id,
			) );
		$this->post
			->shouldReceive( 'get_meta' )
			->once()
			->andReturn( array( '_sha' => $sha ) );
		$this->post
			->shouldReceive( 'set_post' )
			->once()
			->with( Mockery::on( function ( $argument ) use ( &$result_post ) {
				$result_post = $argument;

				return $argument instanceof WP_Post;
			} ) );

		$this->database->save_posts( array( $this->post ), $email );

		$this->assertEquals( 0, $result_post->post_author );
		$this->assertEquals( $sha, get_post_meta( $result_post->ID, '_sha', true ) );
		$this->assertCount( 2, $revisions = wp_get_post_revisions( $result_post->ID ) );

		$revision = array_shift( $revisions );
		$this->assertEquals( $user_id, $revision->post_author );
		$this->assertEquals( $result_post->post_content, $revision->post_content );
		$revision = array_shift( $revisions );
		$this->assertEquals( 0, $revision->post_author );
	}

	public function test_should_update_latest_post_revision_with_no_user() {
		$email   = 'randomemailaddress@example.com';
		$post_id = $this->factory->post->create();
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		// create a revision for existing post
		wp_update_post( array( 'ID' => $post_id ) );
		$sha = '1234567890qwertyuiop';
		/** @var WP_Post $result_post */
		$result_post = '';
		$this->post
			->shouldReceive( 'is_new' )
			->twice()
			->andReturn( false );
		$this->post
			->shouldReceive( 'get_args' )
			->once()
			->andReturn( array(
				'post_content' => 'New post content',
				'post_title'   => 'New post title',
				'ID'           => $post_id,
			) );
		$this->post
			->shouldReceive( 'get_meta' )
			->once()
			->andReturn( array( '_sha' => $sha ) );
		$this->post
			->shouldReceive( 'set_post' )
			->once()
			->with( Mockery::on( function ( $argument ) use ( &$result_post ) {
				$result_post = $argument;

				return $argument instanceof WP_Post;
			} ) );

		$this->database->save_posts( array( $this->post ), $email );

		$this->assertEquals( 0, $result_post->post_author );
		$this->assertEquals( $sha, get_post_meta( $result_post->ID, '_sha', true ) );
		$this->assertCount( 2, $revisions = wp_get_post_revisions( $result_post->ID ) );

		$revision = array_shift( $revisions );
		$this->assertEquals( 0, $revision->post_author );
		$this->assertEquals( $result_post->post_content, $revision->post_content );
		$revision = array_shift( $revisions );
		$this->assertEquals( 0, $revision->post_author );
	}

	public function test_should_return_error_if_path_not_found() {
		$post_id = $this->factory->post->create();

		$result = $this->database->delete_post_by_path( '_posts/2015-10-22-new-post.md' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'path_not_found', $result->get_error_code() );
		$this->assertEquals( 'publish', get_post( $post_id )->post_status );
	}

	public function test_should_delete_post_matching_path() {
		$path    = '_posts/2015-10-22-post-title.md';
		$post_id = $this->factory->post->create();
		update_post_meta( $post_id, '_wpghs_github_path', $path );

		$result = $this->database->delete_post_by_path( $path );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( 'Successfully deleted post ID', $result );
		$this->assertEquals( 'trash', get_post( $post_id )->post_status );
	}

	public function test_should_delete_post_by_matching_title() {
		$path    = '_posts/2015-10-22-post-title.md';
		$post_id = $this->factory->post->create( array( 'post_title' => 'Post title' ) );

		$result = $this->database->delete_post_by_path( $path );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( 'Successfully deleted post ID', $result );
		$this->assertEquals( 'trash', get_post( $post_id )->post_status );
	}

	public function test_should_delete_page_by_matching_title() {
		$path    = '_pages/page-title.md';
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Page title',
			'post_type'  => 'page',
		) );

		$result = $this->database->delete_post_by_path( $path );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( 'Successfully deleted post ID', $result );
		$this->assertEquals( 'trash', get_post( $post_id )->post_status );
	}

	public function test_should_delete_page_in_root() {
		$path    = 'page-title.md';
		$post_id = $this->factory->post->create( array(
				'post_title' => 'Page title',
				'post_type'  => 'page',
		) );

		$result = $this->database->delete_post_by_path( $path );

		$this->assertInternalType( 'string', $result );
		$this->assertContains( 'Successfully deleted post ID', $result );
		$this->assertEquals( 'trash', get_post( $post_id )->post_status );
	}

	public function test_should_update_post_sha() {
		$post_id = $this->factory->post->create();
		$post    = new WordPress_GitHub_Sync_Post( $post_id, $this->api );
		$sha     = '1234567890qwertyuiop';

		$this->database->set_post_sha( $post, $sha );

		$this->assertSame( $sha, get_post_meta( $post_id, '_sha', true ) );
	}
}
