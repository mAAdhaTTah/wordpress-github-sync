<?php
/**
 * The controller object which manages
 */
class WordPress_GitHub_Sync_Controller {

  /**
   * Instantiates a new Controller object
   *
   * $posts - array of post IDs to export
   */
  function __construct() {}

  /**
   * Sets up and begins the background export process
   */
  function cron_start() {
    wp_schedule_single_event(time(), 'wpghs_export');
    WordPress_GitHub_Sync::write_log( __( "Starting export to GitHub", WordPress_GitHub_Sync::$text_domain ) );
    spawn_cron();
    update_option( '_wpghs_export_started', 'yes' );
  }

  /**
   * Sets up and begins the CLI export process
   */
  function cli_start() {
    $this->get_data();

    while(!empty($this->posts)) {
      $this->export_post();
    }

    $this->finalize();
  }

  /**
   * Export posts
   *
   * Runs as cronjob
   */
  function cron_process() {
    $i = 0;
    $this->get_data();

    while(!empty($this->posts) && $i < 50) {
      $this->export_post();

      $i++;
    }

    if (!empty($this->posts)) {
      $this->handoff();
    } else {
      $this->finalize();
    }

    die();
  }

  /**
   * Takes the next post off the top of the list
   * and exports it to a blob on GitHub
   */
  function export_post() {
    $post_id = array_shift($this->posts);
    WordPress_GitHub_Sync::write_log( __("Exporting Post ID: ", WordPress_GitHub_Sync::$text_domain ) . $post_id );

    $post = new WordPress_GitHub_Sync_Post($post_id);
    $result = $post->push_blob();

    if ( is_wp_error( $result ) ) {
      array_unshift($this->posts, $post_id);
      $this->error($result);
      die();
    }

    usleep(500000);
    $this->blobs[] = $post_id;
  }

  /**
   * Takes the posts array and hands it off to a new process
   */
  function handoff() {
    $nonce = wp_hash( time() );
    update_option( '_wpghs_export_nonce', $nonce );

    $this->save_data();

    // Request page that will continue export
    wp_remote_post( add_query_arg( 'github', 'sync', site_url( 'index.php' ) ), array(
      'body' => array(
        'nonce' => $nonce,
      ),
      'blocking' => false,
    ) );
  }

  /**
   * After all the blobs are saved,
   * create the tree, commit, and adjust master ref
   */
  function finalize() {
    WordPress_GitHub_Sync::write_log(__( 'Creating the tree.', WordPress_GitHub_Sync::$text_domain ));
    $tree_sha = $this->create_tree();

    if ( is_wp_error( $tree_sha ) ) {
      $this->error($tree_sha);
      die();
    }

    WordPress_GitHub_Sync::write_log(__( 'Creating the commit.', WordPress_GitHub_Sync::$text_domain ));
    $commit_sha = $this->create_commit($tree_sha);

    if ( is_wp_error( $commit_sha ) ) {
      $this->error($commit_sha);
      die();
    }

    WordPress_GitHub_Sync::write_log(__( 'Setting the master branch to our new commit.', WordPress_GitHub_Sync::$text_domain ));
    $ref_sha = $this->set_ref($commit_sha);

    if ( is_wp_error( $ref_sha ) ) {
      $this->error($ref_sha);
      die();
    }

    $this->success();
  }

  /**
   * Create the tree from the saved blobs
   */
  function create_tree() {
    global $wpghs;

    if ($wpghs->push_lock)
      return false;

    $tree = array();

    foreach ($this->blobs as $post_id) {
      $post = new WordPress_GitHub_Sync_Post($post_id);
      $tree[] = array(
        "path" => $post->github_path(),
        "mode" => "100644",
        "type" => "blob",
        "sha"  => $post->sha(),
      );
    }

    $args = array(
      "method"  => "POST",
      "headers" => array(
          "Authorization" => "token " . $wpghs->oauth_token()
        ),
      "body"    => json_encode( array(
          'tree' => $tree,
          'base_tree' => $this->last_tree_sha(),
        )
      )
    );

    $response = wp_remote_request( $this->tree_endpoint(), $args );
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if ($data && isset($data->sha) && !isset($data->errors)) {
      return $data->sha;
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }
  }

  /**
   * Retrieves the sha for the last tree
   *
   * Makes a live call if not saved
   */
  function last_tree_sha() {
    global $wpghs;

    $sha = get_option( "_wpghs_last_tree_sha" );

    if ( !empty($sha) ) {
      return $sha;
    }

    $response = wp_remote_get( $this->commit_endpoint() . "/" . $this->last_commit_sha(), array(
      "headers" => array(
        "Authorization" => "token " . $wpghs->oauth_token()
        )
      )
    );
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if ($data && isset($data->tree) && !isset($data->errors)) {
      update_option( "_wpghs_last_tree_sha", $data->tree->sha );
      return $data->tree->sha;
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }
  }

