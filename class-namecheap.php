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

	function register_domain($domain, $namecheap_apiuser = false, $namecheap_apikey = false, $namecheap_clientip = false, $namecheap_sandbox = "yes") {

		$namecheap_url = self::construct_url($namecheap_apiuser, $namecheap_apikey, $namecheap_clientip, $namecheap_sandbox);
		if (is_wp_error($namecheap_url)) return $namecheap_url;

		// TODO: Nameservers=(CSV)
		// (Registrant,Aux,Tech,Billing)(FirstName,LastName,Address1,StateProvince,PostalCode,Country,Phone,EmailAddress,OrganizationName,City)
		// Later: PromotionCode=GOLDDEAL (once you have 50+ domains in your account)
		// Possible: AddFreeWhoisguard=yes&WGEnabled=No&
		$extra_params = array (
			'Command' => 'namecheap.domains.create',
			'DomainName' => $domain,
			'Years' => 1
		);
			
		$namecheap_url .= '&'.http_build_query($extra_params);

		return new WP_Error('not_yet_impl', "Not yet implemented. Would call: ".$namecheap_url);

	}

	// Returns true (available), false (not available), or a WP_Error object
	function checkavailability($domain, $namecheap_apiuser = false, $namecheap_apikey = false, $namecheap_clientip = false, $namecheap_sandbox = "yes") {

		$namecheap_url = self::construct_url($namecheap_apiuser, $namecheap_apikey, $namecheap_clientip, $namecheap_sandbox);

		if (is_wp_error($namecheap_url)) return $namecheap_url;

		$namecheap_url .= '&Command=namecheap.domains.check&DomainList='.urlencode($domain);

		$result = wp_remote_get($namecheap_url);

		if ( false == $result || is_wp_error($result) ) {
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