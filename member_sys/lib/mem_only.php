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

/// Any web page in the site that is intended to be seen only by members must be
/// in a file named with suffix .php and should invoke this script at the start
/// of its first line (with no intervening whitepace).
/// If the browser has not initiated a signin session, the script redirects the
/// browser to a signin page.
/// If the user has signed in, the script checks whether the user has paid
/// membership dues, and if not helps the user to contact us.
///
/// In addition, any page that defines variable $admin_only_page requires the
/// current user to have the flag i_administrator set to 1 in the user's row
/// in the users table.
///
/// @note: a member who is not signed in may still see member-only pages if
/// those pages remain in the browser cache.

require_once( 'mem_config.php' );
require_once( 'mem_user_only.php' );	// ensure signin, set $mdb, $user, $lang
require_once( 'mem_htmlpage.class.php' );

// verify that the signed-in user is currently in good standing,
// e.g. has membership dues paid up. If not, post a notice requesting payment.
if ( ! ($user->members_only_ok()) )  {

	$page = new HtmlPage;
	$page->top( T( "Access Denied" ) );

	echo(
	'<p><b>' .
	T( "Unfortunately, our information seems to indicate that " .
	"your membership has expired." ) .
	'</b></p><p>' .
	T( "If your dues have been paid, or for further information, " .
	"please contact" ) . '&nbsp;' );

	// This HEREDOC contains an e-mail message that the user can send...
    echo <<<HEREDOC
<a href="mailto:membership@My_Org.net,?subject=expired membership
&body=The My_Org website indicates that my membership for&nbsp;
{$user->userdata['s_firstname']}&nbsp;{$user->userdata['s_lastname']}&nbsp;
(user {$user->userdata['s_username']})&nbsp;has expired.">
HEREDOC;
	echo( T( "membership" ) .		// ... when this link appears in the browser
	'</a>.</p>' );
 	$page->bottom();
	exit;
}

// check if this page requires administrator privilege
if( isset( $admin_only_page ) && !( $user->is_admin()) ) {

	$page = new HtmlPage;
	$page->top( "Access Denied" );
	echo( '<p><b>Sorry, you must signin as an administrator to use this.</b></p>' );

	$page->bottom();
	exit;
}

// here all is well - continue to display the originally-requested page...
