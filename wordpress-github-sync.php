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

require_once dirname( __FILE__ ) . '/lib/admin.php';
require_once dirname( __FILE__ ) . '/lib/post.php';

class WordPress_GitHub_Sync {

    static $instance;
    static $text_domain = "wordpress-github-sync";
    static $version = "0.0.1";

    function __construct() {
      self::$instance = &$this;

		  add_action( 'init', array( &$this, 'l10n' ) );
      add_action( 'save_post', array( &$this, 'save_post_callback' ) );
      add_action( 'wp_ajax_nopriv_wpghs_sync_request', array( &$this, 'pull_posts' ));

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

    function repository() {
      return get_option( "wpghs_repository" );
    }

    function oauth_token() {
      return get_option( "wpghs_oauth_token" );
    }

    function api_base() {
      return get_option( "wpghs_host" );
    }

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

    function pull_posts() {
      $data = json_decode(file_get_contents('php://input'));

      $nwo = $data->repository->owner->name . "/" . $data->repository->name;
      if ( $nwo != $this->repository() )
        wp_die( $nwo . " is an invalid repository" );

      $modified = [];
      $added = [];
      $removed = [];

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
}

$wpghs = new WordPress_GitHub_Sync;
