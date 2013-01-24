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
/// Miscellany for an html page.
///
class HtmlPage {

	/// Echo the standard top of our html pages, with the specified page title.
	/// Pragma and Expires headers attempt to stop moronic Internet Explorer
	/// from caching stale dynamic content - see Microsoft KB234067.
	/// @param string $title for the browser to display
	public function top( $title ) {
	echo <<<HEREDOC
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<META HTTP-EQUIV="Pragma" CONTENT="no-cache">
<META HTTP-EQUIV="Expires" CONTENT="-1">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex,nofollow" />
<title>$title</title>
</head>
<body>
HEREDOC;
	}

	/// Output the boilerplate introduction for a form
	/// @param string $action alternative action for submit; the default action
	/// uses the calling script to process the form.
	public function form( $action='') {

		// iehack forces utf-8 from some browsers; see stackoverflow.com #153527
		echo( '
		<p>
		<form action="' . $action . '" method="POST" accept-charset="utf-8">
		<input name="iehack" type="hidden" value="&#9760;" />
		' );
	}

	/// Output the boilerplate html to end a form
	public function form_bottom() {

		echo( '
		</form></p>
		' );
	}

	/// Get a boolean value posted from a form.
	/// Unset element, false, element with 0 or non-numeric text values yield 0.
	/// Values true or 1 or '1' or numeric text values yield 1.
	/// @return 1 if member $field of array $_POST has a bool true value, else 0
	public function get_bool( $field ) {
		return ( isset( $_POST[$field] ) ? ( 0 != ( 0 + $_POST[$field] )) : 0 );
	}

	/// Output a row of a table to let the user input a boolean value by
	/// selecting one of a pair of radio buttons labelled Yes and No. If the
	/// page was displayed as a result of ts submit request, the initial state
	/// is the previous state of the field; otherwise the state is provided by
	/// parameter $initial.
	/// @param string $label localized label string for left side of the row
	/// @param string $field_name the field's internal name
	/// $param boolean $initial initial input state if $field is not in $_POST[]
	/// @return nothing
	public function infield_bool( $label, $field_name, $initial = false ) {

		$yes_checked = $initial ? 'checked' : '';
		$no_checked = $initial ? '' : 'checked';

		echo( '
		<tr><td bgcolor="' . MEM_FORM_COLOR . '"><div align="right">' .
		 $label . '</div></td>
		<td bgcolor="' . MEM_FORM_COLOR . '">
			<table cellspacing="5"><tr>
				<td><input name="' . $field_name . '" type="radio" value="1" ' .
				$yes_checked . '>' .
				T( "yes" ) . '</td>
				<td><input name="' . $field_name . '" type="radio" value="0" ' .
				$no_checked . '>' .
				T( "no" ) . '</td>
			</tr></table></td>
		</tr>
		');
	}

	/// Output a row of a table to let the user seleect from a list. The initial
	/// state is  established by the key provided via parameter $initial.
	/// The result of selecting an entry is the key of the selection.
	/// An option with key 0 is always present before the supplied list.
	/// @param string $label localized label string for left side of the row
	/// @param string $field_name the field's internal name
	/// @param array $list array of key=>value pairs where each key is used when
	/// initializing or querying the field, and the field displays the
	/// corresponding value. Key 0 is forbidden as it is a reserved value.
	/// $param int $initial key of the selection to display
	/// param int $width if nonzero specifies the field width
	/// @return nothing
	public function infield_select( $label, $field_name, $list,
			 $initial = 0, $width = 0 ) {

		$filler = '';					// content of empty-looking selection...
		if ( 0 != $width ) {			// load filler with _ _ _ _ _ _
			// official html doesn't seem to let us specify field width; try
			for( ($ii = $width/2); ($ii >= 0); $ii-- ) $filler .= '_ ';
		}
		echo( '<tr><td bgcolor="' . MEM_FORM_COLOR . '"><div align="right">' .
			$label . '</div></td>
			<td bgcolor="' . MEM_FORM_COLOR . '"><select id="' . $field_name .
			'" name="' . $field_name . '">
			<option value="0"' . (( 0 === $initial )? ' selected' : '' ) . '>' .
			$filler . '</option>
			' );
		foreach( $list as $key => $text ) {
			if( 0 !== $key ) {
				printf( '<option value="%s"%s>%s</option>
				', $key,
				(( $key == $initial )? ' selected' : '' ),
				$text );
			}
		}
		echo( '</select></td></tr>' );
	}

	/// Output a row of a table to let the user input text.
	/// The initial content to display is provided by parameter $initial.
	/// @param string $label localized label string for left side of the row
	/// @param string $field_name the field's internal name
	/// @param int $max_size width of the field to display the value
	/// $param string $initial initial text value to display
	/// @return nothing
	public function infield_text( $label, $field_name, $max_size,
	$initial = '' ) {
		echo( '
		<tr><td bgcolor="' . MEM_FORM_COLOR . '"><div align="right">' .
		$label . '</div></td>
		<td bgcolor="' . MEM_FORM_COLOR . '">
		<input id="' . $field_name . '" name="' . $field_name . '" type="text"
		size="' . $max_size . '" maxlength="' . $max_size . '"
		value="' . htmlentities( $initial, ENT_COMPAT, 'UTF-8' ) .
		'" /></td></tr>
		');
	}

	/// Output a row of a table containing only text. The text is processed
	/// to sanitize html tags.
	/// @param string $label localized label string for left side of the row
	/// $param string $value text to display for right side of the row
	/// @return nothing
	public function static_text_row( $label, $value ) {
		echo( '
		<tr><td bgcolor="' . MEM_FORM_COLOR . '"><div align="right">' .
		$label . '</div></td>
		<td bgcolor="' . MEM_FORM_COLOR . '" ><b>' .
		 htmlentities( $value, ENT_COMPAT, 'UTF-8' ) . '</b></td></tr>
		');
	}

	/// Remove leading & trailing whitespace from a string element of array
	/// $_POST and discard bytes if necessary to limit the size
	/// @param string $field name of an element in $_POST
	/// @param int $maxsize n of bytes to limit the size of the string
	/// @return string trimmed to size, or '' if the array element is unset
	/// or empty
	public function trim_entry( $field, $maxsize ) {
		if( isset( $_POST[$field] ) && !empty( $_POST[$field] ))
			return( substr( trim( $_POST[$field] ), 0, $maxsize ));
		else
			return '';
	}

	/// Echo the boilerplate end of the html page.
	public function bottom() {
	echo <<<HEREDOC
</body>
</html>
HEREDOC;
	}
}
