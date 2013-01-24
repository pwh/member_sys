<?php
/// This module removes the effects of the ill-conceived magic-quotes
/// feature of PHP. See "Why not to use magic quotes" in the PHP manual,
/// and the code in a comment there by "Albin Mrtensson"
if ( get_magic_quotes_gpc()) {
    function strip_array( $var ) {
        return is_array( $var ) ?
        array_map( "strip_array", $var ) : stripslashes( $var );
    }

    $_POST = strip_array($_POST);
    $_SESSION = strip_array($_SESSION);
    $_GET = strip_array($_GET);
}
