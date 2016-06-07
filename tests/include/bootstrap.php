<?php
error_reporting( E_ALL  & ~E_DEPRECATED );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../../wp-github-sync.php';
	remove_action( 'plugins_loaded', array( WordPress_GitHub_Sync::$instance, 'boot' ) );
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

require dirname( __FILE__ ) . '/../../vendor/jdgrimes/wp-http-testcase/wp-http-testcase.php';
