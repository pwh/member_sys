<?php
$admin_only_page = true;			// only users having i_administrator allowed
require ( '../lib/mem_only.php' );	// ... who are also members in good standing
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>admin-only page</title>
</head>
<body>
<hr/>
<p><b>Yes! You are signed in with the i_administrator privilege.</b>
</p>
<hr/>
<p>This is an example <i>admin</i> web page. It is a <i>members-only</i> page
that also requires flag i_administrator to have value 1 in the user's record
in the database.
</p>
<p>The <i>require</i> statement at the top of this file specifies a relative
location for the file for this page; it must reside in a subdirectory on
the same level as "lib", e.g. the "admin" subdirectory.
</p>
</body>
</html>
