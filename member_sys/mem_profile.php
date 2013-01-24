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

/// Page to create or update a user profile in the application database.
///
///    1) Adding a new user (URL contains ?adduser, or no user signed in)
///
/// Anyone may use this to create a new entry in the users table. (Note that
/// only an administrator can mark an entry as belonging to an active
/// (i.e. dues-paying) member, via a different admin-only page.)
///
/// If the user is not signed in, or if the URL contains ?adduser,
/// this page displays empty entry fields,
/// the submit button displays "Register a new user", and
/// the page displays a link to the signin page.
///
/// If the signed-in user is an administrator and the URL contains ?adduser,
/// then we suppress the password entry fields and make a random password.
/// After successfully registering the user, we display a link
/// to page admin/mem_pass_reset.php labelled "Notify <username>"
/// with the new userid in URL parameter ?userid=nn.
///
///    2) Administrator updating another user (URL has ?userid=nn)
///
/// If the signed-in user is an administrator, and the URL has ?userid=nn
/// where nn is a value from users table column i_userid, then this page
/// initially displays the specified user's row from that table,
/// the password entry fields are suppressed, and
/// the submit button displays "Update <username>".
/// (Argument ?userid is ignored, however, if the value specifies the current
/// user or if ?adduser is specified.)
///
///    3) Updating the signed-in user
///
/// If a user is signed in and we're not in mode (1) or (2), this page
/// initially displays data from the current user's profile,
/// the submit button displays "Update <username>", and
/// the link to the signin page does not appear.
///
///    Optional profile data
///
/// If the signed-in user is an administrator, after successfully creating or
/// updating a user the page displays a link to mem_profile_xtra.php
/// with parameter ?username=<username> specifying that user.
///
/// If the signed-in user is an administrator, the page permits editing a
/// comment field attached to the profile.

set_include_path( dirname(__FILE__) . '/lib' );

require_once( 'mem_config.php' );
require_once( 'mem_db.class.php' );	// MemDb wrapper for application database
$mdb = new MemDb;
require_once( 'mem_translate.php' );	// T(), uses $mdb
require_once( 'mem_user.class.php' );		// MemGate
require_once( 'mem_htmlpage.class.php' );	// HtmlPage


// ================ initialization ======================
$status = true;						// true => all is well so far
$cur_user = new MemUser;			// gets the signed-in user, if any
$page = new HtmlPage;

$newuserdata = array();				// gets any profile data to be updated
$msg = '';							// text to display to the user

// check if user is signed in, and if so load $cur_user->userdata
$signed_in = $cur_user->is_signed_in();	// check for session, get userdata

// if ?adduser is in URL or no user is signed in, we're registering a new user
$adduser = isset( $_GET['adduser'] ) || (! $signed_in );

$admin_update = false;				// true => administrator updating other user

// if registering a user, we check that the form is submitted by a human
// (unless the current user is a signed-in administrator)
$check_human = ($adduser && ! $cur_user->is_admin());

class AntiRobot {					/// a tiny, very silly little captcha
	protected $numa = array();		// three random numbers
	public function init() {		// load with three random numbers 1-99
		$this->numa = array( mt_rand(1,99), mt_rand(1,99), mt_rand(1,99));
	}
	public function text() {		// return string representation of numbers
		return ( implode( ',', $this->numa ));
	}
	public function load( $str ) {	// store numbers from a string
		$this->numa = explode( ',', $str );
	}
	public function pass( $ans ) {  // return true if $ans is the largest number
		return ( $ans == max( $this->numa ));
	}
}
$antirobot = new AntiRobot;			// set of three random numbers 1-99
$antirobot_ans = '';			 	// user response (s/b largest of the three)