  /**
   * Create the commit from tree sha
   *
   * $sha - string   shasum for the tree for this commit
   */
  function create_commit($sha) {
    global $wpghs;

    if ($wpghs->push_lock)
      return false;

    $commit = array(
      "message" => "Full export from WordPress at " . site_url() . " (" . get_bloginfo( 'name' ) . ")",
      "author"  => $this->export_user(),
      "tree"    => $sha,
      "parents" => array( $this->last_commit_sha() ),
    );

    $args = array(
      "method"  => "POST",
      "headers" => array(
          "Authorization" => "token " . $wpghs->oauth_token()
        ),
      "body"    => json_encode( $commit )
    );

    $response = wp_remote_request( $this->commit_endpoint(), $args );
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if ($data && isset($data->sha) && !isset($data->errors)) {
      return $data->sha;
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }
  }

  /**
   * Updates the master branch to point to the new commit
   *
   * $sha - string   shasum for the commit for the master branch
   */
  function set_ref($sha) {
    global $wpghs;

    if ($wpghs->push_lock)
      return false;

    $args = array(
      "method"  => "POST",
      "headers" => array(
          "Authorization" => "token " . $wpghs->oauth_token()
        ),
      "body"    => json_encode( array(
          'sha' => $sha,
        )
      )
    );

    $response = wp_remote_request( $this->master_reference_endpoint(), $args );
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if ($data && isset($data->object) && !isset($data->errors)) {
      update_option( '_wpghs_last_commit_sha', $data->object->sha );
      return $data->object->sha;
    } else {
      // save a message and quit
      if ( isset($data->message) ) {
        $error = new WP_Error( 'wpghs_error_message', $data->message );
      } elseif( empty($data) ) {
        $error = new WP_Error( 'wpghs_error_message', __( 'No body returned', WordPress_GitHub_Sync::$text_domain ) );
      }

      return $error;
    }
  }

  /**
   * Get the data for the current user
   */
  function export_user() {
    $user_id = get_option( '_wpghs_export_user_id' );
    delete_option( '_wpghs_export_user_id' );

    $user = get_user_by( 'id', intval($user_id) );

    return array(
      'name'  => $user->display_name,
      'email' => $user->user_email,
    );
  }

  /**
   * Retrieve the sha for the latest commit
   *
   * Will make a live call if not found
   */
  function last_commit_sha() {
    global $wpghs;

    $sha = get_option( "_wpghs_last_commit_sha" );

    if ( !empty($sha) ) {
      return $sha;
    }

    $response = wp_remote_get( $this->master_reference_endpoint(), array(
      "headers" => array(
        "Authorization" => "token " . $wpghs->oauth_token()
        )
      )
    );
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);
    $sha = $data->object->sha;

    update_option( "_wpghs_last_commit_sha", $sha );
    return $sha;
  }

  /**
   * Api to update the master branch's reference
   */
  function master_reference_endpoint() {
    global $wpghs;
    $url = $wpghs->api_base() . "/repos/";
    $url = $url . $wpghs->repository() . "/git/refs/heads/master";
    return $url;
  }

  /**
   * Api to get and create commits
   */
  function commit_endpoint() {
    global $wpghs;
    $url = $wpghs->api_base() . "/repos/";
    $url = $url . $wpghs->repository() . "/git/commits";
    return $url;
  }

  /**
   * Api to get and create trees
   */
  function tree_endpoint() {
    global $wpghs;
    $url = $wpghs->api_base() . "/repos/";
    $url = $url . $wpghs->repository() . "/git/trees";
    return $url;
  }

  /**
   * Writes out the results of a successful export
   */
  function success() {
    update_option( '_wpghs_export_complete', 'yes' );
    delete_option( '_wpghs_posts_to_export' );
    delete_option( '_wpghs_exported_blobs' );
    WordPress_GitHub_Sync::write_log( __('Export to GitHub completed successfully.', WordPress_GitHub_Sync::$text_domain ), 'success' );
  }

  /**
   * Writes out the results of an error and saves the data
   */
  function error($result) {
    update_option( '_wpghs_export_error', $result->get_error_message() );
    WordPress_GitHub_Sync::write_log( __("Error exporting to GitHub. Error: ", WordPress_GitHub_Sync::$text_domain ) . $result->get_error_message(), 'error' );

    $this->save_data();
  }

  /**
   * Retrieves the object's data from the database
   */
  function get_data() {
    global $wpdb;
    $posts = get_option( '_wpghs_posts_to_export' );

    if ( ! $posts ) {
      $posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ('post', 'page' )" );
    }

    $this->posts = $posts;

    $blobs = get_option( '_wpghs_exported_blobs' );

    if ( ! $blobs ) {
      $blobs = array();
    }

    $this->blobs = $blobs;
  }

  /**
   * Save the object's data to the database
   */
  function save_data() {
    update_option( '_wpghs_posts_to_export', $this->posts );
    update_option( '_wpghs_exported_blobs', $this->blobs );
  }
}