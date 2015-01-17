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
   * Sets up and begins the export process
   */
  function start() {
    wp_schedule_single_event(time(), 'wpghs_export');
    WordPress_GitHub_Sync::write_log( __( "Starting export to GitHub", WordPress_GitHub_Sync::$text_domain ) );
    spawn_cron();
    update_option( '_wpghs_export_started', 'yes' );
  }

  /**
   * Export posts
   *
   * Runs as cronjob
   */
  function process() {
    $i = 0;
    $this->get_data();

    while(!empty($this->posts) && $i < 50) {
      $post_id = array_shift($this->posts);
      WordPress_GitHub_Sync::write_log( __("Exporting Post ID: ", WordPress_GitHub_Sync::$text_domain ) . $post_id );

      $post = new WordPress_GitHub_Sync_Post($post_id);
      $result = $post->push();

      if ( is_wp_error( $result ) ) {
        array_unshift($this->posts, $post_id);
        $this->error($result);
        die();
      }

      usleep(500000);
      $this->blobs[] = $result;

      $i++;
    }

    if (!empty($this->posts)) {
      $this->handoff();
    } else {
      $this->success();
    }

    die();
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
   * Writes out the results of a successful export
   */
  function success() {
    update_option( '_wpghs_export_complete', 'yes' );
    delete_option( '_wpghs_posts_to_export' );
    delete_option( '_wpghs_exported_blobs' );
    WordPress_GitHub_Sync::write_log( __('Export to GitHub completed successfully.', WordPress_GitHub_Sync::$text_domain ) );
  }

  /**
   * Writes out the results of an error and saves the data
   */
  function error($result) {
    update_option( '_wpghs_export_error', $result->get_error_message() );
    WordPress_GitHub_Sync::write_log( __("Error exporting to GitHub. Error: ", WordPress_GitHub_Sync::$text_domain ) . $result->get_error_message() );

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
    WordPress_GitHub_Sync::write_log( __( "Number of retrieved posts: ", WordPress_GitHub_Sync::$text_domain ) . count($this->posts) );

    $blobs = get_option( '_wpghs_exported_blobs' );

    if ( ! $blobs ) {
      $blobs = array();
    }

    $this->blobs = $blobs;
    WordPress_GitHub_Sync::write_log( __( "Number of retrived blobs: ", WordPress_GitHub_Sync::$text_domain ) . count($this->blobs) );
  }

  /**
   * Save the object's data to the database
   */
  function save_data() {
    update_option( '_wpghs_posts_to_export', $this->posts );
    update_option( '_wpghs_exported_blobs', $this->blobs );
    WordPress_GitHub_Sync::write_log( __( "Number of saved posts: ", WordPress_GitHub_Sync::$text_domain ) . count($this->posts) );
    WordPress_GitHub_Sync::write_log( __( "Number of saved blobs: ", WordPress_GitHub_Sync::$text_domain ) . count($this->blobs) );
  }
}