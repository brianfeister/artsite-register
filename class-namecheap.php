<?php

class ArtSite_NameCheap {

	function construct_url ($namecheap_apiuser = false, $namecheap_apikey = false, $namecheap_clientip = false, $namecheap_sandbox = "yes") {

		$options = get_site_option('artsite_signup_options');
		if ($namecheap_apiuser === false && isset($options['namecheap_apiuser'])) $namecheap_apiuser = $options['namecheap_apiuser'];
		if ($namecheap_apikey === false && isset($options['namecheap_apikey'])) $namecheap_apikey = $options['namecheap_apikey'];
		if ($namecheap_clientip === false && isset($options['namecheap_clientip'])) $namecheap_clientip = $options['namecheap_clientip'];
		if (empty($namecheap_sandbox) && isset($options['namecheap_sandbox'])) $namecheap_sandbox = $options['namecheap_sandbox'];

		if (empty($namecheap_apiuser) || empty($namecheap_apikey) || empty($namecheap_clientip)) return new WP_Error('missing_params', 'Missing NameCheap API configuration parameters' );

		$namecheap_auth = "ApiUser=$namecheap_apiuser&ApiKey=$namecheap_apikey&UserName=$namecheap_apiuser&ClientIP=$namecheap_clientip";

		$namecheap_url = ($namecheap_sandbox == "yes") ? 'https://api.sandbox.namecheap.com' : 'https://api.namecheap.com';
		$namecheap_url .= '/xml.response?'.$namecheap_auth;

		return $namecheap_url;
	}

	// $details is an array, with entires:
	// fname, lname, addr1, town, state, zip, country, phone, email, org
	// Returns either a WP_Error or (upon success) a NameCheap transaction ID
	function register_domain($domain, $details, $namecheap_apiuser = false, $namecheap_apikey = false, $namecheap_clientip = false, $namecheap_sandbox = "yes") {

		$options = get_site_option('artsite_signup_options');

		$namecheap_url = self::construct_url($namecheap_apiuser, $namecheap_apikey, $namecheap_clientip, $namecheap_sandbox);
		if (is_wp_error($namecheap_url)) return $namecheap_url;

		// TODO: Nameservers=(CSV)
		// (Registrant,Aux,Tech,Billing)(FirstName,LastName,Address1,StateProvince,PostalCode,Country,Phone,EmailAddress,OrganizationName,City)
		// Later: PromotionCode=GOLDDEAL (once you have 50+ domains in your account)
		// Possible: AddFreeWhoisguard=yes&WGEnabled=No&
		$extra_params = array (
			'Command' => 'namecheap.domains.create',
			'DomainName' => $domain,
			'Years' => 1,
			'Nameservers' => $options['nameservers_default']
		);

		$extra_params['RegistrantFirstName'] = $details['fname'];
		$extra_params['RegistrantLastName'] = $details['lname'];
		$extra_params['RegistrantAddress1'] = $details['addr1'];
		$extra_params['RegistrantCity'] = $details['town'];
		$extra_params['RegistrantStateProvince'] = $details['state'];
		$extra_params['RegistrantPostalCode'] = $details['zip'];
		$extra_params['RegistrantCountry'] = $details['country'];
		$extra_params['RegistrantPhone'] = preg_replace("/\s/", '', $details['phone']);
		$extra_params['RegistrantEmailAddress'] = $details['email'];
		$extra_params['RegistrantOrganizationName'] = $details['org'];

		foreach (array('AuxBilling', 'Tech', 'Admin') as $ctype) {
			$extra_params[$ctype.'FirstName'] = $options['domainreg_fname'];
			$extra_params[$ctype.'LastName'] = $options['domainreg_lname'];
			$extra_params[$ctype.'Address1'] = $options['domainreg_address1'];
			$extra_params[$ctype.'City'] = $options['domainreg_town'];
			$extra_params[$ctype.'StateProvince'] = $options['domainreg_state'];
			$extra_params[$ctype.'PostalCode'] = $options['domainreg_zip'];
			$extra_params[$ctype.'Country'] = $options['domainreg_country'];
			$extra_params[$ctype.'Phone'] = preg_replace("/\s/", '', $options['domainreg_phone']);
			$extra_params[$ctype.'EmailAddress'] = $options['domainreg_email'];
			$extra_params[$ctype.'OrganizationName'] = $options['domainreg_org'];
		}

		$namecheap_url .= '&'.http_build_query($extra_params);

		$result = wp_remote_get($namecheap_url, array('timeout' => 60));

		if (is_wp_error($result)) return $result;

		$xml = new SimpleXMLElement( $result['body'] );
		if ( 'ERROR' == $xml['Status'] ) {
			return new WP_Error('namecheap_error', (string) $xml->Errors->Error );
		} elseif ( 'OK' == $xml['Status'] ) {
			$result = strtolower( (string)$xml->CommandResponse->DomainCreateResult->attributes()->Registered );
			if ($result == "true") return strtolower( (string)$xml->CommandResponse->DomainCreateResult->attributes()->TransactionID );
			if ($result == "false") return  new WP_Error('namecheap_registration_failed', "The domain name could not be registered" );
			return new WP_Error('namecheap_unknown_status', "Unrecognised result returned from NameCheap API" );
		} else {
			return new WP_Error('namecheap_unknown_status', "Unrecognised result status (".$xml['Status'].") returned from NameCheap API" );
		}

	}

