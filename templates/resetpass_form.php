<?php wp_enqueue_script('utils');
wp_enqueue_script('user-profile');
wp_admin_css( 'login', true );
wp_enqueue_script('user-profile'); ?>
<div id="password-reset-form" class="widecolumn">
  <?php if ( count( $attributes['errors'] ) > 0 ) : ?>
    <?php foreach ( $attributes['errors'] as $error ) : ?>
        <p>
            <?php echo $error; ?>
        </p>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ( $attributes['show_title'] ) : ?>
      <h3><?php _e( 'Pick a New Password', 'personalize-login' ); ?></h3>
  <?php endif; ?>

  <form name="resetpassform" id="resetpassform" action="<?php echo esc_url( network_site_url( 'wp-login.php?action=resetpass', 'login_post' ) ); ?>" method="post" autocomplete="off">
  	<input type="hidden" name="rp_login" id="user_login" value="<?php echo esc_attr( $attributes['login'] ); ?>" autocomplete="off" />

  	<div class="user-pass1-wrap">
  		<p>
  			<label for="pass1"><?php _e( 'New password' ) ?></label>
  		</p>

  		<div class="wp-pwd">
  			<span class="password-input-wrapper">
  				<input type="password" data-reveal="1" data-pw="<?php echo esc_attr( wp_generate_password( 16 ) ); ?>" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="off" aria-describedby="pass-strength-result" />
  			</span>
  			<div id="pass-strength-result" class="hide-if-no-js" aria-live="polite"><?php _e( 'Strength indicator' ); ?></div>
  		</div>
  	</div>

  	<p class="user-pass2-wrap">
  		<label for="pass2"><?php _e( 'Confirm new password' ) ?></label><br />
  		<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" />
  	</p>

  	<p class="description indicator-hint"><?php echo wp_get_password_hint(); ?></p>
  	<br class="clear" />

  	<?php
  	/**
  	 * Fires following the 'Strength indicator' meter in the user password reset form.
  	 *
  	 * @since 3.9.0
  	 *
  	 * @param WP_User $user User object of the user whose password is being reset.
  	 */
  	do_action( 'resetpass_form', $user );
  	?>
  	<input type="hidden" name="rp_key" value="<?php echo esc_attr( $attributes['key'] ); ?>" />
  	<p class="submit"><input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Reset Password'); ?>" /></p>
  </form>
</div>
