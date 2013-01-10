<?php

// Artsite Stripe class

class ArtSite_Stripe {

	// Set up anything necessary to use the Stripe API; returns true or false
	function initialise() {

		// Load Stripe API library
		if (!is_readable(ARTSIGNUP_DIR.'/stripe-php/Stripe.php')) return false;
		require_once(ARTSIGNUP_DIR.'/stripe-php/Stripe.php');

		// Set the API key from our options
		$options = get_site_option('artsite_signup_options');
		if (empty($options['stripe_apisecretkey'])) return false;

		Stripe::setApiKey($options['stripe_apisecretkey']);

		return true;

	}

}

?>