<div id="register-form" class="widecolumn">
    <?php if ( $attributes['show_title'] ) : ?>
        <h3><?php _e( 'Register', 'personalize-login' ); ?></h3>
    <?php endif; ?>

    <form id="signupform" action="<?php echo wp_registration_url(); ?>" method="post">
        <p class="form-row">
            <label for="email"><?php _e( 'Email', 'personalize-login' ); ?> <strong>*</strong></label>
            <input type="text" name="email" id="email">
        </p>

        <p class="form-row">
            <label for="first_name"><?php _e( 'First name', 'personalize-login' ); ?></label>
            <input type="text" name="first_name" id="first-name">
        </p>

        <p class="form-row">
            <label for="last_name"><?php _e( 'Last name', 'personalize-login' ); ?></label>
            <input type="text" name="last_name" id="last-name">
        </p>

        <p class="form-row">
            <?php _e( 'Note: Your password will be generated automatically and sent to your email address.', 'personalize-login' ); ?>
        </p>

        <!-- Honeypot -->
        <input type="text" style="border:none;height:0;font-size:0;position:absolute;left:-9999px;" id="foobar" name="foobar" placeholder="Foobar" autocomplete="off">

        <p class="signup-submit">
            <input type="submit" name="submit" class="register-button"
                   value="<?php _e( 'Register', 'personalize-login' ); ?>"/>
        </p>
    </form>
</div>
