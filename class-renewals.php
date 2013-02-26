<?php

// How many days out to charge site owners at
define('ARTSITE_CHARGEDAYS', 3);

/*

Snipped example of relevant meta-data for a user. This class makes decisions based on, and maintains, this meta-data.

mysql> select * from wm_usermeta where user_id=18;
+----------+---------+----------------------------+-------------------------------------+
| umeta_id | user_id | meta_key                   | meta_value                          |
+----------+---------+----------------------------+-------------------------------------+
|      445 |      18 | first_name                 |                                     |
|      446 |      18 | last_name                  |                                     |
|      447 |      18 | nickname                   | robertoa                            |
|      448 |      18 | description                |                                     |
|      457 |      18 | primary_blog               | 16                                  |
|      458 |      18 | source_domain              | franglethrob86.org                  |
|      459 |      18 | wm_16_capabilities         | a:1:{s:13:"administrator";s:1:"1";} |
|      460 |      18 | wm_16_user_level           | 10                                  |
|      461 |      18 | stripe_customer_token      | cus_1AvPYhdOnqZpdL                  |
|      462 |      18 | card_expiry                | 12/2015                             |
|      463 |      18 | signup_fname               | Roberto                             |
|      464 |      18 | signup_lname               | McCheese                            |
|      465 |      18 | signup_addr1               | 1, The Street                       |
|      466 |      18 | signup_town                | Bobville                            |
|      467 |      18 | signup_state               | Arizona                             |
|      468 |      18 | signup_country             | AF                                  |
|      469 |      18 | signup_zip                 | 12345                               |
|      470 |      18 | signup_email               | tk@simbahosting.co.uk               |
|      471 |      18 | signup_org                 |                                     |
|      472 |      18 | signup_phone               | +1242.12 213424                     |
|      473 |      18 | last_namecheap_transaction | 637359                              |
|      474 |      18 | paid_until                 | 1374847460                          |
+----------+---------+----------------------------+-------------------------------------+

*/


// Set up cron jobs, admin columns, etc.
ArtSite_Renewals::init();

class ArtSite_Renewals {

	public static function init() {
		
		// Register cron handler
		add_action('artsite_cron_runner', array('ArtSite_Renewals', 'cron_runner'));

		$timestamp = wp_next_scheduled('artsite_cron_runner');
		
		// If not scheduled, then schedule it
		if (!$timestamp) {
			wp_schedule_event(time()+60, 'twicedaily', 'artsite_cron_runner');
		}

		add_shortcode('artsite-card-change-form', array('ArtSite_Renewals', 'card_renewal_page'));

		add_action('admin_init', array('Artsite_Renewals', 'admin_init'));

		// Add the renewal status column
		add_filter('wpmu_blogs_columns', array('ArtSite_Renewals', 'renewal_status_column'), 1);
		// Add the column content
		add_action('manage_sites_custom_column',  array('ArtSite_Renewals', 'renewal_status_column_content'), 1, 3 );
		// Make it sortable
		add_filter('manage_sites-network_sortable_columns', array('ArtSite_Renewals', 'renewal_status_column_sortable'), 1);
		add_filter('request', array('ArtSite_Renewals', 'paymentstatus_column_orderby') );

	}

	public static function dashboard_access_check() {

		// Exempt super admin (should be superfluous)
 		if (is_super_admin()) return;

		get_currentuserinfo();
		global $user_ID;

		$paid_until = get_user_meta($user_ID, 'paid_until', true);

		if ($paid_until >100 && $paid_until < time()) {
			// No dashboard for you, sir.
			$options = get_site_option('artsite_signup_options');
			$url = $options['card_change_url'];
			if ($url) {
				wp_redirect($url);
			} else {
				wp_redirect(home_url());
			}
			die;
		}

	}

	public static function admin_init() {

		// Prevent users accessing the dashboard if they have not paid
		self::dashboard_access_check();

		// Warn if card expiry is approaching
		self::dashboard_expiry_warning_check();

		// Count down free trial
		self::dashbaord_freetrial_notice_check();


	}

