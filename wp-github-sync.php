<?php
/**
 * Plugin Name: WordPress GitHub Sync
 * Plugin URI: https://github.com/benbalter/wordpress-github-sync
 * Description: A WordPress plugin to sync content with a GitHub repository (or Jekyll site)
 * Version: 1.3.2
 * Author:  Ben Balter, James DiGioia
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

// if the functions have already been autoloaded, don't reload
// this fixes function duplication during unit testing
$path = dirname( __FILE__ ) . '/vendor/autoload_52.php';
if ( ! function_exists( 'get_the_github_view_link' ) && file_exists( $path ) ) {
	require_once $path;
}

$wpghs = new WordPress_GitHub_Sync;

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
	public static $version = '1.3.2';

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
	 * CLI object.
	 *
	 * @var WordPress_GitHub_Sync_CLI
	 */
	protected $cli;

	/**
	 * Request object.
	 *
	 * @var WordPress_GitHub_Sync_Request
	 */
	protected $request;

	/**
	 * Response object.
	 *
	 * @var WordPress_GitHub_Sync_Response
	 */
	protected $response;

	/**
	 * Api object.
	 *
	 * @var WordPress_GitHub_Sync_Api
	 */
	protected $api;

	/**
	 * Import object.
	 *
	 * @var WordPress_GitHub_Sync_Import
	 */
	protected $import;

	/**
	 * Called at load time, hooks into WP core
	 */
	public function __construct() {
		self::$instance = $this;

		if ( is_admin() ) {
			$this->admin = new WordPress_GitHub_Sync_Admin;
		}

		$this->controller = new WordPress_GitHub_Sync_Controller( $this );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );

		add_action( 'init', array( $this, 'l10n' ) );

		// Controller actions.
		add_action( 'save_post', array( $this->controller, 'export_post' ) );
		add_action( 'delete_post', array( $this->controller, 'delete_post' ) );
		add_action( 'wp_ajax_nopriv_wpghs_sync_request', array( $this->controller, 'pull_posts' ) );
		add_action( 'wpghs_export', array( $this->controller, 'export_all' ) );
		add_action( 'wpghs_import', array( $this->controller, 'import_master' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wpghs', $this->cli() );
		}
	}

	/**
	 * Init i18n files
	 */
	public function l10n() {
		load_plugin_textdomain( self::$text_domain, false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Sets and kicks off the export cronjob
	 */
	public function start_export() {
		update_option( '_wpghs_export_user_id', get_current_user_id() );
		update_option( '_wpghs_export_started', 'yes' );

		WordPress_GitHub_Sync::write_log( __( 'Starting full export to GitHub.', 'wordpress-github-sync' ) );

		wp_schedule_single_event( time(), 'wpghs_export' );
		spawn_cron();
	}

	/**
	 * Sets and kicks off the import cronjob
	 */
	public function start_import() {
		update_option( '_wpghs_import_started', 'yes' );

		WordPress_GitHub_Sync::write_log( __( 'Starting import from GitHub.', 'wordpress-github-sync' ) );

		wp_schedule_single_event( time(), 'wpghs_import' );
		spawn_cron();
	}

	/**
	 * Enables the admin notice on initial activation
	 */
	public function activate() {
		if ( 'yes' !== get_option( '_wpghs_fully_exported' ) ) {
			set_transient( '_wpghs_activated', 'yes' );
		}
	}

	/**
	 * Displays the activation admin notice
	 */
	public function activation_notice() {
		if ( ! get_transient( '_wpghs_activated' ) ) {
			return;
		}

		delete_transient( '_wpghs_activated' );

		?><div class="updated">
			<p>
				<?php
					printf(
						__( 'To set up your site to sync with GitHub, update your <a href="%s">settings</a> and click "Export to GitHub."', 'wordpress-github-sync' ),
						admin_url( 'options-general.php?page=wordpress-github-sync' )
					);
				?>
			</p>
		</div><?php
	}

	/**
	 * Get the Controller object.
	 *
	 * @return WordPress_GitHub_Sync_Controller
	 */
	public function controller() {
		return $this->controller;
	}

	/**
	 * Lazy-load the CLI object.
	 *
	 * @return WordPress_GitHub_Sync_CLI
	 */
	public function cli() {
		if ( ! $this->cli ) {
			$this->cli = new WordPress_GitHub_Sync_CLI( $this );
		}

		return $this->cli;
	}

	/**
	 * Lazy-load the Request object.
	 *
	 * @return WordPress_GitHub_Sync_Request
	 */
	public function request() {
		if ( ! $this->request ) {
			$this->request = new WordPress_GitHub_Sync_Request( $this );
		}

		return $this->request;
	}

	/**
	 * Lazy-load the Response object.
	 *
	 * @return WordPress_GitHub_Sync_Response
	 */
	public function response() {
		if ( ! $this->response ) {
			$this->response = new WordPress_GitHub_Sync_Response( $this );
		}

		return $this->response;
	}

	/**
	 * Lazy-load the Api object.
	 *
	 * @return WordPress_GitHub_Sync_Api
	 */
	public function api() {
		if ( ! $this->api ) {
			$this->api = new WordPress_GitHub_Sync_Api();
		}

		return $this->api;
	}

	/**
	 * Lazy-load the Import object.
	 *
	 * @return WordPress_GitHub_Sync_Import
	 */
	public function import() {
		if ( ! $this->import ) {
			$this->import = new WordPress_GitHub_Sync_Import( $this );
		}

		return $this->import;
	}

	/**
	 * Print to WP_CLI if in CLI environment or
	 * write to debug.log if WP_DEBUG is enabled
	 * @source http://www.stumiller.me/sending-output-to-the-wordpress-debug-log/
	 *
	 * @param mixed $msg
	 * @param string $write
	 */
	public static function write_log( $msg, $write = 'line' ) {
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
