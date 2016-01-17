<?php
/**
 * Test Option Field.
 * @package WordPress_GitHub_Sync
 */

?>
<?php $value = get_option( $args['name'], $args['default'] ); ?>
<input name="<?php echo esc_attr( $args['name'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>" type="<?php echo 'wpghs_secret' === $args['name'] ? 'password' : 'text'; ?>" value="<?php echo esc_attr( $value ); ?>" />
<p class="description"><?php echo $args['help_text']; ?></p>
