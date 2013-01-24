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

/// This sets language preference variable $lang, and it defines function
/// T() which uses that variable to provide translated text to to the
/// user interface.
///
/// The url of the page that invokes this can indicate the user's language.
/// If the url includes parameter lang=xx, global variable $lang is set to xx
/// (shifted to lower case).
/// Otherwise if the name of the script ends with '_fr.php', indicating french,
/// $lang gets 'fr'.
/// Otherwise, $lang gets 'en' for English.
if ( isset( $_GET['lang'] )) {
	$lang = ( 2 == strlen( $_GET['lang'] )) ?
	strtolower( $_GET['lang'] ) : 'en';
}
elseif( isset( $_SERVER['SCRIPT_NAME'] ) &&
		preg_match( '/.*_fr.php$/i',  $_SERVER['SCRIPT_NAME'] )  ) {
	$lang = 'fr';
}
else {
	$lang = 'en';
}

/// Function T() tries to fetch a translated string from the application
/// database when global $lang specifies a non-English language.
/// It otherwise just returns the supplied English string.
/// In case of error, a marker in red is appended.
/// @param string $en_text English-language text
function T( $en_text ) {
	global $mdb;
	global $lang;     				// language identifier

	if ( 'en' == $lang )
		return $en_text;

	$ret = $mdb->mdb_lookup( ( 's_' . $lang ), 'translations',
	's_en', $en_text  );
	if ( ! $ret ) {
		$ret = $en_text .
		'&nbsp;<b><font color="red">' . $lang . '!</font></b>';
	}
	return $ret ;
}
