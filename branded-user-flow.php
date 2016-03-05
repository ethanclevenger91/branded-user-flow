<?php
/**
 * Plugin Name: Branded User Flow
 * Description: Replace the WordPress user flow
 * Version: 1.1.0
 * Author: Ethan Clevenger
 * BitBucket Plugin URI: https://bitbucket.org/webspec/branded-user-flow
 * Based on the tutorial by Jarkko Laine at http://code.tutsplus.com/tutorials/build-a-custom-wordpress-user-flow-part-1-replace-the-login-page--cms-23627
 */

class Branded_User_Flow {

  /**
   * Initialize plugin
   */
  public function __construct() {
    //shortcodes
    add_shortcode('branded-user-flow-login', [$this, 'render_login_form']);
    add_shortcode('branded-user-flow-register', [$this, 'render_register_form']);
    add_shortcode('branded-user-flow-lostpassword', [$this, 'render_lostpassword_form']);
    add_shortcode('branded-user-flow-resetpass', [$this, 'render_resetpass_form']);
    add_shortcode('branded-user-flow-account', [$this, 'render_account_form']);

    //login hooks
    add_action('login_form_login', [$this, 'redirect_to_custom_login']);
    add_filter('authenticate', [$this, 'maybe_redirect_at_authenticate'], 101, 3);
    add_action('wp_logout', [$this, 'redirect_after_logout']);
    add_filter('login_redirect', [$this, 'redirect_after_login'], 10, 3);

    //register hooks
    add_action('login_form_register', [$this, 'redirect_to_custom_register']);
    add_action('login_form_register', [$this, 'do_register_user']);

    //reset/lost password hooks
    add_action('login_form_lostpassword', [$this, 'redirect_to_custom_lostpassword']);
    add_action('login_form_lostpassword', [$this, 'do_password_lost']);
    add_action('login_form_rp', [$this, 'redirect_to_custom_resetpass']);
    add_action('login_form_resetpass', [$this, 'redirect_to_custom_resetpass']);
    add_action('login_form_rp', [$this, 'do_password_reset']);
    add_action('login_form_resetpass', [$this, 'do_password_reset']);

    //if you'd like to customize the password reset email, use the following in your own plugin or functions.php (it has not been implemented in this plugin in the interest of being unopinionated):
    //add_filter( 'retrieve_password_message', array( $this, 'replace_retrieve_password_message' ), 10, 4 );

  }

  /**
   * Plugin Activation http_build_cookie
   *
   * Creates WordPress pages
   */

  public static function plugin_activated() {
    $page_definitions = [
      'member-login' => [
        'title' => __( 'Sign In', 'personalize-login' ),
        'content' => '[branded-user-flow-login]'
      ],
      'member-account' => [
        'title' => __('Your Account', 'personalize-login'),
        'content' => '[branded-user-flow-account]'
      ],
      'member-register' => [
        'title' => __('Register', 'personalize-login'),
        'content' => '[branded-user-flow-register]'
      ],
      'member-password-lost' => [
        'title' => __('Forgot Your Password?', 'personalize-login'),
        'content' => '[branded-user-flow-lostpassword]'
      ],
      'member-password-reset' => [
        'title' => __('Pick a New Password', 'personalize-login'),
        'content' => '[branded-user-flow-resetpass]'
      ]
    ];
    foreach($page_definitions as $slug => $page) {
      $query = new WP_Query(['pagename' => $slug]);
      if(!$query->have_posts()) {
        wp_insert_post([
          'post_content' => $page['content'],
          'post_name' => $slug,
          'post_title' => $page['title'],
          'post_status' => 'publish',
          'post_type' => 'page',
          'ping_status' => 'closed',
          'comment_status' => 'closed'
        ]);
      }
    }
  }

  /**
   * Redirect user to custom login page rather than wp-login.php
   */

