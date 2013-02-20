<?php

class Artsite_Forms {

	// Note - opens a div, which is closed in artsite_signupform_end
	public static function form_start() {

		// CSS prefix
		$csp = ARTSITE_CSSPREFIX;

		// Pull in jQuery validation plugin
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-validation');

		// Get the nonces
		$nonce = wp_create_nonce('artsite-nonce');
		$nonce_field = wp_nonce_field('artsite-nonce', "_wpnonce", true, false);

		$ret = "<div id=\"${csp}_form\">\n";

		$options = get_site_option('artsite_signup_options');
		$stripe_public_key = isset($options['stripe_apikey']) ? $options['stripe_apikey'] : "";
		if ($stripe_public_key != "") {
			$load_stripe = 'Stripe.setPublishableKey("'.$stripe_public_key.'");';
			// Stripe.Js library (https://stripe.com/docs/stripe.js)
			wp_enqueue_script('stripe-js');
		} else {
			$load_stripe = (WP_DEBUG == true) ? "" : 'alert("Please configure the Stripe API details in the back-end.");';
		}

		$ret .= <<<ENDHERE

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

		<form id="${csp}_form_signup" method="post" onsubmit="return ${csp}_validate()";> 
		$nonce_field
ENDHERE;

		return $ret;

	}

	public static function form_end($button_text) {

		// CSS prefix
		$csp = ARTSITE_CSSPREFIX;

		$ret = <<<ENDHERE

		<div id="${csp}_row_submit" class="${csp}_form_row">
			<button id="${csp}_submit" data-validate="#${csp}_form_signup">$button_text</button>
		</div>

		</form>

		</div>

ENDHERE;

		return $ret;

	}

	public static function cardchangeform_render() {

		$ret = "";

		$ret .= self::form_start();

		$form_file = (file_exists(get_stylesheet_directory().'/card-forms.php')) ? get_stylesheet_directory().'/card-forms.php' : ARTSIGNUP_DIR.'/includes/card-forms.php';
		require_once($form_file);

		// We use this to detect POST-ed content
		$ret .= '<input type="hidden" name="cardchangeform" value="thatsus">';

		$ret .= artsite_signup_form_creditcard_render();

		$ret .= self::form_end('Change details');

		return $ret;

	}

	public static function signupform_render() {

	/* What needs to go in the sign-up form:
	1. Standard details taken by usual wp-signup.php: Username, e-mail address, site name (in our case, domain name), site title, (privacy? not needed).
	2. I mentioned the domain name in 1., but that needs AJAX-ifying. As does the username/email address verification.
	3. Need to take their payment details, and run it through Stripe.
	4. On submission, that verification also needs to be run all over again.
	5. If final verification succeeds, then call a WP action that another plugin can pick up.
	Also, make sure everything is given CSS classes + IDs
	*/

		$ret = self::form_start();

		$form_file = (file_exists(get_stylesheet_directory().'/card-forms.php')) ? get_stylesheet_directory().'/card-forms.php' : ARTSIGNUP_DIR.'/includes/card-forms.php';
		require_once($form_file);

		$ret .= artsite_signup_form_render();

		$ret .= self::form_end('Sign up');

		return $ret;

	}

}

?>