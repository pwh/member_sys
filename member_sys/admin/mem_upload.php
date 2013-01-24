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
<p>This admin-only page uploads a .csv file to a specified table in our
application database. The file may be one created using the mem_download page,
perhaps after additions or modifications to the content.</p>
<p><b>Warning!</b> This will <b>replace</b> the entire contents of the table!
</p>
<p>Note: take care that the encoding selected here is the encoding used
by your spreadsheet program.</p>
HEREDOC;

/// @note: the user is trusted so input checking is relaxed (famous last words).

set_include_path( dirname(__FILE__) . '/../lib' );

$admin_only_page = true;			// only users having i_administrator allowed
require_once( 'mem_only.php' );		// $mdb
require_once( 'quotesplit.php' );	// parse comma-separated-value (csv) row

ini_set( 'upload_tmp_dir', MEM_TMPDIR );	// for loading csv files to the db

$page = new HtmlPage;

$max_file_size = 2048 * 1024;
$msg = '';

/// if the user previously selected some option in a select list,
/// on the form, preselect that option when re-displaying the form
/// @param name string name of the select list
/// @param option string value of the option to test
/// @return string to select the option, or an empty string
function preselect( $name, $option ) {
	return ( isset( $_POST[$name] ) && ( $_POST[$name] == $option )) ?
 		' selected="selected"' : '' ;
}


