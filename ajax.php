<?php

if (!defined ('ABSPATH')) die ('No direct access allowed');

// All AJAX calls are (at least initially) funnelled through a function in here

# Make sure the JavaScript gets pulled in

add_action( 'admin_enqueue_scripts', 'artsite_ajax_enqueue' );
add_action( 'wp_enqueue_scripts', 'artsite_ajax_enqueue' );

function artsite_ajax_enqueue() {
	wp_enqueue_script( 'artsite-ajax', ARTSIGNUP_URL.'/js/ajax.js', array( 'jquery' ) );
	wp_localize_script( 'artsite-ajax', 'artsite_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
}

# Set up our handler for when WordPress receives the AJAX call
add_action('wp_ajax_artsite_ajax', 'artsite_ajax_dispatcher');
add_action('wp_ajax_nopriv_artsite_ajax', 'artsite_ajax_dispatcher');

// Note that it is compulsory to die after giving your output on AJAX responses

function artsite_ajax_dispatcher() {

	$call = $_GET['artsite_ajaxaction'];

	// We are not intending to allow the world to use this site to poll username/domain name availability
	check_ajax_referer('artsite-nonce');

	if (function_exists('artsite_ajax_'.$call)) {
		call_user_func('artsite_ajax_'.$call);
	} else {
		echo "ERROR";
	}

	# This is required, and putting it here saves repetition
	die;

}

function artsite_ajax_email_checkavail($email = false) {
	if ($email == false) $email =  isset($_GET['artsite_email']) ? strtolower($_GET['artsite_email']) : "";
	echo artsite_email_checkavail($email);
}

function artsite_email_checkavail($email) {
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

function artsite_ajax_username_checkavail($username = false) {
	// WordPress usernames are case-sensitive, so do not change case
	if ($username == false) $username =  isset($_GET['artsite_username']) ? $_GET['artsite_username'] : "";
	echo artsite_username_checkavail($username);
}

function artsite_username_checkavail($username = false) {
	if (preg_match("/^[a-zA-Z0-9]+$/", $username)) {
		return ($user = get_user_by('login', $username)) ? "NAVAIL" : "AVAIL";
	} else {
		return "ERRBAD";
	}
}

function artsite_ajax_domain_checkavail($dom = false, $domsuf = false) {
	// WordPress usernames are case-sensitive, so do not change case
	if ($dom == false) $dom =  isset($_GET['artsite_domain']) ? $_GET['artsite_domain'] : "";
	if ($domsuf == false) $domsuf =  isset($_GET['artsite_domainsuf']) ? $_GET['artsite_domainsuf'] : "";

	if ($dom) {
		echo artsite_domain_checkavail($dom.$domsuf);
	} else {
		echo "ERRNUL";
	}
}

// function artsite_ajax_stripe_cardverify() {
// 
// 	// 	$dom = isset($_GET['artsite_domain']) ? strtolower($_GET['artsite_domain']) : "";
// 
// 	// Set up access to Stripe
// 	artsite_stripe_initialise();
// 
// 	echo "NOTIMPL, TODO";
// 
// }

?>