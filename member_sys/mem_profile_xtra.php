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

///
/// Display a form to permit changing optional fields of a profile.
///
/// If the URL has ?userid= and the signed-in user is an administrator
/// this page displays and updates userdata for the specified target user.
///
/// Otherwise, the page displays and updates userdata for the signed-in user.
///

set_include_path( dirname(__FILE__) . '/lib' );

require_once( 'mem_config.php' );
require_once( 'mem_user_only.php' );	// ensure signin, set $mdb, $user, $lang
require_once( 'mem_htmlpage.class.php' );
if( !function_exists('hex2bin') )
	require_once( 'hex2bin.php' );

$page = new HtmlPage;

$newuserdata = array();				// gets any profile data to be updated
$field = array(						// entry fields on this page; see db schema
's_mail_address', 's_mail_city', 's_mail_provincestate',
's_mail_postalcode', 's_mail_country', 'i_snailmail', 's_lang',
's_cott_phone', /* *** 'i_cott_phone_private', *** */
's_phone1', 's_phone2', /* *** 'i_phone_private', *** */
's_email1', 's_email2', /* *** 'i_email_private' *** */
);
$msg = '';							// text to display to the user

$admin_update = false;				// true => administrator updating other user

// if signed-in user ($user) is an administrator, get any userid from the URL
// to specify the user to update
if( $user->is_admin() && isset( $_GET['userid'] )) {
	$nn = $_GET['userid'];
	if ( $nn <= 0 ) {
		die( 'The userid is invalid.<br>userid: ' . htmlentities( $nn ));
	}
	elseif ( $user->userid() != $nn ) {	// target is not the signed-in user
		$target = new MemUser();
		if( !$target->get_userdata_i( $nn )) {	// load target's userdata
			die( $target->error_msg . '<br>userid: ' . htmlentities( $nn ));
		}
		$admin_update = true;
	}
}
if( ! $admin_update ) {			// updating the currently signed-in user
	$target = &$user;
}


if ( ! isset( $_POST['submit'] )) {

	// load initial values for the entry fields from the database
	foreach ( $field as $s ) {
		$$s = $target->userdata[$s];
	}
	unset( $s );
}
else {								// user clicked submit button

	// fetch entries from the form; trim the strings;
	// load $newuserdata[] with any items that the user has changed
	// (compare each field entry with the initial value from the database)
	foreach ( $field as $s ) {
		if ( 's' == $s[0] ) {		// get a string
			$$s = $page->trim_entry( $s, MEM_NAME_MAXSIZE );
		}
		elseif ( 'i' == $s[0] ) {	// get (boolean) integer
			$$s = $page->get_bool( $s );
		}

		// set language preference to 'en' (the column default) if unselected
		if( ( 's_lang' == $s ) && empty( $s_lang )) {
			$s_lang = 'en';
		}

		if ( $$s != $target->userdata[$s] ) {
			$newuserdata[$s] = $$s;
		}
	}
	unset( $s );

	if ( count( $newuserdata ) == 0 ) {	// nothing to update?
		$msg = T( "The profile did not change." );
	}
	else if ( !($target->update_user( $newuserdata, $user ))) {	// update!
		$msg = $target->error_msg . '<br>' .
		 T( "The profile did not change." );
	} else {
		$msg = T( "The update succeeded." );
	}
}



// ---------------- HTML page generator: display optional profile page ------

$page->top( T( "Profile update" ));

$title = sprintf(
	T( "Add or update optional contact information for %s." ),
	'<b>' .	$target->userdata['s_username'] . '</b>' );
echo( '<center><font size=4>' . $title . '</font></center>' );
if( $user->is_admin() ) {
	echo( '<center>' );
	printf( 'You are signed in as %s (administrator)',
	$user->userdata['s_username'] );
	echo( '</center>' );
}

$page->form();						// display the form

echo( '<table  cellpadding="4" width="100%" cellspacing="3">
	' );

echo( '<tr><td><br>' .
	T( "Mailing address" ) . '
		</td></tr>' );

$page->infield_text(
	T( "Number and street or post box:"),
		's_mail_address', MEM_NAME_MAXSIZE, $s_mail_address );

$page->infield_text(
	T( "City:"),
		's_mail_city', MEM_NAME_MAXSIZE, $s_mail_city );

$page->infield_text(
	T( "Province or state:"),
		's_mail_provincestate', MEM_NAME_MAXSIZE, $s_mail_provincestate );

$page->infield_text(
	T( "Postal code or zip code:"),
		's_mail_postalcode', MEM_NAME_MAXSIZE, $s_mail_postalcode );

$page->infield_text(
	T( "Country (if not Canada):"),
		's_mail_country', MEM_NAME_MAXSIZE, $s_mail_country );

echo( '<tr><td><br>' .
		T( "Mailing options" ) . '
		</td></tr>' );

$page->infield_bool(
	T( "Communication by post (dues increase $10):" ),
		'i_snailmail', $i_snailmail );

$page->infield_select(
	T( "Preferred language for correspondence:" ),
	's_lang', array('en'=>'English','fr'=>'français'), $s_lang );

echo( '
		<tr><td><br>' .
	T( "Telephone" ) . '
		</td></tr>' );

$page->infield_text(
	T( "Cottage phone number:" ),
		's_cott_phone', MEM_NAME_MAXSIZE, $s_cott_phone );

/* *** (future use)
$page->infield_bool(
	T( "Keep my cottage phone number private:" ),
		'i_cott_phone_private', $i_cott_phone_private );
 *** */

$page->infield_text(
	T( "Phone number:" ),
		's_phone1', MEM_NAME_MAXSIZE, $s_phone1 );

$page->infield_text(
	T( "Alternate phone number:" ),
		's_phone2', MEM_NAME_MAXSIZE, $s_phone2 );

/* *** (future use)
$page->infield_bool(
	T( "Keep these phone number(s) private:" ),
		'i_phone_private', $i_phone_private );
 *** */
echo( '
		<tr><td><br>' .
	T( "Email" ) . '
		</td></tr>' );

$page->infield_text(
	T( "Email address:" ),
		's_email1', MEM_NAME_MAXSIZE, $s_email1 );

$page->infield_text(
	T( "Alternate email address:" ),
		's_email2', MEM_NAME_MAXSIZE, $s_email2 );

/* *** (future use)
$page->infield_bool(
	T( "Keep my email address(es) private:" ),
		'i_email_private', $i_email_private );
 *** */
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
