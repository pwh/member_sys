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

require_once( 'mem_config.php' );	// MEM_DATABASE_PASSWORD, etc.

///
/// This class provides a type of mysqli having a method to open our configured
/// application database and methods to support a few trivial queries.
///
class MemDb extends mysqli {
	protected $connected;			/// bool true while connected
	protected $mdb_connect_error;	/// string tells why mdb_connect failed
	protected $cached_stmt;			/// mdb_lookup() prepared statement cache

	public function __construct() {
		parent::init();
		$this->connected = false;
		$this->mdb_connect_error = '';
	}

	/// close the connection; future mdb_connect() can re-open it
	/// @return true for success
	public function close() {
		if ( $this->connected ) {
			$ret = parent::close();
		} else {
			$ret = true;
		}

		$this->connected = false;
		$this->mdb_connect_error = '';
		return( $ret );
	}

	/// connect to the application database if necessary, and
	/// set the encoding to utf-8.
	/// @param bool $kill_cached_stmt true to discard $this->cached_stmt
	/// @return true for success
	public function mdb_connect( $kill_cached_stmt = true ) {

		if ($kill_cached_stmt && $this->cached_stmt ) {
			$this->cached_stmt->close();
			$this->cached_stmt = null;
		}

		if ( !$this->connected ) {

			if( !( parent::real_connect( MEM_DATABASE_HOST, MEM_DATABASE_USER,
					MEM_DATABASE_PASSWORD, MEM_DATABASE_NAME ))) {
				$this->mdb_connect_error = __METHOD__ . ': ' . $this->connect_error;
				return( false );
			}

			if ( !( parent::set_charset( 'utf8' ))) {
				$this->mdb_connect_error = __METHOD__ . ': set_charset failed: ' .
				parent::error ;
				return( false );
			}

			$this->connected = true;
			$this->mdb_connect_error = '';
		}
		return( true );
	}


	/// @return string error message for most recent database failure
	public function mdb_error() {
		if( $this->mdb_connect_error )
			return( $this->mdb_connect_error );
		elseif( $this->error )
			return( $this->error );
		else
			return( '' );
	}


	/// get a string with the column names for the specified table as a .csv
	/// row, doublequote quoted and comma-delimited
	/// @param string $table name of the database table for which to get column
	/// names
	/// @return string with top row for a .csv file, or false on error
	function mdb_get_col_names( $table ) {
		$result = false;

		$ret = $this->mdb_connect();
		if( $ret)  {
			$result = $this->query( "SHOW COLUMNS FROM ".
					$this->real_escape_string( $table ) );
			if ( ! $result ) {
				$ret = false;
			}
		}
	 	if ( $ret ) {
	 		$ncols = $result->num_rows;
	 		if ( 0 >= $ncols ) {
	 			$ret = false;
	 		}
	 	}
	 	if ( $ret ) {
	 		$ii = 0;
	 		$ret = '';
	 		while ( $ii < $ncols ) {
				$row = $result->fetch_row();
				$ret .= "\"${row[0]}\"";
				if ( ++$ii < $ncols )
					$ret .= ',';
	 		}
		}

		if ( $result ) {
			$result->close();
		}
		return $ret;
	}


	/// Get an array with the contents of a table as key=>value pairs
	/// with data from the specified pair of columns.
	///
	/// Parameter $pattern provides a means to select a subset of rows
	/// where $val_col contains a string that includes the text in $pattern.
	/// (For example, a row will be included when $val_col in that row
	/// contains "abcdef" if $pattern contains "bcd" ).
	/// The match is not case-sensitive.
	///
	/// @param string $table name of the database table of the columns
	/// @param string $key_col name of a column/select_expr containing keys
	/// @param string $val_col name of a column/select_expr with the
	/// corresponding values
	/// @param string $match_col name of a column for sql LIKE matching
	/// @param string $pattern optional pattern to select items from $match_col;
	/// all rows match if $match_col or $pattern is empty or missing
	/// @param string $sort_col optional name of column with which to sort
	/// the list. Specify reserved name 'value_column' to order by $val_col.
	/// The result is unsorted if this is empty or missing.
	/// @return array of key=>value pairs, or false for error
	function mdb_get_list( $table, $key_col, $val_col, $match_col = '',
			$pattern = '', $sort_col='' ) {
		$ret = array();
		$stmt = false;

		$status = $this->mdb_connect();	// open db if necessary
		if ( $status ) {			// prepare & execute sql statement

			$sql = "SELECT $key_col,$val_col AS value_column FROM $table";
			if( !empty( $pattern ))	// append optional pattern match
				$sql .= " WHERE $match_col COLLATE utf8_general_ci LIKE ?";
			if( !empty( $sort_col ))// append optional sort
				$sql  .= " ORDER BY $sort_col";

			$status = ( $stmt = $this->prepare( $sql )) !== false;

			// resolve the parameter for optional 'LIKE' pattern match
			// such that e.g. pattern "FOO" matches value "abcFOOdef"
			if( $status && !empty( $pattern )) {
				$wildpattern = "%$pattern%";
				$status = $stmt->bind_param( 's', $wildpattern );
			}

			if( $status ) {			// execute the query
				$status = ( $stmt->execute() &&
				$stmt->bind_result( $key, $val ));
			}
		}
		if ( $status ) {			// copy result data to the output array
			while( $stmt->fetch() ) {
				$ret[$key] = $val;
			}
		}

		if ( $stmt )
			$stmt->close();			// clean up

		return ( $status ? $ret : false );
	}


	/// Fetch a single value from the database given the name
	/// and value of a key.
	/// (As this may be used very frequently for language translations, it tries
	/// to re-use a prepared statement that it saves at $this->cached_stmt.)
	/// @param string $val_col name of column containing the result
	/// @param string $table name of the table containing the specified columns
	/// @param string $key_name name of unique key of $table; the name must
	/// begin with a type specification character: 's' for text,
	/// 'i' for integer, 'd' for double, 'b' for blob
	/// @param mixed key_value a value in the key column
	/// @return looked-up value from the value_name column, or false on error.
	public function mdb_lookup( $val_col, $table, $key_name, $key_value ) {
		static $last_value_name;	// arguments saved from last call
		static $last_table;
		static $last_key_name;
		$ret;

		$status = $this->mdb_connect( false );	// false => save_cached stmt

		if ( $status ) {

			// prepare sql statement, unless the one used last time is still ok
			if ( (! $this->cached_stmt ) ||
			$last_value_name != $val_col ||
			$last_table != $table ||
			$last_key_name != $key_name ) {
				$sql = "SELECT $val_col from $table where $key_name = ? LIMIT 1";
				if( $this->cached_stmt )
					$this->cached_stmt->close();
				$this->cached_stmt = $this->prepare( $sql );
				if ( ! $this->cached_stmt )
					$status = false;
			}
		}
		if ( $status ) {
			$status = (
			$this->cached_stmt->bind_param( $key_name[0], $key_value ) &&
			$this->cached_stmt->execute() &&
			$this->cached_stmt->bind_result( $ret ) &&
			$this->cached_stmt->fetch() );
		}

		if (! $status ) {
			if ( $this->cached_stmt )
				$this->cached_stmt->close();
			$this->cached_stmt = null;	// forget prepared stmt after any error
		} else {					// success - remember args for next call
			$last_value_name = $val_col;
			$last_table = $table;
			$last_key_name = $key_name;
		}

		return ( $status ? $ret : false);
	}
}
