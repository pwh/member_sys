<?php
/*****
 *  Function str_getcsv() is implemented in PHP version 5.3.
 * This is a replacement version of the function for use with
 * earlier versions. It appears in a comment on the PHP manual
 * page for split(). )
 *
 * moritz 09-Apr-2004 04:54
 * Often you want to split CSV-Like data, so this is the function for this :)
 *****/
function quotesplit($s)
{
    $r = Array();
    $p = 0;
    $l = strlen($s);
    while ($p < $l) {
        while (($p < $l) && (strpos(" \r\t\n",$s[$p]) !== false)) $p++;
        if ($s[$p] == '"') {
            $p++;
            $q = $p;
            while (($p < $l) && ($s[$p] != '"')) {
                if ($s[$p] == '\\') { $p+=2; continue; }
                $p++;
            }
            $r[] = stripslashes(substr($s, $q, $p-$q));
            $p++;
            while (($p < $l) && (strpos(" \r\t\n",$s[$p]) !== false)) $p++;
            $p++;
        } else if ($s[$p] == "'") {
            $p++;
            $q = $p;
            while (($p < $l) && ($s[$p] != "'")) {
                if ($s[$p] == '\\') { $p+=2; continue; }
                $p++;
            }
            $r[] = stripslashes(substr($s, $q, $p-$q));
            $p++;
            while (($p < $l) && (strpos(" \r\t\n",$s[$p]) !== false)) $p++;
            $p++;
        } else {
            $q = $p;
            while (($p < $l) && (strpos(",;",$s[$p]) === false)) {
                $p++;
            }
            $r[] = stripslashes(trim(substr($s, $q, $p-$q)));
            while (($p < $l) && (strpos(" \r\t\n",$s[$p]) !== false)) $p++;
            $p++;
        }
    }
    return $r;
}
