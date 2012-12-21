//<?php
// The purpose of the above line is to give a hint to the editor to give the desired syntax highlighting

$username = isset($_POST[$csp.'_user_name']) ? htmlspecialchars($_POST[$csp.'_user_name']) : "";
$email = isset($_POST[$csp.'_email']) ? htmlspecialchars($_POST[$csp.'_email']) : "";
$domain = isset($_POST[$csp.'_domain']) ? htmlspecialchars($_POST[$csp.'_domain']) : "";

return <<<ENDHERE
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

ENDHERE;

//?>