/// Load a table
/// @param string $filename2 name of uploadedd csv file on local server
/// @param string $enc name of file encoding
/// @param string $table name of database table to load
/// @param array $column_list list of columns from top of csv file
/// @return true for success
/// @note: use inefficient LOCAL option in the sql statement to
/// workaround our inability to use GRANT FILE on cPanel hosting account;
/// with LOCAL, the PHP client copies the file and feeds it to the mysql
/// server.
function load_db( $filename2, $enc, $table, $column_list ) {
	global $mdb;
	global $msg;

	if ( !($mdb->mdb_connect())) {
		$msg .= 'Error: failed to connect to database.<br>
		$nbsp;&nbsp;' . $mdb->mdb_error() . '<br>
		';
		return false;
	}
	if ( $mdb->errno ) die( "$mdb->errno has " . $mdb->errno );

	$sql_columns = '(' . implode( ',', $column_list) . ')'; // columns as string
	$multi_sql = array(
	"TRUNCATE TABLE $table",
	"LOAD DATA LOW_PRIORITY LOCAL INFILE '${filename2}' INTO TABLE $table
	CHARACTER SET $enc
	FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '\"'
	LINES TERMINATED BY '\r\n' $sql_columns" );

	$ii = 0;
	if ( $mdb->multi_query( implode( ';', $multi_sql))) {

		// the first sql statement succeeded; test the next one
	    do {
	        $ii++;
	    } while ( $mdb->next_result() );
	}
	if ( $mdb->errno ) {
	    $msg .= 'Error for sql statement ' . $ii . ':<br>
	    &nbsp;&nbsp;' . $multi_sql[$ii] . '<br>
	    &nbsp;&nbsp;$column_list has ' .
	    count( $column_list ) . ' column names: ' .
	    $sql_columns . '<br>
	    &nbsp;&nbsp;MySQL error ' . $mdb->errno . ' '. $mdb->mdb_error() . '<br>
	    ';
	    return false;
	}
	return true;
}

if ( isset( $_POST['submit'] )) {

	if ( empty( $_FILES ) || !isset ( $_FILES['file']['name'] ) ||
	( $_FILES["file"]["error"] == 4)) {
		$msg = "Please specify a .csv file to upload.";
	}
	else if ( $_FILES["file"]["error"] > 0 ) {
		$msg = "Error uploading: " . $_FILES["file"]["error"];
	}
	elseif( !preg_match("/\.csv$/i", $_FILES['file']['name'])){
		$msg = "Error: <i>" . $_FILES['file']['name']. "</i> is not a .csv file." ;
	}
	elseif (( $_FILES["file"]["size"] ) > $max_file_size ) {
		$msg = "Error: the file is too big (more than $max_file_size bytes).";
	}
	else {
		$enc = $_POST['enc'];
		$filename = $_FILES['file']['name'];
		$local_filename = $_FILES['file']['tmp_name'];
		$table = $_POST['table'];
		$msg = '';

		ini_set( "auto_detect_line_endings", true ); // handle \r Mac line slew

		// open the uploaded file
		// and a temp output file for loading the database;
		// convert the uploaded file line-by-line
		$filename2 = $local_filename . '2' ;
		if ( false === ( $fh = fopen( $local_filename, "rb" ))) {
			$msg .= "Error opening uploaded $local_filename" . '<br>
			';
		}
		elseif ( false === ( $fh2 = fopen( $filename2, 'wb'))) {
			$msg .= "Error creating temp file $filename2" . '<br>
			';
		}
		else {
			if ( 'utf8' == $enc ) {	// skip optional byte-order-mark in utf-8 file
				$bom = fread( $fh, 3 );
				if ( b'\xEF\xBB\xBF' != $bom ) {
					rewind( $fh );
				}
			}

			// process (the remainder of) the file line-by-line
			$lineno = 0;
			$status = true;
			while( $status && (false != ( $line = fgets( $fh )))) {

				$line_cell_array = quotesplit( $line );

				if ( 0 == $lineno ) {
					// the top line has the column names
					$column_list = $line_cell_array;

					$db_col_str = $mdb->mdb_get_col_names( $table );
					if( ! $db_col_str ) {
						$msg .= ( "Error: failed to access $table:" .
						'<br>' .
						$mdb->mdb_error() . '<br>' );
						$status = false;
					} else {
						$file_cols = implode( ',', $column_list );
						$db_cols = implode( ',', quotesplit( $db_col_str ));
						if ( $file_cols != $db_cols ) {
							$msg .= "Error: the table has these columns" .
							'<br>' . $db_cols . '<br>' .
							"but the columns described in the file don't match:" .
							'<br>' . $file_cols . '<br>';
							$status = false;
						}
					}
				} else {

					// write canonical csv format output with Microsoft-style slew
					if ( false == fputcsv( $fh2, $line_cell_array) ||
						( -1 == fseek( $fh2, -1, SEEK_CUR )) ||
						( 2 != fwrite( $fh2, "\r\n", 2 ))) {
						$msg .= ( "Error writing $filename2 at line $lineno" .
						'<br>' );
						$status = false;
					}
				}
				++$lineno;
			}
			fclose( $fh );
			fclose( $fh2 );

			if( $status && ( 2 > $lineno )) {
				$msg .= "Error: the .csv file is empty." . '<br>';
				$status = false;
			}

			// finally, tell the database to load the table
			if ( $status && ! load_db( $filename2, $enc, $table, $column_list )) {
				$status = false;
			}
			if ( $fh2 ) {		// don't fill the disk with old tempfiles
				unlink( $filename2 );
			}
			if ( $status ) {
				$msg .= ( "Info: We succesfully processed $lineno lines " .
				"from $filename (including the initial column list)." );
			}
		}
	}
}


// ---------------- HTML page generator: display the upload form -----------

$title = 'Upload a .csv file to the database';
$page->top( $title );

echo( '<center><font size=4>' . $title . '</font></center>
' );
echo( '<center>' . $you_are . '</center>
' );
echo( '<p>' . $description . '
</p>' );

echo(
	'<p>
	<form action="' . $_SERVER['SCRIPT_NAME'] .
		'" method="post"
		enctype="multipart/form-data">' .
	"Select a database table to update:" . '<br>
		<select name="table">
			<option value="translations"' . preselect( 'table', 'translations' ) . '>' .
		"translations (for non-English language users)" . '</option>
			<option value="roads"' . preselect( 'table', 'roads') . '>' .
		"roads (names of local roads)" . '</option>
			<option value="users"' . preselect( 'table', 'users') . '>' .
		"users (the membership list)" . '</option>
		</select>
		<br><br>' .
	"Specify a .csv file to upload to the table:" . '<br>
		<input type="hidden" name="MAX_FILE_SIZE" value="' . $max_file_size . '">
		<input type="file" name="file" size="60">
		<br><br>' .
	"Select the encoding scheme for accented characters used in the .csv file:" . '<br>
		<select name=enc>
			<option value="cp850"' . preselect( 'enc', 'cp850') . '>' .
		"cp850 (DOS western euro codepage)" . '</option>
			<option value="latin1"' . preselect( 'enc', 'latin1') . '>' .
		"latin1 ANSI cp1252 (Windows western euro codepage)" . '</option>
			<option value="utf8"' . preselect( 'enc', 'utf8') . '>' .
		"utf8" . '</option>
		</select>
		<br><br>
		<input type="submit" name="submit" value="' .
	"Upload the file" .
		'"><br>
	</form>
	</p>
	' );

if ( isset( $_POST['submit'] )) {
	if (! empty( $msg )) {
		echo( '<p><b>' . $msg . '</b></p>' );
	}
}

$page->bottom();
?>

