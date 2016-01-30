<?php

/**
 * @group managers
 */
class WordPress_GitHub_Sync_Import_Test extends WordPress_GitHub_Sync_TestCase {

	/**
	 * @var string
	 */
	protected $sha = '1234567890asdfghjklqwertyuiopzxcvbnm';

	/**
	 * @var string
	 */
	protected $blob_content = '';

	/**
	 * @var array
	 */
	protected $blob_meta = array();

	/**
	 * @var array
	 */
	protected $post_meta = array();

	/**
	 * @var array
	 */
	protected $post_args = array();

	public function setUp() {
		parent::setUp();

		$this->import = new WordPress_GitHub_Sync_Import( $this->app );
	}

	public function test_should_fail_payload_import_if_api_fails() {
		$error = new WP_Error( 'api_failed', 'Api failed.' );
		$this->payload
			->shouldReceive( 'get_commit_id' )
			->once()
			->andReturn( $this->sha );
		$this->fetch
			->shouldReceive( 'commit' )
			->with( $this->sha )
			->once()
			->andReturn( $error );
		$this->set_payload_expectations();

		$this->assertEquals( $error, $this->import->payload( $this->payload ) );
	}

	public function test_should_successfully_import_payload() {
		$this->blob_content = 'Post content.';
		$this->blob_meta    = array();
		$this->post_meta    = array(
			'_sha' => $this->sha,
		);
		$this->post_args    = array(
			'post_content' => $this->blob_content,
		);
		$this->payload
			->shouldReceive( 'get_commit_id' )
			->once()
			->andReturn( $this->sha );
		$this->fetch
			->shouldReceive( 'commit' )
			->with( $this->sha )
			->once()
			->andReturn( $this->commit );
		$this->set_blob_expectations();
		$this->fetch
			->shouldReceive( 'master' )
			->never();
		$this->set_payload_expectations();

		$this->assertEquals( 'Payload processed', $this->import->payload( $this->payload ) );
	}

	public function test_should_fail_master_import_if_api_fails() {
		$error = new WP_Error( 'api_failed', 'Api failed.' );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $error );