	// This is a convenience method. Send the url from the name of the command onwards
	function call_url($url) {
		$namecheap_url = self::construct_url();
		$namecheap_url .= "&Command=".$url;
		return wp_remote_get($namecheap_url, array('timeout' => 60));
	}

	function domain_renew($domain) {

		$result = self::call_url("namecheap.domains.renew&DomainName=$domain&Years=1");

		if (is_wp_error($result)) return $result;

		$xml = new SimpleXMLElement( $result['body'] );

		if ( 'ERROR' == $xml['Status'] ) {
			return new WP_Error('namecheap_error', (string) $xml->Errors->Error );
		} elseif ( 'OK' == $xml['Status'] ) {
			$results = $xml->CommandResponse->DomainRenewResult;
			if (isset($results['Renew']) && $results['Renew'] == 'true') {
				return (string)$results->attributes()->TransactionID;
			}
			return new WP_Error('api_error', 'An error occurred when examining the domain status');
		}


	}

	// Gets the information on a domain
	// Returns false if it is not in our account
	// Returns a WP_Error if something went wrong
	// Returns an array of information if all goes well
	function domaininfo($domain) {

		$namecheap_url = self::construct_url();

		$namecheap_url .= "&Command=namecheap.domains.getList&SearchTerm=$domain&Page=1&PageSize=100";

		$result = wp_remote_get($namecheap_url, array('timeout' => 60));

		if (is_wp_error($result)) return $result;

		$xml = new SimpleXMLElement( $result['body'] );
		if ( 'ERROR' == $xml['Status'] ) {
			return new WP_Error('namecheap_error', (string) $xml->Errors->Error );
		} elseif ( 'OK' == $xml['Status'] ) {

			$results = $xml->CommandResponse->DomainGetListResult;

			if (!is_a($results, 'SimpleXMLElement') || count($results)<1) return new WP_Error('no_domain', 'No existing domain registration was found for this domain');

			foreach ($results as $dom) {

				// NameCheap return substring matches - we want exact
				if (isset($dom->Domain['Name']) && isset($dom->Domain['Expires']) && strtolower($dom->Domain['Name']) == strtolower($domain)) {
					return $dom->Domain;
				}

			}

			return new WP_Error('not_found', 'This domain was not found in our account at the registrar');
		} else {
			return new WP_Error('api_error', 'An error occurred when examining the domain status');
		}

	}

	// Returns true (available), false (not available), or a WP_Error object
	function checkavailability($domain, $namecheap_apiuser = false, $namecheap_apikey = false, $namecheap_clientip = false, $namecheap_sandbox = "yes") {

		$namecheap_url = self::construct_url($namecheap_apiuser, $namecheap_apikey, $namecheap_clientip, $namecheap_sandbox);

		if (is_wp_error($namecheap_url)) return $namecheap_url;

		$namecheap_url .= '&Command=namecheap.domains.check&DomainList='.urlencode($domain);

		$result = wp_remote_get($namecheap_url, array('timeout' => 60));

		if (is_wp_error($result)) return $result;

		if ( false == $result ) {
			return new WP_Error('network_error', 'Communication error' );
		}

		$xml = new SimpleXMLElement( $result['body'] );
		if ( 'ERROR' == $xml['Status'] ) {
			return new WP_Error('namecheap_error', (string) $xml->Errors->Error );
		} elseif ( 'OK' == $xml['Status'] ) {
			$result = strtolower( (string)$xml->CommandResponse->DomainCheckResult->attributes()->Available );
			if ($result == "true") return true;
			if ($result == "false") return false;
			return new WP_Error('namecheap_unknown_status', "Unrecognised result returned from NameCheap API" );
		}

	}


}

?>
