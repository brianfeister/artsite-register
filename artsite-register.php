<?php
/*
Plugin Name: Art Site Register
Description: This plugin provides a customised, AJAX-ified sign-up form, and code to set up a blog when a valid sign-up occurs.
Author: David Anderson
Version: 0.2.0
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
	$validated_data = ArtSite_DataValidator::verify_form();
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

	$csp = ARTSITE_CSSPREFIX;
	$ret = "";

	if (isset($_POST['as_signup_user_name'])) {

		// There must have been errors, otherwise we should not be arriving here (unless no filter to pick up the success exists). So, display them.
		global $artsite_form_errors;
		$ret .= "<div id=\"${csp}_errorbox\">";

		if (count($artsite_form_errors) == 0) $artsite_form_errors[] = "No input errors - but apparently nothing picked up the artsite_signup_validated action and redirected us away to another page; hence we're still here.";

		$ret .= "<p><strong>Validation errors occured - please check your input and try again.</strong></p>\n";

		$ret .= "<ul>\n";
		foreach ($artsite_form_errors as $err) {
			$ret .= '<li>'.htmlspecialchars($err)."</li>\n";
		}
		$ret .= "</ul>\n";

		$ret .="</div>";
	}

	$ret .= artsite_signupform_render();

	return $ret;
}

	function artsite_signupform_render() {

	// CSS prefix
	$csp = ARTSITE_CSSPREFIX;

	/* What needs to go in the sign-up form:
	1. Standard details taken by usual wp-signup.php: Username, e-mail address, site name (in our case, domain name), site title, (privacy? not needed).
	2. I mentioned the domain name in 1., but that needs AJAX-ifying. As does the username/email address verification.
	3. Need to take their payment details, and run it through Stripe.
	4. On submission, that verification also needs to be run all over again.
	5. If final verification succeeds, then call a WP action that another plugin can pick up.
	Also, make sure everything is given CSS classes + IDs
	*/

	// Pull in jQuery validation plugin
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-validation');

	// Get the nonces
	$nonce = wp_create_nonce('artsite-nonce');
	$nonce_field = wp_nonce_field('artsite-nonce', "_wpnonce", true, false);

	$ret = "<div id=\"${csp}_form\">\n";

	$ret .= <<<ENDHERE

	<form id="${csp}_form_signup" method="post" onsubmit="return ${csp}_validate()";> 
	$nonce_field

