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
  function __construct() {
    $this->api = new WordPress_GitHub_Sync_Api;
    $this->posts = array();
    $this->tree = array();
  }

  /**
   * Sets up and begins the background export process
   */
  function cron_start() {
    wp_schedule_single_event(time(), 'wpghs_export');
    WordPress_GitHub_Sync::write_log( __( "Starting export to GitHub.", WordPress_GitHub_Sync::$text_domain ) );
    spawn_cron();
    update_option( '_wpghs_export_started', 'yes' );
  }

  /**
   * Sets up and begins the CLI export process
   */
  function process() {
    $this->get_posts();
    $this->get_tree();
    $this->changed = false;

    WordPress_GitHub_Sync::write_log( __("Building the tree.", WordPress_GitHub_Sync::$text_domain ) );
    foreach ($this->posts as $post_id) {
      $this->export_post($post_id);
    }

    $this->finalize();
  }

  /**
   * Takes the next post off the top of the list
   * and exports it to the new GitHub tree
   */
  function export_post($post_id) {
    $match = false;
    $post = new WordPress_GitHub_Sync_Post($post_id);

    foreach ($this->tree as $index => $blob) {
      if ( !isset($blob->sha)) {
        continue;
      }

      if ( $blob->sha === $post->sha() ) {
        $match = true;

        $this->tree[ $index ] = $this->new_blob($post, $blob);
        break;
      }
    }

    if ( ! $match ) {
      $this->tree[] = $this->new_blob($post);
      $this->changed = true;
    }
  }

  /**
   * After all the blobs are saved,
   * create the tree, commit, and adjust master ref
   */
  function finalize() {
    if ( ! $this->changed ) {
      $this->no_change();
      die();
    }

    WordPress_GitHub_Sync::write_log(__( 'Creating the tree.', WordPress_GitHub_Sync::$text_domain ));
    $tree = $this->api->create_tree($this->tree);

    if ( is_wp_error( $tree ) ) {
      $this->error($tree);
      die();
    }

    $rtree = $this->api->last_tree_recursive();

    $this->save_post_shas($rtree);

    WordPress_GitHub_Sync::write_log(__( 'Creating the commit.', WordPress_GitHub_Sync::$text_domain ));
    $commit_sha = $this->api->create_commit($tree->sha);

    if ( is_wp_error( $commit_sha ) ) {
      $this->error($commit_sha);
      die();
    }

    WordPress_GitHub_Sync::write_log(__( 'Setting the master branch to our new commit.', WordPress_GitHub_Sync::$text_domain ));
    $ref_sha = $this->api->set_ref($commit_sha);

    if ( is_wp_error( $ref_sha ) ) {
      $this->error($ref_sha);
      die();
    }

    $this->success();
  }

  /**
   * Combines a post and (potentially) a blob
   *
   * If no blob is provided, turns post into blob
   *
   * If blob is provided, compares blob to post
   * and updates blob data based on differences
   */
  function new_blob($post, $blob = array()) {
    if ( empty($blob) ) {
      $blob = $this->blob_from_post($post);
    } else {
      unset($blob->url);
      unset($blob->size);

      if ( $blob->path !== $post->github_path()) {
        $blob->path = $post->github_path();
        $this->changed = true;
      }

      $blob_data = $this->api->get_blob($blob->sha);

      if ( base64_decode($blob_data->content) !== $post->github_content() ) {
        unset($blob->sha);
        $blob->content = $post->github_content();
        $this->changed = true;
      }
    }

    return $blob;
  }

  /**
   * Creates a blob with the data required for the tree
   */
  function blob_from_post($post) {
    $blob = new stdClass;

    $blob->path = $post->github_path();
    $blob->mode = "100644";
    $blob->type = "blob";
    $blob->content = $post->github_content();

    return $blob;
  }

  /**
   * Use the new tree to save sha data
   * for all the updated posts
   */
  function save_post_shas($tree) {
    foreach ($this->posts as $post_id) {
      $post = new WordPress_GitHub_Sync_Post($post_id);
      $match = false;

      foreach ($tree as $blob) {
        // this might be a problem if the filename changed since it was set
        // (i.e. post updated in middle mass export)
        // solution?
        if ($post->github_path() === $blob->path) {
          $post->set_sha($blob->sha);
          $match = true;
          break;
        }
      }

      if ( ! $match ) {
        WordPress_GitHub_Sync::write_log( __('No sha matched for post ID ', WordPress_GitHub_Sync::$text_domain ) . $post_id);
      }
    }
  }

  /**
   * Writes out the results of an unchanged export
   */
  function no_change() {
    update_option( '_wpghs_export_complete', 'yes' );
    delete_option( '_wpghs_posts_to_export' );
    WordPress_GitHub_Sync::write_log( __('There were no changes, so no additional commit was added.', WordPress_GitHub_Sync::$text_domain ), 'warning' );
  }

  /**
   * Writes out the results of a successful export
   */
  function success() {
    update_option( '_wpghs_export_complete', 'yes' );
    update_option( '_wpghs_fully_exported', 'yes' );
    delete_option( '_wpghs_posts_to_export' );
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
   * Retrieves all the posts to export
   * unless we've already set that info
   */
  function get_posts() {
    global $wpdb;

    if ( ! empty($this->posts) ) {
      return;
    }

    $posts = get_option( '_wpghs_posts_to_export' );

    if ( ! $posts ) {
      $posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ('post', 'page' )" );
    }

    $this->posts = $posts;
  }

  /**
   * Retrieve the saved tree we're building
   * or get the latest tree from the repo
   */
  function get_tree() {
    if ( ! empty($this->tree) ) {
      return;
    }

    $this->tree = $this->api->last_tree_recursive();
  }

  /**
   * Save the object's data to the database
   */
  function save_data() {
    update_option( '_wpghs_posts_to_export', $this->posts );
  }
}