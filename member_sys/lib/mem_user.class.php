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
//require_once( 'mem_db.class.php' ); // wrapper for application database
require_once( 'mem_gate.class.php' ); // session handling
require_once( 'mem_err.php' );		// common error handling
require_once( 'mem_translate.php');	// T() message translator
require 'phpass-0.3/PasswordHash.php';  // hardened wrapper for crypt()


/// Class to represent a user.
/// It supports reading, creating and maintaining user entries.
///
/// An instance may represent a user about to be registered, or may be the
/// currently signed-in user, or may be some other registered user.
///
/// @note: the underlying MySQL fields for username and encrypted password must
/// hold 60-character strings.
class MemUser {
	public $error_msg;				/// gets error message on failure
	public $gate;					/// MemGate session info
	protected $hasher;				/// PasswordHash instance
	public $status;					/// bool true if function succeeded
	public $userdata;				/// array w a row from db table 'users'
	protected $userdata_requred =	/// userdata required to register new user
		array("s_lastname","s_username","password");


	/// Constructor; make a MemGate instance and a password hasher
	public function __construct( $userid = 0 ) {
		$this->gate = new MemGate;

    	// instantiate PasswordHash to make strength 7 blowfish password hashes
    	$this->hasher = new PasswordHash( 7, false );
	}


	/// clean up
	public function __destruct() {
		unset( $this->gate );
    	unset( $this->hasher );
	}


