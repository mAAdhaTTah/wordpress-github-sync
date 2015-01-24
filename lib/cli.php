<?php
/**
 * WP_CLI Commands
 */
class WordPress_GitHub_Sync_CLI {

  /**
   * Wire up controller object on init
   */
  function __construct() {
    $this->controller = new WordPress_GitHub_Sync_Controller;
  }

  /**
   * Exports an individual post
   * all your posts to GitHub
   *
   * ## OPTIONS
   *
   * <post_id|all>
   * : The post ID to export or 'all' for full site
   *
   * <user_id>
   * : The user ID you'd like to save the commit as
   *
   * ## EXAMPLES
   *
   *     wp wpghs export all 1
   *     wp wpghs export 1 1
   *
   * @synopsis <post_id|all> <user_id>
   */
  function export( $args, $assoc_args ) {
    list( $post_id, $user_id ) = $args;

    if ( $post_id === 'all' ) {
      update_option( '_wpghs_export_user_id', $user_id );
      $this->controller->process();
    } elseif ( is_numeric($post_id) ) {
      $post = new WordPress_GitHub_Sync_Post($post_id);
      $this->controller->api->push($post);
    } else {
      WP_CLI::error( __("Invalid Post ID", WordPress_GitHub_Sync::$text_domain) );
    }
  }
}