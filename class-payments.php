<?php

// Artsite Payments class

$artsite_payments = new ArtSite_Payments;
class ArtSite_Payments {

	var $initialised = false;

	// For quicker reading, we save our options here upon initialisation
	var $options;

	// Set up anything necessary to use the Stripe API; returns true or false
	function initialise() {

		if ($this->initialised) return true;

		// Load Stripe API library
		if (!is_readable(ARTSIGNUP_DIR.'/stripe-php/Stripe.php')) return false;
		require_once(ARTSIGNUP_DIR.'/stripe-php/Stripe.php');

		// Set the API key from our options
		$options = get_site_option('artsite_signup_options');
		if (empty($options['stripe_apisecretkey'])) return false;

		$this->options = $options;

		Stripe::setApiKey($options['stripe_apisecretkey']);

		$this->initialised = true;
		return true;

	}

	function create_customer($customer_details) {
		return Stripe_Customer::create($customer_details);
	}

	function update_customer($customer_token, $customer_details) {
		$cu = Stripe_Customer::retrieve($customer_token);
		$cu->card = $customer_details;
		return $cu->save();
	}

	// Returns either a WP_Error or a Stripe_Charge->id
	function charge($token, $amount) {

		if (!$this->initialised) {
			return new WP_Error('stripe_not_initialised', 'You should call the initialise method successfully before calling any other Stripe methods');
		}

		if (!is_string($token)) {
			return new WP_Error('invalid_token', 'You did not pass a valid token to the charge method');
		}

		$description = isset($this->options['charge_description']) ? $this->options['charge_description'] : "Artsite";

		// Attempt a charge
		try {
			$charge = Stripe_Charge::create(array(
				"amount" => 100*$amount,
				"currency" => "usd",
				"customer" => $token,
				"description" => $description
			));
		} catch (Stripe_InvalidRequestError $e) {
		// Invalid parameters were supplied to Stripe's API
			$body = $e->getJsonBody();
			$err  = $body['error'];
			$charge = new WP_Error('stripe_invalid_request', $err['message']);
		} catch (Stripe_AuthenticationError $e) {
		// Authentication with Stripe's API failed
		// (maybe you changed API keys recently)
			$body = $e->getJsonBody();
			$err  = $body['error'];
			$charge = new WP_Error('stripe_authentication_error', $err['message']);
		} catch (Stripe_ApiConnectionError $e) {
		// Network communication with Stripe failed
			$body = $e->getJsonBody();
			$err  = $body['error'];
			$charge = new WP_Error('stripe_api_connection_error', $err['message']);
		} catch (Stripe_Error $e) {
		// Display a very generic error to the user, and maybe send
		// yourself an email
			$body = $e->getJsonBody();
			$err  = $body['error'];
			$charge = new WP_Error('stripe_error', $err['message']);
		} catch (Exception $e) {
		// Something else happened, completely unrelated to Stripe
			$charge = new WP_Error((string)$e->getCode(), $e->getMessage());
		}

		if (is_wp_error($charge)) return $charge;

		return $charge->id;

	}

	// Send the user a payment receipt
	// Beautify this by deploying a plugin such as WP Better Emails
	function send_receipt($recipient_email, $payment_id, $amount) {
	
		$message = "We have received your payment for $".sprintf("%02.2f", $amount).". Your transaction reference number, should you have any enquiries, is: $payment_id.";

		$bcc = bloginfo('admin_email');

		wp_mail($recipient_email, "Your payment receipt", $message, array("Bcc: $bcc"));
	
	}

}

?>
