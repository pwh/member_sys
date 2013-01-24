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
/// End the session and display the sign-out page
///

set_include_path( dirname(__FILE__) . '/lib' );

require_once( 'mem_config.php' );
require_once( 'mem_db.class.php' );	// MemDb wrapper for application database
$mdb = new MemDb;
require_once( 'mem_translate.php' );	// T(), uses $mdb
require_once( 'mem_user.class.php' );
require_once( 'mem_htmlpage.class.php' );

$user = new MemUser;
$page = new HtmlPage;

if ( ! $user->is_signed_in()) {
	$msg = T( "You are not signed in." );
}
else {
	$username = $user->userdata['s_username'];
	if ( ! $user->signout() ) {
		$msg = '<b>'. $user->error_msg . '</b>';
	}
	else {
		$msg = sprintf( T( "The session for %s has ended." ),
		$username );
	}
}

// ---------------- HTML page generator -----------------

$page->top( T( "Sign out" ));

echo( '<p>' . $msg . '
</p>' );
echo( '<p><a href="' . MEM_APP_ROOT. 'mem_signin.php' .
'?lang=' . $lang . '">' .
T( "Return to the signin page" ) . '</a>
</p>' );

$page->bottom();
?>