	public static function renewal_status_column( $columns ) {
		$first_array = array_splice ($columns, 0, 2);
		$columns = array_merge ($first_array, array('paymentstatus' => 'Payment Status'), $columns);
		return $columns;
	}

	public static function renewal_status_column_sortable( $columns ) {
		$columns['paymentstatus'] = 'paymentstatus';
		return $columns;
	}

	public static function paymentstatus_column_orderby( $vars ) {
		if ( isset( $vars['orderby'] ) && 'paymentstatus' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => 'paid_until',
				'orderby' => 'meta_value_num'
			) );
		}
		
		return $vars;
	}

	function renewal_status_column_content( $column, $blog_id ) {

		if ( $column != 'paymentstatus' ) return;

		// Find which user ID owns this blog (meta key: primary_blog)

		global $wpdb;
		$prefix = $wpdb->prefix;

		$sql = "SELECT u.ID FROM ${prefix}usermeta m, ${prefix}users u WHERE u.ID=m.user_ID AND m.meta_key='primary_blog' AND m.meta_value='$blog_id'";

		$user = $wpdb->get_results($sql);

		if (isset($user[0]->ID)) {
			// Find that user's card/payment status

			// UNIX time
			$paid_until = get_user_meta($user[0]->ID, 'paid_until', true);
			if ($paid_until >0) {
				$time_now = time();
				$days_until = floor(($paid_until - $time_now)/86400);
				$days_ago = $days_until * -1;
				if ($paid_until < $time_now) {
					echo "<span style=\"color: #f00\">Payment expired $days_ago days ago<br></span>";
				} elseif ($time_now + ARTSITE_CHARGEDAYS*86400 > $paid_until) {
					echo "<span style=\"color: #f0f\">Payment expires in $days_until days<br></span>";
				} else {
					echo "Payment expires in $days_until days<br>";
				}
			}

			// MM/YYYY
			$card_expiry = get_user_meta($user[0]->ID, 'card_expiry', true);

			if (preg_match('/(\d{2})\/(\d{4})/', $card_expiry)) {
				$days_to_expiry = self::days_to_expiry($card_expiry);
				$days_ago = $days_to_expiry*-1;
				if ($days_to_expiry < 0) {
					echo "<span style=\"color: #f00\">Card expired $days_ago days ago<br></span>";
				} elseif ($days_to_expiry < 7) {
					echo "<span style=\"color: #f0f\">Card expires in $days_to_expiry days<br></span>";
				} else {
					echo "Card expires in $days_to_expiry days<br>";
				}
			}

		}

	}

	public static function dashboard_expiry_warning_check() {
		if (is_admin()) {
			get_currentuserinfo();
			global $user_ID;
			$card_expiry = get_user_meta($user_ID, 'card_expiry', true);
			if (preg_match('/(\d{2})\/(\d{4})/', $card_expiry)) {
				$days_to_go = self::days_to_expiry($card_expiry);
				if ($days_to_go !== false && $days_to_go < 28) {
					add_action('admin_notices', array('ArtSite_Renewals', 'show_expiry_warning') );
				}
			}
		}
	}

	public static function dashbaord_freetrial_notice_check() {
		if (is_admin()) {
			get_currentuserinfo();
			global $user_ID;

			$paid_until = get_user_meta($user_ID, 'paid_until', true);

			$nowtime = time();
			$days_away = floor(($paid_until-$nowtime)/86400);

			if ($paid_until >100 && $paid_until>time()) {

				// See if they're in their first 180 days

				$user = get_userdata($user_ID);

				$registered = strtotime($user->user_registered);

				if ($registered>100) {

					$six_months_later = strtotime($user->user_registered.' + 6 months');

					if ($paid_until<=$six_months_later+86400 && $six_months_later>=time()) {

						global $artsite_trial_days_away;
						$artsite_trial_days_away = $days_away;
						add_action('admin_notices', array('ArtSite_Renewals', 'show_trial_warning') );

					}

				}

			}

		}
	}

	public static function show_trial_warning() {

		global $artsite_trial_days_away;

		$days = ($artsite_trial_days_away == 1) ? 'day' : 'days';

		$message = "You are presently in your initial six-month trial period - for another $days_away $days";
		$class = 'updated';

		echo '<div class="'.$class.'">'."<p>$message</p></div>";
	}

	public static function show_expiry_warning() {
		$class = 'error';
		$options = get_site_option('artsite_signup_options');
		$url = $options['card_change_url'];

		$message = "The payment card which we have on file for you is nearing expiry. <a href=\"$url\">Please go here to update your card details</a>.";

		echo '<div class="'.$class.'">'."<p>$message</p></div>";
	}

	public static function card_renewal_page() {

		$ret = "";
		$csp = ARTSITE_CSSPREFIX;

		// Display form (just the form - site operator is embedding via a short-code, so they can put in whatever blurb they like in the page)

		// Anything submitted?
		if (is_user_logged_in() && isset($_POST['cardchangeform']) && isset($_POST['_wpnonce'])) {

			// Validate

			global $artsite_form_errors;

			get_currentuserinfo();
			global $user_ID;
			
			$existing_customer_token = get_user_meta($user_ID, 'stripe_customer_token', true);

			if (is_string($existing_customer_token)) {

				$stripe_customer_token = ArtSite_DataValidator::validate_creditcard($existing_customer_token);

			} else {
				$artsite_form_errors[] = "There was an error retrieving your account details - please contact support";
			}

			if (count($artsite_form_errors) == 0 && !$stripe_customer_token) {
				$artsite_form_errors[] = 'The credit card details did not validate - please check, and try again.';
			}

			if (count($artsite_form_errors)>0) {

				$ret .= ArtSite_DataValidator::display_errors();

			} elseif (isset($stripe_customer_token) && is_string($stripe_customer_token)) {

				// Update user's meta-data
				# MM/YYYY
				add_user_meta($user_ID, 'card_expiry', sprintf("%02d/%02d", $exp_month, $exp_year));

				self::check_pending_charges($user_ID);

				$ret .= '<p>You successfully changed your card details.</p>';
				// Perhaps at some later point somebody will want to do something other than just display the message.
				do_action('artsite_changed_card_details');
				$cancel_show_form = true;

			} else {
				$artsite_form_errors[] = 'The credit card details did not validate (2) - please check, and try again.';
				$ret .= ArtSite_DataValidator::display_errors();
			}


		}

		if (is_user_logged_in()) {
			if (!isset($cancel_show_form)) {

				$paid_until = get_user_meta($user_ID, 'paid_until', true);

				if ($paid_until > 100 && $paid_until<time()) {
					$ret .= <<<ENDHERE
						<div id="${csp}_overduepayment_warning">
							Your account is overdue. You need to supply a valid card which can be charged before access can be restored to your account.
						</div>
ENDHERE;
				}

				$ret .= Artsite_Forms::cardchangeform_render();
			}
		} else {
// 			$options = get_site_option('artsite_signup_options');
// 			$redirect = isset($options['card_change_url']) ? $options['card_change_url'] : get_permalink();
			$redirect = wp_login_url(get_permalink());
			// Can't redirect - headers already sent

			$ret .= "<a href=\"$redirect\">You need to log in before you can update your card details. Please follow this link.</a>";

		}

		return $ret;

	}

	// This function does a best effort - will make a charge if it can, but nothing is guaranteed. (If the user does not pay in the long term, then they cannot log in)

	// Returns:
	// - true if a charge was performed successfully.
	// - false if no charge was needed
	// - A WP_Error object if a charged was needed but failed

	public static function check_pending_charges($uid, $existing_customer_token = false, $paid_until = false, $amount = false) {

		global $artsite_payments;

		// See when they've paid up to

		// We allow these to be supplied in order to save resources - perhaps the caller has already enumerated them
		if (false === $paid_until) $paid_until = (int)get_user_meta($uid, 'paid_until', true);
		if (false === $amount) {
			$options = get_site_option('artsite_signup_options');
			$amount = (!empty($options['charge_monthly_amount'])) ? (int)$options['charge_monthly_amount'] : false;
		}


		// Charge at indicated number of days out
		if (false != $amount && $paid_until > 100 && $paid_until - time() < (ARTSITE_CHARGEDAYS*86400)) {

			if ($amount >0) {

				if (false === $existing_customer_token) $existing_customer_token = get_user_meta($uid, 'stripe_customer_token', true);

				if (is_string($existing_customer_token)) {
					// Returns either a WP_Error or a charge ID (string)
					// This transient should be redundant, in that a successful charge will update the paid_until field out of range. But it does not harm to add an extra layer of security against double-charges.

					$idhash = 'as_charged_id_'.$uid;
					if (get_site_transient($idhash) !== 'charged') {
						$charged = $artsite_payments->charge($existing_customer_token, $amount);
					} else {
						$charged = false;
					}
				} else {
					$charged = false;
				}

				// Update the user's meta data
				if (!is_wp_error($charged) && is_string($charged)) {
					set_site_transient($idhash, 'charged', 86400);
					$user = get_user_by('id', $uid);
					$email = $user->email;
					$artsite_payments->send_receipt($email, $charged, $amount);
				
					$paid_until_string = date('Y-m-d', $paid_until);
					$paid_until_new = strtotime($paid_until_string.' +1 month');

					update_user_meta($uid, 'paid_until', $paid_until_new);

					return true;

				} else {

					return new WP_Error('charge_failed', 'Attempt to charge the card failed');

				}

			} else {
					return new WP_Error('incomplete_config', 'Attempt to charge the card failed due to missing configuration data (charge_monthly_amount)');
			}

		}

		return false;

	}

	// Purpose of this function: See what domains are about to expire, and renew them
	public static function renew_due_domains() {
		
		// Enumerate domain names

		global $wpdb;
		$blogs = $wpdb->get_results( $wpdb->prepare("SELECT blog_id, domain, path FROM $wpdb->blogs WHERE site_id = %d AND public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' ORDER BY registered DESC", $wpdb->siteid), ARRAY_A );

		foreach ($blogs as $blog) {

			if (!isset($blog['domain'])) continue;

			// See what NameCheap says about the domain's expiry status
			$dominfo = ArtSite_NameCheap::domaininfo($blog['domain']);

			if (is_a($dominfo, 'SimpleXMLElement')) {
				$expiry = strtotime(((string)$dominfo['Expires']));

				// We renew if it is no more than 3 days away, and not more than 10 days past (there must have been a problem - no point retrying endlessly)

				$nowtime=time();
				if ($expiry >100 && $expiry-$nowtime<86400*3 && $nowtime-$expiry<86400*10) {
					$renew = ArtSite_NameCheap::domain_renew($blog['domain']);
					// Could also be a WP_Error
					if (is_string($renew)) {

						// Renewal was successful - we now have a transaction ID

						// Store new metadata for the domain (though this is not really used since we really on NameCheap's authoritative upstream information for expiry status)
						// And there's nothing we use this info for anyway. But you never know what may be needed in future; or it may be useful for auditing/chasing problems in future.
						// Get user_id
						$user = $wpdb->get_row("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='primary_blog' AND meta_value='".$blog['blog_id']."'");
						if (isset($user->user_id) && is_numeric($user->user_id)) {
							update_user_meta($user->user_id, 'last_namecheap_transaction', $renew);
						}
					}
				}
			}

		}

	}

	public static function email_unpaid_users() {
		// Enumerate users who are unpaid

		global $wpdb;

		$users = $wpdb->get_results("SELECT u.ID, u.user_email, m.meta_value FROM $wpdb->usermeta m, $wpdb->users u WHERE u.ID=m.user_ID AND m.meta_key='paid_until'");

		$nowtime = time();

		$options = get_site_option('artsite_signup_options');
		$url = (!empty($options['card_change_url'])) ? $options['card_change_url'] : home_url();

		foreach ($users as $user) {

			$paid_until = $user->meta_value;

			$days_overdue = floor(($nowtime-$paid_until)/86400);

			// Nag them if they are 0, 7 or 14 days overdue
			if ($days_overdue == 0 || $days_overdue == 7 || $days_overdue == 14 || $days_overdue==-150) {

				$email = $user->user_email;

				$days = ($days_overdue == 1) ? 'day' : 'days';

				$overdue_descrip = ($days_overdue == 0) ? "today" : "$days_overdue $days ago";

				$ehash = md5($email); // Transient names must be a maximum of 45 characters long
				$user_nag_transient = get_site_transient("as_odnag_".$ehash);
				if ($user_nag_transient == 'naggedtoday') continue;

				wp_mail($email, 'Your account is now overdue', "Your account is now overdue ($overdue_descrip); our previous attempts to charge your card failed, and you have not supplied us with a working card number in the mean-while.\r\n\r\nPlease visit $url to update your card in order to ensure continued service.\r\n");

				set_transient("as_odnag_".$ehash, 'naggedtoday', 86399);

			}

		}

	}

	// This is our main (twice-)daily job. From here we despatch + do all other checks and jobs.
	public static function cron_runner() {

		// Go through the various regular tasks. These functions should make no assumptions themselves how regularly they are called - i.e. be stateless, within a week (to allow use of transients).
		self::email_users_with_expiring_cards();

		self::charge_due_users();

		self::renew_due_domains();

		self::email_unpaid_users();

	}

	// This function charges those users who are due, and increases their 'paid_until' field
	public static function charge_due_users() {

		global $wpdb;
		$prefix = $wpdb->prefix;

		$users = $wpdb->get_results("SELECT u.ID, u.user_email,  m.meta_value FROM ${prefix}usermeta m, ${prefix}users u WHERE u.ID=m.user_ID AND m.meta_key='paid_until'");

		$options = get_site_option('artsite_signup_options');

		$amount = $options['charge_monthly_amount'];
		$url = (!empty($options['card_change_url'])) ? $options['card_change_url'] : home_url();
		
		foreach ($users as $user) {
			$charged = self::check_pending_charges($user->id, false, $user->meta_value, $amount);

			// A WP_Error specifically indicates that a charge was detected as required and should have been possible (a payment token existed) but that it failed
			if (is_wp_error($charged)) {
				wp_mail($user->user_email, 'Attempt to charge your card failed', "We attempted to charge your card as part of your regular subscription; however, this attempt failed. Please go to $url and check your card details, to ensure continued service.");
			}
		}
	}

	public static function email_users_with_expiring_cards() {

		// There is apparently no quick way to enumerate all users across all sites other than a direct DB call

		global $wpdb;
		$prefix = $wpdb->prefix;

		$users = $wpdb->get_results("SELECT u.ID, u.user_email, m.meta_value FROM ${prefix}usermeta m, ${prefix}users u WHERE u.ID=m.user_ID AND m.meta_key='card_expiry'");

		$options = get_site_option('artsite_signup_options');
		$url = (!empty($options['card_change_url'])) ? $options['card_change_url'] : home_url();

		foreach ($users as $user) {
			$days_away = self::days_to_expiry($user->meta_value);
			// Are we 1, 14 or 28 days away? (i.e. within those days)
			if (1 == $days_away || 14 == $days_away || 28 == $days_away) {
				$email_user = $user->user_email;
				// Check transient - they may already have been emailed today
				// Transient names are limited to 45 characters length - emails are not
				$ehash = 'as_emld_'.$days_away.'_'.md5($email_user);
				$emailed = get_site_transient($ehash);
				if ('done' != $emailed) {
					// Set for a day - by which time the check will no longer match
					set_site_transient($ehash, 'done', 86400);
					// Send the actual email
					$days = ($days_away == 1) ? 'day' : 'days';
					wp_mail($email_user, 'Your card is about to expire', "The card on your account will expire in $days_away $days time.\r\n\r\nPlease visit $url to update it in order to ensure continued access.\r\n");
				}
			}
		} 

	}

	// Beware - can return 0, but can also return false
	// Note - this function rounds up
	public static function days_to_expiry($expiry_date) {
		if (!preg_match('/(\d{2})\/(\d{4})/',$expiry_date, $matches)) {
			return false;
		} else {
			$month = $matches[1];
			$year = $matches[2];
			$last_day = date('t', gmmktime(12, 0, 0, $month, 1, $year));
			// Almost midnight on the last day of the month
			$card_expires = date('U', gmmktime(23, 59, 59, $month, $last_day, $year));
			$days_away = floor(($card_expires - time())/86400)+1;
			return $days_away;
		}
	}


}