if ( $adduser ) {					// set up a new row for the user table
	$target = new MemUser;
	$target->userdata['s_firstname'] = '';
	$target->userdata['s_lastname'] = '';
	$target->userdata['s_username'] = '';
	$target->userdata['i_in_directory'] = 1;
	$target->userdata['s_email1'] = '';
	$target->userdata['s_civicnum'] = '';
	$target->userdata['i_roadid'] = '0';
	$target->userdata['i_administrator'] = '0';
	$target->userdata['s_comment'] = '';
}
elseif( $signed_in ) {				// an existing user is to be updated

	// if cur_user is an administrator, get any userid from the URL to
	// specify the user to update
	if( $signed_in && $cur_user->is_admin() && isset( $_GET['userid']) ) {
		$nn = $_GET['userid'];
		if ( (0 < $nn) && ($cur_user->userid() != $nn)) {
			$target = new MemUser();
			if( !$target->get_userdata_i( $nn )) {
				die( $target->error_msg . '<br>' . 'userid: ' . $nn );
			}
			$admin_update = true;	// administrator updating other user
		}
	}
	if( ! $admin_update ) {			// updating the current user
		$target = &$cur_user;
	}
}
else {								// must be signed in to update profile
	$page->top(
	T( "Update your profile" ) );
	echo(
	T( "You must sign in to change your profile." ) .
	'<br><a href="' . MEM_APP_ROOT. 'mem_signin.php?lang=' . $lang . '">' .
	T( "Sign in" ) . '</a>' );
	$page->bottom();
	exit();
}

// ================ form handling ======================
if ( ! isset( $_POST['submit'] )) {	// initialize: get user's data
	$s_lastname = $target->userdata['s_lastname'];
	$s_firstname = $target->userdata['s_firstname'];
	$s_username = $target->userdata['s_username'];
	$i_in_directory = $target->userdata['i_in_directory'];
	$s_email1 = $target->userdata['s_email1'];
	$s_civicnum = $target->userdata['s_civicnum'];
	$i_roadid = $target->userdata['i_roadid'];
	$i_administrator = $target->userdata['i_administrator'];
	$s_comment = $target->userdata['s_comment'];

	if( $check_human )				// initialize anti-robot test
		$antirobot->init();
}
else {		// get data from the form; trim the string entries

	$s_lastname = $page->trim_entry( 's_lastname', MEM_NAME_MAXSIZE );
	if ( $s_lastname != $target->userdata['s_lastname'] ) {
		$newuserdata['s_lastname'] = $s_lastname;
	}
	$s_firstname = $page->trim_entry( 's_firstname', MEM_NAME_MAXSIZE );
	if ( $s_firstname != $target->userdata['s_firstname'] ) {
		$newuserdata['s_firstname'] = $s_firstname;
	}

	$s_username = $page->trim_entry( 's_username', MEM_NAME_MAXSIZE );
	if ( $s_username != $target->userdata['s_username'] ) {
		$newuserdata['s_username'] = $s_username;
	}

	$i_in_directory = $page->get_bool( 'i_in_directory' );
	if( $i_in_directory != $target->userdata['i_in_directory'] ) {
		$newuserdata['i_in_directory'] = $i_in_directory;
	}

	// if an administrator is adding a user, assign a very temporary
	// inaccessible random password (we'll use its s_passhash later)
	if( $adduser && $cur_user->is_admin() ) {
		$randpw = md5( mt_rand() );
		$randpw[31] = 'B';			// per MemUser::rulecheck_password()
		$randpw[0] = 'l';
		$newuserdata['password'] = $randpw;
		$newuserdata['password2'] = $randpw;
	}
	else {
		// get password fields
		$password = $page->trim_entry( 'password', MEM_PASSWORD_MAXSIZE );
		if ( ! empty( $password ))
			$newuserdata['password'] = $password;
		$password2 = $page->trim_entry( 'password2', MEM_PASSWORD_MAXSIZE );
		if ( ! empty( $password2 ))
			$newuserdata['password2'] = $password2;
	}

	$s_email1 = $page->trim_entry( 's_email1', MEM_NAME_MAXSIZE );
	if ( $s_email1 != $target->userdata['s_email1'] )
		$newuserdata['s_email1'] = $s_email1;

	$s_civicnum = $page->trim_entry( 's_civicnum', MEM_NAME_MAXSIZE );
	if ( $s_civicnum != $target->userdata['s_civicnum'] )
		$newuserdata['s_civicnum'] = $s_civicnum;

	$i_roadid = isset( $_POST['i_roadid'] )? ( (int)$_POST['i_roadid'] ) : 0;
	if ( $i_roadid != $target->userdata['i_roadid'] )
		$newuserdata['i_roadid'] = $i_roadid;

	if( $admin_update ) {
		$i_administrator = $page->get_bool( 'i_administrator' );
		if ( $i_administrator != $target->userdata['i_administrator'] )
			$newuserdata['i_administrator'] = $i_administrator;
	}

	if( $cur_user->is_admin() ) {
		$s_comment = $page->trim_entry( 's_comment', MEM_NAME_MAXSIZE );
		if ( $s_comment != $target->userdata['s_comment'] )
			$newuserdata['s_comment'] = $s_comment;
	}

	if( $check_human ) {	// get anti-robot question & answer; test it

		$antirobot->load( $page->trim_entry( 'ar', 2+1+2+1+2 ) );
		$antirobot_ans = 0 + $page->trim_entry( 'antirobot_ans', 2 );
		if ( ! $antirobot->pass( $antirobot_ans ) ) {
			$msg = T( "To register, you must enter the largest number." );
			$status = false;
		}
	}
	if ( $status && count( $newuserdata ) == 0 ) {	// nothing to update?
		$msg = T( "The profile did not change." );
		$status = false;
	}
	if( $status ) {							// validate data, update database

		// initialize any new profile to the current user's language preference
		if ( $adduser )
			$newuserdata['s_lang'] = $lang;

		$status = $target->update_user( $newuserdata, $cur_user );
		if ( !$status ) {
			$msg = $target->error_msg;
		}
		else {								// success...
			if( $check_human )				// reinitialize anti-robot test
			$antirobot->init();
		}
	}
}