ENDHERE;

	$form_file = (file_exists(get_stylesheet_directory().'/signup-form.php')) ? get_stylesheet_directory().'/signup-form.php' : ARTSIGNUP_DIR.'/includes/signup-form.php';

	require_once($form_file);
	$ret .= artsite_signup_form_render();

	$options = get_site_option('artsite_signup_options');

	$stripe_public_key = isset($options['stripe_apikey']) ? $options['stripe_apikey'] : "";

	if ($stripe_public_key != "") {
		$use_stripe = 1;
		$load_stripe = 'Stripe.setPublishableKey("'.$stripe_public_key.'");';
		// Stripe.Js library (https://stripe.com/docs/stripe.js)
		wp_enqueue_script('stripe-js');
	} else {
		$use_stripe = 0;
		$load_stripe = (WP_DEBUG == true) ? "" : 'alert("Please configure the Stripe API details in the back-end.");';
	}


	$ret .= <<<ENDHERE

	<div id="${csp}_row_submit" class="${csp}_form_row">
	<button id="${csp}_submit" data-validate="#${csp}_form_signup">Sign Up</button>
	</div>

	</form>

	<script type="text/javascript">
	/* <![CDATA[ */
	jQuery(document).ready(function() {

		$load_stripe

		jQuery.fn.validations.options.errorClasses.newusername = 'newusername-error';
		jQuery.fn.validations.options.validators.newusername = function(validationValue, form) {
			// This gets caught by the blank test, but needs to return here as valid to avoid spoiling CSS
			userval = this.val();
			if (userval == "") { return true;}
			retval = false;
			jurl = artsite_ajax.ajaxurl + "?_ajax_nonce=$nonce&action=artsite_ajax&artsite_ajaxaction=username_checkavail&artsite_username=" + encodeURIComponent(userval);
			jQuery.ajax({
				method: 'get',
				url: jurl,
				async: false,
				success: function(data,status){
					if (data == "AVAIL") { retval = true; }
					else if (data == "ERRBAD") { alert("Usernames should only contain letters and numbers"); }
					else if (data != "NAVAIL") { alert("There was an error in checking username availability"); }
				}
			});
			return retval;
		}

		jQuery.fn.validations.options.errorClasses.newemail = 'newemail-error';
		jQuery.fn.validations.options.validators.newemail = function(validationValue, form) {
			// This gets caught by the email test, but needs to return here as valid to avoid spoiling CSS
			emailval = this.val();
			// Regex same as that used in the validation plugin
			if (! /^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(emailval)) { return true;}
			retval = false;
			jurl = artsite_ajax.ajaxurl + "?_ajax_nonce=$nonce&action=artsite_ajax&artsite_ajaxaction=email_checkavail&artsite_email=" + encodeURIComponent(emailval);
			jQuery.ajax({
				method: 'get',
				url: jurl,
				async: false,
				success: function(data,status){
					if (data == "AVAIL") { retval = true; }
					else if (data == "ERRBAD") { return false; }
					else if (data != "NAVAIL") { alert("There was an error in checking email availability"); }
				}
			});
			return retval;
		}

		jQuery.fn.validations.options.errorClasses.ccnumber = 'ccnumber-error';
		jQuery.fn.validations.options.validators.ccnumber = function(validationValue, form) {
			ccval = this.val();
			if (ccval == "") { return true; }
			retval = Stripe.validateCardNumber(ccval);
			return retval;
		}

		jQuery.fn.validations.options.errorClasses.domain = 'domain-error';
		jQuery.fn.validations.options.validators.domain = function(validationValue, form) {
			// This gets caught by the domain test, but needs to return here as valid to avoid spoiling CSS
			domainval = this.val();
			if (domainval == "") { return true; }
			domainval = domainval + jQuery('#${csp}_domain_suffix').val();
			// Regex same as that used in the validation plugin
			if (! /^(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/.test(domainval)) { alert("You entered an invalid domain name."); return false; }
			if (/\.(xxx)$/.test(domainval)) { alert(".xxx domains are not permitted"); return false; }
			retval = false;
			jurl = artsite_ajax.ajaxurl + "?_ajax_nonce=$nonce&action=artsite_ajax&artsite_ajaxaction=domain_checkavail&artsite_domain=" + encodeURIComponent(domainval);
			jQuery.ajax({
				method: 'get',
				url: jurl,
				async: false,
				success: function(data,status){
					if (data == "AVAIL" ) {
						if (! /\.(org|net|info|com|uk)$/.test(domainval)) {
							alert("Your domain is available, but does not end in one of .com, .uk, .org, .net or .info - please contact support to arrange purchase.");
						} else {
							retval = true;
						}
					} else if (data == "NAVAIL") {
						retval = false;
					} else if (data == "ERRBAD") {
						alert("You entered a domain name that our system could not understand - please check and try again, or ask for help. Examples of valid domain names are: example.com, mydomain.co.uk, another-example.org");
					} else if (data == "ERRWWW") {
						alert("Do not enter www. at the front of your domain name.");
					} else if (data == "ERRWHO1" || data == "ERRWHO2" ) {
						alert("The domain lookup failed - please try again, or contact and alert us. Possibly you entered an invalid domain name; examples of valid domain names are: example.com, mydomain.co.uk, another-example.org");
					} else {
						alert("An unknown/unexpected error occured - please try again, or contact and alert us (http status: "+status+", data:"+data+")");
					}
				}
			});
			return retval;
		}

		jQuery('#${csp}_form_signup').validations();

	});

	function ${csp}_validate() {
		return jQuery('#${csp}_form_signup').validate();
	}
	/* ]]> */
	</script>


ENDHERE;

	$ret .= "</div>\n";

	return $ret;

}
