<?php

class WordPress_GitHub_Sync_Api_Test extends WordPress_GitHub_Sync_TestCase {

	public function setUp() {
		parent::setUp();
		WP_HTTP_TestCase::init();
		update_option( 'wpghs_repository', 'person/repo' );
		update_option( 'wpghs_oauth_token', 'the-token' );
		update_option( 'wpghs_host', 'github.api' );
		$this->http_responder = array( $this, 'mock_github_api' );
		$this->api = new WordPress_GitHub_Sync_Api( $this->app );
	}

	public function test_should_return_blob() {
		$response = $this->api->get_blob( '123' );

		$this->assertCount( 1, $this->http_requests );
		$this->assertEquals( 'Content of the blob', $response->content );
		$this->assertEquals( '3a0f86fb8db8eea7ccbb9a95f325ddbedfb25e15', $response->sha );
	}

	public function test_should_return_tree() {
		$tree = $this->api->get_tree_recursive( '123' );

		$this->assertCount( 1, $this->http_requests );
		$this->assertInstanceOf( 'WordPress_GitHub_Sync_tree', $tree );
		$this->assertCount( 2, $tree->get_data() );
	}

	public function test_should_return_commit() {
		$commit = $this->api->get_commit( '123' );

		$this->assertCount( 1, $this->http_requests );
		$this->assertEquals( "added readme, because im a good github citizen\n", $commit->message() );
		$this->assertEquals( '7638417db6d59f3c431d3e1f261cc637155684cd', $commit->sha() );
	}

	public function test_should_return_master_reference() {
		$response = $this->api->last_commit_sha();

		$this->assertCount( 1, $this->http_requests );
		$this->assertEquals( 'aa218f56b14c9653891f9e74264a383fa43fefbd', $response );
	}

	public function test_should_create_tree() {
		$this->tree
			->shouldReceive( 'to_body' )
			->once()
			->andReturn( array( 'tree' => array() ) );
		$response = $this->api->create_tree( $this->tree );

		$this->assertCount( 1, $this->http_requests );
		$this->assertEquals( 'cd8274d15fa3ae2ab983129fb037999f264ba9a7', $response->sha );
	}

	public function test_should_create_commit() {
		$response = $this->api->create_commit_by_sha( '1234', 'New Commit' );

		$this->assertCount( 2, $this->http_requests );
		$this->assertEquals( '7638417db6d59f3c431d3e1f261cc637155684cd', $response->sha );
	}

	public function test_should_update_master_reference() {
		$response = $this->api->set_ref( '123' );

		$this->assertCount( 1, $this->http_requests );
		$this->assertEquals( 'aa218f56b14c9653891f9e74264a383fa43fefbd', $response->object->sha );
	}

	public function test_should_return_lastest_tree() {
		$tree = $this->api->last_tree_recursive();

		$this->assertCount( 3, $this->http_requests );
		$this->assertInstanceOf( 'WordPress_GitHub_Sync_tree', $tree );
		$this->assertCount( 2, $tree->get_data() );
	}

	/**
	 * This does some checks and fails the test if something is wrong
	 * or returns intended mock data for the given endpoint + method
	 */
	public function mock_github_api( $request, $url ) {
		if ( 'github.api' !== substr( $url, 0, 10 ) ) {
			$this->assertTrue( false, "Didn't call the correct host" );
		}

		$url = explode( '/', substr( $url, 11 ) );

		if ( 'repos' !== $url[0] ) {
			$this->assertTrue( false, "Didn't call the repos endpoint" );
		}

		if ( 'person' !== $url[1] || 'repo' !== $url[2] ) {
			$this->assertTrue( false, "Didn't call the correct repo" );
		}

		if ( 'GET' === $request['method'] ) {
			return $this->mock_get_requests( $url[3], $url[4] );
		}

		if ( 'POST' === $request['method'] ) {
			return $this->mock_post_requests( $url[3], $url[4], $request['body'] );
		}
	}

