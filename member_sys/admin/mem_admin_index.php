<?php
$admin_only_page = true;			// only users having i_administrator allowed
require ( '../lib/mem_only.php' );	// ... who are also members in good standing

$page = new HtmlPage;
$page->top( 'Membership system administrator links' );
?>
<hr>
<center>
This is a quick guide to the web pages for managing membership data, with links.
</center>
<hr>
<p>As a registered user having the 'admin' privilege, you are permitted to
access several restricted pages for managing the system.
In addition, several other pages behave differently when the user is an
administrator who is adding or modifying another user's profile.
&nbsp;<a href="#multilingual">¹</a>
</p>
<p>For example, the page for registering new users does not let you set
a password.
Instead, once you click the <i>Register</i> button, a <i>Notify</i> link
appears; clicking that launches script mem_pass_reset.
This includes a <i>mailto:</i> link that creates an email message for you to
send to the new user showing how to activate the account and set the password.
&nbsp;<a href="#mailto">²</a>
</p>
<p>When users contact you about forgotten passwords, you
can use the following link to send them the same kind of email notice:
<blockquote>
<a href="mem_select_user.php?continue=/admin/mem_pass_reset.php">
Reset a user's password</a>
</blockquote>

That link activates script mem_select_user to let you find the user's data.
A <i>Continue</i> link on its page takes you on to mem_pass_reset
(already described above).
The app uses the same "select user &amp; continue" scheme whenever an
administrator needs to handle a user's data:
<blockquote>
<a href="mem_select_user.php?continue=/mem_status.php">
Show user status</a><br>
<a href="mem_select_user.php?continue=/admin/mem_dues.php">
Log dues payment</a><br>
<a href="mem_select_user.php?continue=/mem_profile.php">
Update main profile fields</a><br>
<a href="mem_select_user.php?continue=/mem_profile_xtra.php">
Update optional contact info</a>
</blockquote>

The next links open pages that let you dump tables from the database to
.csv files on your computer and reload the tables from your
.csv files.
(Please be sure you fully understand that upload operation;
it can discard recent updates or trash a table!)
You might download the translations table to a .csv, import that to
a spreadsheet editor like Excel, correct french
translations there offline, then export the result to another .csv
and upload back to the database.
Also, you could download the user table
periodically as a crude backup of the profiles, or as the basis for creating
a membership list spreadsheet.

<blockquote><a href="mem_download.php">Download a table</a><br>
<a href="mem_upload.php">Upload a table</a>
</blockquote>

The next uploader script is very different; it parses a .csv file in a pre-2012
format and <i>updates</i> rows for affected users in the user table:

<blockquote>
<a href="mem_load_oldstyle.php">Update users old-style</a>
</blockquote>

The application includes a configuration file named lib/mem_config.php which
controls various configuration options such as the password for the database.
The web server should be configured to prevent users from seeing this and the
other scripts.

<p>A site for which security is important might consider configuration
option MEM_REQUIRE_HTTPS. Setting that to true makes the browser use
encryption to protect the app's passwords and session identifiers.
Using this with Internet Explorer introduces a requirement for the web
site to publish a
<a href="http://www.sitepoint.com/p3p-cookies-ie6/">P3P privacy policy</a>.
</p>





<br><br>
<p>Notes
</p>
<p><a id="multilingual">1: As a measure of respect for our many
neighbors who use this site, all user-facing pages have been engineered to be
multilingual.
The app currently includes english and limited french text.
Messages that start with a spurious "F" still need french translations, and
any having 'fr!' indicate missing rows in our translation table.
Note that the admin pages currently support English only.</a>
</p>
<p><a id="mailto">2: Script mem_pass_reset uses html's <i>mailto:</i>
scheme, which requires support in your browser configuration.
It works fine with local email clients like Thunderbird, and I understand
it can be made to work with Gmail.</a>
</p>
<?php
$page->bottom();
?>
