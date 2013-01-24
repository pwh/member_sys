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

/// Someone who does not know his or her account password invokes this by
/// activating the URL sent to the user via page mem_pass_reset.php.
/// It permits setting a password.
///
/// The URL must specify a userid (?u=) and that user's passhash (?ph=).
/// This script fetches the corresponding userdata, checks that i_change_pass
/// is set, and checks that the pashhash matches.
/// On failure it displays somethig like "Please contact membership.".
/// If all is well it notifies that a new password is required and displays
/// password entry fields.
///
/// Entering a valid password here creates a session and its cookie to sign in
/// the user, stores a new s_passhash, and clears i_change_pass

set_include_path( dirname(__FILE__) . '/lib' );
require_once( 'mem_config.php' );
require_once( 'mem_db.class.php' );	// MemDb wrapper for application database
$mdb = new MemDb;
require_once( 'mem_translate.php' );	// T(), sets global $lang, uses $mdb
require_once( 'mem_user.class.php' );
require_once( 'mem_htmlpage.class.php' );

$target= new MemUser;				// the target user
$page = new HtmlPage;
$newuserdata = array();				// gets the new password etc


// ============= initializations ==============
$msg = '';

if( !( isset( $_GET['u'] ) && isset( $_GET['ph'] ))) {	// both parameters set?
	$msg = T( "The web address for this page is incomplete." );
}
else {								// check the parameters, load userdata
	$nn = $_GET['u'];
	if ( ( 0 >= $nn ) ||			// impossible userid?
	( ! $target->get_userdata_i( $nn )) || // can't find the user?
	( $target->userdata['s_passhash'] != $_GET['ph'] )) { // bad passhash?
		$msg = T( "The web address for this page is invalid." );
	}
	elseif ( (! isset( $target->userdata['i_change_pass'] )) ||
	(0 == $target->userdata['i_change_pass'] )) { // no password reset request
		$msg = T( "The account is already activated." );
	}
}
if ( ! empty( $msg) ) {			// if we have an error message just say it
	$page->top(
	T( "Error" ));
	echo( '<p><b>' . $msg . '</b></p>' );
	if( MEM_DEBUG ) {
		echo( '?u = ' . $_GET['u'] . '<br>' );
		echo( '?ph = ' . $_GET['ph'] . '<br>' );
		echo( '$target->error_msg = ' . $target->error_msg . '<br>' );
		echo( '$target->userdata = ' .
		print_r( $target->userdata, true ) . '<br>' );
	}
	$page->bottom();
	exit;
}

// ============= form handling ==============
if( isset( $_POST['submit'])) {		// submit button clicked

	// get password fields
	$password = $page->trim_entry( 'password', MEM_PASSWORD_MAXSIZE );
	if ( ! empty( $password ))
		$newuserdata['password'] = $password;
	$password2 = $page->trim_entry( 'password2', MEM_PASSWORD_MAXSIZE );
	if ( ! empty( $password2 ))
		$newuserdata['password2'] = $password2;

	if ( count( $newuserdata ) == 0 ) {	// nothing to update?
		$msg = T( "The profile did not change." );
	}
	else {			// validate data, update database, and sign in
		if ( ! ($target->update_user( $newuserdata, $target ) &&
				$target->sign_me_in() )) {
			$msg = $target->error_msg;
		}
	}

	if ( empty( $msg )) {			// all done; announce the good news
		$page->top(
		T( "Account activated" ));
		printf(
		T( "The account for user <b>%1\$s</b> at %2\$s is now active, " .
		"and you are signed in." ),
		$target->userdata['s_username'], $_SERVER['SERVER_NAME'] );
		$page->bottom();
		exit;
	}
}


// ---------------- HTML page generator: display the account activation form --

$page->top(
T( "Account activation" ));

echo(
	T( "To activate your account, you must create a password or passphrase." )
		. '<br>
		<ul><li>' .
	T( "Entries are case-sensitive." ) .
		'<li>' .
	T( "Entries may contain spaces, but " .
	"leading and trailing spaces are ignored." ) .
		'</li><li>' .
		sprintf(
	T( "The password or passphrase must have between %1\$d and %2\$d " .
	"characters, and must have at least one digit." ),
		MEM_PASSWORD_MINSIZE, MEM_PASSWORD_MAXSIZE ) .
		'</li></ul>
' );

$page->form();						// display the password entry fields
echo( '
		<p><table  cellpadding="4" width="100%" cellspacing="3">
		<tr><td bgcolor="' . MEM_FORM_COLOR .
		'" rowspan="2"><div align="right">' .
	T( "Password or passphrase (entered twice, once in each box):" ) .
		'</div></td>
		<td bgcolor="' . MEM_FORM_COLOR . '">
		<input id="password" name="password" type="password"
		size="' . MEM_PASSWORD_MAXSIZE . '"
		maxlength="' . MEM_PASSWORD_MAXSIZE . '" /></td></tr>
		<td bgcolor="' . MEM_FORM_COLOR . '">
		<input id="password2" name="password2" type="password"
		size="' . MEM_PASSWORD_MAXSIZE. '"
		maxlength="' . MEM_PASSWORD_MAXSIZE . '" /></td></tr>');

echo( '
		</table>
		</p>
		' );

echo( '<p><input id="submit" name="submit" value=' .
	T( "Submit" ) .
		' type="submit" />' );
echo( '&nbsp;&nbsp;&nbsp;' );
echo( '<button type="reset" >' .
	T( "Reset" ) . '</button>' );

$page->form_bottom();

if ( ! empty( $msg) ) {				// if we have an error message say it now.
	echo( '<p><b>' . $msg . '</b></p>' );
}

if ( MEM_DEBUG ) {
	echo( '<br><br>debug: $newuserdata has ' . print_r( $newuserdata, true ));
}

$page->bottom();
?>