	public function mock_get_requests( $api, $endpoint ) {
		$get = array(
			'git/blobs' => array( 'body' => '{"content": "Content of the blob","encoding": "utf-8","url": "https://api.github.com/repos/octocat/example/git/blobs/3a0f86fb8db8eea7ccbb9a95f325ddbedfb25e15","sha": "3a0f86fb8db8eea7ccbb9a95f325ddbedfb25e15","size": 100}' ),
			'git/trees' => array( 'body' => '{"sha": "fc6274d15fa3ae2ab983129fb037999f264ba9a7","url": "https://api.github.com/repos/person/repo/git/trees/fc6274d15fa3ae2ab983129fb037999f264ba9a7","tree": [{"path": "README.md","mode": "100644","type": "blob","sha": "fc6274d15fa3ae2ab983129fb037999f264ba9a7","size": 526,"url": "https://api.github.com/repos/person/repo/git/blobs/fc6274d15fa3ae2ab983129fb037999f264ba9a7"},{"path": "subdir","mode": "040000","type": "tree","sha": "fc6274d15fa3ae2ab983129fb037999f264ba9a7","url": "https://api.github.com/repos/person/repo/git/trees/fc6274d15fa3ae2ab983129fb037999f264ba9a7"},{"path": "subdir/README.md","mode": "100644","type": "blob","sha": "fc6274d15fa3ae2ab983129fb037999f264ba9a7","size": 2227357,"url": "https://api.github.com/repos/person/repo/git/blobs/fc6274d15fa3ae2ab983129fb037999f264ba9a7"}],"truncated": false}' ),
			'git/commits' => array( 'body' => '{"sha":"7638417db6d59f3c431d3e1f261cc637155684cd","url":"https://api.github.com/repos/octocat/Hello-World/git/commits/7638417db6d59f3c431d3e1f261cc637155684cd","author":{"date":"2010-04-10T14:10:01-07:00","name":"ScottChacon","email":"schacon@gmail.com"},"committer":{"date":"2010-04-10T14:10:01-07:00","name":"ScottChacon","email":"schacon@gmail.com"},"message":"added readme, because im a good github citizen\n","tree":{"url":"https://api.github.com/repos/octocat/Hello-World/git/trees/691272480426f78a0138979dd3ce63b77f706feb","sha":"691272480426f78a0138979dd3ce63b77f706feb"},"parents":[{"url":"https://api.github.com/repos/octocat/Hello-World/git/commits/1acc419d4d6a9ce985db7be48c6349a0475975b5","sha":"1acc419d4d6a9ce985db7be48c6349a0475975b5"}]}' ),
			'git/refs' => array( 'body' => '{"ref":"refs/heads/featureA","url":"https://api.github.com/repos/octocat/Hello-World/git/refs/heads/featureA","object":{"type":"commit","sha":"aa218f56b14c9653891f9e74264a383fa43fefbd","url":"https://api.github.com/repos/octocat/Hello-World/git/commits/aa218f56b14c9653891f9e74264a383fa43fefbd"}}' ),
		);

		$response = $get[ $api . '/' . $endpoint ];
		$response['headers'] = array( 'status' => '200 OK' );

		return $response;
	}

	public function mock_post_requests( $api, $endpoint, $body ) {
		$post = array(
			'git/trees' => array( 'body' => '{"sha": "cd8274d15fa3ae2ab983129fb037999f264ba9a7","url": "https://api.github.com/repos/octocat/Hello-World/trees/cd8274d15fa3ae2ab983129fb037999f264ba9a7","tree": [{"path": "file.rb","mode": "100644","type": "blob","size": 132,"sha": "7c258a9869f33c1e1e1f74fbb32f07c86cb5a75b","url": "https://api.github.com/repos/octocat/Hello-World/git/blobs/7c258a9869f33c1e1e1f74fbb32f07c86cb5a75b"}]}' ),
			'git/commits' => array( 'body' => '{"sha":"7638417db6d59f3c431d3e1f261cc637155684cd","url":"https://api.github.com/repos/octocat/Hello-World/git/commits/7638417db6d59f3c431d3e1f261cc637155684cd","author":{"date":"2010-04-10T14:10:01-07:00","name":"ScottChacon","email":"schacon@gmail.com"},"committer":{"date":"2010-04-10T14:10:01-07:00","name":"ScottChacon","email":"schacon@gmail.com"},"message":"added readme, because im a good github citizen\n","tree":{"url":"https://api.github.com/repos/octocat/Hello-World/git/trees/691272480426f78a0138979dd3ce63b77f706feb","sha":"691272480426f78a0138979dd3ce63b77f706feb"},"parents":[{"url":"https://api.github.com/repos/octocat/Hello-World/git/commits/1acc419d4d6a9ce985db7be48c6349a0475975b5","sha":"1acc419d4d6a9ce985db7be48c6349a0475975b5"}]}' ),
			'git/refs' => array( 'body' => '{"ref":"refs/heads/featureA","url":"https://api.github.com/repos/octocat/Hello-World/git/refs/heads/featureA","object":{"type":"commit","sha":"aa218f56b14c9653891f9e74264a383fa43fefbd","url":"https://api.github.com/repos/octocat/Hello-World/git/commits/aa218f56b14c9653891f9e74264a383fa43fefbd"}}' ),
		);

		if ( 'trees' === $endpoint ) {
			$this->assertObjectHasAttribute( 'tree', json_decode( $body ) );
		}

		if ( 'commits' === $endpoint ) {
			$this->assertObjectHasAttribute( 'message', json_decode( $body ) );
			$this->assertObjectHasAttribute( 'author', json_decode( $body ) );
			$this->assertObjectHasAttribute( 'tree', json_decode( $body ) );
			$this->assertObjectHasAttribute( 'parents', json_decode( $body ) );
		}

		if ( 'refs' === $endpoint ) {
			$this->assertObjectHasAttribute( 'sha', json_decode( $body ) );
		}

		$response = $post[ $api . '/' . $endpoint ];
		$response['headers'] = array( 'status' => '201 CREATED' );

		return $response;
	}
}

