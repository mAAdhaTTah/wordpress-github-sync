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

class WordPress_GitHub_Sync {

    static $instance;
    static $text_domain = "wordpress-github-sync";
    static $version = "0.0.1";

    function __construct() {
      self::$instance = &$this;

		  add_action( 'init', array( &$this, 'l10n' ) );
      add_action( 'save_post', array( &$this, 'push_post' ) );
      add_action( 'wp_ajax_nopriv_wpghs_sync_request', array( &$this, 'pull_posts' ));

      if (is_admin()) {
        $this->admin = new WordPress_GitHub_Sync_Admin($this);
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

    function github_path($post_id) {
      $post = get_post($post_id);
      if ($post->post_type == "post") {
        $path = "_posts/";
        $path = $path . get_the_time("Y-m-d-", $post_id);
        $path = $path . $post->post_name . ".html";
      } elseif ($post->post_type == "page") {
        $path = $post->post_name . ".html";
      }

      return $path;
    }

    function last_modified_author($post_id) {
      if ( $last_id = get_post_meta( $post_id, '_edit_last', true) ) {
        $user = get_userdata($last_id);
        return array( "name" => $user->display_name, "email" => $user->user_email );
      } else {
        return array();
      }
    }

    function api_endpoint($post_id) {
      $url = $this->api_base() . "/repos/";
      $url = $url . $this->repository() . "/contents/";
      $url = $url . $this->github_path($post_id);
      return $url;
    }

    function get_remote_post_contents($post_id) {
      $response = wp_remote_get( $this->api_endpoint($post_id), array(
        "headers" => array(
          "Authorization" => "token " . $this->oauth_token()
          )
        )
      );
      $body = wp_remote_retrieve_body($response);
      $data = json_decode($body);
      return $data;
    }

    function sha($post_id) {
      if ($sha = get_post_meta( $post_id, "_sha", true)) {
        return $sha;
      } else {
        $data = $this->get_remote_post_contents($post_id);
        if ($data && isset($data->sha)) {
          return $data->sha;
        } else {
          return "";
        }
      }
    }

    function push_post($post_id) {

      if ( wp_is_post_revision( $post_id ) )
        return;

      $post = get_post($post_id);

      // Right now CPTs are not supported
      if ($post->post_type != "page" && $post->post_type != "post")
        return;

      // Not yet published
      if ($post->post_status != "publish")
        return;

      $args = array(
        "method"  => "PUT",
        "headers" => array(
            "Authorization" => "token " . $this->oauth_token()
          ),
        "body"    => json_encode( array(
            "message" => "Syncing " . $this->github_path($post_id) . " from WordPress",
            "content" => base64_encode($post->post_content),
            "author"  => $this->last_modified_author($post_id),
            "sha"     => $this->sha($post_id)
          ) )
      );

      $response = wp_remote_request( $this->api_endpoint($post_id), $args );
      $body = wp_remote_retrieve_body($response);
      $data = json_decode($body);
      if ($body && $data && !isset($data->errors)) {
        $sha = $data->content->sha;
        add_post_meta( $post_id, '_sha', $sha, true ) || update_post_meta( $post_id, '_sha', $sha );
      } else {
        wp_die( "WordPress <--> GitHub sync error: " . $data->message );
      }
    }



    function parts_from_path($path) {
      preg_match("/_posts\/([0-9]{4})-([0-9]{2})-([0-9]{2})-(.*)\.html/", $path, $matches);
      return $matches;

    }

    function title_from_path($path) {
      $matches = $this->parts_from_path($path);
      return $matches[4];
    }

    function date_from_path($path) {
      $matches = $this->parts_from_path($path);
      return $matches[1] . "-" . $matches[2] . "-" . $matches[3] . "00:00:00";
    }

    function id_from_path($path) {
      global $wpdb;
      $title = $this->title_from_path($path);
      return $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$title'");
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

      // Modified
      foreach (array_unique($modified) as $path) {
        $post_id = $this->id_from_path($path);
        $this->pull_post($post_id);
      }

      // Removed
      foreach (array_unique($removed) as $path) {
        $post_id = $this->id_from_path($path);
        wp_delete_post($post_id);
      }

      // Added
      foreach (array_unique($added) as $path) {
        $post_id = wp_insert_post( array(
            'post_name' => $this->title_from_path($path),
            'post_date' => $this->date_from_path($path)
          )
        );
        $this->pull_post($post_id);
      }

    }

    function pull_post($post_id) {
      $post = get_post($post_id);
      $data = $this->get_remote_post_contents($post_id);
      remove_action( 'save_post', array( &$this, 'push_post' ) );
      wp_update_post( array(
          "ID"           => $post_id,
          "post_content" => base64_decode($data->content)
        )
      );
      add_action( 'save_post', array( &$this, 'push_post' ) );
    }

}

$wpghs = new WordPress_GitHub_Sync;
