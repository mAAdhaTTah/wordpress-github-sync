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

    static $instance;
    static $text_domain = "wordpress-github-sync";
    static $version = "0.0.1";
    public $push_lock = false;

    /**
     * Called at load time, hooks into WP core
     */
    function __construct() {
      self::$instance = &$this;

      add_action( 'init', array( &$this, 'l10n' ) );
      add_action( 'save_post', array( &$this, 'save_post_callback' ) );
      add_action( 'delete_post', array( &$this, 'delete_post_callback' ) );
      add_action( 'wp_ajax_nopriv_wpghs_sync_request', array( &$this, 'pull_posts' ));
      add_action( 'wpghs_export', array( &$this, 'export_posts' ));
      add_action( 'init', array( &$this, 'continue_export' ) );

      if (is_admin()) {
        $this->admin = new WordPress_GitHub_Sync_Admin;
      }
    }

    /**
      * Init i18n files
      */
    function l10n() {
      load_plugin_textdomain( self::$text_domain, false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Returns the repository to sync with
     */
    function repository() {
      return get_option( "wpghs_repository" );
    }

    /**
     * Returns the user's oauth token
     */
    function oauth_token() {
      return get_option( "wpghs_oauth_token" );
    }

    /**
     * Returns the GitHub host to sync with (for GitHub Enterprise support)
     */
    function api_base() {
      return get_option( "wpghs_host" );
    }

    /**
     * Returns the Webhook secret
     */
    function secret() {
      return get_option( "wpghs_secret" );
    }

    /**
     * Callback triggered on post save, used to initiate an outbound sync
     *
     * $post_id - (int) the post to sync
     */
    function save_post_callback($post_id) {

      if ( wp_is_post_revision( $post_id ) )
        return;

      $post = get_post($post_id);

      // Right now CPTs are not supported
      if ($post->post_type != "page" && $post->post_type != "post")
        return;

      // Not yet published
      if ($post->post_status != "publish")
        return;

      $post = new WordPress_GitHub_Sync_Post($post_id);
      $post->push();

    }

    /**
     * Callback triggered on post delete, used to initiate an outbound sync
     *
     * $post_id - (int) the post to delete
     */
    function delete_post_callback( $post_id ) {

      $post = get_post($post_id);

      // Right now CPTs are not supported
      if ($post->post_type != "page" && $post->post_type != "post")
        return;

      $post = new WordPress_GitHub_Sync_Post($post_id);
      $post->delete();

    }

    /**
     * Webhook callback as trigered from GitHub push
     * Reads the payload and syncs posts as necessary
     */
    function pull_posts() {

      # Prevent pushes on update
      $this->push_lock = true;

      $raw_data = file_get_contents('php://input');
      $headers = $this->headers();

      // validate secret
      $hash = hash_hmac( "sha1", $raw_data, $this->secret() );
      if ( $headers["X-Hub-Signature"] != "sha1=" . $hash )
        wp_die( __("Failed to validate secret.", WordPress_GitHub_Sync::$text_domain) );

      $data = json_decode($raw_data);

      $nwo = $data->repository->owner->name . "/" . $data->repository->name;
      if ( $nwo != $this->repository() )
        wp_die( $nwo . __(" is an invalid repository", WordPress_GitHub_Sync::$text_domain) );

      $modified = $added = $removed = array();

      foreach ($data->commits as $commit) {
        $modified = array_merge( $modified, $commit->modified );
        $added    = array_merge( $added,    $commit->added    );
        $removed  = array_merge( $removed,  $commit->removed  );
      }

      // Added or Modified (pull)
      $to_pull = array_merge($modified, $added);
      foreach (array_unique($to_pull) as $path) {
        $post = new WordPress_GitHub_Sync_Post($path);
        $post->pull();
      }

      // Removed
      foreach (array_unique($removed) as $path) {
        $post = new WordPress_GitHub_Sync_Post($path);
        wp_delete_post($post->id);
      }
    }

    /**
     * Cross-server header support
     * Returns an array of the request's headers
     */
    function headers() {
      if (function_exists('getallheaders'))
        return getallheaders();

      // Nginx and pre 5.4 workaround
      // http://www.php.net/manual/en/function.getallheaders.php
      $headers = array();
      foreach ($_SERVER as $name => $value) {
       if (substr($name, 0, 5) == 'HTTP_') {
         $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
       }
      }
      return $headers;
    }

    /**
     * Get posts to export, set and kick off cronjob
     */
    function start_export() {
      global $wpdb;
      $posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ('post', 'page' )" );

      wp_schedule_single_event(time(), 'wpghs_export', array($posts));
      spawn_cron(); ?>
      <div class="updated">
          <p><?php _e( 'Export to GitHub started.', WordPress_GitHub_Sync::$text_domain ); ?></p>
      </div>
      <?php
    }

    /**
     * Export posts
     *
     * Runs as cronjob
     */
    function export_posts($posts) {
      $i = 0;

      while(!empty($posts) && $i < 50) {
        $post_id = array_shift($posts);

        $post = new WordPress_GitHub_Sync_Post($post_id);
        $post->push();

        $i++;
      }

      if (!empty($posts)) {
        wp_remote_post( add_query_arg( 'github', 'sync', site_url( 'index.php' ) ), array(
          'body' => array(
            'posts' => $posts
          ),
          'blocking' => false,
        ) );
      }

      die();
    }

    /**
     * Receives the remaining posts
     *
     * Kicks off exporting the next batch
     */
    function continue_export() {
      if ( ! isset( $_GET['github'] ) || 'sync' !== $_GET['github'] ) {
        return;
      }

      if ( !current_user_can( 'manage_options' ) ) {
        return;
      }

      if ( !array_key_exists('posts', $_POST) || !is_array($_POST['posts']) ) {
        return;
      }

      $posts = $_POST['posts'];

      $this->export_posts($posts);
    }
}

$wpghs = new WordPress_GitHub_Sync;
