<?php

/**
 * @group payload
 */
class WordPress_GitHub_Sync_Payload_Test extends WordPress_GitHub_Sync_TestCase {

	public function setUp() {
		parent::setUp();
		$this->fetch
			->shouldReceive( 'repository' )
			->once()
			->andReturn( 'owner/repo' )
			->byDefault();
	}

	public function test_should_not_import_invalid_repository() {
		$payload = new WordPress_GitHub_Sync_Payload(
			$this->app,
			file_get_contents( $this->data_dir . 'payload-invalid-repo.json' )
		);

		$this->assertFalse( $payload->should_import() );
	}

	public function test_should_not_import_invalid_branch() {
		$payload = new WordPress_GitHub_Sync_Payload(
			$this->app,
			file_get_contents( $this->data_dir . 'payload-invalid-branch.json' )
		);

		$this->assertFalse( $payload->should_import() );
	}

	public function test_should_not_import_synced_commit() {
		$payload = new WordPress_GitHub_Sync_Payload(
			$this->app,
			file_get_contents( $this->data_dir . 'payload-synced-commit.json' )
		);

		$this->assertFalse( $payload->should_import() );
	}

	public function test_should_be_valid_payload() {
		$payload = new WordPress_GitHub_Sync_Payload(
			$this->app,
			file_get_contents( $this->data_dir . 'payload-valid.json' )
		);

		$this->assertTrue( $payload->should_import() );
		$this->assertEquals( 'ad4e0a9e2597de40106d5f52e2041d8ebaeb0087', $payload->get_commit_id() );
		$this->assertEquals( 'username@github.com', $payload->get_author_email() );
		$this->assertCount( 1, $commits = $payload->get_commits() );
		$this->assertEquals( 'ad4e0a9e2597de40106d5f52e2041d8ebaeb0087', $commits[0]->id );

	}
}
