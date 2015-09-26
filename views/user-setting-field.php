<?php $value = get_option( $args['name'], $args['default'] ); ?>
<select name="<?php echo esc_attr( $args['name'] ) ?>" id="<?php echo esc_attr( $args['name'] ) ?>">
	<?php foreach ( get_users() as $user ) : ?>
		<option value="<?php echo esc_attr( $user->ID ); ?>"<?php echo (int) $value === $user->ID ? ' selected' : '';?>>
			<?php echo esc_html( $user->display_name ); ?>
		</option>
	<?php endforeach; ?>
</select>
<p class="description"><?php echo $args['help_text'] ?></p>