		$this->assertEquals( $error, $this->import->master() );
	}

	public function test_should_fail_master_import_if_commit_synced() {
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit );
		$this->commit
			->shouldReceive( 'already_synced' )
			->once()
			->andReturn( true );

		$this->assertInstanceOf( 'WP_Error', $error = $this->import->master() );
		$this->assertEquals( 'commit_synced', $error->get_error_code() );
	}

	public function test_should_save_new_post_with_empty_meta() {
		$this->blob_content = 'Post content.';
		$this->blob_meta    = array();
		$this->post_meta    = array(
			'_sha' => $this->sha,
		);
		$this->post_args    = array(
			'post_content' => $this->blob_content,
		);

		$this->validate_meta();
	}

	public function test_should_save_layout_as_post_type() {
		$this->blob_content = 'Post content.';
		$this->blob_meta    = array(
			'layout' => 'custom_type'
		);
		$this->post_meta    = array(
			'_sha' => $this->sha,
		);
		$this->post_args    = array(
			'post_content' => $this->blob_content,
			'post_type'    => 'custom_type',
		);

		$this->validate_meta();
	}

	public function test_should_save_published_as_post_status() {
		$this->blob_content = 'Post content.';
		$this->blob_meta    = array(
			'published' => true
		);
		$this->post_meta    = array(
			'_sha' => $this->sha,
		);
		$this->post_args    = array(
			'post_content' => $this->blob_content,
			'post_status'  => 'publish',
		);

		$this->validate_meta();
	}

	public function test_should_save_post_title_as_post_title() {
		$this->blob_content = 'Post content.';
		$this->blob_meta    = array(
			'post_title' => 'Post title'
		);
		$this->post_meta    = array(
			'_sha' => $this->sha,
		);
		$this->post_args    = array(
			'post_content' => $this->blob_content,
			'post_title'   => 'Post title',
		);

		$this->validate_meta();
	}

	public function test_should_save_ID_as_post_id() {
		$id = $this->factory->post->create();
		$this->blob_content = 'Post content.';
		$this->blob_meta    = array( 'ID' => $id );
		$this->post_meta    = array( '_sha' => $this->sha );
		$this->post_args    = array(
			'post_content' => $this->blob_content,
			'ID'           => $id,
		);

		$this->validate_meta();
	}

	public function test_should_save_blob_meta_as_meta() {
		$this->blob_content = 'Post content.';
		$this->blob_meta    = array(
			'link_url' => 'http://jamesdigioia.com/',
		);
		$this->post_meta    = array(
			'_sha'     => $this->sha,
			'link_url' => 'http://jamesdigioia.com/',
		);
		$this->post_args    = array(
			'post_content' => $this->blob_content,
		);

		$this->validate_meta();
	}

	public function validate_meta() {
		$this->set_blob_expectations();

		$posts = $this->import->master();
		$this->assertCount( 1, $posts );

		$post = array_pop( $posts );

		$this->assertEquals( $this->post_meta, $post->get_meta() );
		$this->assertEquals( $this->post_args, $post->get_args() );
	}

	protected function set_blob_expectations() {
		$email = 'mAAdhaTTah@github';
		$blobs = array( $this->blob );
		$this->fetch
			->shouldReceive( 'master' )
			->once()
			->andReturn( $this->commit )
			->byDefault();
		$this->commit
			->shouldReceive( 'already_synced' )
			->once()
			->andReturn( false );
		$this->commit
			->shouldReceive( 'tree' )
			->andReturn( $this->tree );
		$this->tree
			->shouldReceive( 'blobs' )
			->once()
			->andReturn( $blobs );
		$this->blob
			->shouldReceive( 'content_import' )
			->once()
			->andReturn( $this->blob_content );
		$this->blob
			->shouldReceive( 'meta' )
			->once()
			->andReturn( $this->blob_meta );
		$this->blob
			->shouldReceive( 'sha' )
			->twice()
			->andReturn( $this->sha );
		$this->blob
			->shouldReceive( 'path' )
			->once()
			->andReturn( '_posts/2015-10-25-new-post.md' );
		$this->database
			->shouldReceive( 'fetch_by_sha' )
			->once()
			->with( $this->sha )
			->andReturn( new WP_Error( 'sha_not_found' ) );
		$this->blob
			->shouldReceive( 'has_frontmatter' )
			->once()
			->andReturn( true );
		$this->commit
			->shouldReceive( 'author_email' )
			->once()
			->andReturn( $email );
		$this->database
			->shouldReceive( 'save_posts' )
			->once()
			->with( Mockery::on( function ( $argument ) {
				if ( count( $argument ) !== 1 ) {
					return false;
				}

				return $argument[0] instanceof WordPress_GitHub_Sync_Post;

			} ), $email );

		if ( ! isset( $this->blob_meta['ID'] ) ) {
			$msg = 'Commit message';
			add_filter( 'wpghs_commit_msg_new_posts', function () use ( $msg ) {
				return $msg;
			} );
			$this->export
				->shouldReceive( 'new_posts' )
				->once()
				->with( Mockery::on( function ( $argument ) {
					if ( count( $argument ) !== 1 ) {
						return false;
					}

					return $argument[0] instanceof WordPress_GitHub_Sync_Post;

				} ) )
				->andReturn( 'Successful export' );
		}
	}

	protected function set_payload_expectations() {
		$path                    = '_post/2015-10-24-post-to-delete.md';
		$payload_commit          = new stdClass;
		$payload_commit->removed = array( $path );
		$this->payload
			->shouldReceive( 'get_commits' )
			->once()
			->andReturn( array( $payload_commit ) );
		$this->database
			->shouldReceive( 'delete_post_by_path' )
			->with( $path )
			->once();
	}
}
