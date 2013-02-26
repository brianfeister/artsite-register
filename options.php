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
		$arr = array(
			"namecheap_apiuser" => "", 
			'namecheap_apikey' => "", 
			'namecheap_clientip' => "", 
			'namecheap_sandbox' => 'yes', 
			'stripe_apikey' => '', 
			'stripe_apisecretkey' => '',
			'charge_initial_amount' => '50',
			'charge_monthly_amount' => '20',
			'charge_description' => 'Artsite',
			'signup_url' => "", 
			'post_signup_url' => '',
			'nameservers_default' => '',
			'card_change_url' => ''
		);
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
		$available = ArtSite_NameCheap::checkavailability("google.com", $_POST['namecheap_apiuser'], $_POST['namecheap_apikey'], $_POST['namecheap_clientip'], $_POST['namecheap_sandbox']);
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

	$other_options = array(
		'stripe_apikey', 'stripe_apisecretkey',
		'signup_url', 'post_signup_url',
		'charge_description',
		'domainreg_address1', 'domainreg_town', 'domainreg_state', 'domainreg_zip', 'domainreg_phone', 'domainreg_email', 'domainreg_org', 'domainreg_fname', 'domainreg_lname', 'domainreg_country',
		'card_change_url'
	);
	foreach ($other_options as $key) $options[$key] = $_POST[$key];

	$options['charge_initial_amount'] = (float)$_POST['charge_initial_amount'];
	$options['charge_monthly_amount'] = (float)$_POST['charge_monthly_amount'];

	$options['nameservers_default'] = preg_replace("/\s/", "", $_POST['nameservers_default']);

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

<?php artsite_signup_options_standardrow('Default DNS servers', 'nameservers_default', 'Use commas to separate', 64, 200); ?>

<?php artsite_signup_options_standardrow('Admin contact first name', 'domainreg_fname', '', 32, 200); ?>
<?php artsite_signup_options_standardrow('Admin contact last name', 'domainreg_lname', '', 32, 200); ?>
<?php artsite_signup_options_standardrow('Admin contact house/street', 'domainreg_address1', 'These are the details to be used for registering new domain names', 32, 200); ?>
<?php artsite_signup_options_standardrow('Admin contact town', 'domainreg_town', '', 32, 200); ?>
<?php artsite_signup_options_standardrow('Admin contact state', 'domainreg_state', '', 32, 200); ?>
<?php artsite_signup_options_standardrow('Admin contact zip', 'domainreg_zip', '', 32, 200); ?>
<?php artsite_signup_options_standardrow('Admin contact country code', 'domainreg_country', '(Two-letters)', 32, 200); ?>
<?php artsite_signup_options_standardrow('Admin contact phone', 'domainreg_phone', '(Must be exactly in format like: +44.12345etc)', 32, 200); ?>
<?php artsite_signup_options_standardrow('Admin contact email address', 'domainreg_email', '', 32, 200, get_bloginfo('admin_email')); ?>
<?php artsite_signup_options_standardrow('Admin contact organisation', 'domainreg_org', '', 32, 200); ?>

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
	<td><input maxlength="128" type="text" size="48" name="signup_url" value="<?php if (!empty($options['signup_url'])) { echo htmlspecialchars($options['signup_url']);} else { echo "http://"; } ?>"/> <em>Attempts to sign-up via wp-signup.php will be intercepted and redirected to here.</em></td>
</tr>

<tr valign="top">
	<th scope="row">After successful signup, redirect to:</th>
	<td><input maxlength="128" type="text" size="48" name="post_signup_url" value="<?php if (!empty($options['post_signup_url'])) { echo htmlspecialchars($options['post_signup_url']);} else { echo "http://"; } ?>"/></td>
</tr>

<?php artsite_signup_options_standardrow('URL for card details change:', 'card_change_url', 'This should be a URL for a page containing the [artsite-card-change-form] shortcode', 48); ?>

<tr valign="top">
	<th scope="row">Charge description:</th>
	<td><input maxlength="128" type="text" size="48" name="charge_description" value="<?php if (!empty($options['charge_description'])) { echo htmlspecialchars($options['charge_description']);} else { echo "Artsite"; } ?>"/> <em>This is passed to Stripe as the charge description</em></td>
</tr>

<tr valign="top">
	<th scope="row">Initial charge:</th>
	<td>$ <input maxlength="6" type="text" size="6" name="charge_initial_amount" value="<?php if (!empty($options['charge_initial_amount'])) { echo htmlspecialchars($options['charge_initial_amount']);} else { echo "50"; } ?>"/></td>
</tr>

<?php artsite_signup_options_standardrow('Monthly charge (after "free" period):', 'charge_monthly_amount', "N.B. Affects both existing and new users", 6, 6, 20, '$ '); ?>

</table>
<?php

}

// There is no deep reason why this function is not used more. I added it after realising that I was going to repeat a lot, but did not fix non-broken things by converting everything to using it.
function artsite_signup_options_standardrow($label, $name, $description = "", $size = 12, $maxlength = 100, $default = "", $prefix = "" ) {
$options = get_site_option('artsite_signup_options');
?>
<tr valign="top">
	<th scope="row"><?php echo htmlspecialchars($label); ?></th>
	<td><?php echo $prefix; ?><input maxlength="<?php echo $maxlength; ?>" type="text" size="<?php echo $size; ?>" name="<?php echo htmlspecialchars($name); ?>" value="<?php if (!empty($options[$name])) { echo htmlspecialchars($options[$name]);} else { echo htmlspecialchars($default); } ?>"><?php if ($description) { echo " <em>".htmlspecialchars($description)."</em>"; } ?></td>
</tr>
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
