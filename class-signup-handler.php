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

$artsite_signuphandler = new ArtSite_SignupHandler();

class ArtSite_SignupHandler {

	function __construct() {

		add_action('artsite_signup_validated', array($this, 'process_signup'), 10, 1);

	}

	function process_signup($passed) {

		// Create the user
		// Issue a password, and inform them

// Look at validate_user_signup in wp-signup.php

		// Create the blog

		// Charge the Stripe token

		// Store the Stripe token as metadata

		// Store a card expiry date as metadata

		// Email a payment receipt

		// Create the user's ProSite entry (manual, permanent)

		// Store the "next charge due" date as meta-data for the user (6 months ahead)

	}

}

?>