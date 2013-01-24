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

/// This page displays membership status information. The user must be
/// signed in (@see mem_user_only.php).
///
/// If the URL has ?userid= and the signed-in user is an administrator
/// this page displays information about the specified target user.
///
/// Otherwise, the page displays information about the signed-in user.

set_include_path( dirname(__FILE__) . '/lib' );

require_once( 'mem_config.php' );
require_once( 'mem_user_only.php' );	// ensure signin, set $mdb, $user, $lang
if( !function_exists('hex2bin') )
	require_once( 'hex2bin.php' );

// if signed-in user ($user) is an administrator, get any userid from the URL
if( $user->is_admin() && isset( $_GET['userid'] )) {
	$nn = $_GET['userid'];
	if ( $nn <= 0 ) {
		die( 'The userid is invalid.<br>userid: ' . htmlentities( $nn ));
	}
	elseif ( $user->userid() == $nn ) {
		$target = &$user;			// URL specified the signed-in user
	}
	else {
		$target = new MemUser();
		if( !$target->get_userdata_i( $nn )) {
			die( $target->error_msg . '<br>userid: ' . htmlentities( $nn ));
		}
	}
}
else {								// show info for the signed-in user
	$target = &$user;
}



// ---------------- HTML page generator: display status ------

$page = new HtmlPage;
$page->top( T( "User status" ));

$title = sprintf(
		T( "Status for %s" ),
		'<b>' . $target->userdata['s_firstname'] . '&nbsp;' .
		$target->userdata['s_lastname'] .
		'&nbsp;(<b>' . $target->userdata['s_username'] . '</b>)' );
echo( '<center><font size=4>' . $title . '</font></center>' );
if( $user->is_admin() ) {
	echo( '<center>' );
	printf( 'You are signed in as %s (administrator)',
			$user->userdata['s_username'] );
	echo( '</center>' );
}

echo( '<p>' );
if ( $target->userdata['i_change_pass'] ) {
	echo( T("The password must be changed when the user signs in." ));
} else {
	echo( T("The password is valid." ));
}
echo( '
	</p>' );

echo( '<p>' );
if ( $target->userdata['i_administrator'] ) {
	echo( T("The user is a membership administrator." ));
} else {
	echo( T("The user has ordinary privileges." ));
}
echo( '
	</p>' );

echo( '<p>');
printf(
	T("The user's profile was created %s." ),
	$target->userdata['s_date_joined'] );
echo( '
	</p>' );

echo( '<p>' );
if( 0 == $target->userdata['i_membership_year'] ) {
	echo( T( "No dues payment has been recorded." ));
} else {
	printf(
	T("The most recent dues payment was recorded %s." ) . '<br>',
	$target->userdata['s_date_of_payment'] );
	printf(
	T("Membership is paid up through %d." ),
	$target->userdata['i_membership_year'] );
}
if( ! $target->members_only_ok() ) {
	echo( '<br>
	' . T( "Access to members-only pages is not currently allowed." ) );
}
echo( '
	</p>' );

$page->bottom();
?>