// ---------------- HTML page generator: display profile page -----------------

if ( $adduser ) {
	$title = T( "Register a new user" );	// set page title
	$submit_label = $title;					// set label for submit button
}
else {
	$title = sprintf(
	T( "Add or update profile items for %s." ),
	'<b>' .	$target->userdata['s_username'] . '</b>' );
	$submit_label = T( "Update") . '&nbsp;' . $target->userdata['s_username'];
}

$page->top( $title );

echo( '<center><font size=4>' . $title . '</font></center>' );
if( $signed_in && $cur_user->is_admin() ) {
	echo( '<center>' );
	printf( 'You are signed in as %s (administrator)',
	$cur_user->userdata['s_username'] );
	echo( '</center>' );
}

// explain the rules for entry text
echo( '<p>' . $target->rulecheck_rules() . '</p>' );

$action = '?lang=' . $lang;
if( $admin_update )
	$action .= '&userid=' . $target->userdata['i_userid'];
$page->form( $action );	// display the form


echo( '<table  cellpadding="4" width="100%" cellspacing="3">
	' );

if( $check_human ) {				// hide antirobot test question in the form
	echo( " <input name='ar' type='hidden' value='" . $antirobot->text() . "'/>
	" );
}

$page->infield_text(
	T( "Last name:" ),
		's_lastname', MEM_NAME_MAXSIZE, $s_lastname );
$page->infield_text(
	T( "First name:" ),
		's_firstname', MEM_NAME_MAXSIZE, $s_firstname );

$page->infield_bool(
	T( "Include me in the member directory:"),
		'i_in_directory', $i_in_directory );

echo( '<tr><td><br>
	</td></tr>' );

$page->infield_text(
	T( "Username:" ),
		's_username', MEM_NAME_MAXSIZE, $s_username );


// allow password entry unless an administrator is adding or updating a user
if( !(( $cur_user->is_admin() && $adduser ) || $admin_update)) {
	echo( '
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
}

// new users require an email address, for notification about setting a password
$page->infield_text(
	T( "Email address:" ),
		's_email1', MEM_NAME_MAXSIZE, $s_email1 );

echo( '
		<tr><td><br>' .
	T( "Cottage address" ) . '
		</td></tr>' );

$page->infield_text(
	T( "Civic number:"),
		's_civicnum', MEM_NAME_MAXSIZE, $s_civicnum );

// dropdown list of road names where the value of a selection
// is the road's id number
$roadlist = $mdb->mdb_get_list( 'roads', 'i_roadid', 's_name',
'', '', 's_name' );
if( ! $roadlist )
	$msg .=  '<br>' . $mdb->mdb_error();
else {
	$page->infield_select(
	T( "Cottage road:" ),
	 'i_roadid', $roadlist, $i_roadid );
}

// an administrator is provided with a means to control any
// **other** user's administrator privilege...
if( $admin_update ) {
	echo( '
	<tr><td><br>Administrator</td></tr>' );
	$page->infield_bool(
			'This user is a membership administrator:',
			'i_administrator', $i_administrator );
}

if( $cur_user->is_admin() ) {
	$page->infield_text(
	'Comments:', 's_comment', MEM_NAME_MAXSIZE, $s_comment );
}

// non-admin users provide evidence of being human here
if( $check_human ) {
	echo( '
	<tr><td><br></td></tr>' );
	$page->infield_text(
	T( "Please enter the largest of these numbers:" ) .
	' (' . $antirobot->text() . '): ',
	'antirobot_ans', MEM_NAME_MAXSIZE, $antirobot_ans );
}

echo( '
		</table>
		</p>
		' );

echo( '<p><input id="submit" name="submit" value=' . $submit_label .
		' type="submit" />' );

echo( '&nbsp;&nbsp;&nbsp;' );

echo( '<button type="reset" >' .
		T( "Reset" ) . '</button>' );

$page->form_bottom();

if( $status && isset( $_POST['submit'] )) {	// success!
	echo( '<p>' );
	if( $adduser ) {
		printf(
		T( "User <i>%s</i> is registered successfully!" ) ,
		htmlentities( $s_username, ENT_COMPAT, 'UTF-8' ));
	}
	else {
		echo( T( "The update succeeded." ));
	}
	echo( '</p>
	' );
	if( $adduser && !$signed_in ) {
		// display a link to the signin page
		echo( '<p>'.
		T( "You may register another name, or select continue " .
		"to sign in and add contact information to the profile:" ) .
		'<br>
		<a href="' . MEM_APP_ROOT . 'mem_signin.php?lang=' . $lang .
		'&username=' . bin2hex( $s_username ) .
		'&back=' . MEM_APP_ROOT . 'mem_profile_xtra.php' .
		'">' .
		T( "Continue") . '</a></p>' );
	}
	else {

		// if an admin added a user, display a link to the password reset page
		if ( $adduser && $cur_user->is_admin() ) {
			echo( '
			<p>You may select Notify to send a password to the new user:
			<br><a href="' . MEM_APP_ROOT . 'admin/mem_pass_reset.php' .
			'?lang=' . $lang .
			'&userid=' . $target->userdata['i_userid'] . '">' .
			'Notify&nbsp;' . $target->userdata['s_username'] .'</a></p>' );
		}

		// display link to the page for updating optional profile data
		echo( '
		<p>' .
		T("You may select continue to add contact information to the profile:") .
		'<br><a href="' . MEM_APP_ROOT . 'mem_profile_xtra.php' .
		'?lang=' . $lang .
		'&userid=' . $target->userdata['i_userid'] . '">' .
		T( "Continue") . '</a></p>' );
	}
}

if ( !empty( $msg )) {
	// tell the user the bad news
	echo( '<p><b>' . $msg . '</b></p>' );
}

if ( MEM_DEBUG ) {
	echo( 'debug: $newuserdata has ' . print_r( $newuserdata, true ) . '<br>');
	echo( 'debug: $adduser has ' . ($adduser?"true":"false") . '<br>');
	echo( 'debug: $signed_in has ' . ($signed_in?"true":"false") . '<br>');
	echo( 'debug: $admin_update  has ' . ($admin_update?"true":"false") . '<br>');
}

$page->bottom();
?>
