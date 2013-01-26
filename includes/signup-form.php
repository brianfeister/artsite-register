<?php
function artsite_signup_form_render() {

$csp = ARTSITE_CSSPREFIX;

$username = isset($_POST[$csp.'_user_name']) ? htmlspecialchars($_POST[$csp.'_user_name']) : "";
$email = isset($_POST[$csp.'_email']) ? htmlspecialchars($_POST[$csp.'_email']) : "";
$domain = isset($_POST[$csp.'_domain']) ? htmlspecialchars($_POST[$csp.'_domain']) : "";

$addr1 = isset($_POST[$csp.'_']) ? htmlspecialchars($_POST[$csp.'_addr1']) : "";
$town = isset($_POST[$csp.'_']) ? htmlspecialchars($_POST[$csp.'_town']) : "";
$state = isset($_POST[$csp.'_']) ? htmlspecialchars($_POST[$csp.'_state']) : "";
$zip = isset($_POST[$csp.'_']) ? htmlspecialchars($_POST[$csp.'_zip']) : "";
$phoneno = isset($_POST[$csp.'_']) ? htmlspecialchars($_POST[$csp.'_phoneno']) : "";

$ret2 = <<<ENDHERE
	<div class="${csp}_form_row">

		<div class="${csp}_editform-label"><label for="${csp}_real_fname">Your name:</label>
		<input type="text" id="${csp}_real_fname" class="${csp}_form_textinput" name="${csp}_real_fname" size="15" maxlength="48" value="" data-validate-presence="true" data-validate-error=".${csp}_real_fname-error"> <input type="text" id="${csp}_real_lname" class="${csp}_form_textinput" name="${csp}_real_lname" size="15" maxlength="48" value="" data-validate-presence="true" data-validate-error=".${csp}_real_lname-error"> </div>
		<div class="${csp}_error ${csp}_real_fname-error">Required (so that your domain fname can be properly registered to you)</div>

	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_user_name">Username:</label> <input type="text" id="${csp}_user_name" class="${csp}_form_textinput" name="${csp}_user_name" size="14" value="$username" data-validate-newusername="true" data-validate-presence="true" data-validate-error=".${csp}_user_name-error"> <em>Letters and numbers only</em></div>
		<div class="${csp}_error ${csp}_user_name-error"><span class="newusername-error">Username is not available - choose another</span> <span class="blank-error">Required</span></div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_email">Your email address:</label> <input type="text" id="${csp}_email" class="${csp}_form_textinput" name="${csp}_email" size="32" value="$email" data-validate-newemail="true" data-validate-email="true" data-validate-error=".${csp}_email-error"></div>
		<div class="${csp}_error ${csp}_email-error"><span class="newemail-error">E-mail used by another account - use another</span> <span class="email-error">Valid address required</span></div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_domain">Domain name:</label> <input type="text" id="${csp}_domain" class="${csp}_form_textinput" name="${csp}_domain" size="27" value="$domain" data-validate-domain="true" data-validate-presence="true" data-validate-error=".${csp}_domain-error">
		<select name="${csp}_domain_suffix" id="${csp}_domain_suffix">
			<option value=".org">.org</option>
			<option value=".me">.me</option>
			<option value=".com">.com</option>
			<option value=".net">.net</option>
		</select>
		</div>

		<div class="${csp}_error ${csp}_domain-error"><span class="domain-error">Domain not available - use another</span> <span class="blank-error">Required</span></div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_ccnumber">Credit card number:</label> <input type="text" id="${csp}_ccnumber" class="${csp}_form_textinput" name="${csp}_ccnumber" size="32" value="" data-validate-ccnumber="true" data-validate-presence="true" data-validate-error=".${csp}_ccnumber-error"></div>
		<div class="${csp}_error ${csp}_ccnumber-error"><span class="ccnumber-error">Invalid credit card number</span> <span class="blank-error">Required</span></div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_ccexpiry">Credit card expiry:</label> <input type="text" id="${csp}_ccexpiry" class="${csp}_form_textinput" name="${csp}_ccexpiry" size="5" maxlength="5" value="" data-validate-presence="true" data-validate-format="/^(0[0-9]|1[0-2])\/(1[2-9]|[2-9][0-9])$/" data-validate-error=".${csp}_ccexpiry-error"> <em>Format: MM/YY</em></div>
		<div class="${csp}_error ${csp}_ccexpiry-error">Enter a valid, future date (two digits each for month and year: MM/YY)</div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_cccvc">CVC code:</label> <input type="text" id="${csp}_cccvc" class="${csp}_form_textinput" name="${csp}_cccvc" size="5" maxlength="5" value="" data-validate-presence="true" data-validate-format="/^([0-9]{3,4})$/" data-validate-error=".${csp}_cccvc-error"> <em>(usually found on the back of your card)</em></div>
		<div class="${csp}_error ${csp}_cccvc-error">Enter a valid CVC code (usually 3 digits)</div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_addr1">Address:</label> <input type="text" id="${csp}_addr1" class="${csp}_form_textinput" name="${csp}_addr1" size="32" maxlength="96" value="$addr1" data-validate-presence="true" data-validate-error=".${csp}_addr1-error"></div>
		<div class="${csp}_error ${csp}_addr1-error">Required (so that your domain name can be properly registered to you)</div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_town">Town/City:</label> <input type="text" id="${csp}_town" class="${csp}_form_textinput" name="${csp}_town" size="32" maxlength="96" value="$town" data-validate-presence="true" data-validate-error=".${csp}_town-error"></div>
		<div class="${csp}_error ${csp}_town-error">Required (so that your domain name can be properly registered to you)</div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_state">State:</label> <input type="text" id="${csp}_state" class="${csp}_form_textinput" name="${csp}_state" size="32" maxlength="96" value="$state" data-validate-presence="true" data-validate-error=".${csp}_state-error"></div>
		<div class="${csp}_error ${csp}_state-error">Required (so that your domain name can be properly registered to you)</div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_zip">Zip:</label> <input type="text" id="${csp}_zip" class="${csp}_form_textinput" name="${csp}_zip" size="12" maxlength="8" value="$zip" data-validate-presence="true" data-validate-error=".${csp}_zip-error"></div>
		<div class="${csp}_error ${csp}_zip-error">Required (so that your domain name can be properly registered to you)</div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_country">Country:</label> 
ENDHERE;

	ob_start();
	require(ARTSIGNUP_DIR.'/includes/countrylist.php');
	$ret2 .= ob_get_clean();

$ret2 .= <<<ENDHERE
		</div>
	</div>

	<div class="${csp}_form_row">
		<div class="${csp}_editform-label"><label for="${csp}_phone">Phone:</label> 
ENDHERE;

	ob_start();
	require(ARTSIGNUP_DIR.'/includes/phonecodelist.php');
	$ret2 .= ob_get_clean();

$ret2 .= <<<ENDHERE
		<input type="text" id="${csp}_phoneno" class="${csp}_form_textinput" name="${csp}_phoneno" size="16" maxlength="15" value="" data-validate-presence="true" data-validate-format="/^[ 0-9]{5,14}$/" data-validate-error=".${csp}_phoneno-error">	</div>
		<div class="${csp}_error ${csp}_phoneno-error">Numbers only</div>
	</div>



ENDHERE;

return $ret2;

}

?>
