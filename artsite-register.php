<?php
/*
Plugin Name: Art Site Register
Description: This plugin provides a short-code for the customised, AJAX-ified sign-up form
Author: David Anderson
Version: 0.1.0
Author URI: http://www.simbahosting.co.uk
*/

if (!defined ('ABSPATH')) die ('No direct access allowed');

# Globals
define ('ARTSIGNUP_SLUG', "artsite-register");
define ('ARTSIGNUP_DIR', WP_PLUGIN_DIR . '/' . ARTSIGNUP_SLUG);
define ('ARTSIGNUP_URL', plugins_url()."/".ARTSIGNUP_SLUG);
define ('ARTSITE_CSSPREFIX', 'as_signup');

# Options admin interface
if (is_admin()) require_once( ARTSIGNUP_DIR . "/options.php");

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

# AJAX - used by custom signup form to verify input
require_once(ARTSIGNUP_DIR . '/ajax.php');

add_action('init', 'artsite_verify_form');
function artsite_verify_form() {

	$csp = ARTSITE_CSSPREFIX;

	if (isset($_POST[$csp.'_user_name']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'artsite-nonce')) {
		// Verify form
		// If validation passed, then issue a WordPress action (that's our spec). Otherwise re-render form (with error message)
		global $artsite_form_errors;

		if (empty($_POST[$csp.'_user_name'])) {
			$artsite_form_errors[] = "You must give a username.";
		} else {
			// Verify username - valid form + available
			$avail = artsite_username_checkavail($_POST[$csp.'_user_name']);
			if ('NAVAIL' == $avail) {
				$artsite_form_errors[] = "The chosen username is already taken - please choose another.";
			} elseif ('ERRBAD' == $avail) {
				$artsite_form_errors[] = "The chosen username is not allowed - please use letters and numbers only.";
			} elseif ('AVAIL' != $avail) {
				$artsite_form_errors[] = "An unknown error occured when checking if your chosen username was available - please try again.";
			}
		}

		// Verify email - valid + available
		if (empty($_POST[$csp.'_email'])) {
			$artsite_form_errors[] = "You must give an email address.";
		} else {
			$avail = artsite_email_checkavail($_POST[$csp.'_email']);
			if ('NAVAIL' == $avail) {
				$artsite_form_errors[] = "The chosen email address is already taken - please choose another.";
			} elseif ('ERRBAD' == $avail) {
				$artsite_form_errors[] = "The chosen email address is not allowed (invalid format) - please check and try again.";
			} elseif ('AVAIL' != $avail) {
				$artsite_form_errors[] = "An unknown error occured when checking if your chosen email address was available - please try again.";
			}
		}

		// Verify domain name - valid + available
		if (empty($_POST[$csp.'_domain'])) {
			$artsite_form_errors[] = "You must enter a domain name.";
		} else {
			$avail = artsite_domain_checkavail($_POST[$csp.'_domain']);
			// Some of these error messages are quite similar, but at least the differences will give the site admin information on which code-path was followed.
			switch ($avail) {
				case "AVAIL":
					break;
				case "NAVAIL":
					$artsite_form_errors[] = "Your chosen domain name is already registered - please choose another.";
					break;
				case "ERRWWW":
					$artsite_form_errors[] = "Do not begin your domain name with www. - remove that and try again.";
					break;
				case "ERRBAD":
					$artsite_form_errors[] = "Invalid domain name - please check your domain name and try again.";
					break;
				case "ERRUK":
					$artsite_form_errors[] = "Unexpected error when trying to check the domain name's availability - please try again.";
					break;
				default:
					if (substr($avail,0,4) == "ERR:") { $artsite_form_errors[] = "An error occured when trying to check the domain name's availability - please try again."; }
					else { $artsite_form_errors[] = "An unknown error occurred when trying to check the domain name's availability - please try again."; }
			}
		}

		// Valid credit card details - can get a token
		if (empty($_POST[$csp.'_ccnumber']) || empty($_POST[$csp.'_ccexpiry']) || empty($_POST[$csp.'_cccvc'])) {
			$artsite_form_errors[] = "Please enter a credit card number, expiry date and CVC code.";
		} else {
			$credit_card_invalid = false;
			if (!is_numeric($_POST[$csp.'_ccnumber']) || !artsite_is_valid_luhn($_POST[$csp.'_ccnumber'])) { $credit_card_invalid = true; $artsite_form_errors[] = "Please enter a valid credit card number."; }
			if (!preg_match("/^(0[0-9]|1[0-2])\/(1[2-9]|[2-9][0-9])$/",$_POST[$csp.'_ccexpiry'])) { $credit_card_invalid = true; $artsite_form_errors[] = "Please enter a future four-digit expiry date for this credit card (format: MM/YY)."; }
			if (!is_numeric($_POST[$csp.'_cccvc']) || !preg_match("/^([0-9]{3,4})$/",$_POST[$csp.'_cccvc'])) { $credit_card_invalid = true; $artsite_form_errors[] = "Please enter a valid CVC code for this credit card."; }
			if ($credit_card_invalid == false) {
				// Stripe token
				if (!artsite_stripe_initialise()) {
					$artsite_form_errors[] = "We had an internal error trying to process your credit card number. Please try again, or contact support.";
				} else {
					$exp_month = substr($_POST[$csp.'_ccexpiry'], 0, 2);
					$exp_year = substr($_POST[$csp.'_ccexpiry'], 3, 2);

					try {

						$customer = Stripe_Customer::create( array( 'description' => "Customer for ".$_POST[$csp.'_email'], 'card' => array ( 'number' => $_POST[$csp.'_ccnumber'], 'exp_month' => $exp_month, 'exp_year' => $exp_year, 'cvc' => $_POST[$csp.'_cccvc'])));

						$stripe_customer_token = $customer->id;

					} catch (Exception $e) {
						$artsite_form_errors[] = "An error occurred when trying to process your credit card (".htmlspecialchars($e->getMessage()).")";
					}
				}
				
			}
		}
		if (count($artsite_form_errors) == 0 && isset($stripe_customer_token)) {
			$validated_data = array (
				'username' => $_POST[$csp.'_user_name'],
				'email' => $_POST[$csp.'_email'],
				'domain' => $_POST[$csp.'_domain'],
				'stripe_customer_token' => $stripe_customer_token
			);
			do_action('artsite_signup_validated', $validated_data);
		}
	}


}

