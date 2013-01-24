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
This admin-only page downloads the contents of a table from our application
database into a .csv file. The file is suitable for later use with a
spreadsheet program, and is also suitable for use with the mem_upload page.
HEREDOC;

$admin_only_page = true;			// only users having i_administrator allowed
require( '../lib/mem_only.php' );	// administrator must log in

$page = new HtmlPage;

$msg = '';							// output message for user

/// if the user previously selected some option in a select list,
/// on the form, preselect that option when re-displaying the form
/// @param name string name of the select list
/// @param option string value of the option to test
/// @return string to select the option, or an empty string
function preselect( $name, $option ) {
	return ( isset( $_POST[$name] ) && ( $_POST[$name] == $option )) ?
 		' selected="selected"' : '' ;
}

/// dump the specified table from the database as a Windows Excel-style .csv file
/// @param filename string server-side path to temp output file
/// @param enc string name of a character encoding for MySQL
/// @param mdb MemDb the database
/// @param table string name of the database table to dump
/// @param msg where to append error message if an error occurs
/// @return true for success
/// @note: This would be simpler if it used SELECT * INTO OUTFILE, but cheap
/// hosting providers disable that option as a security measure. (They forbid
/// the FILE privilege that it requires.)
function output_table( $filename, $enc, &$mdb, $table, &$msg ) {
	$ret = true;

	// open the file
	if( false === ( $out = fopen( $filename, 'x' ))) {
		$msg .= "Error opening temp file $filename";
		$ret = false;
	}
	if( $ret )
		$ret = ( $mdb->mdb_connect() && $mdb->set_charset( $enc ));

	if( $ret ) {					// query the table
 		$result = $mdb->query( "SELECT * FROM $table" );
 		$ret = false !== $result;
	}

	while( $ret && ( $row = $result->fetch_row()) ) {	// get the next row

		// output row; replace newline with Microsoft line slew
		if( ( false === fputcsv( $out, $row )) ||
		( 0 != fseek( $out, -1, SEEK_CUR )) ||
		( false ===  fwrite( $out, "\r\n" )) ) {
			$msg .= "Error writing temp file $filename";
			$ret = false;
		}
	}
	if( $result )
		$result->free();

	// report any database error
	if( ( !$ret ) && ( $mdb->connect_error || $mdb->error )) {
		$msg .= ( 'Database error: ' . $mdb->mdb_error() );
	}

	$mdb->close();					// don't reuse connection w modified charset
	if( $out )
		fclose( $out );				// close the output file
	return $ret;
}

/// tell the browser to prompt the user for a download location
/// and feed a file to the browser; the file is then DELETED and
/// the script EXITS.
/// @param top_row string the first .csv row, containing column names
/// @param filename string fully-qualified path of server-side file
/// @return returns only if the supplied file cannot be read
/// @see google "php" "download a file"
/// @note to work with really large files, this should use Accept-ranges
/// as described at
/// http://w-shadow.com/blog/2007/08/12/how-to-force-file-download-with-php/
/// and the page would need some kind of progress bar.
function download_file( $top_row, $filename )  {

	if ( is_readable( $filename ) && ( $fh = fopen( $filename, 'rb' ))) {
		// (just let open failures dump a PHP warning message)

		// required for IE, otherwise Content-Disposition may be ignored
		$compression = ini_get( 'zlib.output_compression' );
		if( $compression )
			ini_set( 'zlib.output_compression', 'Off' );

		header( 'Content-type: application/octet-stream' );

		// use 'attachment' header to make browser prompt for download
		header( 'Content-Disposition: attachment; filename="' .
				basename( $filename ) . '"' );
		header( 'Content-length: '. (strlen( $top_row ) + filesize( $filename )));

		// these headers deal with various nightmares caused by
		// too-helpful caching proxies and browsers of all vintages
		header( 'Content-Description: File Transfer' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );

		echo( $top_row );
		while( ! feof( $fh )) {
			$buf = fread( $fh, 4096 );
			if ( !empty( $buf )) {
				echo( $buf );
				@ob_flush();
				@flush();
			}
		}
		fclose( $fh );
		unlink( $filename );		// don't fill the disk with temp files
		if( $compression )			// restore compression setting
			ini_set( 'zlib.output_compression', 'On' );

		exit;						// QUIT! anything else can download junk
	}
}

if( isset( $_POST['submit'] )) {

	$table = (isset( $_POST['table'] )) ? $_POST['table'] : '';
	if ( empty( $table )) {
		$msg = "Please select a database table to download.<br>";
	}else {
		$enc = $_POST['enc'];		// get character encoding

		$status = true;				// assume success


		// make a name for the file we'll be downloading
		$timestamp = date( 'YmdHis');	// yyyymmddhhmmss
		$filename = MEM_TMPDIR . "/${table}_${timestamp}_$enc.csv";

		// get column names row for the .csv file
		$top_row = $mdb->mdb_get_col_names( $table );
		if ( ! $top_row ) {
			$msg .= ( "Error: failed to get column names for <i>$table</i>:" . '<br>' .
					$mdb->mdb_error() . '<br>' );
			$status = false;
		} else {
			$top_row .= "\r\n";		// append DOS line slew
		}

		// dump the table into the temporary file
		if ( $status && ! output_table( $filename, $enc, $mdb, $table, $msg  )) {
			$status = false;
		}

		if( $status ) {
			download_file( $top_row, $filename );
			// note that the function only returns here if it fails
			$status = false;
			$msg .= "Error in download_file - strange...";
		}

		@unlink( $filename );		// don't fill the disk with tempfiles
	}
}




// ---------------- HTML page generator: display the download form ---------

$title = 'Download a database table';
$page->top( $title );

echo( '<center><font size=4>' . $title . '</font></center>
' );
echo( '<center>' . $you_are . '</center>
' );
echo( '<p>' . $description . '
</p>' );
echo( '<p>Note: take care that the encoding selected here is the encoding used
by your spreadsheet program.
</p>' );

echo(
	'<p>
	<form action="' . $_SERVER['SCRIPT_NAME'] .
		'" method="post"
		enctype="multipart/form-data">' .
	"Select a database table to download:" . '<br>
		<select name="table">
			<option value=""/>
			<option value="translations"' . preselect( 'table', 'translations' ) . '>' .
		"translations (for non-English language users)" . '</option>
			<option value="roads"' . preselect( 'table', 'roads') . '>' .
		"roads (names of local roads)" . '</option>
			<option value="users"' . preselect( 'table', 'users') . '>' .
		"users (the membership list)" . '</option>
		</select>
		<br><br>' .
	"Select the encoding scheme for accented characters to be used in the .csv file:" . '<br>
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
	"Download the file" .
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
