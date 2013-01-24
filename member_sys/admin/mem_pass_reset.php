<?php
//	Copyright 2012 Paul Hays
//	(Email: Paul.Hays@cattail.ca)
//
//	This file is part of Member_sys.
//
//	Member_sys is free software: you can redistribute it and/or modify
//	it under the terms of the GNU General Public License as published by
//	the Free Software Foundation, either version 3 of the License, or
//	(at your option) any later version.
//
//	Member_sys is distributed in the hope that it will be useful,
//	but WITHOUT ANY WARRANTY; without even the implied warranty of
//	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//	GNU General Public License for more details.
//
//	You should have received a copy of the GNU General Public License
//	along with Member_sys.  If not, see <http://www.gnu.org/licenses/>.
/* * * * * * * * * * * * * * * * * * * * * * */

$description = <<<HEREDOC
This admin-only script updates the target user's profile so that the user
must update the password.
HEREDOC;

$admin_only_page = true;			// only users having i_administrator allowed
require ( '../lib/mem_only.php' );	// ... who are also members in good standing

$msg = '';
$target = new MemUser();			// the user to update
$page = new HtmlPage();

$title = 'Require a password update';
$page->top(  $title );

echo( '<center><font size=4>' . $title . '</font></center>
' );
echo( '<center>' . $you_are . '</center>
' );
echo( '<p>' . $description . '
</p>' );

// check the supplied userid number and load the corresponding target userdata
if( ! isset( $_GET['userid']) )
	$msg = 'The URL did not specify a userid (?userid=nn)';
else {
	$nn = $_GET['userid'];

	if( $nn == $user->userid() )
		$msg = 'The URL must specify a userid other than your own.';
	elseif ( 0 >= $nn)
		$msg = 'The URL specified an invalid userid.';
	else if( ! $target->get_userdata_i( $nn )) {
		$msg = 'The URL specified an unrecognized userid (' . $nn . ')
		<br>' . $target->error_msg ;
	}
	elseif ( (! isset( $target->userdata['s_email1'])) ||
	empty( $target->userdata['s_email1'] ))
		$msg = 'We have no primary email address registered for ' .
		'the target user (' . $target->userdata['s_username'] . ')';
}

if( empty( $msg )) {
	if( ! $target->update_user(array('i_change_pass'=>1), $user )) {
		$msg = 'The update to set the password expiration flag failed:
		<br>' . $target->error_msg ;
	}
}

if( empty( $msg )) {				// make an email message to the target user

	/// @note: This uses a mailto: hyperlink that the administrator must
	/// activate to run an email client. The administrator's computer must be
	/// configured to handle that. An alternative implementation might
	/// invoke the ISP's mail script, or PHP's mail() function. See
	/// http://www.apptools.com/phptools/forms/forms6.php
	///
	/// @note: the line slew used here works with some email programs
	/// (e.g. Thunderbird) but not others; you might need to change
	/// $newline to '%0d%0a'.
	/// See http://www.ssi-developer.net/design/mailto.shtml
	$newline = '%0a';				// line break for email message body

	echo( '<p>Click the Notify link to generate an email to notify user <b>' .
	$target->userdata['s_username'] . '</b> to update the password.</p>
	' );
	echo( '<p>' ) ;
	// make a URL for page mem_pass_renew.php with the userid and
	// current passhash of the target user as parameters 'u' and 'ph'
	$protocol = MEM_REQUIRE_HTTPS ? 'https:' : 'http:';
	$host = $_SERVER['SERVER_NAME'];
	$url = $protocol . '//' . $host . MEM_APP_ROOT . 'mem_pass_renew.php' .
			'?u=' . $target->userdata['i_userid'] .
			'%26ph=' . $target->userdata['s_passhash'] ;	// ( %26 means '&' )

	$lang = 'fr';					// use english and french here...
	$subject = "Account activation";	// T("") will provide translations
	$subject .= '/' . T( $subject );
	$subject = rawurlencode( $subject );

	$body_en = "Click the following link to activate (or reactivate) " .
	"your account for user %1\$s at %2\$s:";
	$body_fr = T( $body_en );
	$body =
	rawurlencode(sprintf( $body_en, $target->userdata['s_username'], $host )) .
	$newline .
	$url .
	$newline .
	$newline .
	rawurlencode(sprintf( $body_fr, $target->userdata['s_username'], $host )) .
	$newline .
	$url . '%26lang=fr' ;				// user clicks this link for french

	echo( '<a href="mailto:' . $target->userdata['s_email1'] . ',' .
	'?subject=' . $subject .
	'&body=' .  $body  . '">' .
	'Notify ' . $target->userdata['s_username'] . '</a>' );

	echo( '</p>' );
	if( MEM_DEBUG ) {
		echo( 'debug: $url = ' . $url . '<br>' );
		echo( 'debug: $subject = ' . $subject . '<br>' );
		echo( 'debug: $body = ' . $body . '<br>' );
	}
}

if (! empty( $msg )) {
	echo( '<p><b>' . $msg . '</b></p>' );
}

$page->bottom();

?>
