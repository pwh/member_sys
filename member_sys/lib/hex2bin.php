<?php
if( !function_exists( 'hex2bin' )) {

	 /// hextobin() converts the hex representation of data to binary.
	 /// It is based on code by "azizsaleh at gmail dot com" per
	 /// http://www.php.net/manual/en/function.hex2bin.php
	 /// @param   string  $str Hexadecimal representation of data
	 /// @return  string The binary representation of the given data
	function hex2bin( $data )
	{
		$nn = strlen( $data ) - 1;	// subtract 1 to avoid array-bounds error
		$bin = "";
		$ii = 0;
		do {
			$bin .= chr( hexdec( $data{$ii}.$data{( $ii + 1 )}));
			$ii += 2;				// step to next 2-byte hex value
		} while ( $ii < $nn );

		return $bin;
	}
}
