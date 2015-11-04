<?php
use WordPress_GitHub_Sync_Base_Client as Base;

/**
 * @group api
 */
class WordPress_GitHub_Sync_Persist_Client_Test extends WordPress_GitHub_Sync_Base_Client_Test {

	public function setUp() {
		parent::setUp();

		$this->persist = new WordPress_GitHub_Sync_Persist_Client( $this->app );
		$this->commit
			->shouldReceive( 'tree' )
			->andReturn( $this->tree );
	}

	public function test_should_not_add_commit_if_no_change() {
		$this->tree
			->shouldReceive( 'is_changed' )
			->once()
			->andReturn( false );

		$this->assertWPError( $error = $this->persist->commit( $this->commit ) );
		$this->assertSame( 'no_commit', $error->get_error_code() );
	}

	public function test_should_fail_if_create_tree_fails() {
		$this->tree
			->shouldReceive( 'is_changed' )
			->once()
			->andReturn( true );
		$this->tree
			->shouldReceive( 'to_body' )
			->once()
			->andReturn(
				array(
					'tree' => array(
						array(
							'path'    => '_posts/2015-10-23-new-post.md',
							'type'    => 'blob',
							'content' => 'Post content 1',
							'mode'    => '100644',
						)
					)
				)
			);
		$this->set_post_trees( false );

		$this->assertWPError( $error = $this->persist->commit( $this->commit ) );
		$this->assertSame( '404_not_found', $error->get_error_code() );
	}

	public function test_should_fail_if_create_commit_fails() {
		$this->tree
			->shouldReceive( 'is_changed' )
			->once()
			->andReturn( true );
		$this->tree
			->shouldReceive( 'to_body' )
			->once()
			->andReturn(
				array(
					'tree' => array(
						array(
							'path'    => '_posts/2015-10-23-new-post.md',
							'type'    => 'blob',
							'content' => 'Post content 1',
							'mode'    => '100644',
						)
					)
				)
			);
		$this->commit
			->shouldReceive( 'sha' )
			->once()
			->andReturn( '1234567890qwertyuiop' );
		$this->commit
			->shouldReceive( 'message' )
			->once()
			->andReturn( 'Commit message' );
		$this->set_post_trees( true );
		$this->set_post_commits( false );

		$this->assertWPError( $error = $this->persist->commit( $this->commit ) );
		$this->assertSame( '404_not_found', $error->get_error_code() );
	}

	public function test_should_fail_if_update_master_fails() {
		$this->tree
			->shouldReceive( 'is_changed' )
			->once()
			->andReturn( true );
		$this->tree
			->shouldReceive( 'to_body' )
			->once()
			->andReturn(
				array(
					'tree' => array(
						array(
							'path'    => '_posts/2015-10-23-new-post.md',
							'type'    => 'blob',
							'content' => 'Post content 1',
							'mode'    => '100644',
						)
					)
				)
			);
		$this->commit
			->shouldReceive( 'sha' )
			->once()
			->andReturn( '1234567890qwertyuiop' );
		$this->commit
			->shouldReceive( 'message' )
			->once()
			->andReturn( 'Commit message' );
		$this->set_post_trees( true );
		$this->set_post_commits( true );
		$this->set_patch_refs_heads_master( false );

		$this->assertWPError( $error = $this->persist->commit( $this->commit ) );
		$this->assertSame( '404_not_found', $error->get_error_code() );
	}

	public function test_should_create_anonymous_commit() {
		$this->tree
			->shouldReceive( 'is_changed' )
			->once()
			->andReturn( true );
		$this->tree
			->shouldReceive( 'to_body' )
			->once()
			->andReturn(
				array(
					'tree' => array(
						array(
							'path'    => '_posts/2015-10-23-new-post.md',
							'type'    => 'blob',
							'content' => 'Post content 1',
							'mode'    => '100644',
						)
					)
				)
			);
		$this->commit
			->shouldReceive( 'sha' )
			->once()
			->andReturn( '1234567890qwertyuiop' );
		$this->commit
			->shouldReceive( 'message' )
			->once()
			->andReturn( 'Commit message' );
		$this->set_post_trees( true );
		$this->set_post_commits( true );
		$this->set_patch_refs_heads_master( true );

		$this->assertTrue( $this->persist->commit( $this->commit ) );
	}

	public function test_should_create_authored_commit() {
		$this->tree
			->shouldReceive( 'is_changed' )
			->once()
			->andReturn( true );
		$this->tree
			->shouldReceive( 'to_body' )
			->once()
			->andReturn(
				array(
					'tree' => array(
						array(
							'path'    => '_posts/2015-10-23-new-post.md',
							'type'    => 'blob',
							'content' => 'Post content 1',
							'mode'    => '100644',
						)
					)
				)
			);
		$this->commit
			->shouldReceive( 'sha' )
			->once()
			->andReturn( '1234567890qwertyuiop' );
		$this->commit
			->shouldReceive( 'message' )
			->once()
			->andReturn( 'Commit message' );
		$this->set_post_trees( true );
		update_option( '_wpghs_export_user_id', $this->factory->user->create( array(
			'display_name' => 'James DiGioia',
			'user_email'   => 'jamesorodig@gmail.com',
		) ) );
		$this->set_post_commits( true, false );
		$this->set_patch_refs_heads_master( true );

		$this->assertTrue( $this->persist->commit( $this->commit ) );
	}
}
