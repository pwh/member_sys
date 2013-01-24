<?php
/// configuration options for installing this application

// (the following initializations probably belong elsewhere...)
ini_set( 'default_charset', 'UTF-8');	// ensure we use utf8 encoding
ini_set( 'session.cache_limiter', 'nocache' ); // dynamic content: don't cache

DEFINE( 'MEM_APP_ROOT', '/apps/member_sys/' ); /// location of app's PHP scripts

/// MEM_DEBUG enables debugging text on output pages.
/// MEM_DEBUG must be *false* in the production environment!!
DEFINE( 'MEM_DEBUG', true );

/// database access (load MySQL schema mem_sys.sql to set up database)
DEFINE( 'MEM_DATABASE_HOST', 'localhost' );
DEFINE( 'MEM_DATABASE_USER', 'mem_user' );
DEFINE( 'MEM_DATABASE_NAME', 'mem_db' );
DEFINE( 'MEM_DATABASE_PASSWORD', 'mem_pass' );

/// html background color of forms
DEFINE( 'MEM_FORM_COLOR', '#3399ff' );

/// field sizes (maintenance note: max sizes also appear in database schema)
DEFINE( 'MEM_NAME_MINSIZE', 2 );		/// min size limit for name fields
DEFINE( 'MEM_NAME_MAXSIZE', 60 );		/// max size limit for name fields
DEFINE( 'MEM_PASSWORD_MINSIZE', 8 );	/// min size for passphrase
DEFINE( 'MEM_PASSWORD_MAXSIZE', 60 );	/// max size of for passphrase
										///   (PasswordHash needs short strings)
DEFINE( 'MEM_USERNAME_MINSIZE', 4 );	/// min size limit for username fields

/// true=> browser must use https://, for cookies; also forces https:
/// for all internal redirects (https requires a cert)
DEFINE( 'MEM_REQUIRE_HTTPS', false );


/// session = a period of time while the app keeps track of the fact
/// that a specific user is signed in, using a browser cookie
DEFINE( 'MEM_SES_COOKIE', 'mem_ses' );	/// name of app's session cookie
DEFINE( 'MEM_SESSION_TIMEOUT', '02:00:00' );/// session max hours:minutes:seconds
DEFINE( 'MEM_COOKIE_PATH', '/' );	    /// top webserver directory for this app

DEFINE( 'MEM_USR_COOKIE', 'mem_username' ); /// optional cookie for username

/// where we'll put temporary files (note: not /tmp, because the webserver
/// can't unlink files there after MySQL creates them with a different uid)
DEFINE( 'MEM_TMPDIR', '/tmp/mem_sys' );
