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
This admin-only page lets you record membership status. Select the
latest year in which the user may access the members-only pages of the website.
(Note: selecting blank indicates that the user is no longer a member.)
HEREDOC;

/// The affected user must be specified by parameter $userid=nn appended to
/// the URL.

$admin_only_page = true;			// only users having i_administrator allowed
require ( '../lib/mem_only.php' );	// ... who are also members in good standing

$msg = '';							// gets any result message to display

// this peculiar associative array gets the years we'll display for selection
// (the select control provides an initial empty selection whose value is 0)
$select_years = array();
$yyyy = date( 'Y' );				// current year, e.g. '2000'
for( $ii = 0; $ii < 10; $ii++ ) {
	$select_years[$yyyy] = $yyyy;	// [2000]=>2000, [2001]=>2001, ...
	++$yyyy;
}

// get userid from the URL to specify the user to update
$nn = isset( $_GET['userid'] ) ? $_GET['userid'] : 0;
if ( $nn <= 0 ) {
	die( 'The userid is invalid.<br>userid: ' . htmlentities( $nn ));
}
elseif ( $user->userid() == $nn ) {	// target is the signed-in user
	$target = &$user;
}
else {
	$target = new MemUser();
	if( !$target->get_userdata_i( $nn )) {	// load target's userdata
		die( $target->error_msg . '<br>userid: ' . htmlentities( $nn ));
	}
}

if ( ! isset( $_POST['submit'] )) {

	// display current year as the default value if membership expired;
	// otherwise use current membership year +1
	$year = date( 'Y' );			// current year, yyyy
	$i_membership_year = $target->userdata['i_membership_year'];
	if ( $i_membership_year >= $year )
		$year = $i_membership_year + 1 ;
}
else {								// user clicked submit button

	if( ! isset( $_POST['year'] )) {
		$msg = 'Internal error: select failed.';
	}
	else {
		$year = $_POST['year'];

		// try to update database
		if ( !$target->set_membership_year( $year, $user )) {
			$msg = $target->error_msg . '<br>' .
					'The profile did not change.';
		} else {
			$msg = 'The update succeeded.' ;
		}
	}
}



// ---------------- HTML page generator: display optional profile page ------

$page = new HtmlPage;
$page->top( 'Record membership status');

$title = 'Record membership status for <b>' .
	$target->userdata['s_username'] . '</b>';
echo( '<center><font size=4>' . $title . '</font></center>' );

echo( '<center>' );
printf( 'You are signed in as %s (administrator)',
		$user->userdata['s_username'] );
echo( '</center>' );

echo( '<p>' . $description . '
	</p>' );

echo( '<p>Status for <b>' .
	$target->userdata['s_firstname'] . '&nbsp;' .
	$target->userdata['s_lastname'] . '</b>&nbsp;(<b>' .
	$target->userdata['s_username'] . '</b>):<br>
	' );
echo( '<blockquote>
' );
if ( 0 == $target->userdata['s_date_of_payment'] ) {
	echo( 'There is no membership change on record.<br>' );
} else {
	printf( 'Membership was last changed %s.<br>',
	$target->userdata['s_date_of_payment'] );
}
if( 0 == $target->userdata['i_membership_year'] ) {
	echo( 'The record indicates that ' . $target->userdata['s_username'] .
	' is not a member.' );
} else {
	if( $target->userdata['i_membership_year'] < date( 'Y' ))
		$fmt = 'Membership expired after %d.';
	else
		$fmt = 'Membership is paid up through %d.';
	printf($fmt, $target->userdata['i_membership_year'] );
}
echo( '
	</blockquote></p>' );

$page->form();						// start the form

echo( '<p><table  cellpadding="4" width="100%" cellspacing="3">
		' );

// generate a table row with two colunms. Left column has the label...
echo( '<tr><td bgcolor="' . MEM_FORM_COLOR . '"><div align="right">' .
	'Highest year of membership:</div></td>' );

// 			... and right column has a select list and a submit pushbutton
echo( '<td bgcolor="' . MEM_FORM_COLOR . '">
	<select id="year" name="year">
		<option value="0"></option>
		');
	foreach( $select_years as $key => $txt ) {	// select $year in dropdown list
		printf( '<option value="%d"%s>%s</option>
		', $key, (( $key == $year )? ' selected' : '' ), $txt );
	}
	echo( '</select>&nbsp;<input id="submit" name="submit" ' .
		'value="Record dues payment" type="submit" />' .
	'</td></tr>
	' );
echo( '</table>
	</p>' );

echo( '<button type="reset" >' .
		'Reset' . '</button>' );

$page->form_bottom();

if ( ! empty( $msg ) ) {				// if we have a  message say it now.
	echo( '<p><b>' . $msg . '</b></p>' );
}

$page->bottom();

?>

