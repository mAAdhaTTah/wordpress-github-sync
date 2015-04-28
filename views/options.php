<div class="wrap">
	<h2><?php _e( 'WordPress <--> GitHub Sync', WordPress_GitHub_Sync::$text_domain ); ?></h2>
	<form method="post" action="options.php">
		<?php settings_fields( WordPress_GitHub_Sync::$text_domain ); ?>
		<?php do_settings_sections( WordPress_GitHub_Sync::$text_domain ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php _e( 'Webhook callback', WordPress_GitHub_Sync::$text_domain ); ?></th>
				<td><code><?php echo admin_url( 'admin-ajax.php' ); ?>?action=wpghs_sync_request</code></td>
			</tr>
			<tr>
				<th scope="row"><?php _e( 'Bulk actions', WordPress_GitHub_Sync::$text_domain ); ?></th>
				<td>
					<a href="<?php echo add_query_arg( array( 'action' => 'export' ) ) ?>"><?php _e( 'Export to GitHub', WordPress_GitHub_Sync::$text_domain ); ?></a> |
					<a href="<?php echo add_query_arg( array( 'action' => 'import' ) ) ?>"><?php _e( 'Import from GitHub', WordPress_GitHub_Sync::$text_domain ); ?></a>
				</td>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
