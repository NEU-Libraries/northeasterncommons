<?php


// shibboleth attempts to put users back where they came from after authenticating with the redirect_to param.
// that param is not always preserved through the login flow, so handle it here with a cookie to be sure.
function hcommons_maybe_redirect_after_login() {
	// Some pages need to be excluded from being redirect targets.
	$is_blacklisted = function() {
		$blacklist = [
			'/',
			'/clear-session/',
			'/logged-out/',
			'/not-a-member/',
			'/wp-admin/admin-ajax.php',
		];

		return (
			in_array( $_SERVER['REQUEST_URI'], $blacklist ) ||
			false !== strpos( $_SERVER['REQUEST_URI'], '/wp-login.php' ) ||
			false !== strpos( $_SERVER['REQUEST_URI'], '/wp-json/' )
		);
	};

	$param_name = 'redirect_to';
	$cookie_name = $param_name;

	// Once user has authenticated, maybe redirect to original destination.
	if ( is_user_logged_in() ) {

		if ( isset( $_COOKIE[ $cookie_name ] ) ) {
			// unset cookie on each network domains
			foreach ( get_networks() as $network ) {
				setcookie( $cookie_name, '', time() - YEAR_IN_SECONDS, COOKIEPATH, $network->cookie_domain );
			}

			// only redirect if we're not already there and we aren't in the admin
			if ( false === strpos( $_SERVER['REQUEST_URI'], 'wp-admin' ) && false === strpos( $_COOKIE[ $cookie_name ], $_SERVER['REQUEST_URI'] ) ) {
				// Can't use wp_safe_redirect due to filters, just send directly.
				header( 'Location: ' . $_COOKIE[ $cookie_name ] );
				exit;
			}
		}

	// Otherwise, as long as this isn't a blacklisted page, set cookie.
	} else if ( ! $is_blacklisted() ) {
		// Direct access to protected group docs is handled with another redirect, leave as-is.
		if (
			! isset( $_COOKIE['bp-message'] ) ||
			! preg_match( '/You must be a logged-in member/', $_COOKIE['bp-message'] )
		) {
			$cookie_value = isset( $_REQUEST[ $param_name ] ) ? $_REQUEST[ $param_name ] : get_home_url() . $_SERVER['REQUEST_URI'];

			setcookie( $cookie_name, $cookie_value, time() + MINUTE_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		}

		// No need to set duplicate cookies, once we set one we're done with this request.
		remove_action( 'bp_do_404', __METHOD__ );
		remove_action( 'init', __METHOD__, 15 );
		remove_action( 'wp', __METHOD__ );
	}
}
if ( ! ( defined( 'DOMAIN_MAPPING' ) && DOMAIN_MAPPING ) ) {
	// bp_do_404 runs before wp, so needs an additional hook to set cookie for hidden content etc.
	add_action( 'bp_do_404', 'hcommons_maybe_redirect_after_login' );
	// priority 15 to allow shibboleth_auto_login() to run first
	add_action( 'init', 'hcommons_maybe_redirect_after_login', 15 );
	// to catch cookies set by cac_catch_group_doc_request
	add_action( 'wp', 'hcommons_maybe_redirect_after_login' );
	// to catch users trying to access their notifications while not logged in
	add_filter( 'bp_core_no_access', function( $r ) {
		hcommons_maybe_redirect_after_login();
		return $r;
	} );
}

function hcommons_filter_wp_redirect( $url ) {
	if ( strpos( $url, 'action=bpnoaccess' ) !== false ) {
		$url = add_query_arg( array( 'action' => 'shibboleth' ), $url );
	}
	return $url;
}
add_filter( 'wp_redirect', 'hcommons_filter_wp_redirect' );

/**
 * Intercept URLs generated by buddypress-group-email-subscription to redirect
 * action=bpnoaccess to action=shibboleth.
 *
 * ...and other URLs from bookmarks, etc. - everyone should use shibboleth.
 */
function hcommons_maybe_redirect_login() {
	if (
		isset( $_REQUEST['action'] ) &&
		'shibboleth' !== $_REQUEST['action']
	) {
		$url = add_query_arg( [ 'action' => 'shibboleth' ] );
		if ( isset( $_REQUEST['redirect_to'] ) ) {
			setcookie( 'redirect_to', $_REQUEST['redirect_to'], time() + MINUTE_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
		}
		wp_safe_redirect( $url );
	}
}
add_action( 'login_init', 'hcommons_maybe_redirect_login' );

/**
 * Filter the login redirect to prevent landing on wp-admin when logging in with shibboleth.
 *
 * @param string $location
 * @return string $location Modified url
 */
function hcommons_remove_admin_redirect( $location ) {
	if (
		isset( $_REQUEST['action'] ) &&
		'shibboleth' === $_REQUEST['action'] &&
		strpos( $location, 'wp-admin' ) !== false
	) {
		$location = get_site_url();
	}
	return $location;
}
add_filter( 'wp_safe_redirect_fallback', 'hcommons_remove_admin_redirect' );
add_filter( 'login_redirect', 'hcommons_remove_admin_redirect' );

/**
 * override core function to remove actual check on referer since we have lots of domains
 * this is an attempt to prevent "are you sure you want to do this?" errors
 */
if ( !function_exists('check_admin_referer') ) :
function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	if ( -1 == $action )
		_doing_it_wrong( __FUNCTION__, __( 'You should specify a nonce action to be verified by using the first parameter.' ), '3.2.0' );

	$adminurl = strtolower(admin_url());
	$referer = strtolower(wp_get_referer());
	$result = isset($_REQUEST[$query_arg]) ? wp_verify_nonce($_REQUEST[$query_arg], $action) : false;

	/**
	 * Fires once the admin request has been validated or not.
	 *
	 * @since 1.5.1
	 *
	 * @param string    $action The nonce action.
	 * @param false|int $result False if the nonce is invalid, 1 if the nonce is valid and generated between
	 *                          0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago.
	 */
	do_action( 'check_admin_referer', $action, $result );

	// this is the part changed from core. don't care about referer.
	//if ( ! $result && ! ( -1 == $action && strpos( $referer, $adminurl ) === 0 ) ) {
	if ( ! $result && ! ( -1 == $action ) ) {
		wp_nonce_ays( $action );
		die();
	}

	return $result;
}
endif;

// override to allow global super admins to always verify
function wp_verify_nonce( $nonce, $action = -1 ) {
	$nonce = (string) $nonce;
	$user = wp_get_current_user();

	if ( defined( 'GLOBAL_SUPER_ADMINS' ) ) {
		$global_super_admin_list = constant( 'GLOBAL_SUPER_ADMINS' );
		$global_super_admins = explode( ',', $global_super_admin_list );

		if (
			$user &&
			in_array( $user->user_login, $global_super_admins )
		) {
			return 1;
		}
	}

	$uid = (int) $user->ID;
	if ( ! $uid ) {
		/**
		 * Filters whether the user who generated the nonce is logged out.
		 *
		 * @since 3.5.0
		 *
		 * @param int    $uid    ID of the nonce-owning user.
		 * @param string $action The nonce action.
		 */
		$uid = apply_filters( 'nonce_user_logged_out', $uid, $action );
	}

	if ( empty( $nonce ) ) {
		return false;
	}

	$token = wp_get_session_token();
	$i = wp_nonce_tick();

	// Nonce generated 0-12 hours ago
	$expected = substr( wp_hash( $i . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10 );
	if ( hash_equals( $expected, $nonce ) ) {
		return 1;
	}

	// Nonce generated 12-24 hours ago
	$expected = substr( wp_hash( ( $i - 1 ) . '|' . $action . '|' . $uid . '|' . $token, 'nonce' ), -12, 10 );
	if ( hash_equals( $expected, $nonce ) ) {
		return 2;
	}

	/**
	 * Fires when nonce verification fails.
	 *
	 * @since 4.4.0
	 *
	 * @param string     $nonce  The invalid nonce.
	 * @param string|int $action The nonce action.
	 * @param WP_User    $user   The current user object.
	 * @param string     $token  The user's session token.
	 */
	do_action( 'wp_verify_nonce_failed', $nonce, $action, $user, $token );

	// Invalid nonce
	return false;
}

/**
 * If we're serving wp-login.php without shibboleth, redirect to the shibboleth login URL with JS.
 *
 * @return void
 */
function hc_add_login_redirect_script() {
	wp_parse_str( $_SERVER['QUERY_STRING'], $parsed_querystring );

	$redirect_url = shibboleth_get_option( 'shibboleth_login_url' );

	if ( isset( $parsed_querystring['redirect_to'] ) ) {
		$redirect_url = add_query_arg(
			'redirect_to',
			$parsed_querystring['redirect_to'],
			$redirect_url
		);
	}

	// Only add redirect script if password-protected is not active, otherwise this causes a loop.
	if ( ! class_exists( 'Password_Protected' ) ) {
		echo "<script>window.location = '$redirect_url'</script>";
	}
}
//add_action( 'login_enqueue_scripts', 'hc_add_login_redirect_script' );