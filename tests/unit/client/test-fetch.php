<?php
use WordPress_GitHub_Sync_Base_Client as Base;

/**
 * @group api
 */
class WordPress_GitHub_Sync_Fetch_Client_Test extends WordPress_GitHub_Sync_Base_Client_Test {

	public function setUp() {
		parent::setUp();

		$this->fetch = new WordPress_GitHub_Sync_Fetch_Client( $this->app );
		$this->api_cache
			->shouldReceive( 'fetch_commit' )
			->andReturn( false )
			->byDefault();
		$this->api_cache
			->shouldReceive( 'fetch_tree' )
			->andReturn( false )
			->byDefault();
		$this->api_cache
			->shouldReceive( 'fetch_blob' )
			->andReturn( false )
			->byDefault();
	}

	public function test_should_fail_if_missing_token() {
		delete_option( Base::TOKEN_OPTION_KEY );

		$this->assertInstanceOf( 'WP_Error', $error = $this->fetch->master() );
		$this->assertSame( 'missing_token', $error->get_error_code() );
	}

	public function test_should_fail_if_missing_repo() {
		delete_option( Base::REPO_OPTION_KEY );

		$this->assertInstanceOf( 'WP_Error', $error = $this->fetch->master() );
		$this->assertSame( 'missing_repository', $error->get_error_code() );
	}

	public function test_should_fail_if_malformed_repo() {
		// If you find a particular name passing that shouldn't,
		// add it to the list here and make it pass.
		$this->malformed_repo( 'repositoryname' );
	}

	public function test_should_fail_if_cant_fetch_master() {
		$this->set_get_refs_heads_master( false );

		$this->assertInstanceOf( 'WP_Error', $error = $this->fetch->master() );
		$this->assertSame( '404_not_found', $error->get_error_code() );
	}

	public function test_should_return_commit_from_cache() {
		$this->api_cache
			->shouldReceive( 'fetch_commit' )
			->once()
			->with( 'db2510854e6aeab68ead26b48328b19f4bdf926e' )
			->andReturn( $this->commit );
		$this->set_get_refs_heads_master( true );

		$this->assertSame( $this->commit, $this->fetch->master() );
	}

	public function test_should_fail_if_cant_fetch_commit() {
		$this->set_get_refs_heads_master( true );
		$this->set_get_commits( false );

		$this->assertInstanceOf( 'WP_Error', $error = $this->fetch->master() );
		$this->assertSame( '404_not_found', $error->get_error_code() );
	}

	public function test_should_return_commit_with_tree_from_cache() {
		$this->set_get_refs_heads_master( true );
		$this->set_get_commits( true );
		$this->api_cache
			->shouldReceive( 'fetch_tree' )
			->once()
			->with( '9108868e3800bec6763e51beb0d33e15036c3626' )
			->andReturn( $this->tree );
		$this->api_cache
			->shouldReceive( 'set_commit' )
			->once()
			->with( 'db2510854e6aeab68ead26b48328b19f4bdf926e', Mockery::type( 'WordPress_GitHub_Sync_Commit' ) )
			->andReturnUsing( function ( $sha, $commit ) {
				return $commit;
			} );

		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Commit', $master = $this->fetch->master() );
		$this->assertSame( $this->tree, $master->tree() );
	}

	public function test_should_fail_if_cant_fetch_tree() {
		$this->set_get_refs_heads_master( true );
		$this->set_get_commits( true );
		$this->set_get_trees( false );

		$this->assertInstanceOf( 'WP_Error', $error = $this->fetch->master() );
		$this->assertSame( '422_unprocessable_entity', $error->get_error_code() );
	}

	public function test_should_return_commit_with_blobs_from_cache() {
		$this->set_get_refs_heads_master( true );
		$this->set_get_commits( true );
		$this->set_get_trees( true );
		$this->api_cache
			->shouldReceive( 'fetch_blob' )
			->times( 3 )
			->with( Mockery::anyOf(
				'9fa5c7537f8582b71028ff34b8c20dfd0f3b2a25',
				'8d9b2e6fd93761211dc03abd71f4a9189d680fd0',
				'2d73165945b0ccbe4932f1363457986b0ed49f19'
			) )
			->andReturn( $this->blob );
		$this->blob
			->shouldReceive( 'sha' )
			->times( 3 )
			->andReturnUsing( function () {
				static $count = 0;

				return $count ++;
			} );
		$this->blob
			->shouldReceive( 'path' )
			->times( 3 )
			->andReturnUsing( function () {
				static $count = 0;

				return $count ++ . '.md';
			} );
		$this->api_cache
			->shouldReceive( 'set_tree' )
			->once()
			->with( '9108868e3800bec6763e51beb0d33e15036c3626', Mockery::type( 'WordPress_GitHub_Sync_Tree' ) )
			->andReturnUsing( function ( $sha, $commit ) {
				return $commit;
			} );
		$this->api_cache
			->shouldReceive( 'set_commit' )
			->once()
			->with( 'db2510854e6aeab68ead26b48328b19f4bdf926e', Mockery::type( 'WordPress_GitHub_Sync_Commit' ) )
			->andReturnUsing( function ( $sha, $commit ) {
				return $commit;
			} );

		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Commit', $master = $this->fetch->master() );
		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Tree', $tree = $master->tree() );
		$this->assertCount( 3, $blobs = $tree->blobs() );

		foreach ( $blobs as $blob ) {
			$this->assertSame( $this->blob, $blob );
		}
	}

