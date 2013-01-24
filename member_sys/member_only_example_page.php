<?php require 'lib/mem_only.php'; /* (this must be the first line.) */ ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Signed in</title>
</head>
<body>
<hr>
<center><b>Yes! You are signed in.</b></center>
<hr>
<p>This is an example <i>members only</i> web page. The server will send its
html only if the user has signed in and has paid dues; if not, the user sees a
signin page or a reminder to pay.</p>
<p>To make a plain html page into a members only page, modify its file:</p>
<ol>
<li>Insert this text (as shown at the top of the file for this example) as the
<b>first line</b> of the members-only file:<br>
<font color="maroon">&lt;?php require&nbsp;'lib/mem_only.php'; ?&gt;</font><br>
(Important: there must be <b>no space</b> before that initial '&lt;' !)</li>
<li>Change its filename extension from ".htm" or ".html" to ".php"</li>
</ol>
<p>Also, here is a link to demonstrate how a user can sign out:<br>
To sign out, click <a href="mem_signout.php">here</a>.</p>
</body>
</html>
