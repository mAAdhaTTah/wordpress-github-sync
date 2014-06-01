<?php
class WordPress_GitHub_Sync_Admin {
  function __construct() {
    add_action( 'admin_menu', array( &$this, 'add_admin_menu' ) );
    add_action( 'admin_init', array( &$this, 'register_settings' ) );
  }

  function settings_page() {
    include dirname(dirname( __FILE__ )) . '/views/options.php';
  }

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
        "help_text" => __("The GitHub repository to commit to, with owner, e.g., <code>benbalter/benbalter.github.com</code>.", WordPress_GitHub_Sync::$text_domain)
      )
    );
    register_setting( WordPress_GitHub_Sync::$text_domain, "wpghs_oauth_token" );
    add_settings_field( "wpghs_oauth_token", __("Oauth Token", WordPress_GitHub_Sync::$text_domain), array(&$this, "field_callback"), WordPress_GitHub_Sync::$text_domain, "general", array(
        "default"   => "",
        "name"      => "wpghs_oauth_token",
        "help_text" => __("A <a href='https://github.com/settings/tokens/new'>personal oauth token</a> with <code>public_repo</code> scope.", WordPress_GitHub_Sync::$text_domain)
      )
    );
  }

  function field_callback($args) {
    include dirname(dirname( __FILE__ )) . '/views/setting-field.php';
  }

  function section_callback() { }

  function add_admin_menu() {
    add_options_page( __('WordPress <--> GitHub Sync', WordPress_GitHub_Sync::$text_domain), __('GitHub Sync', WordPress_GitHub_Sync::$text_domain), 'manage_options', WordPress_GitHub_Sync::$text_domain, array( &$this, 'settings_page' ) );
  }
}
