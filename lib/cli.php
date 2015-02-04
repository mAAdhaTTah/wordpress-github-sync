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

    if ( !is_numeric($user_id) ) {
      WP_CLI::error( __("Invalid user ID", WordPress_GitHub_Sync::$text_domain) );
    }

    update_option( '_wpghs_export_user_id', (int) $user_id );

    if ( $post_id === 'all' ) {
      WP_CLI::line( __( 'Starting full export to GitHub.', WordPress_GitHub_Sync::$text_domain ) );
      $this->controller->export_all();
    } elseif ( is_numeric($post_id) ) {
      WP_CLI::line( __( 'Exporting post ID to GitHub: ', WordPress_GitHub_Sync::$text_domain ). $post_id );
      $this->controller->export_post((int) $post_id);
    } else {
      WP_CLI::error( __("Invalid post ID", WordPress_GitHub_Sync::$text_domain) );
    }
  }

  /**
   * Imports the post in your GitHub repo
   * into your WordPress blog
   *
   * ## OPTIONS
   *
   * <user_id>
   * : The user ID you'd like to save the commit as
   *
   * ## EXAMPLES
   *
   *     wp wpghs import 1
   *
   * @synopsis <user_id>
   */
  function import( $args, $assoc_args ) {
    list( $user_id ) = $args;

    if ( !is_numeric($user_id) ) {
      WP_CLI::error( __("Invalid user ID", WordPress_GitHub_Sync::$text_domain) );
    }

    update_option( '_wpghs_export_user_id', (int) $user_id );

    WP_CLI::line( __( 'Starting import from GitHub.', WordPress_GitHub_Sync::$text_domain ) );

    $this->controller->import_master();
  }
}