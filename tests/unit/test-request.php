<?php

/**
 * @group http
 */
class WordPress_GitHub_Sync_Request_Test extends WordPress_GitHub_Sync_TestCase {

	/**
	 * @var string
	 */
	protected $secret;

	/**
	 * @var string
	 */
	protected $raw_data;

	public function setUp() {
		parent::setUp();

		$this->secret = '1234567890qwertyuiopasdfghjklzxcvbnm';
		update_option( 'wpghs_secret', $this->secret );

		$this->request = new WordPress_GitHub_Sync_Request_Stub( $this->app );
		$this->request->set_data_dir( $this->data_dir );
	}

	public function test_should_return_error_if_header_invalid() {
		$this->set_auth_header( hash_hmac( 'sha1', file_get_contents( $this->data_dir . 'payload-valid.json' ), 'wrong-secret' ) );

		$this->assertInstanceOf( 'WP_Error', $error = $this->request->is_secret_valid() );
		$this->assertEquals( 'invalid_headers', $error->get_error_code() );
	}

	public function test_should_return_true_if_header_valid() {
		$this->set_auth_header( hash_hmac( 'sha1', file_get_contents( $this->data_dir . 'payload-valid.json' ), $this->secret ) );

		$this->assertTrue( $this->request->is_secret_valid() );
		$this->assertEquals( 'ad4e0a9e2597de40106d5f52e2041d8ebaeb0087', $this->request->payload()->get_commit_id() );
	}

	protected function set_auth_header( $hash ) {
		$_SERVER['HTTP_X_HUB_SIGNATURE'] = 'sha1=' . $hash;
	}
}

class WordPress_GitHub_Sync_Request_Stub extends WordPress_GitHub_Sync_Request {
	/**
	 * @var string
	 */
	protected $data_dir;

	public function set_data_dir( $data_dir ) {
		$this->data_dir = $data_dir;
	}
	protected function read_raw_data() {
		return file_get_contents( $this->data_dir . 'payload-valid.json' );
	}

}