  function redirect_to_custom_login() {
    if($_SERVER['REQUEST_METHOD'] == 'GET') {
      $redirect_to = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : null;

      if(is_user_logged_in()) {
        $this->redirect_logged_in_user($redirect_to);
        exit;
      }

      $login_url = $this->get_login_url();
      if(!empty($redirect_to)) {
        $login_url = add_query_arg('redirect_to', $redirect_to, $login_url);
      }

      wp_redirect($login_url);
      exit;
    }
  }

  /**
   * Redirects the user to the custom registration page instead
   * of wp-login.php?action=register.
   */
  public function redirect_to_custom_register() {
    if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
        if ( is_user_logged_in() ) {
            $this->redirect_logged_in_user();
        } else {
            wp_redirect( $this->get_register_url() );
        }
        exit;
    }
  }

  /**
   * Redirects the user to the custom "Forgot your password?" page instead of
   * wp-login.php?action=lostpassword.
   */
  public function redirect_to_custom_lostpassword() {
    if('GET' == $_SERVER['REQUEST_METHOD']) {
      if(is_user_logged_in()) {
        $this->redirect_logged_in_user();
        exit;
      }
      wp_redirect($this->get_lostpassword_url());
      exit;
    }
  }

  /**
   * Redirects to the custom password reset page, or the login page
   * if there are errors.
   */
  public function redirect_to_custom_resetpass() {
    if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
      // Verify key / login combo
      $user = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );
      if ( ! $user || is_wp_error( $user ) ) {
        if ( $user && $user->get_error_code() === 'expired_key' ) {
            $redirect_url = get_login_url();
            $redirect_url = add_query_arg('login', 'expiredkey', $redirect_url);
            wp_redirect( $redirect_url );
        } else {
            $redirect_url = get_login_url();
            $redirect_url = add_query_arg('login', 'invalidkey', $redirect_url);
            wp_redirect( $redirect_url );
        }
        exit;
      }

      $redirect_url = $this->get_resetpass_url();
      $redirect_url = add_query_arg( 'login', esc_attr( $_REQUEST['login'] ), $redirect_url );
      $redirect_url = add_query_arg( 'key', esc_attr( $_REQUEST['key'] ), $redirect_url );

      wp_redirect( $redirect_url );
      exit;
    }
  }

  /**
   * Redirects the user to the correct page depending on whether or not admin
   *
   * @param string $redirect_to An optional redirect_to URL for admin users
   */

  private function redirect_logged_in_user($redirect_to = null) {
    $user = wp_get_current_user();
    if(user_can($user, 'manage_options')) {
      if($redirect_to) {
        wp_safe_redirect($redirect_to);
      } else {
        wp_redirect(admin_url());
      }
    } else {
      //TODO: add filter here or use settings page
      wp_redirect($this->get_logged_in_url($user));
    }
    exit;
  }

  /**
   * Returns the URL to which the user should be redirected after the (successful) login.
   *
   * @param string           $redirect_to           The redirect destination URL.
   * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
   * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
   *
   * @return string Redirect URL
   */
  public function redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
      $redirect_url = home_url();

      if ( ! isset( $user->ID ) ) {
          return $redirect_url;
      }

      if ( user_can( $user, 'manage_options' ) ) {
          // Use the redirect_to parameter if one is set, otherwise redirect to admin dashboard.
          if ( $requested_redirect_to == '' ) {
              $redirect_url = admin_url();
          } else {
              $redirect_url = $requested_redirect_to;
          }
      } else {
          // Non-admin users always go to their account page after login
          $redirect_url = $this->get_logged_in_url($user);
      }

      return wp_validate_redirect( $redirect_url, home_url() );
  }

  /**
   * Redirect the user after authentication if there were any errors.
   *
   * @param Wp_User|Wp_Error  $user       The signed in user, or the errors that have occurred during login.
   * @param string            $username   The user name used to log in.
   * @param string            $password   The password used to log in.
   *
   * @return Wp_User|Wp_Error The logged in user, or error information if there were errors.
   */

  function maybe_redirect_at_authenticate($user, $username, $password) {
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
      if(is_wp_error($user)) {
        $error_codes = join(',', $user->get_error_codes());

        $login_url = $this->get_login_url();
        $login_url = add_query_arg('login', $error_codes, $login_url);
        wp_redirect($login_url);
        exit;
      }
    }
    return $user;
  }

  /**
   * Redirect to custom login page after user has been logged out
   */
  public function redirect_after_logout() {
    $redirect_url = $this->get_login_url();
    $redirect_url = add_query_arg('logged_out', 'true', $redirect_url);
    wp_safe_redirect($redirect_url);
    exit;
  }

  /**
   * Handles the registration of a new user.
   *
   * Used through the action hook "login_form_register" activated on wp-login.php
   * when accessed through the registration action.
   */
  public function do_register_user() {
    if('POST' == $_SERVER['REQUEST_METHOD']) {
      $redirect_url = $this->get_register_url();
      if(!get_option('users_can_register')) {
        $redirect_url = add_query_arg('register-errors', 'closed', $redirect_url);
      } else if(empty($_POST['foobar'])) {
        $email = $_POST['email'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);

        $result = $this->register_user($email, $first_name, $last_name);

        do_action('branded_user_flow_after_register', $_POST, $result);

        if(is_wp_error($result)) {
          $errors = join(',', $result->get_error_codes());
          $redirect_url = add_query_arg('register-errors', $errors, $redirect_url);
        } else {
          $redirect_url = $this->get_login_url();
          $redirect_url = add_query_arg('registered', $email, $redirect_url);
        }
      }
      wp_redirect($redirect_url);
      exit;
    }
  }

  public function do_update_user() {
    if('POST' == $_SERVER['REQUEST_METHOD'] && is_user_logged_in()) {
      // TODO: Here would be the place to _update_ the existing user data
      do_action('branded_user_flow_after_update', $_POST, get_current_user_id());
    }
  }

  /**
   * Initiates password reset
   */

  public function do_password_lost() {
    if('POST' == $_SERVER['REQUEST_METHOD']) {
      $errors = retrieve_password();
      if(is_wp_error($errors)) {
        $redirect_url = $this->get_lostpassword_url();
        $redirect_url = add_query_arg('errors', join(',', $errors->get_error_codes()), $redirect_url);
      } else {
        $redirect_url = $this->get_login_url();
        $redirect_url = add_query_arg('checkemail', 'confirm', $redirect_url);
      }
      wp_redirect($redirect_url);
      exit;
    }
  }

  /**
   * Resets the user's password if the password reset form was submitted.
   */
  public function do_password_reset() {
    if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
      $rp_key = $_REQUEST['rp_key'];
      $rp_login = $_REQUEST['rp_login'];

      $user = check_password_reset_key( $rp_key, $rp_login );

      if ( ! $user || is_wp_error( $user ) ) {
        if ( $user && $user->get_error_code() === 'expired_key' ) {
            $redirect_url = get_login_url();
            $redirect_url = add_query_arg('login', 'expiredkey', $redirect_url);
            wp_redirect( $redirect_url );
        } else {
            $redirect_url = $this->get_login_url();
            $redirect_url = add_query_arg('login', 'invalidkey', $redirect_url);
            wp_redirect( $redirect_url );
        }
      }

      if ( isset( $_POST['pass1'] ) ) {
        if ( $_POST['pass1'] != $_POST['pass2'] ) {
          // Passwords don't match
          $redirect_url = $this->get_resetpass_url();

          $redirect_url = add_query_arg( 'key', $rp_key, $redirect_url );
          $redirect_url = add_query_arg( 'login', $rp_login, $redirect_url );
          $redirect_url = add_query_arg( 'error', 'password_reset_mismatch', $redirect_url );

          wp_redirect( $redirect_url );
          exit;
        }

        if ( empty( $_POST['pass1'] ) ) {
          // Password is empty
          $redirect_url = $this->get_resetpass_url();

          $redirect_url = add_query_arg( 'key', $rp_key, $redirect_url );
          $redirect_url = add_query_arg( 'login', $rp_login, $redirect_url );
          $redirect_url = add_query_arg( 'error', 'password_reset_empty', $redirect_url );

          wp_redirect( $redirect_url );
          exit;
        }

        // Parameter checks OK, reset password
        reset_password( $user, $_POST['pass1'] );
        $redirect_url = $this->get_login_url();
        $redirect_url = add_query_arg('password', 'changed', $redirect_url);
        wp_redirect( $redirect_url );
      } else {
        echo "Invalid request.";
      }

      exit;
    }
  }

  /**
   * Validates and then completes the new user signup process if all went well.
   *
   * @param string $email         The new user's email address
   * @param string $first_name    The new user's first name
   * @param string $last_name     The new user's last name
   *
   * @return int|WP_Error         The id of the user that was created, or error if failed.
   */
  private function register_user( $email, $first_name, $last_name ) {
    $errors = new WP_Error();

    // Email address is used as both username and email. It is also the only
    // parameter we need to validate
    if ( ! is_email( $email ) ) {
        $errors->add( 'email', $this->get_error_message( 'email' ) );
        return $errors;
    }

    if ( username_exists( $email ) || email_exists( $email ) ) {
        $errors->add( 'email_exists', $this->get_error_message( 'email_exists') );
        return $errors;
    }

    $user_data = array(
        'user_login'    => $email,
        'user_email'    => $email,
        'user_pass'     => $password,
        'first_name'    => $first_name,
        'last_name'     => $last_name,
        'nickname'      => $first_name,
    );

    $user_id = wp_insert_user( $user_data );
    wp_new_user_notification( $user_id, null, 'both' );

    return $user_id;
  }


  /**
   * Finds and returns a matching error message for the given error code.
   *
   * @param string $error_code    The error code to look up.
   *
   * @return string               An error message.
   */
  private function get_error_message($error_code) {
    switch($error_code) {
      case 'empty_username':
        return __('Email was blank', 'personalize-login');
        case 'empty_password':
            return __( 'Password was blank', 'personalize-login' );

        case 'invalid_username':
            return __(
                "We don't have any users with that email address. Maybe you used a different one when signing up?",
                'personalize-login'
            );

        case 'incorrect_password':
            $err = __(
                "The password you entered wasn't quite right. <a href='%s'>Did you forget your password</a>?",
                'personalize-login'
            );
            return sprintf( $err, wp_lostpassword_url() );

        // Registration errors

        case 'email':
          return __( 'The email address you entered is not valid.', 'personalize-login' );

        case 'email_exists':
          return __( 'An account exists with this email address.', 'personalize-login' );

        case 'closed':
          return __( 'Registering new users is currently not allowed.', 'personalize-login' );

        // Lost password

        case 'empty_username':
            return __( 'You need to enter your email address to continue.', 'personalize-login' );

        case 'invalid_email':
        case 'invalidcombo':
            return __( 'There are no users registered with this email address.', 'personalize-login' );

        // Reset password

        case 'expiredkey':
        case 'invalidkey':
            return __( 'The password reset link you used is not valid anymore.', 'personalize-login' );

        case 'password_reset_mismatch':
            return __( "The two passwords you entered don't match.", 'personalize-login' );

        case 'password_reset_empty':
            return __( "Sorry, we don't accept empty passwords.", 'personalize-login' );

        default:
            break;
    }
    return __( 'An unknown error occurred. Please try again later.', 'personalize-login' );
  }

  /**
   * A shortcode for rendering the login form.
   *
   * @param array $attributes Shortcode attributes
   * @param string $content The text content for shortcode. Not used
   *
   * @return string The shortcode output
   */
  public function render_login_form($attributes, $content=null) {
    $default_attributes = ['show_title' => false];
    $attributes = shortcode_atts($default_attributes, $attributes);
    $show_title = $attributes['show_title'];

    if(is_user_logged_in()) {
      return __( 'You are already signed in.', 'personalize-login');
    }

    $attributes['redirect'] = '';
    if(isset($_REQUEST['redirect_to'])) {
      $attributes['redirect'] = wp_validate_redirect($_REQUEST['redirect_to'], $attributes['redirect']);
    }

    //add error messages
    $errors = [];
    if(isset($_REQUEST['login'])) {
      $error_codes = explode(',', $_REQUEST['login']);
      foreach($error_codes as $code) {
        $errors[] = $this->get_error_message($code);
      }
    }
    $attributes['errors'] = $errors;

    //add other various messages
    $attributes['registered'] = isset( $_REQUEST['registered'] );
    $attributes['lost_password_sent'] = isset( $_REQUEST['checkemail'] ) && $_REQUEST['checkemail'] == 'confirm';
    $attributes['logged_out'] = isset($_REQUEST['logged_out']) && isset( $_REQUEST['logged_out'] ) && $_REQUEST['logged_out'] == true;
    $attributes['password_updated'] = isset( $_REQUEST['password'] ) && $_REQUEST['password'] == 'changed';

    return $this->get_template_html('login_form', $attributes);
  }

  /**
   * A shortcode for rendering the new user registration form
   *
   * @param  array   $attributes  Shortcode attributes.
   * @param  string  $content     The text content for shortcode. Not used.
   *
   * @return string  The shortcode output
   */

  public function render_register_form($attributes, $content = null) {
    $default_attributes = array( 'show_title' => false );
    $attributes = shortcode_atts( $default_attributes, $attributes );
    $attributes['errors'] = [];


    if(isset($_REQUEST['register-errors'])) {
      $error_codes = explode(',', $_REQUEST['register-errors']);
      foreach($error_codes as $code) {
        $attributes['errors'][] = $this->get_error_message($error_code);
      }
    }

    if ( is_user_logged_in() ) {
        return __( 'You are already signed in.', 'personalize-login' );
    } elseif ( ! get_option( 'users_can_register' ) ) {
        return __( 'Registering new users is currently not allowed.', 'personalize-login' );
    } else {
        return $this->get_template_html( 'register_form', $attributes );
    }
  }

  /**
   * A shortcode for rendering the form used to initiate the password reset.
   *
   * @param  array   $attributes  Shortcode attributes.
   * @param  string  $content     The text content for shortcode. Not used.
   *
   * @return string  The shortcode output
   */
  public function render_lostpassword_form($attributes, $content = null) {
    $default_attributes = ['show_title' => false];
    $attributes = shortcode_atts($default_attributes, $attributes);

    // Retrieve possible errors from request parameters
    $attributes['errors'] = [];
    if ( isset( $_REQUEST['errors'] ) ) {
        $error_codes = explode( ',', $_REQUEST['errors'] );

        foreach ( $error_codes as $error_code ) {
            $attributes['errors'][] = $this->get_error_message( $error_code );
        }
    }

    if(is_user_logged_in()) {
      return __('You are already signed in.', 'personalize-login');
    } else {
      return $this->get_template_html('lostpassword_form', $attributes);
    }
  }

  /**
   * A shortcode for rendering the form used to reset a user's password.
   *
   * @param  array   $attributes  Shortcode attributes.
   * @param  string  $content     The text content for shortcode. Not used.
   *
   * @return string  The shortcode output
   */
  public function render_resetpass_form( $attributes, $content = null ) {
      // Parse shortcode attributes
      $default_attributes = array( 'show_title' => false );
      $attributes = shortcode_atts( $default_attributes, $attributes );

      if ( is_user_logged_in() ) {
          return __( 'You are already signed in.', 'personalize-login' );
      } else {
          if ( isset( $_REQUEST['login'] ) && isset( $_REQUEST['key'] ) ) {
              $attributes['login'] = $_REQUEST['login'];
              $attributes['key'] = $_REQUEST['key'];

              // Error messages
              $errors = array();
              if ( isset( $_REQUEST['error'] ) ) {
                  $error_codes = explode( ',', $_REQUEST['error'] );

                  foreach ( $error_codes as $code ) {
                      $errors []= $this->get_error_message( $code );
                  }
              }
              $attributes['errors'] = $errors;

              return $this->get_template_html( 'resetpass_form', $attributes );
          } else {
              return __( 'Invalid password reset link.', 'personalize-login' );
          }
      }
  }

  /**
   * A shortcode for rendering the user's account details.
   *
   * @param  array   $attributes  Shortcode attributes.
   * @param  string  $content     The text content for shortcode. Not used.
   *
   * @return string  The shortcode output
   */
  public function render_account_form( $attributes, $content = null ) {
      // Parse shortcode attributes
      $default_attributes = array( 'show_title' => false );
      $attributes = shortcode_atts( $default_attributes, $attributes );

      if ( !is_user_logged_in() ) {
          return __( 'You must be logged in to view this page.', 'personalize-login' );
      } else {
        $this->do_update_user();
        $user = get_current_user_id();
        $attributes['user'] = $user;
        return $this->get_template_html( 'account_form', $attributes );
      }
  }

  function get_template_html($template_name, $attributes = null) {
    if(!$attributes) {
      $attributes = array();
    }
    ob_start();

    /**
     * Hook preceeding login template output.
     *
     * @since 1.0.0
     *
     */
    do_action('branded_user_flow_before_' . $template_name);

    /**
     * Filter to load custom template
     *
     * @since 1.0.0
     *
     * @param string  $template Path to template.
     *
     */
    require(apply_filters('branded_user_flow_'.$template_name, 'templates/'.$template_name.'.php'));

    /**
     * Hook following login template output.
     *
     * @since 1.0.0
     *
     */
    do_action('branded_user_flow_after_' . $template_name);

    $html = ob_get_contents();
    ob_end_clean();
    return $html;
  }

  /**
   * Get custom login url
   *
   * @return string Custom login URL
   */
  function get_login_url() {
    //TODO: Write settings page that takes pages and use their IDs
    /**
     * Filter to get custom login url
     *
     * @since 1.0.0
     *
     */
    return apply_filters('branded_user_flow_login_url', home_url('member-login'));
  }

  /**
   * Get custom register url
   *
   * @return string Custom register URL
   */
  function get_register_url() {
    //TODO: Write settings page that takes pages and use their IDs
    /**
     * Filter to get custom register url
     *
     * @since 1.0.0
     *
     */
    return apply_filters('branded_user_flow_register_url', home_url('member-register'));
  }

  /**
   * Get url for logged in users
   *
   * @return string The logged in redirect URL
   */
  function get_logged_in_url($user) {
    //TODO: Write settings page that takes pages and use their IDs
    /**
     * Filter to get custom logged in url
     *
     * @since 1.0.0
     *
     */
    return apply_filters('branded_user_flow_logged_in_url', home_url('member-account'), $user);
  }

  /**
   * Get url for lostpassword
   *
   * @return string The lostpassword form URL
   */
  function get_lostpassword_url() {
    //TODO: Write settings page that takes pages and use their IDs
    /**
     * Filter to get custom lostpassword url
     *
     * @since 1.0.0
     *
     */
    return apply_filters('branded_user_flow_lostpassword_url', home_url('member-password-lost'));
  }

  /**
   * Get url for resetpass
   *
   * @return string The resetpass form URL
   */
  function get_resetpass_url() {
    //TODO: Write settings page that takes pages and use their IDs
    /**
     * Filter to get custom resetpass url
     *
     * @since 1.0.0
     *
     */
    return apply_filters('branded_user_flow_resetpass_url', home_url( 'member-password-reset' ));
  }
}
register_activation_hook(__FILE__, array('Branded_User_Flow', 'plugin_activated'));
$branded_user_flow = new Branded_User_Flow();
