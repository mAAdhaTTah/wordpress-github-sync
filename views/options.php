<?php
/**
 * Options Page View.
 * @package WordPress_GitHub_Sync
 */

?>
<div class="wrap">
	<h2><?php esc_html_e( 'WordPress <--> GitHub Sync', 'wordpress-github-sync' ); ?></h2>

	<form method="post" action="options.php">
		<?php settings_fields( 'wordpress-github-sync' ); ?>
		<?php do_settings_sections( 'wordpress-github-sync' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Webhook callback', 'wordpress-github-sync' ); ?></th>
				<td><code><?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>?action=wpghs_sync_request</code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Bulk actions', 'wordpress-github-sync' ); ?></th>
				<td>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'export' ) ) ); ?>">
						<?php esc_html_e( 'Export to GitHub', 'wordpress-github-sync' ); ?>
					</a> |
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'import' ) ) ); ?>">
						<?php esc_html_e( 'Import from GitHub', 'wordpress-github-sync' ); ?>
					</a>
				</td>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
