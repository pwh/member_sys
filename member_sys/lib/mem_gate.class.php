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


//require_once( 'mem_config.php' );
//require_once( 'mem_db.class.php' );	// wrapper for application database
require_once( 'mem_err.php' );		// common error handling
require_once( 'mem_translate.php');	// T() message translator
require_once( 'mem_user.class.php' );		// user magagement

/// Class to mediate access to the access-control database.
/// It supports signing in and creating persistent session state.
///
/// Signing in creates a session: data persisted in a cookie.
/// (To be of use, this requires support for cookies in the browser which
/// persist until the browser terminates.) We store there only the
/// randomly-generated key for a row in our MySQL sessions table.
/// @note: Internet Explorer requires the web site to publish a privacy
/// policy in order to use cookies or else it issues scary warnings; see
/// http://www.sitepoint.com/p3p-cookies-ie6/
class MemGate {

	protected $dbsession;				/// unique key for row in the session table
	public $error_msg;				/// gets error message on failure
	public $status;					/// bool true if function succeeded


	/// Clear this gate's session value string to indicate it is valid.
	public function clear_dbsession() {
		$this->dbsession = '';
	}


	/// Mark the current user's entry in the sessions table as inactive.
	/// @param int $userid a user's id number
	/// @return true for success
	public function end_session( $userid ) {
		global $mdb;
		$stmt = null;

		try {

		$stmt = $mdb->prepare(
				'UPDATE sessions SET i_active = 0 WHERE i_userid = ?' );
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': prepare' ));
		}
		if ( ! $stmt->bind_param( 's', $userid )) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': bind_param' ));
		}
		if ( ! $stmt->execute()) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': execute' ));
		}
		$ret = true;				// return success
		}
		catch( Fail $e ) {
			$this->error_msg .= $e->getMessage();
			$ret = false;
		}

		if ( $stmt )
			$stmt->close();
		return $ret;
	}


	/// Check our browser cookie to see whether it has a session value; if so
	/// prepare a mysqli statement to see if that value matches a row in the
	/// sessions table that is marked as active and was created recently.
	/// The statement is crafted to select the user's row from the users
	/// table as well.
    ///
	/// @return mysqli_stmt - if success, a prepared statement ready to
	/// execute; else false
	public function fetch_session() {
		global $mdb;
		$stmt = null;
		$this->status = true;
		$this->error_msg = '';

		// get the key from the session cookie
		if ( isset( $_COOKIE[ MEM_SES_COOKIE ] ))
			$ses = $_COOKIE[ MEM_SES_COOKIE ];
		else
			return $this->status = false;	//  browser has no dbsession

		try {

		if( !($mdb->mdb_connect())) {
			throw new Fail( $mdb->mdb_error() );
		}

		// look for the active session in the database and check that
		// it has not timed out
		$sql = 'SELECT users.* FROM sessions,users WHERE ' .
		'(sessions.s_value = ?) AND ' .
 		'(sessions.i_active = 1) AND ' .
 		'(sessions.i_time > 0 ) AND ' .
 		'(TIMEDIFF(NOW(), sessions.i_time) < \'' .
 			MEM_SESSION_TIMEOUT . '\') AND ' .
		'(users.i_userid = sessions.i_userid) LIMIT 0,1';
 		$stmt = $mdb->prepare( $sql );
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': prepare' ));
		}
		if ( ! $stmt->bind_param( 's', $ses )) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': bind_param' ));
		}

		$this->dbsession = $ses;
		$this->status = true;

		}
		catch( Fail $e ) {
		if ( $stmt )
			$stmt->close();
		$this->dbsession = '';
		$this->error_msg .= $e->getMessage();
		$this->status = false;
		}
		return $stmt;
	}


	/// Check whether we have identified a session
	/// @return true if we created a session or loaded one from the database
	public function has_session() {
		return !empty( $dbsession );
	}


	/// Create a session in the database for the user represented in
	/// member userdata.
	///
	/// Make a new unique session identifier value, store it in a new row
	/// in the sessions table. MySQL will record a timestamp in column i_time.
	///
	/// @param MemUser $user the user signing in
	/// $this->dbsession gets a string of 80 hex characters on success.
	///
	/// @return boolean true for success
	public function new_session( $user ) {
		global $mdb;
		$stmt = null;

		try {

		// hash up a unique new session value by appending
		// two 40-byte hexadecimal hash strings = 80 chars
		$salt = sha1( rand( 1000000,10000000000 ) . time() );
		$ses_val = $salt . $user->userdata['i_userid'] .
		 	$user->userdata['s_username'] .
			$user->userdata['s_passhash'] . $salt;
		$ses_val = $salt . sha1($ses_val);

		// record new row in session table
		$stmt = $mdb->prepare( 'INSERT INTO sessions ' .
			'(i_userid, s_value, i_active) VALUES (' .
			'(SELECT i_userid FROM users WHERE s_username = ?), ?, 1)');
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': prepare' ));
		}
		if ( ! $stmt->bind_param( 'ss',
		$user->userdata['s_username'], $ses_val)) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': bind_param' ));
		}
		if ( ! $stmt->execute()) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': execute' ));
		}
		$this->status = true;

		}
		catch( Fail $e ) {
		$ses_val = '';
		$this->error_msg .= $e->getMessage();
		$this->status = false;
		}

		$this->dbsession = $ses_val;
		if ( $stmt )
			$stmt->close();
		return $this->status;
	}


	/// Set a session cookie to store the key value dbsession and set the
	/// value in array $_COOKIE.
	/// @note: must be done after sending any header to redirect the browser!
	/// @return true for success
	public function set_session_cookie() {
		$this->status = true;
		$this->error_msg = '';

		// (adjust the domain argument per comments from Harry Truong
		// 08-Feb-2007 on the PHP manual page for setcookie)
		if ( ! setrawcookie( MEM_SES_COOKIE, $this->dbsession,
		0, 						// cookie expires when browser exits
		MEM_COOKIE_PATH,
		($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false,
		MEM_REQUIRE_HTTPS,
		true )					// true=> cookie unavailable to javascript
		) {
			// failed...
			unset( $_COOKIE[ MEM_SES_COOKIE ] );
			$this->error_msg .= mem_error( MEM_ERR_APPLICATION,
			__METHOD__. 'setrawcookie() failed' );
		}
		else {
			$_COOKIE[ MEM_SES_COOKIE ] = $this->dbsession;
		}
		return( $this->status );
	}


	/// Try to tell the browser to forget about the session.
	public function unset_session_cookie() {

		setrawcookie( MEM_SES_COOKIE, false,	// false => deleted
		1,							// => indicate cookie expired in 1970
		MEM_COOKIE_PATH,
		($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false,
		MEM_REQUIRE_HTTPS,
		true );						// true=> cookie unavailable to javascript
		unset( $_COOKIE[ MEM_SES_COOKIE ] );
	}
}
