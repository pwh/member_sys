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

/// Any script in the site that is intended to be seen only by signed-in
/// users invokes this script.
/// If the browser has not initiated a signin session, the script redirects the
/// browser to a signin page.

require_once( 'mem_config.php' );
require_once( 'mem_db.class.php' );	// MemDb wrapper for application database
$mdb = new MemDb;						// the database, significant global var
require_once( 'mem_translate.php' );	// T(), sets global $lang, uses $mdb
require_once( 'mem_user.class.php' );
require_once( 'mem_htmlpage.class.php' );

$user = new MemUser;				// current user, a significant global var

// verify that the user is signed in and has a current password;
// if not, redirect to the signin page.
if ( (! $user->is_signed_in()) || $user->userdata['i_change_pass'] ) {

	// note: http requires an absolute URI for Location header.
	// '?back' tells the signin page where to go next if signin succeeds.
	$protocol = MEM_REQUIRE_HTTPS ? 'https:' : 'http:';
	$host = $_SERVER['HTTP_HOST'];	// ['SERVER_NAME'] ?
	$back = $_SERVER['SCRIPT_NAME'];
	header( "Location: $protocol//$host" . MEM_APP_ROOT . 'mem_signin.php' .
			"?back=$protocol//$host$back" );
	$user->set_session_cookie();	// pass session to the new page...
	exit;
}

// set up to display the username of the signed-in user
$you_are = sprintf( T( "You are signed in as %s" ),
		$user->userdata['s_username'] );
if( $user->is_admin() )
	$you_are .= '&nbsp;(administrator)';

// here all is well - continue to display the originally-requested page...
