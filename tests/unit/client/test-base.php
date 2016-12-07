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

	protected function set_get_refs_heads_master( $succeed ) {
		$this->set_endpoint(
			function ( $request ) {
				if ( '[]' === $request['body'] ) {
					return false;
				}

				return true;
			}, $succeed ? '200 OK' : '404 Not Found', $succeed
		);
	}

	protected function set_get_commits( $succeed ) {
		$this->set_endpoint(
			function ( $request ) {
				if ( '[]' === $request['body'] ) {
					return false;
				}

				return true;
			}, $succeed ? '200 OK' : '404 Not Found', $succeed, 'db2510854e6aeab68ead26b48328b19f4bdf926e'
		);
	}

	protected function set_get_trees( $succeed ) {
		$this->set_endpoint(
			function ( $request ) {
				if ( '[]' === $request['body'] ) {
					return false;
				}

				return true;
			}, $succeed ? '200 OK' : '422 Unprocessable Entity', $succeed, '9108868e3800bec6763e51beb0d33e15036c3626'
		);
	}

	protected function set_get_blobs( $succeed ) {
		$shas = array(
			'9fa5c7537f8582b71028ff34b8c20dfd0f3b2a25',
			'8d9b2e6fd93761211dc03abd71f4a9189d680fd0',
			'2d73165945b0ccbe4932f1363457986b0ed49f19',
		);

		foreach ( $shas as $sha ) {
			$this->set_endpoint(
				function ( $request ) {
					if ( '[]' === $request['body'] ) {
						return false;
					}

					return true;
				}, $succeed ? '200 OK' : '404 Not Found', $succeed, $sha
			);
		}
	}

	protected function set_post_trees( $succeed ) {
		$this->set_endpoint(
			function ( $request ) {
				$body = json_decode( $request['body'], true );

				if ( ! isset( $body['tree'] ) ) {
					return false;
				}

				if ( 1 !== count( $body['tree'] ) ) {
					return false;
				}

				$blob = reset( $body['tree'] );

				if (
					! isset( $blob['path'] ) ||
					! isset( $blob['type'] ) ||
					! isset( $blob['content'] ) ||
					! isset( $blob['mode'] )
				) {
					return false;
				}

				return true;
			},
			$succeed ? '201 Created' : '404 Not Found',
			$succeed
		);
	}

	protected function set_post_commits( $succeed, $anonymous = true ) {
		$this->set_endpoint(
			function ( $request ) use ( $anonymous ) {
				$body = json_decode( $request['body'], true );

				if (
					! isset( $body['tree'] ) ||
					! isset( $body['message'] ) ||
					! isset( $body['parents'] ) ||
					! isset( $body['author'] )
				) {
					return false;
				}

				if ( 1 !== count( $body['parents'] ) ) {
					return false;
				}

				if ( ! $anonymous ) {
					if (
						'James DiGioia' !== $body['author']['name'] ||
						'jamesorodig@gmail.com' !== $body['author']['email']
					) {
						return false;
					}
				} else {
					if (
						'Anonymous' !== $body['author']['name'] ||
						'anonymous@users.noreply.github.com' !== $body['author']['email']
					) {
						return false;
					}
				}

				return true;
			},
			$succeed ? '201 Created' : '404 Not Found',
			$succeed
		);
	}

	protected function set_patch_refs_heads_master( $succeed ) {
		$this->set_endpoint(
			function ( $request ) {
				$body = json_decode( $request['body'], true );

				if ( ! isset( $body['sha'] ) ) {
					return false;
				}

				return true;
			},
			$succeed ? '201 Created' : '404 Not Found',
			$succeed
		);
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
