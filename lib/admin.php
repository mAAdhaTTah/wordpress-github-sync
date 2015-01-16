<?php
/**
 * Administrative UI views and callbacks
 */
class WordPress_GitHub_Sync_Admin {

  /**
   * Hook into GitHub API
   */
  function __construct() {
    add_action( 'admin_menu', array( &$this, 'add_admin_menu' ) );
    add_action( 'admin_init', array( &$this, 'register_settings' ) );
    add_action( 'current_screen', array( &$this, 'callback' ) );
  }

  /**
   * Callback to render the settings page view
   */
  function settings_page() {
    include dirname(dirname( __FILE__ )) . '/views/options.php';
  }

  /**
   * Callback to register the plugin's options
   */
  function register_settings() {
    add_settings_section( "general", "General Settings", array(&$this, "section_callback"), WordPress_GitHub_Sync::$text_domain );

    register_setting( WordPress_GitHub_Sync::$text_domain, "wpghs_host" );
    add_settings_field( "wpghs_host", __("GitHub hostname", WordPress_GitHub_Sync::$text_domain), array(&$this, "field_callback"), WordPress_GitHub_Sync::$text_domain, "general", array(
        "default"   => "https://api.github.com",
        "name"      => "wpghs_host",
        "help_text" => __("The GitHub host to use. Can be changed to support a GitHub Enterprise installation.", WordPress_GitHub_Sync::$text_domain)
      )
    );

    register_setting( WordPress_GitHub_Sync::$text_domain, "wpghs_repository" );
    add_settings_field( "wpghs_repository", __("Repository",WordPress_GitHub_Sync::$text_domain), array(&$this, "field_callback"), WordPress_GitHub_Sync::$text_domain, "general", array(
        "default"   => "",
        "name"      => "wpghs_repository",
        "help_text" => __("The GitHub repository to commit to, with owner (<code>[OWNER]/[REPOSITORY]</code>), e.g., <code>benbalter/benbalter.github.com</code>.", WordPress_GitHub_Sync::$text_domain)
      )
    );

    register_setting( WordPress_GitHub_Sync::$text_domain, "wpghs_oauth_token" );
    add_settings_field( "wpghs_oauth_token", __("Oauth Token", WordPress_GitHub_Sync::$text_domain), array(&$this, "field_callback"), WordPress_GitHub_Sync::$text_domain, "general", array(
        "default"   => "",
        "name"      => "wpghs_oauth_token",
        "help_text" => __("A <a href='https://github.com/settings/tokens/new'>personal oauth token</a> with <code>public_repo</code> scope.", WordPress_GitHub_Sync::$text_domain)
      )
    );

    register_setting( WordPress_GitHub_Sync::$text_domain, "wpghs_secret" );
    add_settings_field( "wpghs_secret", __("Webhook Secret", WordPress_GitHub_Sync::$text_domain), array(&$this, "field_callback"), WordPress_GitHub_Sync::$text_domain, "general", array(
        "default"   => "",
        "name"      => "wpghs_secret",
        "help_text" => __("The webhook's secret phrase.", WordPress_GitHub_Sync::$text_domain)
      )
    );
  }

  /**
   * Callback to render an individual options field
   */
  function field_callback($args) {
    include dirname(dirname( __FILE__ )) . '/views/setting-field.php';
  }

  /**
   * Displays settings messages from background processes
   */
  function section_callback() {
    if ( get_current_screen()->id != "settings_page_" . WordPress_GitHub_Sync::$text_domain)
      return;

    if ('yes' === get_option( '_wpghs_export_started' )) { ?>
      <div class="updated">
        <p><?php _e( 'Export to GitHub started.', WordPress_GitHub_Sync::$text_domain ); ?></p>
      </div><?php
      delete_option( '_wpghs_export_started' );
    }

    if ( $message = get_option( '_wpghs_export_error'  ) ) { ?>
      <div class="error">
        <p><?php _e( 'Export to GitHub failed with error:', WordPress_GitHub_Sync::$text_domain ); ?> <?php echo esc_html( $message ) ;?></p>
      </div><?php
      delete_option( '_wpghs_export_error' );
    }

    if ( 'yes' === get_option( '_wpghs_export_complete'  ) ) { ?>
      <div class="updated">
        <p><?php _e( 'Export to GitHub completed successfully.', WordPress_GitHub_Sync::$text_domain ); ?></p>
      </div><?php
      delete_option( '_wpghs_export_complete' );
    }

  }

  /**
   * Add options menu to admin navbar
   */
  function add_admin_menu() {
    add_options_page( __('WordPress <--> GitHub Sync', WordPress_GitHub_Sync::$text_domain), __('GitHub Sync', WordPress_GitHub_Sync::$text_domain), 'manage_options', WordPress_GitHub_Sync::$text_domain, array( &$this, 'settings_page' ) );
  }

  /**
   * Admin callback to trigger import/export because WordPress admin routing lol
   */
  function callback() {
    global $wpghs;

    if ( !current_user_can( 'manage_options' ) )
      return;

    if ( get_current_screen()->id != "settings_page_" . WordPress_GitHub_Sync::$text_domain)
      return;

    if ( !isset($_GET['action'] ) )
      return;

    if ($_GET['action'] == "export")
      $wpghs->start_export();

  }
}
