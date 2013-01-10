<?php

# http://codex.wordpress.org/Creating_Options_Pages

if (!defined ('ABSPATH')) die ('No direct access allowed');

# Hook to display an options page for our plugin in the admin menu
add_action('admin_menu', 'artsite_signup_options_menu');

register_activation_hook('artsite_signup', 'artsite_signup_options_setdefaults');

// Custom fields in settings
add_filter('wpmu_options', 'artsite_wpmu_options');
add_filter('update_wpmu_options', 'artsite_update_wpmu_options');

function artsite_signup_options_menu() {
	# http://codex.wordpress.org/Function_Reference/add_options_page
	if (is_super_admin()) add_options_page('Art Site Signup', 'Art Site Signup', 'manage_options', 'artsite_signup', 'artsite_signup_options_printpage');
}

function artsite_signup_options_setdefaults() {
	$tmp = get_site_option('artsite_signup_options');
	if (!is_array($tmp)) {
		$arr = array( "namecheap_apiuser" => "", 'namecheap_apikey' => "", 'namecheap_clientip' => "", 'namecheap_sandbox' => 'yes', 'stripe_apikey' => '', 'stripe_apisecretkey' => '', 'signup_url' => "", 'post_signup_url' => '' );
		update_site_option('artsite_signup_options', $arr);
	}
}

function artsite_update_wpmu_options() {

	$options=get_site_option('artsite_signup_options');

	if ( !current_user_can('manage_options') ) wp_die( __( 'You do not have permission to access this page.' ) );

	$errors = array();
 
	if (empty($_POST['namecheap_sandbox'])) $_POST['namecheap_sandbox'] = "no";

	if (!empty($_POST['namecheap_apikey']) || !empty($_POST['namecheap_apiuser'])) {
		# Test the settings e.g. by checking the availability of google.com
		$available = artsite_namecheap_checkavailability("google.com", $_POST['namecheap_apiuser'], $_POST['namecheap_apikey'], $_POST['namecheap_clientip'], $_POST['namecheap_sandbox']);
		# Note: Check for 0 (not available), and avoid false (error)
		if ($available != false) {
			$errors[]="Using the given options, we failed to communicate successfully with NameCheap on a test operation; the NameCheap options will not be saved.";
			if (is_wp_error($available)) {
				foreach ($available->get_error_messages() as $key => $msg) {
					$errors[] = "* ".$msg; 
				}
			}
		} else {
			$options['namecheap_apiuser'] = $_POST['namecheap_apiuser'];
			$options['namecheap_apikey'] = $_POST['namecheap_apikey'];
			$options['namecheap_clientip'] = $_POST['namecheap_clientip'];
			$options['namecheap_sandbox'] = $_POST['namecheap_sandbox'];
		}
	}

	$options['stripe_apikey'] = $_POST['stripe_apikey'];
	$options['stripe_apisecretkey'] = $_POST['stripe_apisecretkey'];

	$options['signup_url'] = $_POST['signup_url'];
	$options['post_signup_url'] = $_POST['post_signup_url'];

	update_site_option('artsite_signup_options', $options);

	return $errors;
}

function artsite_wpmu_options() {

$options = get_site_option('artsite_signup_options');

?>
<hr />
<h3>Art Site Signup - Settings</h3>
<table id="artsite_options" class="form-table">
	<tr valign="top"><th scope="row">NameCheap API user</th><td><input maxlength="32" type="text" size="48" name="namecheap_apiuser" value="<?php if (!empty($options['namecheap_apiuser'])) echo htmlspecialchars($options['namecheap_apiuser']); ?>"/>
	</td></tr><tr valign="top"><th scope="row">NameCheap API key</th><td><input maxlength="32" type="text" size="48" name="namecheap_apikey" value="<?php if (!empty($options['namecheap_apikey'])) echo htmlspecialchars($options['namecheap_apikey']); ?>"/>
	</td></tr><tr valign="top"><th scope="row">NameCheap Client IP address</th><td><input maxlength="15" type="text" size="48" name="namecheap_clientip" value="<?php if (!empty($options['namecheap_clientip'])) echo htmlspecialchars($options['namecheap_clientip']); ?>"/>
	</td></tr><tr valign="top"><th scope="row">Use NameCheap sandbox (not live)</th><td>

	<?php
	if (isset($options['namecheap_sandbox']) && $options['namecheap_sandbox'] == "no") {
		echo '<input type="checkbox" value="yes" name="namecheap_sandbox" id="namecheap_sandbox">';
	} else {
		echo '<input type="checkbox" checked="checked" value="yes" name="namecheap_sandbox" id="namecheap_sandbox">';
	}
?>

<label for="namecheap_sandbox"> Yes, use sandbox</label></td></tr>

<tr valign="top">
	<th scope="row">Stripe API key (public)</th>
	<td><input maxlength="32" type="text" size="48" name="stripe_apikey" value="<?php if (!empty($options['stripe_apikey'])) echo htmlspecialchars($options['stripe_apikey']); ?>"/></td>
</tr>

<tr valign="top">
	<th scope="row">Stripe API key (secret)</th>
	<td><input maxlength="32" type="text" size="48" name="stripe_apisecretkey" value="<?php if (!empty($options['stripe_apisecretkey'])) echo htmlspecialchars($options['stripe_apisecretkey']); ?>"/>
	</td>
</tr>

<tr valign="top">
	<th scope="row">Redirect signup page to:</th>
	<td><input maxlength="128" type="text" size="48" name="signup_url" value="<?php if (!empty($options['signup_url'])) { echo htmlspecialchars($options['signup_url']);} else { echo "http://"; } ?>"/></td>
</tr>

<tr valign="top">
	<th scope="row">After successful signup, redirect to:</th>
	<td><input maxlength="128" type="text" size="48" name="post_signup_url" value="<?php if (!empty($options['post_signup_url'])) { echo htmlspecialchars($options['post_signup_url']);} else { echo "http://"; } ?>"/></td>
</tr>


</table>
<?php

}

# This is the function outputing the HTML for our options page
function artsite_signup_options_printpage() {
	if (!is_super_admin())  {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	if (isset($_POST['_wpnonce']) && isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'artsite-form-settings')) {
		$result = artsite_update_wpmu_options();
		if (count($result) > 0) {
			echo "<div class='error'>\n";
			echo implode("<br />\n", $result);
			echo "</div>\n";
		}
	}

	echo "<form method='post' action=''>\n";
	wp_nonce_field('artsite-form-settings');

	artsite_wpmu_options();

	submit_button('Save Changes');

	echo "</form>\n";

}

function artsite_signup_action_links($links, $file) {

	if ( is_super_admin() && $file == ARTSIGNUP_SLUG."/".ARTSIGNUP_SLUG.".php" ){
		array_unshift( $links, 
			'<a href="options-general.php?page=artsite_signup">Settings</a>'
		);
	}

	return $links;

}
add_filter( 'plugin_action_links', 'artsite_signup_action_links', 10, 2 );

?>
