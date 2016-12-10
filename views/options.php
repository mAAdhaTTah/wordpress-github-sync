<?php
/**
 * Options Page View.
 * @package WordPress_GitHub_Sync
 */

?>
<div class="wrap">
	<h2><?php esc_html_e( 'WordPress <--> GitHub Sync', 'wp-github-sync' ); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( WordPress_GitHub_Sync::$text_domain ); ?>
		<?php do_settings_sections( WordPress_GitHub_Sync::$text_domain ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Webhook callback', 'wp-github-sync' ); ?></th>
				<td><code><?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=wpghs_sync_request</code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Bulk actions', 'wp-github-sync' ); ?></th>
				<td>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'export' ) ) ); ?>">
						<?php esc_html_e( 'Export to GitHub', 'wp-github-sync' ); ?>
					</a> |
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'import' ) ) ); ?>">
						<?php esc_html_e( 'Import from GitHub', 'wp-github-sync' ); ?>
					</a>
				</td>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
