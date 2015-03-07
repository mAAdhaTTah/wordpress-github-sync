<?php
/**
 * Plugin Name: WordPress GitHub Sync
 * Plugin URI: https://github.com/benbalter/wordpress-github-sync
 * Description: A WordPress plugin to sync content with a GitHub repository (or Jekyll site)
 * Version: 0.0.1
 * Author:  Ben Balter
 * Author URI: http://ben.balter.com
 * License: GPLv2
 * Domain Path: /languages
 * Text Domain: wordpress-github-sync
 */

/*  Copyright 2014  Ben Balter  (email : ben@balter.com)

		This program is free software; you can redistribute it and/or modify
		it under the terms of the GNU General Public License, version 2, as
		published by the Free Software Foundation.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once dirname( __FILE__ ) . '/vendor/autoload.php';

class WordPress_GitHub_Sync {

	/**
	 * Object instance
	 * @var self
	 */
	public static $instance;

	/**
	 * Language text domain
	 * @var string
	 */
	public static $text_domain = 'wordpress-github-sync';

	/**
	 * Current version
	 * @var string
	 */
	public static $version = '0.0.1';

	/**
	 * Controller object
	 * @var WordPress_GitHub_Sync_Controller
	 */
	public $controller;

	/**
	 * Controller object
	 * @var WordPress_GitHub_Sync_Admin
	 */
	public $admin;

	/**
	 * Locked when receiving payload
	 * @var boolean
	 */
	public $push_lock = false;

	/**
	 * Called at load time, hooks into WP core
	 */
	public function __construct() {
		self::$instance = &$this;

		if ( is_admin() ) {
			$this->admin = new WordPress_GitHub_Sync_Admin;
		}
		$this->controller = new WordPress_GitHub_Sync_Controller;

		add_action( 'init', array( &$this, 'l10n' ) );
		add_action( 'save_post', array( &$this, 'save_post_callback' ) );
		add_action( 'delete_post', array( &$this, 'delete_post_callback' ) );
		add_action( 'wp_ajax_nopriv_wpghs_sync_request', array( &$this, 'pull_posts' ) );
		add_action( 'wpghs_export', array( &$this->controller, 'export_all' ) );
		add_action( 'wpghs_import', array( &$this->controller, 'import_master' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wpghs', 'WordPress_GitHub_Sync_CLI' );
		}
	}

	/**
		* Init i18n files
		*/
	public function l10n() {
		load_plugin_textdomain( self::$text_domain, false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Returns the Webhook secret
	 */
	public function secret() {
		return get_option( 'wpghs_secret' );
	}

	/**
	 * Callback triggered on post save, used to initiate an outbound sync
	 *
	 * $post_id - (int) the post to sync
	 */
	public function save_post_callback($post_id) {

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Right now CPTs are not supported
		$post = new WordPress_GitHub_Sync_Post( $post_id );
		if ($post->is_post_type_blacklisted()) {
			return;
		}

		// Not yet published
		if ( 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		$this->controller->export_post( $post_id );

	}

	/**
	 * Callback triggered on post delete, used to initiate an outbound sync
	 *
	 * $post_id - (int) the post to delete
	 */
	public function delete_post_callback( $post_id ) {

		$post = get_post( $post_id );

		// Right now CPTs are not supported
		$post = new WordPress_GitHub_Sync_Post( $post_id );
		if ($post->is_post_type_blacklisted()) {
			return;
		}

		$this->controller->delete_post( $post_id );

	}

	/**
	 * Webhook callback as trigered from GitHub push
	 */
	public function pull_posts() {
		# Prevent pushes on update
		$this->push_lock = true;

		$raw_data = file_get_contents( 'php://input' );
		$headers = $this->headers();

		// validate secret
		$hash = hash_hmac( 'sha1', $raw_data, $this->secret() );
		if ( 'sha1=' . $hash !== $headers['X-Hub-Signature'] ) {
			$msg = __( 'Failed to validate secret.', WordPress_GitHub_Sync::$text_domain );
			self::write_log( $msg );
			wp_send_json( array(
				'result'  => 'error',
				'message' => $msg,
			) );
		}

		$result = $this->controller->pull( json_decode( $raw_data ) );
		wp_send_json( $result );
	}

	/**
	 * Cross-server header support
	 * Returns an array of the request's headers
	 */
	public function headers() {
		if ( function_exists( 'getallheaders' ) ) {
			return getallheaders();
		}

		// Nginx and pre 5.4 workaround
		// http://www.php.net/manual/en/function.getallheaders.php
		$headers = array();
		foreach ( $_SERVER as $name => $value ) {
			if ( 'HTTP_' === substr( $name, 0, 5 ) ) {
				$headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
			}
		}
		return $headers;
	}

	/**
	 * Sets and kicks off the export cronjob
	 */
	public function start_export() {
		update_option( '_wpghs_export_user_id', get_current_user_id() );
		update_option( '_wpghs_export_started', 'yes' );

		WordPress_GitHub_Sync::write_log( __( 'Starting full export to GitHub.', WordPress_GitHub_Sync::$text_domain ) );

		wp_schedule_single_event( time(), 'wpghs_export' );
		spawn_cron();
	}

	/**
	 * Sets and kicks off the import cronjob
	 */
	public function start_import() {
		update_option( '_wpghs_import_started', 'yes' );

		WordPress_GitHub_Sync::write_log( __( 'Starting import from GitHub.', WordPress_GitHub_Sync::$text_domain ) );

		wp_schedule_single_event( time(), 'wpghs_import' );
		spawn_cron();
	}

	/**
	 * Print to WP_CLI if in CLI environment or
	 * write to debug.log if WP_DEBUG is enabled
	 * @source http://www.stumiller.me/sending-output-to-the-wordpress-debug-log/
	 */
	public static function write_log($msg, $write = 'line') {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( is_array( $msg ) || is_object( $msg ) ) {
				WP_CLI::print_value( $msg );
			} else {
				WP_CLI::$write( $msg );
			}
		} elseif ( true === WP_DEBUG ) {
			if ( is_array( $msg ) || is_object( $msg ) ) {
				error_log( print_r( $msg, true ) );
			} else {
				error_log( $msg );
			}
		}
	}
}

$wpghs = new WordPress_GitHub_Sync;
