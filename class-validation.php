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

			$rcode = ArtSite_NameCheap::checkavailability(strtolower($dom));
			if (is_wp_error($rcode)) {
				$wp_err = "";
				foreach ($rcode->get_error_messages() as $key => $msg) {
					$wp_err .= ($wp_err == "") ? $msg : ",$msg"; 
				}
				return "ERR:".$wp_err;
			}
			if ($rcode == false) return "NAVAIL";
			if ($rcode == true) return "AVAIL";
			return "ERRUK";

		} else { return "ERRBAD";}
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

	// Returns false or an array. The array is one suitable for passing to ArtSite_SignupHandler::process_signup
	function verify_form() {

		$csp = ARTSITE_CSSPREFIX;

		if (isset($_POST[$csp.'_user_name']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'artsite-nonce')) {

			// Verify form
			// If validation passed, then issue a WordPress action (that's our spec). Otherwise re-render form (with error message)
			global $artsite_form_errors;

			self::validate_username();

			self::validate_email();

			self::validate_domainname();

			if (empty($_POST[$csp.'_country']) || strlen($_POST[$csp.'_country']) != 2) {
				$artsite_form_errors[] = "Unknown country selected";
			}

			if (empty($_POST[$csp.'_phonecountrycode']) || !preg_match("/^[0-9]{1,5}$/", $_POST[$csp.'_phonecountrycode'])) {
				$artsite_form_errors[] = "Invalid phone country code entered";
			}

			if (empty($_POST[$csp.'_phoneno']) || !preg_match("/^[ 0-9]{1,16}$/", $_POST[$csp.'_phonecountrycode']) || preg_match("/^ +$/", $_POST[$csp.'_phonecountrycode'])) {
				$artsite_form_errors[] = "Invalid phone number entered (use numbers only)";
				// Normalise for what the NameCheap API wants
				$_POST[$csp.'_phonecountrycode'] = str_replace(' ', '', $_POST[$csp.'_phonecountrycode']);
			}

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
					global $artsite_payments;
					if (!$artsite_payments->initialise()) {
						$artsite_form_errors[] = "We had an internal error trying to process your credit card number. Please try again, or contact support.";
					} else {
						$exp_month = substr($_POST[$csp.'_ccexpiry'], 0, 2);
						$exp_year = substr($_POST[$csp.'_ccexpiry'], 3, 2);

						try {

							$details = array(
								'description' => "Customer for ".$_POST[$csp.'_email'],
								'card' => array (
									'number' => $_POST[$csp.'_ccnumber'],
									'exp_month' => $exp_month,
									'exp_year' => $exp_year,
									'cvc' => $_POST[$csp.'_cccvc']
								)
							);

							$customer = $artsite_payments->create_customer($details);

							$stripe_customer_token = $customer->id;

						} catch (Exception $e) {
							$artsite_form_errors[] = "An error occurred when trying to process your credit card (".htmlspecialchars($e->getMessage()).")";
						}
					}
					
				}
			}
			if (count($artsite_form_errors) == 0) {
				// In theory, this is redundant, but also harmless as far as validation goes
				// However, it does provide the correct filters to allow other plugins to act
				$wp_validation = wpmu_validate_user_signup($_POST[$csp.'_user_name'], $_POST[$csp.'_email']);
				$wperrs = $wp_validation['errors'];
				if (count($wperrs->errors) > 0) {
					foreach ($wperrs->get_error_messages() as $err) {
						$artsite_form_errors[] = $err;
					}
				} else {
					// Parameters are: blog name, blog title
					$wp_validation2 = wpmu_validate_blog_signup($_POST[$csp.'_user_name'], $_POST[$csp.'_user_name']."'s blog");
					$wperrs = $wp_validation2['errors'];
					if (count($wperrs->errors) > 0) {
						foreach ($wperrs->get_error_messages() as $err) {
							$artsite_form_errors[] = $err;
						}
					}
				}
			}
			// List of domainreg keys: domainreg_(fname,lname,addr1,town,state,zip,country,phone,email,org)
			if (count($artsite_form_errors) == 0 && isset($stripe_customer_token)) {
				$validated_data = array (
					'username' => $_POST[$csp.'_user_name'],
					'email' => $_POST[$csp.'_email'],
					'domain' => $_POST[$csp.'_domain'].$_POST[$csp.'_domain_suffix'],
					'stripe_customer_token' => $stripe_customer_token,
					'card_exp_month' => (int)$exp_month,
					'card_exp_year' => (int)$exp_year,
					'domainreg_fname' => $_POST[$csp.'_real_fname'],
					'domainreg_lname' => $_POST[$csp.'_real_lname'],
					'domainreg_addr1' => $_POST[$csp.'_addr1'],
					'domainreg_town' => $_POST[$csp.'_town'],
					'domainreg_state' => $_POST[$csp.'_state'],
					'domainreg_zip' => $_POST[$csp.'_zip'],
					'domainreg_country' => $_POST[$csp.'_country'],
					'domainreg_phone' => '+'.$_POST[$csp.'_phonecountrycode'].'.'.$_POST[$csp.'_phoneno'],
					'domainreg_org' => '',
					'domainreg_email' => $_POST[$csp.'_email']
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