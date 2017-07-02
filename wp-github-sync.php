<?php
/**
 * Plugin Name: WordPress GitHub Sync
 * Plugin URI: https://github.com/mAAdhaTTah/wordpress-github-sync
 * Description: A WordPress plugin to sync content with a GitHub repository (or Jekyll site).
 * Version: 2.0.0
 * Author:  James DiGioia, Ben Balter
 * Author URI: http://jamesdigioia.com
 * License: GPLv2
 * Domain Path: /languages
 * Text Domain: wp-github-sync
 *
 * @package wp-github-sync
 */

/**
		Copyright 2014  James DiGioia  (email : jamesorodig@gmail.com)

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

// If the functions have already been autoloaded, don't reload.
// This fixes function duplication during unit testing.
$path = dirname( __FILE__ ) . '/vendor/autoload_52.php';
if ( ! function_exists( 'get_the_github_view_link' ) && file_exists( $path ) ) {
	require_once $path;
}

add_action( 'plugins_loaded', array( new WordPress_GitHub_Sync, 'boot' ) );

/**
 * Class WordPress_GitHub_Sync
 *
 * Main application class for the plugin. Responsible for bootstrapping
 * any hooks and instantiating all service classes.
 */
class WordPress_GitHub_Sync {

	/**
	 * Object instance.
	 *
	 * @var self
	 */
	public static $instance;

	/**
	 * Language text domain.
	 *
	 * @var string
	 */
	public static $text_domain = 'wp-github-sync';

	/**
	 * Current version.
	 *
	 * @var string
	 */
	public static $version = '2.0.0';

	/**
	 * Controller object.
	 *
	 * @var WordPress_GitHub_Sync_Controller
	 */
	public $controller;

	/**
	 * Admin object.
	 *
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
	 * Export object.
	 *
	 * @var WordPress_GitHub_Sync_Export
	 */
	protected $export;

	/**
	 * Semaphore object.
	 *
	 * @var WordPress_GitHub_Sync_Semaphore
	 */
	protected $semaphore;

	/**
	 * Database object.
	 *
	 * @var WordPress_GitHub_Sync_Database
	 */
	protected $database;

	/**
	 * Cache object.
	 *
	 * @var WordPress_GitHub_Sync_Cache
	 */
	protected $cache;

	/**
	 * Called at load time, hooks into WP core
	 */
	public function __construct() {
		self::$instance = $this;

		if ( is_admin() ) {
			$this->admin = new WordPress_GitHub_Sync_Admin;
		}

		$this->controller = new WordPress_GitHub_Sync_Controller( $this );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'wpghs', $this->cli() );
		}
	}

	/**
	 * Attaches the plugin's hooks into WordPress.
	 */
	public function boot() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );

		add_action( 'init', array( $this, 'l10n' ) );

		// Controller actions.
		add_action( 'save_post', array( $this->controller, 'export_post' ) );
		add_action( 'delete_post', array( $this->controller, 'delete_post' ) );
		add_action( 'wp_ajax_nopriv_wpghs_sync_request', array( $this->controller, 'pull_posts' ) );
		add_action( 'wpghs_export', array( $this->controller, 'export_all' ) );
		add_action( 'wpghs_import', array( $this->controller, 'import_master' ) );

		add_shortcode( 'wpghs', 'write_wpghs_link' );

		do_action( 'wpghs_boot', $this );
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
		$this->export()->set_user( get_current_user_id() );
		$this->start_cron( 'export' );
	}

	/**
	 * Sets and kicks off the import cronjob
	 */
	public function start_import() {
		$this->start_cron( 'import' );
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
						__( 'To set up your site to sync with GitHub, update your <a href="%s">settings</a> and click "Export to GitHub."', 'wp-github-sync' ),
						admin_url( 'options-general.php?page=' . static::$text_domain)
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
			$this->cli = new WordPress_GitHub_Sync_CLI;
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
			$this->api = new WordPress_GitHub_Sync_Api( $this );
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
	 * Lazy-load the Export object.
	 *
	 * @return WordPress_GitHub_Sync_Export
	 */
	public function export() {
		if ( ! $this->export ) {
			$this->export = new WordPress_GitHub_Sync_Export( $this );
		}

		return $this->export;
	}

	/**
	 * Lazy-load the Semaphore object.
	 *
	 * @return WordPress_GitHub_Sync_Semaphore
	 */
	public function semaphore() {
		if ( ! $this->semaphore ) {
			$this->semaphore = new WordPress_GitHub_Sync_Semaphore;
		}

		return $this->semaphore;
	}

	/**
	 * Lazy-load the Database object.
	 *
	 * @return WordPress_GitHub_Sync_Database
	 */
	public function database() {
		if ( ! $this->database ) {
			$this->database = new WordPress_GitHub_Sync_Database( $this );
		}

		return $this->database;
	}

	/**
	 * Lazy-load the Cache object.
	 *
	 * @return WordPress_GitHub_Sync_Cache
	 */
	public function cache() {
		if ( ! $this->cache ) {
			$this->cache = new WordPress_GitHub_Sync_Cache;
		}

		return $this->cache;
	}

	/**
	 * Print to WP_CLI if in CLI environment or
	 * write to debug.log if WP_DEBUG is enabled
	 *
	 * @source http://www.stumiller.me/sending-output-to-the-wordpress-debug-log/
	 *
	 * @param mixed  $msg   Message text.
	 * @param string $write How to write the message, if CLI.
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

	/**
	 * Kicks of an import or export cronjob.
	 *
	 * @param string $type Cron to kick off.
	 */
	protected function start_cron( $type ) {
		update_option( '_wpghs_' . $type . '_started', 'yes' );
		wp_schedule_single_event( time(), 'wpghs_' . $type . '' );
		spawn_cron();
	}
}