	/// Load public array member userdata with the row from the users table for
	/// the specified full name (lastname and firstname).
	/// @param string lastname user's last name
	/// @param string firstname user's first name
	/// @return bool true for success
	public function get_userdata_f( $firstname, $lastname ) {
		global $mdb;
		$stmt = null;

		try {

		// if database connection does not exist create one
		if( !($mdb->mdb_connect()))
			throw new Fail( $mdb->mdb_error() );

		$stmt = $mdb->prepare(
			'SELECT * FROM users WHERE s_firstname = ? AND s_lastname = ?' );
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': prepare' ));
		}
		if ( ! $stmt->bind_param( 'ss', $firstname, $lastname )) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': bind_param' ));
		}
		if ( ! $this->lookup_userdata( $stmt ) ) {
			throw new Fail;
		}
		$this->status = true;
		}
		catch ( Fail $e ) {
		$this->error_msg .= $e->getMessage();
		$this->status = false;
		}
		if ( $stmt )
			$stmt->close();
		return $this->status;
	}


	/// Load public array member userdata with the row from the users table for
	/// the specified user id number.
	/// @param int userid value of colun i_userid for a row in users table
	/// @return bool true for success
	public function get_userdata_i( $userid ) {
		global $mdb;
		$stmt = null;

		try {

		// if database connection does not exist create one
		if( !($mdb->mdb_connect()))
			throw new Fail( $mdb->mdb_error() );

		$stmt = $mdb->prepare(
			'SELECT * FROM users WHERE i_userid = ?' );
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': prepare' ));
		}
		if ( ! $stmt->bind_param( 'i', $userid )) {
			throw new Fail( mem_error( MEM_ERR_DB,
			__METHOD__.': bind_param' ));
		}
		if ( ! $this->lookup_userdata( $stmt ) ) {
			throw new Fail;
		}
		$this->status = true;
		}
		catch ( Fail $e ) {
		$this->error_msg .= $e->getMessage();
		$this->status = false;
		}
		if ( $stmt )
			$stmt->close();
		return $this->status;
	}


	/// Load public array member userdata with the row from the users table for
	/// the specified username.
	/// @param username username string
	/// @return bool true for success
	public function get_userdata_u( $username ) {
		global $mdb;
		$stmt = null;

		try {

		// if database connection does not exist create one
		if( !($mdb->mdb_connect()))
			throw new Fail( $mdb->mdb_error() );

		$stmt = $mdb->prepare(
			'SELECT * FROM users WHERE s_username = ?' );
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': prepare' ));
		}
		if ( ! $stmt->bind_param( 's', $username )) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': bind_param' ));
		}
		if ( ! $this->lookup_userdata( $stmt ) ) {
			throw new Fail;
		}
		$this->status = true;
		}
		catch ( Fail $e ) {
		$this->error_msg .= $e->getMessage();
		$this->status = false;
		}
		if ( $stmt )
			$stmt->close();
		return $this->status;
	}


	/// Get true if this user is an administrator
	/// @return true if this is an aministrator
	public function is_admin() {
		return (isset( $this->userdata['i_administrator']) &&
				0 != $this->userdata['i_administrator'] );
	}


	/// Check whether a user is signed in yet; if so ensure we have userdata.
	///
	/// A user is signed in if the browser has a session cookie
	/// whose value matches a row in the sessions table
	/// which is marked as active and was created recently.
	///
	/// On the first call, query the session table.
	/// If a valid session exists, $gate remembers it.
	/// On the first call we load this object's public array member userdata.
	///
    /// @return bool true iff the user is signed in
	public function is_signed_in() {
		$stmt = null;

		if ( $this->gate->has_session() ) {
			return $this->status = true;	// already done
		}

		try {

		// we don't yet have a session - try to find it
		$stmt = $this->gate->fetch_session( $this );
		if ( !$stmt ) {
			// session cookie not found, or SQL problem
			throw new Fail( $this->gate->error_msg );
		}

		// we have a session cookie (key); check for actual session data,
		// get userdata if the session is current
		if ( ! $this->lookup_userdata( $stmt )) {
			throw new Fail;			// nope, user is not signed in
		}
		$this->status = true;

		}
		catch( Fail $e ) {
		$this->error_msg .= $e->getMessage();
		$this->gate->clear_dbsession();	// tell gate session value is invalid
		$this->status = false;
		}
		if ( $stmt )
			$stmt->close();
		return $this->status;

	}


	/// Execute a query to lookup user row in the database, and load
	/// associative array userdata.
	/// @param mysqli_stmt $stmt a prepared statement ready to execute
	/// @return bool true for success
	protected function lookup_userdata( $stmt ) {
		$meta = null;
		$this->userdata = array();

		if ( ! $stmt->execute()) {
			$this->error_msg .= mem_error( MEM_ERR_DB, __METHOD__.': execute' );
			return false;
		}
		if( ! $stmt->store_result()) {
			$this->error_msg .= mem_error( MEM_ERR_DB,
			__METHOD__.': store_result' );
			return false;
		}
		if ( 1 != $stmt->num_rows ) {
			$this->error_msg .= mem_error( MEM_ERR_CREDENTIALS,
			__METHOD__.': row not found in db' );
			return false;
		}

		try{

		// load the userdata array with values from the result set via a
		// scheme described by hamidhossain at gmail dot com in comments
		// for mysqli_bind_result in the PHP manual

		$meta = $stmt->result_metadata();	// get mysqli_result object
		if( ! $meta ) {
			throw new Fail( mem_error( MEM_ERR_DB,
			__METHOD__.': result_metadata' ));
		}

		// make parameter list for bind_result(), then
		// bind each value from the db to a slot in array $row
		while( $field = $meta->fetch_field()) {
			$params[] = &$row[$field->name];
		}
		if ( empty( $params) ) {
			throw new Fail( mem_error( MEM_ERR_DB,
			__METHOD__.': fetch_field' ));
		}
		if ( !call_user_func_array( array( $stmt, 'bind_result' ), $params )) {
			throw new Fail( mem_error( MEM_ERR_DB,
			__METHOD__.': bind_result' ));
    	}

    	// now load $row[] from the database row
    	if ( !$stmt->fetch()) {
			throw new Fail( mem_error( MEM_ERR_DB, __METHOD__.': fetch' ));
    	}

    	// finally, copy out the column names & values
    	foreach( $row as $key => $val )
    		$this->userdata[$key] = $val;

    	$ret = true;				// return indicating success
		}
		catch( Fail $e ) {
		$this->error_msg .= $e->getMessage();
		$ret = false;				// return indicating failure
		}
    	if ( $meta )
			$meta->free();
		return $ret;
	}


	/// Indicate whether the current user is permitted to access the
	/// members-only web pages on the site, e.g. if the dues are paid up.
	/// Permit access through the year recorded at i_membership_year
	/// plus an arbitrary 100 days grace period.
	/// @return true iff user is signed in and is permitted to access the
	/// members-only web pages.
	public function members_only_ok() {
		$this->status = true;
		$this->error_msg = '';

		if( ! $this->userdata['s_date_of_payment'] ) {
			$this->error_msg = 'No dues payment recorded';
			$this->status = false;
		}
		if ( $this->status && ( $this->userdata['i_membership_year'] <= 0 )) {
			$this->error_msg = 'No membership year recorded';
			$this->status = false;
		}

		if( $this->status ) {
			// use UNIX time values to simplify comparisons;
			// $xmlrpc gets the end of the paid-up year as a string, e.g.
			// "20131231T23:59:59", and $end gets the UNIX time value of that
			// plus the grace period
			$xmlrpc = $this->userdata['i_membership_year'] . '1231T23:59:59';
			$end = strtotime( '+' . 100 . ' days', strtotime( $xmlrpc ));
			$now = time();

			if ( $end < $now ) {
				$this->error_msg = 'Membership expired';
				$this->status = false;
			}
		}
		return $this->status;
	}


    /// Check lastname or firstname string against the rules.
    /// @note Use this with a web page that specifies charset=UTF-8 content type.
    /// @param $name string proposed name, after trim()
    /// @return true iff the string matches the signin rules
    /// @see rulecheck_rules().
    /// @see http://stackoverflow.com/questions/557377/how-to-check-real-names-and-surnames-php
    protected function rulecheck_name( $name ) {
    	$pcre = '/^[\pL][\pL\p{Mc} .,+&\'_-]*[\pL\p{Mc}.]$/u';
    	if( ( strlen( $name ) < MEM_NAME_MINSIZE ) ||
    	( strlen( $name ) > MEM_NAME_MAXSIZE ) ||
    	! preg_match( $pcre, $name )) {
    		$this->error_msg .= mem_error( MEM_ERR_NAME,
    				__METHOD__.": (name \"$name\")" );
    		return( false );
    	}
    	return( true );
    }


    /// Check username string against the rules.
    /// @note Use this with a web page that specifies charset=UTF-8 content type.
	/// @param $name string proposed username, after trim()
    /// @return true iff the string matches the signin rules
    /// @see rulecheck_rules().
    public function rulecheck_username( $name ) {
    	$pcre = '/^[\pL][\pL\pN\p{Mc} .+&\'_-]+[\pL\pN\p{Mc}.]$/u';
    	if( ( strlen( $name ) < MEM_USERNAME_MINSIZE ) ||
    	( strlen( $name ) > MEM_NAME_MAXSIZE ) ||
    	! preg_match( $pcre, $name )) {
    		$this->error_msg .= mem_error( MEM_ERR_CREDENTIALS,
    		__METHOD__.": (username \"$name\")" );
    		return( false );
    	}
    	return( true );
    }


    /// Check password string against the signin rules.
    /// @note Use this with a web page that specifies charset=UTF-8 content type.
    /// @param $password string proposed password, after trim()
    /// @return true iff the string matches the signin rules
    /// @see rulecheck_rules().
    /// @see manpages: pcresyntax and pcreunicode (or document pcre.txt)
    /// @note: a more robust check would forbid leading or trailing digits:
    /// '/^\p{L}{1,}[\p{L}\p{N} ]{0,}\p{Nd}{1,}[\p{L}\p{N} ]{0,}\p{L}{1,}$/u'
    // One PHP 5.2.17 server complained about \p{Xan} in
    // '/^\p{L}{1,}[\p{Xan} ]{0,}\p{Nd}{1,}[\p{Xan} ]{0,}\p{L}{1,}$/u'
    //  so it is now \p{L}\p{N}
    protected function rulecheck_password( $password ) {

    	if ( strlen( $password ) < MEM_PASSWORD_MINSIZE ||
    	strlen( $password ) > MEM_PASSWORD_MAXSIZE ||
    	!preg_match(
    	'/^[\p{L}\p{N} ]{0,}\p{Nd}{1,}[\p{L}\p{N} ]{0,}$/u',
    	$password )) {
    		$this->error_msg .= mem_error( MEM_ERR_CREDENTIALS,
    		__METHOD__.": (password \"$password\")" );
    		return( false );
    	}
    		return( true );
    }


    /// @return html string to explain the requirements
    /// for username & password strings
    /// @see rulecheck_name()
    /// @see rulecheck_password()
	public function rulecheck_rules() {
		return
		T( "Membership requires your name to identify you." ) . '&nbsp;' .
		T( "The web site also requires a username and password or passphrase." ) .
		'<br><ul><li>' .
		T( "The name and password entries are case-sensitive." ) .
		'<li>' .
		T( "Entries may contain spaces, but " .
		"leading and trailing spaces are ignored." ) .
		'</li><li>' .
		sprintf(
		T( "Last- and First name entries must have between " .
		"%1\$d and %2\$d characters, and may contain typical punctuation." ),
		MEM_NAME_MINSIZE, MEM_NAME_MAXSIZE  ) .
		' (' .
		T( "To establish a membership for a hypothetical Chrétien family, " .
		"the Last name would be <i>Chrétien</i>, and First name might be " .
		"<i>Marie & James B.</i>." ) . ')' .
		'</li><li>' .
		sprintf(
		T( "The Username must have between " .
		"%1\$d and %2\$d characters, and may contain digits and some " .
		"punctuation." ),
		MEM_USERNAME_MINSIZE, MEM_NAME_MAXSIZE  ) . ' (' .
		T( "An example username is: <i>mr_spider</i>." ) . ')' .
		'</li><li>' .
		sprintf(
		T( "The password or passphrase must have between %1\$d and %2\$d " .
		"characters, and must have at least one digit." ),
		MEM_PASSWORD_MINSIZE, MEM_PASSWORD_MAXSIZE ) .
		'</li></ul>';
	}


	/// Update membership year in the profile as a result of a dues payment.
	/// This updates column 's_date_of_payment' with the current timestamp
	/// and stores the specified year to user column 'i_membership_year'.
	/// Value 0 for year is reserved to mean 'not a member'.
	/// It is forbidden to set one's own status to 0.
	/// @param int $year highest year in which the user will have membership
	/// @param MemUser $cur_user the currently signed-in user
	/// @return true for success
	public function set_membership_year( $year, MemUser $cur_user ) {
		global $mdb;
		$this->status = true;		// assume success for now
		$this->error_msg = '';
		$stmt = null;				// for prepared sql statement

		try {

		// check for integer argument >= 0
		if( (!is_numeric( $year )) || ( intval( $year ) != $year ) ||
		( $year < 0 )) {
			throw new Fail( 'Error: ' . __METHOD__ .
			': incorrect value posted: ' . htmlentities( $year ));
		}

		// check for someone setting self as not a member
		if ( ( 0 == $year ) && ( $cur_user == $this )) {
			throw new Fail(
			'Sorry, a blank entry would rescind your membership; disallowed.');
		}

		// if database connection does not exist create one
		if( !($mdb->mdb_connect()))
			throw new Fail( $mdb->mdb_error() );

		$timestamp = date ( 'Y-m-d H:i:s' );
		$sql = 'UPDATE users SET s_date_of_payment = \'' . $timestamp .
		'\', i_membership_year = '. $year .
		' WHERE i_userid = ' . $this->userdata['i_userid'] ;

		$stmt = $mdb->prepare( $sql );
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB,
					__METHOD__.': prepare ( ' . $sql . ' )' ));
		}

		if ( ! $stmt->execute()) {
			throw new Fail( mem_error( MEM_ERR_DB,
					__METHOD__.': execute ( ' . $sql . ' )' .
					' got errno ' . $stmt->errno ));
		}

		$this->userdata['s_date_of_payment'] = $timestamp;
		$this->userdata['i_membership_year'] = $year;

		}
		catch ( Fail $e ) {
		$this->error_msg .= $e->getMessage();
		$this->status = false;
		}
		if ( $stmt )
			$stmt->close();
		return $this->status;
	}


	/// Create a session for this user.
	/// @return true => success
	public function sign_me_in() {

		// mark old session table row (if any) for this user as inactive
		$this->gate->end_session( $this->userdata['i_userid'] );

		// Hash a unique value for the new session & save it.
		// The browser will get a cookie with a fixed name. Its value
		// is the key to the sessions table (stored in our MemGate).
		if ( (! $this->gate->new_session( $this )) ||
				(! $this->gate->set_session_cookie())) {
			$this->status = false;
			$this->error_msg = $this->gate->error_msg;
		}
		return $this->status;
	}


	/// Return true if user is signed in, otherwise validate credentials and
	/// create a session value, a random key for the sessions table. A new row
	/// in that table remembers the signin event. Store the session value in
	/// a browser cookie for use while the user has a brower session.
	/// @param string $username username to validate
	/// @param string $password for $username to validate
	/// @return boolean true if user is signed in
	public function signin( $username, $password ) {
		global $mdb;
		$this->status = true;
		$this->error_msg = '';
		$stmt = null;

		// remove leading & trailing spaces
		$username = trim( $username );
		$password = trim( $password );

		// just return if this user already has an active session
		if (( $username === $this->userdata['s_username'] ) &&
				$this->is_signed_in() ) {
			return $this->status = true;	// already signed in!
		}

		try {

		// if database connection does not exist create one
		if( !($mdb->mdb_connect()))
			throw new Fail( $mdb->mdb_error() );

		// check entries for illegal chars, validate length
		if ( !$this->rulecheck_username( $username ) ||
			!$this->rulecheck_password( $password )) {
			throw new Fail;
		}

		// setup to fetch user row for this username from the database
		$sql = 'SELECT * FROM users WHERE s_username = ?';
		$stmt = $mdb->prepare( $sql );
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB,
			__METHOD__.': prepare ' . $sql ));
		}
		if ( ! $stmt->bind_param( 's', $username )) {
			throw new Fail( mem_error( MEM_ERR_DB,
			__METHOD__.': bind_param' ));
		}

		// load userdata array (including the password hash value)
		if ( ! $this->lookup_userdata( $stmt ) )
			throw new Fail;		// no userdata found

		// so far so good; now verify the entered password
		if ( ! $this->hasher->CheckPassword(
		$password, $this->userdata['s_passhash'] )) {
			throw new Fail( mem_error( MEM_ERR_CREDENTIALS,
			__METHOD__.': incorrect password "' . $password . '"' ));
		}

		// the password is good! onward:
		if( ! $this->sign_me_in() )
			throw new Fail;			// oops, can't create session

			$this->status = true;
		}
		catch( Fail $e ) {
			$this->userdata = array();
			$this->error_msg .= $e->getMessage();
			$this->status = false;
		}

		if ( $stmt )
			$stmt->close();
		return $this->status;
	}


	/// Undo effects of signin(): mark user's session table entry not active,
	/// remove the session variable and its cookie.
	///
	/// @return boolean true for success, false if an error occurred
	public function signout() {
		$this->error_msg = '';

		if( ! $this->gate->end_session( $this->userdata['i_userid'] )) {
			$this->error_msg = $this->gate->error_msg;
			$this->status = false;
		}
		$this->gate->unset_session_cookie();

		return $this->status;
	}


	/// Validate new values for a row in the 'users' database table
	/// and insert or update the row.
	/// @param array $newuserdata key=>value pairs where keys name columns
	/// to add or update in the table. (Fake keys 'password' and 'password2'
	/// provide an unencrypted password; we replace them with ['s_passhash'].)
	/// If element ['i_userid'] is set, update data for this user;
	/// otherwise insert the $newuserdata values to register a new user.
	/// @param MemUser $cur_user the currently signed-in user (may be null
	/// or empty).
	/// @return true for success
	public function update_user( array $newuserdata, MemUser $cur_user ) {
		global $mdb;
		$this->status = true;		// assume success for now
		$this->error_msg = '';
		$replace_session = false;	// username&password unchanged so far
		$stmt = null;				// for prepared sql statement
		$adduser = ! isset( $this->userdata['i_userid'] );

		try {
		if( $adduser ) {			// ensure new user has required userdata
			foreach( $this->userdata_requred as $key ) {
				if( ! isset( $newuserdata[$key] )) {
					$this->error_msg .= mem_error( MEM_ERR_MISSING_DATA,
							__METHOD__.': missing new ' . $key );
					$this->error_msg .= ' (' . $key . ')';
					$this->status = false;
					break;
				}
			}
		}

		// check new username
		if ( isset ( $newuserdata['s_username'] )) {
			$replace_session = true;
			$newuserdata['s_username'] = trim( $newuserdata['s_username'] );
			if ( !$this->rulecheck_username( $newuserdata['s_username'] )) {
				$this->status = false;
			}
		}

		// check s_lastname and s_firstname
		if ( isset ( $newuserdata['s_lastname'] )) {
			$newuserdata['s_lastname'] = trim( $newuserdata['s_lastname'] );
			if ( !$this->rulecheck_name( $newuserdata['s_lastname'] )) {
				$this->error_msg .= mem_error( MEM_ERR_NAME,
				__METHOD__.': bad last name ' . $newuserdata['s_lastname'] );
				$this->status = false;
			}
		}
		if ( isset ( $newuserdata['s_firstname'] )) {
			$newuserdata['s_firstname'] = trim( $newuserdata['s_firstname'] );
			if ( !$this->rulecheck_name( $newuserdata['s_firstname'] )) {
				$this->error_msg .= mem_error( MEM_ERR_NAME,
				__METHOD__.': bad first name ' . $newuserdata['s_firstname'] );
				$this->status = false;
			}
		}

		// check if password is acceptable and matches password2;
		// if so replace them with the password hash
		// and record whether the password is permanent
		if ( isset ( $newuserdata['password'] )) {
			$replace_session = true;
			$password = trim( $newuserdata['password'] );
			if( ! $this->rulecheck_password( $password ) )
				$this->status = false;
		}
		if ( isset( $newuserdata['password2']))
			$password2 = trim( $newuserdata['password2'] );

		// if just one is set, or if they're both set but don't match, error
		if (( isset($password) xor isset( $password2 )) ||
		( isset( $password ) && isset ( $password2 ) &&
		( $password2 != $password ))) {
			$this->error_msg .= mem_error( MEM_ERR_PASS_MISMATCH,
			__METHOD__.': mismatched passwords' );
			$this->status = false;
		}

		if( $this->status && isset( $password )) {

			// all entries are valid... make password hash
			$passhash = $this->hasher->HashPassword( $password );
			if ( strlen( $passhash ) != 60 ) {
				// hasher choked on the password
				throw new Fail( mem_error( MEM_ERR_CREDENTIALS,
				__METHOD__.': HashPassword fails for ' . $password ));
			}
			else {

				// replace the password with the corresponding password hash
				$newuserdata['s_passhash'] = $passhash;

				// if the caller did not declare this to be a temporary
				// password, declare it to be a permanent one
				if ( ! isset( $newuserdata['i_change_pass'] )) {
					$newuserdata['i_change_pass'] = '0';
				}
			}
		}
		unset( $newuserdata['password'] );
		unset( $newuserdata['password2'] );

		// (process any additional user profile attributes here)

		// update database only if the checks done so far all pass
		if( ! $this->status )
			throw new Fail;

		// check whether we're updating the signed-in user; if not,
		// we need not replace the current session value
		if( $adduser || is_null( $cur_user ) ||
		( !isset( $cur_user->userdata['i_userid'] )) ||
		( $this->userdata['i_userid'] != $cur_user->userdata['i_userid'] )) {
			$replace_session = false;
		}

		// if database connection does not exist create one
		if( !($mdb->mdb_connect()))
			throw new Fail( $mdb->mdb_error() );

		if( $adduser) { // inserting a new user
			// construct insert statement like
			// INSERT INTO users SET column1=?, column2=?, ...
			$sql = 'INSERT INTO users SET';
		}
		else {
			// construct update statement like
			// UPDATE LOW PRIORITY users SET column1=?, ... WHERE i_userid=nnn
			$sql = 'UPDATE LOW_PRIORITY users SET';
		}
		$sep = '';					// column separator: '' or ','
		$types = '';				// gets first argument for bind_param()
		reset ( $newuserdata );
		while ( list( $column, $value ) = each( $newuserdata )) {
			$sql .= $sep . ' ' . $column . '=?';
			$types .= $column[0];   // 1st char of column name is its type
			$sep = ",";
		}
		if ( ! $adduser ) {			// for update, specify which row
			$sql .= ' WHERE i_userid = ' . $this->userdata['i_userid'] ;
		}

		$stmt = $mdb->prepare( $sql );
		if( ! $stmt ) {
			throw new Fail( mem_error( MEM_ERR_DB,
			__METHOD__.': prepare ( ' . $sql . ' )' ));
		}

		// call $stmt->bind_params() to bind parameters for the SQL statement
		// (note that bind_param requires references, not values)
		$method = new ReflectionMethod( 'mysqli_stmt', 'bind_param' );
		$args = array();
		foreach ( $newuserdata as $key => $value) {
			$args[$key] = &$newuserdata[$key];
		}
		array_unshift( $args, $types );	// 1st argument for bind_param
        if ( ! $method->invokeArgs( $stmt, $args )) {
			throw new Fail( mem_error( MEM_ERR_DB,
				__METHOD__.': bind_param( ' . print_r( $args, true ) . ')'));
		}

		if ( ! $stmt->execute()) {

			// check if the write failed because a name is already in use
			if ( $stmt->errno == ER_DUP_ENTRY ) {
				$msg = mem_error( MEM_ERR_INUSE,
				__METHOD__.': name in use: ' .
				print_r( $newuserdata, true));
			} else {
			    $msg = mem_error( MEM_ERR_DB,
			    __METHOD__.': update ' . print_r( $newuserdata, true) .
			    ' got errno ' . $stmt->errno );
			}
			throw new Fail( $msg );
		}
		if ( $stmt ) {
			$stmt->close();
			$stmt = null;
		}

		if( $adduser ) {			// load the userdata array from new row
			if( ! $this->get_userdata_u( $newuserdata['s_username'] ))
				throw new Fail;
		}
		else {						// just update userdata with new values
			foreach( $newuserdata as $key=>$val )
				$this->userdata[$key] = $val;
		}

		// did we change credentials of the current user's session? clean up...
		if( $replace_session ) {

			$this->signout();		// terminate old session

			// hash & store value for the new session, reset username cookie
			if ( $this->gate->new_session( $this ) ) {
				$this->gate->set_session_cookie();

				// if username changed, fix username cookie (if any)
				if ( isset( $newuserdata['s_username'] ) &&
				isset( $_COOKIE[MEM_USR_COOKIE] )) {
					setrawcookie( MEM_USR_COOKIE,
					bin2hex( $newuserdata['s_username'] ),
					time()+60*60*24*30,	// valid for 30 days
					MEM_COOKIE_PATH,
					($_SERVER['HTTP_HOST'] != 'localhost')?
							$_SERVER['HTTP_HOST'] : false,
					MEM_REQUIRE_HTTPS,
					true );			// true-> cookie unavailable to javascript
				}
			}
		}
		}
		catch ( Fail $e ) {
		$this->error_msg .= $e->getMessage();
		$this->status = false;
		}
		if ( $stmt )
			$stmt->close();
		return $this->status;
	}

	/// Get the userid of this user, if any.
	/// @return int i_userid from userdata, or 0 if that is unavailable
	public function userid() {
		$nn = 0;
		if( isset( $this->userdata['i_userid'] ))
			$nn += $this->userdata['i_userid'] ;
		return $nn;
	}
}