# Shortcode
add_shortcode( 'custom-signup', 'artsite_shortcode_handler' );
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

	$ret .= eval(file_get_contents($form_file));

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
						alert("An unknown/unexpected error occured - please try again, or contact and alert us (data:"+data+")");
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

// Returns ASCII output; "AVAIL", "NAVAIL", or various error codes
function artsite_domain_checkavail($dom) {

	// Quick short-circuit for testing
	if ($_SERVER['SERVER_NAME'] == "localhost" && $dom == "ebay.com" ) return "NAVAIL";
	if ($_SERVER['SERVER_NAME'] == "localhost" && $dom == "zug93.com" ) return "AVAIL";

	// .xxx domains are not allowed
	if (preg_match("/\.xxx$/i", $dom)) return "NAVAIL";

	if (preg_match("/^www\./i", $dom)) {
		return "ERRWWW";
	} elseif (preg_match("/^([a-z0-9-]+\.)+[a-z]+$/i", $dom) ) {

		$rcode = artsite_namecheap_checkavailability(strtolower($dom));
		if (is_wp_error($rcode)) {
			$wp_err = "";
			foreach ($rcode->get_error_messages() as $key => $msg) {
				$wp_err .= ($wp_err == "") ? $msg : ",$msg"; 
			}
			return "ERR:".$wp_err;
		}
		if ($rcode == "false") return "NAVAIL";
		if ($rcode == "true") return "AVAIL";
		return "ERRUK";

	} else { return "ERRBAD";}
}

// Returns true (available), false (not available), or a WP_Error object
function artsite_namecheap_checkavailability($domain, $namecheap_apiuser = false, $namecheap_apikey = false, $namecheap_clientip = false, $namecheap_sandbox = "yes") {

	$options = get_site_option('artsite_signup_options');
	if ($namecheap_apiuser === false && isset($options['namecheap_apiuser'])) $namecheap_apiuser = $options['namecheap_apiuser'];
	if ($namecheap_apikey === false && isset($options['namecheap_apikey'])) $namecheap_apikey = $options['namecheap_apikey'];
	if ($namecheap_clientip === false && isset($options['namecheap_clientip'])) $namecheap_clientip = $options['namecheap_clientip'];
	if (empty($namecheap_sandbox) && isset($options['namecheap_sandbox'])) $namecheap_sandbox = $options['namecheap_sandbox'];

	if (empty($namecheap_apiuser) || empty($namecheap_apikey) || empty($namecheap_clientip)) return new WP_Error('missing_params', 'Missing NameCheap API configuration parameters' );

	$namecheap_auth = "ApiUser=$namecheap_apiuser&ApiKey=$namecheap_apikey&UserName=$namecheap_apiuser&ClientIP=$namecheap_clientip";

	$namecheap_url = ($namecheap_sandbox == "yes") ? 'https://api.sandbox.namecheap.com' : 'https://api.namecheap.com';

	$namecheap_url .= '/xml.response?'.$namecheap_auth.'&Command=namecheap.domains.check&DomainList='.urlencode($domain);

	$ch = curl_init( $namecheap_url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
	$result = curl_exec( $ch );
	curl_close( $ch );
	if ( false == $result ) {
		return new WP_Error('network_error', 'Communication error' );
	}
	$xml = new SimpleXMLElement( $result );
	if ( 'ERROR' == $xml['Status'] ) {
		return new WP_Error('namecheap_error', (string) $xml->Errors->Error );
	} elseif ( 'OK' == $xml['Status'] ) {
		$result = strtolower( (string)$xml->CommandResponse->DomainCheckResult->attributes()->Available );
		if ($result == "true") return true;
		if ($result == "false") return false;
		return new WP_Error('namecheap_unknown_status', "Unrecognised result returned from NameCheap API" );
	}

}

// Set up anything necessary to use the Stripe API; returns true or false
function artsite_stripe_initialise() {

	// Load Stripe API library
	if (!is_readable(ARTSIGNUP_DIR.'/stripe-php/Stripe.php')) return false;
	require_once(ARTSIGNUP_DIR.'/stripe-php/Stripe.php');

	// Set the API key from our options
	$options = get_site_option('artsite_signup_options');
	if (empty($options['stripe_apisecretkey'])) return false;

	Stripe::setApiKey($options['stripe_apisecretkey']);

	return true;

}

// https://gist.github.com/1287893
function artsite_is_valid_luhn($number) {
	settype($number, 'string');
	$sumTable = array( array(0,1,2,3,4,5,6,7,8,9), array(0,2,4,6,8,1,3,5,7,9));
	$sum = 0;
	$flip = 0;
	for ($i = strlen($number) - 1; $i >= 0; $i--) {
		$sum += $sumTable[$flip++ & 0x1][$number[$i]];
	}
	return $sum % 10 === 0;
}
