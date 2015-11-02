<?php

abstract class WordPress_GitHub_Sync_Base_Client_Test extends WordPress_GitHub_Sync_TestCase {

	/**
	 * @var string
	 */
	const HOST_OPTION_VALUE = 'https://api.github.com';

	/**
	 * @var string
	 */
	const REPO_OPTION_VALUE = 'wpghstest/wpghs-test';

	/**
	 * @var string
	 */
	const TOKEN_OPTION_VALUE = 'the-token';

	/**
	 * @var array
	 */
	protected static $responses = array();

	/**
	 * @var array
	 */
	protected static $validations = array();

	public function setUp() {
		parent::setUp();

		WP_HTTP_TestCase::init();
		update_option( 'wpghs_repository', self::REPO_OPTION_VALUE );
		update_option( 'wpghs_oauth_token', self::TOKEN_OPTION_VALUE );
		update_option( 'wpghs_host', self::HOST_OPTION_VALUE );
		$this->http_responder = array( $this, 'mock_github_api' );
	}

	/**
	 * This does some checks and fails the test if something is wrong
	 * or returns intended mock data for the given endpoint + method.
	 *
	 * @return void|string
	 */
	public function mock_github_api( $request, $url ) {
		$host_length = strlen( self::HOST_OPTION_VALUE );

		if ( self::HOST_OPTION_VALUE !== substr( $url, 0, $host_length ) ) {
			$this->assertTrue( false, 'Called wrong host.' );
		}

		if (
			! isset( $request['headers']['Authorization'] ) ||
			'token ' . self::TOKEN_OPTION_VALUE !== $request['headers']['Authorization']
		) {
			$this->assertTrue( false, 'Missing authorization key.' );
		}

		$url = explode( '/', substr( $url, $host_length + 1 ) );

		if ( 'repos' !== $url[0] ) {
			$this->assertTrue( false, 'Called wrong endpoint.' );
		}

		$repo = $url[1] . '/' . $url[2];

		if ( self::REPO_OPTION_VALUE !== $repo ) {
			$this->assertTrue( false, 'Called wrong repo.' );
		}

		$parts = array_slice( $url, 4 );
		array_unshift( $parts, strtolower( $request['method'] ) );
		$endpoint = implode( '_', $parts );
		$endpoint = str_replace( '?recursive=1', '', $endpoint );
		$this->assertTrue( call_user_func( static::$validations[ $endpoint ], $request ), 'Request did not validate.' );

		return static::$responses[ $endpoint ];
	}

	private function set_endpoint( $validation, $status, $succeed, $sha = '' ) {
		list( , $caller ) = debug_backtrace( false );
		$endpoint = substr( $caller['function'], 4 ) . ( $sha ? "_$sha" : '' );

		static::$validations[ $endpoint ] = $validation;

		static::$responses[ $endpoint ] = array(
			'headers' => array(
				'status' => $status,
			),
			'body'    => file_get_contents(
				$this->data_dir . $endpoint . '_' . ( $succeed ? 'succeed' : 'fail' ) . '.json'
			),
		);
	}
}
