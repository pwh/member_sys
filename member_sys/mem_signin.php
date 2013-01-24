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

/// This signin management script can by invoked directly by a user to sign in
/// or via e.g. script member_only.php to ensure that a user has signed in.
/// If the user does not yet have a session, this prompts for credentials,
/// checks they are valid, and creates one.
///
/// It preloads the username entry field with the value from parameter
/// ?username= or else from an (optional) cookie containing the last
/// username entered.

set_include_path( dirname(__FILE__) . '/lib' );

require_once( 'mem_config.php' );
require_once( 'mem_db.class.php' );	// MemDb wrapper for application database
$mdb = new MemDb;
require_once( 'mem_translate.php' );	// T(), uses $mdb
require_once( 'no_magic_quotes.php' );
require_once( 'mem_user.class.php' );
require_once( 'mem_htmlpage.class.php' );
if( !function_exists('hex2bin') )
	require_once( 'hex2bin.php' );

$user = new MemUser;
$page = new HtmlPage;
$script_name = $_SERVER['SCRIPT_NAME'];

/// the optional URL for the Continue hyperlink
/// @note: $_SERVER['HTTP_REFERER'] "cannot really be trusted" (PHP manual)
/// @todo check that this is encoded/decoded *safely*
$back = isset( $_GET['back'] ) ?  htmlentities( trim( $_GET['back'] )) : '';


if ( !isset( $_POST['submit'] )) {

	// determine whether we already have a signed-in session
	$signed_in = $user->is_signed_in();

	if ( $signed_in && isset($_GET['username'] )) {

		// we'll sign in as a different user; force
		// signout of current user
		$user->signout();
		$signed_in = false;
	}
}
else {								// user clicked submit
	// trim the string entries
	$s_username = $page->trim_entry('s_username', MEM_NAME_MAXSIZE );
	$password = $page->trim_entry( 'password', MEM_PASSWORD_MAXSIZE );

	// sign in the user if the credentials are valid
    $signed_in = $user->signin( $s_username, $password );
}                          // form not yet submitted


if ( $signed_in ) {

	// if "remember my username" checkbox is selected,
	// save username cookie, otherwise delete it
	if ( isset( $_POST['rememberme'] )) {

		// ask the browser to save the username in an optional 30-day cookie
		// used to preload the signin form in future; subsequent signins reset
		// the timeout. note: the string is encoded to workaround misfeatures
		// when handling non-ascii utf8 values (setcookie() implies urlencode,
		// but that is insufficient)
		$cookie_value = bin2hex( $s_username );
		$cookie_expire = time()+60*60*24*30;	// expire after 30 days
	}
	else {
		// try to tell the browser to delete the username cookie
		$cookie_value = '';			// empty => 'deleted'
		$cookie_expire = 1;			// declare cookie expired in 1970
	}
	setrawcookie( MEM_USR_COOKIE, $cookie_value, $cookie_expire,
	MEM_COOKIE_PATH,
	($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false,
	MEM_REQUIRE_HTTPS,
	true );							// true-> cookie unavailable to javascript


	// ---------------- HTML page generator (signed in) -----------------

	$page->top( T( "Welcome" ));

	printf( T( "Welcome <b>%s</b>." ), $user->userdata['s_username'] );
	echo( '&nbsp;' . T( "You are signed in." ) . '<br>' );
	if ( MEM_DEBUG ) {
		echo( 'debug: userid: ' . $user->userdata['i_userid'] .
		' is signed in.<br>
		' );
	}

	// if the user is in arrears say so
	if( ! $user->members_only_ok() ) {
		echo(
		T( "Access to members-only pages is not currently allowed." ) . '<br>
		');
	}

	// if the password must be changed, inform the user
	if( $user->userdata['i_change_pass'] ) {
		echo( '<br><b>' );
		echo( T("To proceed, you must enter a new password; " .
				"to do that, please click Update your Profile."));
		echo( '</b><br><br>
		' );
	}
	else {

		// if there's a page to Continue to, show the Continue link
		if ( !empty( $back )) {
			echo( '<a href="'. $back .
			'?lang=' . $lang . '">' .
			T( "Continue" ) .'</a><br>
			' );
		}

		// show the sign out link
		echo( '<a href="' . MEM_APP_ROOT. 'mem_signout.php' .
		'?lang=' . $lang . '">' .
		T( "Sign out" ) . '</a><br>' );
	}

	// show update profile link
	echo( '<a href="' . MEM_APP_ROOT. 'mem_profile.php' .
	'?lang=' . $lang . '">' .
	T( "Update your profile" ) . '</a>.' );
}
else {								// here if not signed in yet

	// ---------------- HTML page generator (not signed in) -----------------

	$page->top( T( "Please sign in" ));
	if( !isset( $_POST['s_username'] )) {

		// preload the username from the incoming parameter, if any
	    // or else from the cookie, if any
	    $s_username = '';
		if( isset( $_GET['username'] )) {
			$s_username = $_GET['username'] ;
		}
		elseif ( isset( $_COOKIE[MEM_USR_COOKIE] )) {
	    	$s_username =$_COOKIE[MEM_USR_COOKIE];
		}
		if( !empty( $s_username )) {
			$s_username = hex2bin( substr( trim( $s_username ),
			0, 2 * MEM_NAME_MAXSIZE ));

			// for security, do not display invalid input...
			if ( ! $user->rulecheck_username($s_username) ) {
				$s_username = '';
			}
		}
	}

	echo( '<p>' .
	T( "Please sign in to access the members-only pages." ) .
	'<p/>' );

	$page->form( '?back=' . $back . '&lang=' . $lang  );
	echo(
	T( "Username:" ) . '<br>
		<input id="s_username" name="s_username" type="text"
	    size="' . MEM_NAME_MAXSIZE . '"
	    maxlength="' . MEM_NAME_MAXSIZE . '"
	    value="' . htmlentities( $s_username, ENT_COMPAT, 'UTF-8') . '" />
	    <br><br>' .
	T( "Password or passphrase:" ) . '<br>
		<input id="password" name="password" type="password"
	    size="' . MEM_PASSWORD_MAXSIZE . '"
	    maxlength="' . MEM_PASSWORD_MAXSIZE . '" />
	    <p>' .
		'<input id="rememberme" name="rememberme" value="rememberme"
	    type="checkbox" ' . (( '' != $s_username ) ? 'checked' : '') . '/>' .
	T( "Remember me on this computer" ) . '</p><p>' .
		'<input id="submit" name="submit" value=' .
	T( "Submit" ) . ' type="submit" />
	    </p>' );

	$page->form_bottom();
}

// if the signin check failed, display error message
if ( ! $signed_in && isset( $_POST['submit'] )) {
	echo( '<p><b>' . $user->error_msg . '</b></p>' );
}

$page->bottom();
?>
