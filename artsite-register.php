<?php
/*
Plugin Name: Art Site Register
Description: Provides a customised, AJAX-ified sign-up form, code to set up a blog when a valid sign-up occurs\, payment and expiry nagging functions
Author: David Anderson
Version: 0.3.0
Author URI: http://www.simbahosting.co.uk
*/

if (!defined ('ABSPATH')) die ('No direct access allowed');

# Globals
define ('ARTSIGNUP_SLUG', "artsite-register");
define ('ARTSIGNUP_DIR', WP_PLUGIN_DIR . '/' . ARTSIGNUP_SLUG);
define ('ARTSIGNUP_URL', plugins_url()."/".ARTSIGNUP_SLUG);
define ('ARTSITE_CSSPREFIX', 'as_signup');

# Options admin interface
if (is_admin()) require_once(ARTSIGNUP_DIR.'/options.php');

# NameCheap functionality
require_once(ARTSIGNUP_DIR.'/class-namecheap.php');

# Class containing validation class
require_once(ARTSIGNUP_DIR.'/class-validation.php');

# Stripe and receipt-handling functions
require_once(ARTSIGNUP_DIR.'/class-payments.php');

# This file contains the code for handling the artsite_signup_validated action when valid details have been entered
require_once(ARTSIGNUP_DIR.'/class-signup-handler.php');

# Handing of such functions as checking expiry, nagging users, renewing domains - basically all ongoing account maintenance functions
require_once(ARTSIGNUP_DIR.'/class-renewals.php');

# Painting of forms
require_once(ARTSIGNUP_DIR.'/class-forms.php');

# AJAX - used by custom signup form to verify input
require_once(ARTSIGNUP_DIR.'/ajax.php');

# Enqueueing of scripts
add_action('wp_enqueue_scripts', 'artsite_enqueue_scripts');
function artsite_enqueue_scripts() {

	if (WP_DEBUG) {
		wp_register_script( 'jquery-validation', ARTSIGNUP_URL."/js/jquery.validation-1.0.1.js");
	} else {
		# http://www.tectual.com.au/posts/14/jQuery-Validator-Plugin-Validation-Plugin-.html
		wp_register_script( 'jquery-validation', ARTSIGNUP_URL."/js/jquery.validation-1.0.1-min.js");
	}

	wp_register_script ('stripe-js', 'https://js.stripe.com/v1/');

	wp_register_style('artsite-signup-style', ARTSIGNUP_URL."/css/artsite-signup.css" );
	wp_enqueue_style('artsite-signup-style');
}

add_action('init', 'artsite_init_handler');
function artsite_init_handler() {

	// Register short-code handler
	add_shortcode( 'custom-signup', 'artsite_shortcode_handler' );

	// Check if a form was submitted, and if so, intercept it; if it validates, call the action
	$validated_data = ArtSite_DataValidator::verify_signup_form();
	if (is_array($validated_data)) do_action('artsite_signup_validated', $validated_data);

	// Hook and redirect the WordPress sign-up page
	$options = get_site_option('artsite_signup_options');
	if (!empty($options['signup_url']) && $options['signup_url'] != "http://") {
		add_action('signup_header', 'artsite_redirect_signup_go');
	}

}

function artsite_redirect_signup_go() {
	$options = get_site_option('artsite_signup_options');
	$send_to = $options['signup_url'];
	if (!empty($_SERVER["QUERY_STRING"])) $send_to .= '?'.$_SERVER["QUERY_STRING"];
	wp_redirect($send_to);
	exit;
}

# This function handles the [custom-signup] shortcode
function artsite_shortcode_handler($atts) {

	$ret = "";

	if (isset($_POST['as_signup_user_name'])) {

		// There must have been errors, otherwise we should not be arriving here (unless no filter to pick up the success exists). So, display them.

		$ret .= ArtSite_DataValidator::display_errors();

	}

	$ret .= Artsite_Forms::signupform_render();

	return $ret;
}
