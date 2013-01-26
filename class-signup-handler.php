<?php

// This file contains the code for the action handler, when valid sign-up details have been received
// It registers via a WordPress action artsite_signup_validated; so this code can be moved around very freely

/* Brief:

- Registers an action handler, to pick up when valid sign-up data has been provided (from the existing plugin). What it does when invoked for this action:
* Creates the user
* Creates the blog
* Creates a Stripe payment token, and stores it as metadata for the user
* Registers the user's domain name with NameCheap
* Also stores the user's card expiry as metadata
* Charges the Stripe payment token
* Emails the user a payment receipt
* Creates a ProSite (manual payment, permanent)
* Stores a 'next charge due' date as meta-data for the user (on new user creation, is 6 months ahead)

*/

// This constant is the default value level id for new ProSites
define('ARTSITE_DEFAULT_PROSITE_LEVEL', 1);

$artsite_signuphandler = new ArtSite_SignupHandler();
class ArtSite_SignupHandler {

	function __construct() {

		add_action('artsite_signup_validated', array($this, 'process_signup'), 10, 1);

	}

	function process_signup($passed) {

// 		$passed = array (
// 			'username' => $_POST[$csp.'_user_name'],
// 			'email' => $_POST[$csp.'_email'],
// 			'domain' => $_POST[$csp.'_domain'].$_POST[$csp.'_domain_suffix'],
// 			'stripe_customer_token' => $stripe_customer_token,
//			'card_exp_month' => int($month),
//			'card_exp_year' => int($year),
//			and then, domainreg_(fname,lname,addr1,town,state,zip,country,phone,email,org)
// 		);

		// Any errors can still be displayed to the user by adding them here
		global $artsite_form_errors;

		// Charge the Stripe token - do this first
		global $artsite_payments;
		if (!$artsite_payments->initialise()) {
			$artsite_form_errors[] = "There was an error communicating with our card processor when trying to charge your card. Please contact us for help.";
			return false;
		}

		$options = get_site_option('artsite_signup_options');

		$amount = (int)$options['charge_initial_amount'];
		if (!$amount>0) {
			$artsite_form_errors[] = "No initial charge has been configured by the site administrator";
			return false;
		}

		// Returns either a WP_Error or a charge ID (string)
		$charged = $artsite_payments->charge($passed['stripe_customer_token'], $amount);

		if (is_wp_error($charged)) {
			foreach ($charged->get_error_messages() as $key => $msg) {
				$artsite_form_errors[] = $msg;
			}
			return false;
		}

		/* One way to do sign-up is:
			- Hook wpmu_signup_blog_notification
			- Call wpmu_signup_blog, which sets up the entry in the database for activation. Our hook can then abort the sending the email
			- Then perform the activation with wpmu_activate_signup($key)
		Alternatively, since wpmu_signup_blog_notification just puts some stuff in the database (a single INSERT), we could just do that and skip the hooking. And that's what we'll do.
		*/

		// wpmu_activate_signup($key) does it all
		/* As the description for wpmu_activate_signup says:
		* @uses wp_generate_password()
		* @uses wpmu_welcome_user_notification()
		* @uses add_user_to_blog()
		* @uses add_new_user_to_blog()
		* @uses wpmu_create_user()
		* @uses wpmu_create_blog()
		* @uses wpmu_welcome_notification()
		*/

		// Format data as desired
		$meta = serialize(array ('lang_id' => 1, 'public' => 1));
		$key = substr( md5( time() . rand() . $passed['domain'] ), 0, 16 );

		global $wpdb;
		$wpdb->insert( $wpdb->signups, array(
			'domain' => $wpdb->escape($passed['domain']),
			'path' => $wpdb->escape('/'),
			'title' => $wpdb->escape($passed['domain']),
			'user_login' => $passed['username'],
			'user_email' => $passed['email'],
			'registered' => current_time('mysql', true),
			'activation_key' => $key,
			'meta' => $meta
		) );

		wpmu_activate_signup($key);

		// Store the Stripe token as metadata
		# First, get the user ID

		$user = get_user_by('login', $passed['username']);
		if (!$user) {
			$artsite_form_errors[] = "A non-recoverable error occurred when trying to find the user's details";
			return false;
		}
		$user_id = $user->ID;

		add_user_meta($user_id, 'stripe_customer_token', $passed['stripe_customer_token']);

		// Store a card expiry date as metadata

		$store_year = 2000 + $passed['card_exp_year'];
		add_user_meta($user_id, 'card_expiry', $passed['card_exp_month'].'/'.$store_year);

		// Email a payment receipt
		$artsite_payments->send_receipt($passed['email'], $charged, $amount);

		// Create the user's ProSite entry (manual, permanent)
		$blog_id = get_blog_id_from_url($passed['domain']);
		if (false == $blog_id) {
			$artsite_form_errors[] = "A non-recoverable error occurred when trying to find the user's new blog details";
			return false;
		}
		switch_to_blog($blog_id);
		add_option('psts_signed_up', 0, null, true);
		update_option('psts_signed_up', 0);
		add_option('psts_withdrawn', 0, null, true);
		update_option('psts_withdrawn', 0);
		$action_log = get_option('psts_action_log');
		if (!is_array($action_log)) $action_log=array();
		$action_log[time()] = "Pro Site status expiration permanently extended.";
		add_option('psts_action_log', $action_log, null, true);
		update_option('psts_action_log', $action_log);

		global $wpdb;
		$prosite_expire = time() + 86400*365*25;
		$prefix = $wpdb->prefix;
		$wpdb->query("INSERT INTO ${prefix}pro_sites VALUES($blog_id, ".ARTSITE_DEFAULT_PROSITE_LEVEL.", $prosite_expire, 'Manual', 'Permanent', NULL);");

		// Store the "next charge due" date as meta-data for the user (6 months ahead)
		$paid_expire_time = new DateTime();
		$paid_expire_time = $paid_expire_time->add(new DateInterval('P6M'));
		$paid_expire_time = $paid_expire_time->getTimestamp();

		add_user_meta($user_id, 'paid_until', $paid_expire_time);

		// Register the domain name with NameCheap

		$registrant_keys = array(
			'fname',
			'lname',
			'addr1',
			'town',
			'state',
			'country',
			'zip',
			'email',
			'org',
			'phone'
		);

		$registrant_details = array();
		// Record the user's address details as metadata
		// And, prepare array for passing to registration
		foreach ($registrant_keys as $key) {
			$detail = $passed['domainreg_'.$key];
			$registrant_details[$key] = $detail;
			add_user_meta($user_id, 'signup_'.$key, $detail);
		}

		$registration = ArtSite_NameCheap::register_domain($passed['domain'], $registrant_details);
		if (is_wp_error($registration)) {
			foreach ($registration->get_error_messages() as $key => $msg) { $artsite_form_errors[] = $msg; }
		}

	}

}

?>