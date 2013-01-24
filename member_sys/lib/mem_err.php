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

/// Application error handling

define( 'MEM_ERR_CREDENTIALS', 1 );	// invalid username or password
define( 'MEM_ERR_DB', 2 );			// database error seen
define( 'MEM_ERR_INUSE', 3 );		// proposed fullname or username in use
define( 'MEM_ERR_APPLICATION', 4 );	// internal application error
define( 'MEM_ERR_NAME', 5);			// invalid lastname or firstname
define( 'MEM_ERR_PASS_MISMATCH', 6);// pair of password entries do not match
define( 'MEM_ERR_MISSING_DATA', 7);	// userdata missing to register new user

DEFINE( 'ER_DUP_ENTRY',  1062); 	// MySQL UNIQUE constraint violation

// Subclass of Exception just to permit distinguishing mem_sys errors
// for possible future exception-based error handling.
// For now it is used only to replace goto, which is unavailable before PHP 5.3
class Fail extends Exception {
	public function __construct( $message = '', $code = 0 ) {
		return parent::__construct( $message, $code );
	}
}

/// Get text describing an error.
/// Database errors result in additional debug information added to debug_msg.
/// @param int mem_error_code an application-specific error code.
/// @param string debug_msg debug-only text to append if MEM_DEBUG is true
/// @return string error html message to display
function mem_error( $mem_error_code, $debug_msg = '' ) {
	global $mdb;
	$ret = '';

	switch ( $mem_error_code ) {
		case MEM_ERR_PASS_MISMATCH:
			$ret =
			T( "The password fields don't match; please try again." );
			break;
		case MEM_ERR_CREDENTIALS:
			$ret =
			T( "Sorry, that didn't work because the username " .
			"or password was invalid." );
			break;
		case MEM_ERR_NAME:
			$ret =
			T( "Sorry, the entered name did not match the rules." );
			break;
		case MEM_ERR_DB:
			$ret = T( "A database error occurred." );
			if ( MEM_DEBUG )
				$ret .= ( '<br>' . $mdb->mdb_error() );
			break;
		case MEM_ERR_INUSE:
			$ret =
			T( "Unfortunately that name or username is already registered. " .
					"Please try another." );
			break;
		case MEM_ERR_MISSING_DATA:
			$ret = T( "A required entry is missing." );
			break;
		default:
			$debug_msg .= '<br>Invalid arg $mem_error_code=' . $mem_error_code;
			// *** fallthru ***
		case MEM_ERR_APPLICATION:
			$ret .= "ERROR: Internal application error.";
			break;
	}
	$ret .= '<br>';

	if ( MEM_DEBUG && ! empty( $debug_msg ) )
		$ret .= 'debug:' . $debug_msg . '<br>';

	return $ret;
}