	public function test_should_return_commit_with_no_blobs_if_api_fails() {
		$this->set_get_refs_heads_master( true );
		$this->set_get_commits( true );
		$this->set_get_trees( true );
		$this->set_get_blobs( false );
		$this->api_cache
			->shouldReceive( 'set_tree' )
			->once()
			->with( '9108868e3800bec6763e51beb0d33e15036c3626', Mockery::type( 'WordPress_GitHub_Sync_Tree' ) )
			->andReturnUsing( function ( $sha, $tree ) {
				return $tree;
			} );
		$this->api_cache
			->shouldReceive( 'set_commit' )
			->once()
			->with( 'db2510854e6aeab68ead26b48328b19f4bdf926e', Mockery::type( 'WordPress_GitHub_Sync_Commit' ) )
			->andReturnUsing( function ( $sha, $commit ) {
				return $commit;
			} );

		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Commit', $master = $this->fetch->master() );
		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Tree', $tree = $master->tree() );
		$this->assertCount( 0, $tree->blobs() );
	}

	public function test_should_return_and_validate_full_commit() {
		$this->set_get_refs_heads_master( true );
		$this->set_get_commits( true );
		$this->set_get_trees( true );
		$this->set_get_blobs( true );
		$this->api_cache
			->shouldReceive( 'set_blob' )
			->times( 3 )
			->with(
				Mockery::anyOf(
					'9fa5c7537f8582b71028ff34b8c20dfd0f3b2a25',
					'8d9b2e6fd93761211dc03abd71f4a9189d680fd0',
					'2d73165945b0ccbe4932f1363457986b0ed49f19'
				),
				Mockery::type( 'WordPress_GitHub_Sync_Blob' )
			)
			->andReturnUsing( function ( $sha, $blob ) {
				return $blob;
			} );
		$this->api_cache
			->shouldReceive( 'set_tree' )
			->once()
			->with( '9108868e3800bec6763e51beb0d33e15036c3626', Mockery::type( 'WordPress_GitHub_Sync_Tree' ) )
			->andReturnUsing( function ( $sha, $tree ) {
				return $tree;
			} );
		$this->api_cache
			->shouldReceive( 'set_commit' )
			->once()
			->with( 'db2510854e6aeab68ead26b48328b19f4bdf926e', Mockery::type( 'WordPress_GitHub_Sync_Commit' ) )
			->andReturnUsing( function ( $sha, $commit ) {
				return $commit;
			} );

		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Commit', $master = $this->fetch->master() );

		/**
		 * Validate the commit's api data mapped correctly.
		 */
		$this->assertSame( '7497c0574b9430ff5e5521b4572b7452ea36a056', $master->sha() );
		$this->assertSame( 'test@test.com', $master->author()->email );
		$this->assertSame( '2015-11-02T00:36:54Z', $master->author()->date );
		$this->assertSame( 'test@test.com', $master->committer()->email );
		$this->assertSame( '2015-11-02T00:36:54Z', $master->committer()->date );
		$this->assertSame( 'Initial full site export - wpghs', $master->message() );
		$this->assertCount( 1, $parents = $master->parents() );
		$this->assertSame( 'db2510854e6aeab68ead26b48328b19f4bdf926e', $parents[0]->sha );

		$this->assertInstanceOf( 'WordPress_GitHub_Sync_Tree', $tree = $master->tree() );

		/**
		 * Validate the tree's api data mapped correctly.
		 */
		$this->assertSame( '9108868e3800bec6763e51beb0d33e15036c3626', $tree->sha() );

		$this->assertCount( 3, $blobs = $tree->blobs() );

		/**
		 * Validate the blobs' api data mapped correctly.
		 */
		$blobs = $tree->blobs();
		$this->assertCount( 3, $blobs );
		foreach ( $blobs as $blob ) {
			$this->assertTrue( in_array( $blob->sha(), array(
				'2d73165945b0ccbe4932f1363457986b0ed49f19',
				'8d9b2e6fd93761211dc03abd71f4a9189d680fd0',
				'9fa5c7537f8582b71028ff34b8c20dfd0f3b2a25',
			) ) );
			$this->assertTrue( in_array( $blob->path(), array(
				'_pages/sample-page.md',
				'_posts/2015-11-02-hello-world.md',
				'README.md',
			) ) );
		}
	}

	protected function malformed_repo( $repo ) {
		update_option( Base::REPO_OPTION_KEY, $repo );

		$this->assertInstanceOf( 'WP_Error', $error = $this->fetch->master() );
		$this->assertSame( 'malformed_repository', $error->get_error_code() );
	}
}
