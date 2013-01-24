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

$description = <<< HEREDOC
This admin_only page lets the administrator select a user to update.
HEREDOC;

/// If a user is selected and the URL has ?continue= specifying a URL,
/// we append ?userid= and the selected i_userid value to the URL and
/// redirect to the specified page.
///
/// If a user is selected and the URL has no ?continue, we display
/// links to pages that process userdata given the ?userid= value.

$admin_only_page = true;			// only users having i_administrator allowed
require ( '../lib/mem_only.php' );	// ... who are also members in good standing

set_include_path( dirname(__FILE__) . '/../lib' );

$page = new HtmlPage;

$msg = '';							// output message for user
$i_userid = 0;						// no user selected yet

if( ! ( isset( $_POST['submit'] ) || isset( $_POST['search'] ))) {

	// initial form display values (no button clicked)
	$pattern = '';
}
else {

	// load posted pattern string if any
	$pattern = ( isset( $_POST['pattern'] )? $_POST['pattern'] : '' );
	if ( empty( $pattern )) {		// empty pattern for 'Search'
		$msg = 'Please enter all or part of a user\'s last name.';
		$i_userid = 0;
	}
	elseif( isset( $_POST['submit'] ) ) { // on Submit, get user id
		$i_userid = isset( $_POST['i_userid' ]) ? ((int)$_POST['i_userid']) : 0;
		if( 0 == $i_userid ) {
			$msg = 'Please select a user from the dropdown list.';
		}
		elseif( isset( $_GET['continue'] )) {	// go process the selected user

			// note: http requires an absolute URI for Location header.
			$protocol = MEM_REQUIRE_HTTPS ? 'https:' : 'http:';
			$host = $_SERVER['HTTP_HOST'];	// ['SERVER_NAME'] ?
			header( "Location: $protocol//$host" . MEM_APP_ROOT .
					 htmlspecialchars( $_GET['continue'] ) .
					'?userid=' . $i_userid );
			$user->set_session_cookie();	// pass session to the new page...
			exit;
		}
	}
}


// ---------------- HTML page generator: display form to select a user -------

$title = 'User maintenance';
$page->top( $title );

echo( '<center><font size=4>' . $title . '</font></center>
' );
echo( '<center>' . $you_are . '</center>
' );
echo( '<p>' . $description . '
</p>' );
echo( '<p>Enter text in the first field to select lastnames of users to
show in the dropdown list.
The search is not case-sensitive (e.g. <i>mcd</i> matches <i>The McDonalds</i>).
Special value \'<i>_</i>\' is a wildcard that matches one character
(e.g. <i>h_y</i>  matches <i>Hays</i>).
Special value \'<i>%</i>\' is a multi-character wildcard
(e.g. <i>H%s</i>  matches <i>Haversack</i> and <i>Hays</i>).
Click the search button, then select a user from the dropdown list.
</p>' );

$page->form();
echo( '<table  cellpadding="4" width="100%" cellspacing="3">
 ' );

// the first row of this form's table has a text entry field and a Search button
echo( '
	<tr>' .
	'<td bgcolor="' . MEM_FORM_COLOR . '">
	<input id="pattern" name="pattern" type="text" ' .
	'size="' . MEM_NAME_MAXSIZE . '" maxlength="' . MEM_NAME_MAXSIZE . '"
	value="' . htmlentities( $pattern, ENT_COMPAT, 'UTF-8' ) . '" />
	<input id="search" name="search" value="Search"" type="submit" />' .
	'</td></tr>
	');

// The second row displays a dropdown list with a subset of the full names
// where the value of a selection is the user's id number.
// If we have a pattern,
// look up matching items and populate the <select> list with them.
if ( !empty( $pattern )) {
	$display = 'CONCAT(s_lastname,\', \',s_firstname,\' (\',s_username,\')\')';
	$userlist = $mdb->mdb_get_list( 'users',
		'i_userid',					// the <select> will post a userid
		$display,
		's_lastname',				// match last names
		$pattern,					// select this subset of the lastnames
		'value_column' );			// sort the list by full name
	if( false === $userlist ) {
		$msg = $mdb->mdb_error();	// oops - database failed
		$userlist = array();		// leave select list empty
	}
	elseif( empty( $userlist )) {
		$msg = 'No matching users were found; please try again.';
	}
	elseif( 1 == count( $userlist )) {	// only one user matched...
		reset( $userlist );
		$i_userid = key( $userlist );	// ... select it in the dropdown list
	}
}
else {
	$userlist = array();			// leave select list empty
}

// Before you select an entry, the <select> field shows the text
// we'll put in $select0.
// HTML offers little to control width of <select>, so pad with blanks;
// welcome to the stone age...
if( empty( $userlist )) {
	$select0 =
	'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
	'<i>Search with all or part of the user\'s real name above</i>' .
	'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
} else {
	$select0 =
	'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
	'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
	'<i>Now select a name and click Submit&gt;</i>' .
	'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' .
	'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp&nbsp;&nbsp;';
}
echo( '<tr>' .
	'<td bgcolor="' . MEM_FORM_COLOR . '"><select id="i_userid" name="i_userid">
	<option value="0"' . (( 0 == $i_userid )? ' selected' : '' ) . '>' .
	$select0 .
	'</option>
	' );
foreach( $userlist as $key => $text ) {
	if( 0 != $key ) {
		printf( '<option value="%d"%s>%s</option>
		', $key,
		(( $key == $i_userid )? ' selected' : '' ),
		$text );
	}
}
echo( '</select><input id="submit" name="submit"
	value="Submit&nbsp;" type="submit" /></td></tr>' );

echo( '
	</table>
	' );

$page->form_bottom();


if (! empty( $msg )) {
	echo( '<p><b>' . $msg . '</b></p>' );
}

// if ?continue not present and a userid is selected, display appropriate links
if( ( ! isset( $_GET['continue'] )) && ( 0 < $i_userid )) {

	$who = '?userid=' . $i_userid ;	// userid URL parameter

	echo( '<p><b>Select a link here to access the record for ' .
	$userlist[$i_userid] . '</b>:
	<br>' );
	echo( '<a href="' . MEM_APP_ROOT . 'mem_status.php' . $who .
	'">Show membership status</a>
	<br>' );
	echo( '<a href="' . MEM_APP_ROOT . 'admin/mem_dues.php' . $who .
	'">Log dues payment</a>
	<br>' );
	echo( '<a href="' . MEM_APP_ROOT . 'admin/pass_reset.php' . $who .
	'">Reset the password</a>
	<br>' );
	echo( '<a href="' . MEM_APP_ROOT . 'mem_profile.php' . $who .
	'">Update main profile fields</a>
	<br>' );
	echo( '<a href="' . MEM_APP_ROOT . 'mem_profile_xtra.php' . $who .
	'">Update optional contact info</a>
	<br>' );
}

$page->bottom();
?>
