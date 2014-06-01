<div class="wrap">
  <h2>WordPress <--> GitHub Sync</h2>
  <form method="post" action="options.php">
    <?php settings_fields( WordPressGitHubSync::$text_domain ); ?>
    <?php do_settings_sections( WordPressGitHubSync::$text_domain ); ?>
    <?php submit_button(); ?>
  </form>
</div>
