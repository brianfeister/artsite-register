<?php

// Validation routines
// Those dealing with a submitted form each modify the global variable (array) $artsite_form_errors by adding entries for any errors. No added entries = no detected errors.

class ArtSite_DataValidator {

	function validate_username() {

		global $artsite_form_errors;

		if (empty($_POST[ARTSITE_CSSPREFIX.'_user_name'])) {
			$artsite_form_errors[] = "You must give a username.";
		} else {
			// Verify username - valid form + available
			$avail = self::username_checkavailability($_POST[ARTSITE_CSSPREFIX.'_user_name']);
			if ('NAVAIL' == $avail) {
				$artsite_form_errors[] = "The chosen username is already taken - please choose another.";
			} elseif ('ERRBAD' == $avail) {
				$artsite_form_errors[] = "The chosen username is not allowed - please use letters and numbers only.";
			} elseif ('AVAIL' != $avail) {
				$artsite_form_errors[] = "An unknown error occured when checking if your chosen username was available - please try again.";
			}
		}
	}

	function username_checkavailability($username = false) {
		if (preg_match("/^[a-zA-Z0-9]+$/", $username)) {
			return ($user = get_user_by('login', $username)) ? "NAVAIL" : "AVAIL";
		} else {
			return "ERRBAD";
		}
	}

	function validate_email() {

		global $artsite_form_errors;

		// Verify email - valid + available
		if (empty($_POST[ARTSITE_CSSPREFIX.'_email'])) {
			$artsite_form_errors[] = "You must give an email address.";
		} else {
			$avail = self::email_checkavailability($_POST[ARTSITE_CSSPREFIX.'_email']);
			if ('NAVAIL' == $avail) {
				$artsite_form_errors[] = "The chosen email address is already taken - please choose another.";
			} elseif ('ERRBAD' == $avail) {
				$artsite_form_errors[] = "The chosen email address is not allowed (invalid format) - please check and try again.";
			} elseif ('AVAIL' != $avail) {
				$artsite_form_errors[] = "An unknown error occured when checking if your chosen email address was available - please try again.";
			}
		}
	}

	function email_checkavailability($email) {
		if (preg_match("/^[a-z_\.0-9]+\@[-a-z0-9]+(\.[-a-z0-9]+)+$/",$email)) {
			if ($user = get_user_by('email', $email)) {
				return "NAVAIL";
			} else {
				return "AVAIL";
			}
		} else {
			return "ERRBAD";
		}
	}

	function validate_domainname() {

		global $artsite_form_errors;

		// Verify domain name - valid + available
		if (empty($_POST[ARTSITE_CSSPREFIX.'_domain']) || empty($_POST[ARTSITE_CSSPREFIX.'_domain_suffix'])) {
			$artsite_form_errors[] = "You must enter a domain name.";
		} else {
			$avail = self::domain_checkavailability($_POST[ARTSITE_CSSPREFIX.'_domain'].$_POST[ARTSITE_CSSPREFIX.'_domain_suffix']);
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
	}

	// Returns ASCII output; "AVAIL", "NAVAIL", or various error codes
	function domain_checkavailability($dom) {

		// Quick short-circuit for testing
		if (( $_SERVER['SERVER_NAME'] == "localhost" || $_SERVER["REMOTE_ADDR"] == "::ffff:127.0.0.1" || $_SERVER['REMOTE_ADDR'] == '127.0.0.1' ) && $dom == "ebay.com" ) return "NAVAIL";
		if (( $_SERVER['SERVER_NAME'] == "localhost" || $_SERVER["REMOTE_ADDR"] == "::ffff:127.0.0.1" || $_SERVER['REMOTE_ADDR'] == '127.0.0.1' ) && $dom == "zug93.com" ) return "AVAIL";

		// .xxx domains are not allowed
		if (preg_match("/\.xxx$/i", $dom)) return "NAVAIL";

		if (preg_match("/^www\./i", $dom)) {
			return "ERRWWW";
		} elseif (preg_match("/^([a-z0-9-]+\.)+[a-z]+$/i", $dom) ) {

			$rcode = self::namecheap_checkavailability(strtolower($dom));
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
	function namecheap_checkavailability($domain, $namecheap_apiuser = false, $namecheap_apikey = false, $namecheap_clientip = false, $namecheap_sandbox = "yes") {

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

	// https://gist.github.com/1287893
	function is_valid_luhn($number) {
		settype($number, 'string');
		$sumTable = array( array(0,1,2,3,4,5,6,7,8,9), array(0,2,4,6,8,1,3,5,7,9));
		$sum = 0;
		$flip = 0;
		for ($i = strlen($number) - 1; $i >= 0; $i--) {
			$sum += $sumTable[$flip++ & 0x1][$number[$i]];
		}
		return $sum % 10 === 0;
	}

	// Returns false or an array
	function verify_form() {

		$csp = ARTSITE_CSSPREFIX;

		if (isset($_POST[$csp.'_user_name']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'artsite-nonce')) {

			// Verify form
			// If validation passed, then issue a WordPress action (that's our spec). Otherwise re-render form (with error message)
			global $artsite_form_errors;

			self::validate_username();

			self::validate_email();

			self::validate_domainname();

			// Valid credit card details - can get a token
			if (empty($_POST[$csp.'_ccnumber']) || empty($_POST[$csp.'_ccexpiry']) || empty($_POST[$csp.'_cccvc'])) {
				$artsite_form_errors[] = "Please enter a credit card number, expiry date and CVC code.";
			} else {
				$credit_card_invalid = false;
				if (!is_numeric($_POST[$csp.'_ccnumber']) || !self::is_valid_luhn($_POST[$csp.'_ccnumber'])) { $credit_card_invalid = true; $artsite_form_errors[] = "Please enter a valid credit card number."; }
				if (!preg_match("/^(0[0-9]|1[0-2])\/(1[2-9]|[2-9][0-9])$/",$_POST[$csp.'_ccexpiry'])) { $credit_card_invalid = true; $artsite_form_errors[] = "Please enter a future four-digit expiry date for this credit card (format: MM/YY)."; }
				if (!is_numeric($_POST[$csp.'_cccvc']) || !preg_match("/^([0-9]{3,4})$/",$_POST[$csp.'_cccvc'])) { $credit_card_invalid = true; $artsite_form_errors[] = "Please enter a valid CVC code for this credit card."; }
				if ($credit_card_invalid == false) {
					// Stripe token
					if (!ArtSite_Stripe::initialise()) {
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
					'domain' => $_POST[$csp.'_domain'].$_POST[$csp.'_domain_suffix'],
					'stripe_customer_token' => $stripe_customer_token
				);
				return $validated_data;
			} else {
				return false;
			}
		}
		return false;

	}


}

?>