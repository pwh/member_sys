-- phpMyAdmin SQL Dump
-- version 3.4.5deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 26, 2012 at 12:27 AM
-- Server version: 5.1.62
-- PHP Version: 5.3.6-13ubuntu3.6

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/* event scheduler will trim the sessions table periodically (requires priv) */
-- SET @@global.event_scheduler=ON;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: '_mem_sys'
--
/* ************ (requires privilege)
DROP DATABASE _mem_sys;
CREATE DATABASE _mem_sys DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE _mem_sys;
 ************* */

-- --------------------------------------------------------

--
-- Table structure for table 'roads'
--

DROP TABLE IF EXISTS roads;
CREATE TABLE roads (
  i_roadid tinyint(3) unsigned NOT NULL AUTO_INCREMENT COMMENT 'unique id for a road - NEVER change value',
  s_name varchar(40) NOT NULL COMMENT 'name of a cottage road',
  UNIQUE KEY s_name (s_name),
  UNIQUE KEY i_roadid (i_roadid)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='cottage road names' ;

-- --------------------------------------------------------

--
-- Table structure for table 'sessions'
--

DROP TABLE IF EXISTS sessions;
CREATE TABLE sessions (
  i_userid int(12) unsigned NOT NULL COMMENT 'the session user - foreign key  to users',
  s_value char(80) NOT NULL COMMENT 'hash identifying this session',
  i_active tinyint(1) NOT NULL COMMENT 'bool: user is signed in',
  i_time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'timestamp of session start',
  PRIMARY KEY (s_value),
  KEY i_userid (i_userid)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='this logs signin times and frequencies per user' ;

-- --------------------------------------------------------

--
-- Table structure for table 'translations'
--

DROP TABLE IF EXISTS translations;
CREATE TABLE translations (
  s_notes varchar(80) NOT NULL COMMENT 'notes- do not translate',
  s_en varchar(255) NOT NULL COMMENT 'key to lookup translated text - per PHP code',
  s_fr varchar(255) NOT NULL COMMENT 'translated text',
  PRIMARY KEY (s_en)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='table to lookup translated text using english-language key' ;

-- --------------------------------------------------------

--
-- Table structure for table 'users'
--

DROP TABLE IF EXISTS users;
CREATE TABLE users (
  i_userid int(12) unsigned NOT NULL AUTO_INCREMENT COMMENT 'unchangable identifier for a user',
  s_lastname varchar(60) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'surname (or entire name) for sorting',
  s_firstname varchar(60) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'given name, optional',
  s_username varchar(60) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'username (or phrase) for signin (utf8-bin to be case-sensitive)',
  s_lang char(2) NOT NULL DEFAULT 'en' COMMENT 'preferred language per ISO639-1 (fr,en,..)',
  i_in_directory tinyint(1) NOT NULL DEFAULT '1' COMMENT 'bool true=> publish me in member directory',
  s_passhash char(60) NOT NULL COMMENT 'salted hash of passphase',
  i_change_pass tinyint(1) NOT NULL DEFAULT '0' COMMENT 'true=> new passphrase required',
  i_administrator tinyint(1) NOT NULL DEFAULT '0' COMMENT 'true=> this user may use admin-only web pages',
  s_civicnum varchar(60) NOT NULL DEFAULT '' COMMENT 'cottage civic address number',
  i_roadid tinyint(3) NOT NULL DEFAULT '0' COMMENT 'foreign key for table ''roads''',
  s_mail_address varchar(60) NOT NULL DEFAULT '' COMMENT 'mailing address: number + street name',
  s_mail_city varchar(60) NOT NULL DEFAULT '' COMMENT 'mailing address: city name',
  s_mail_provincestate varchar(60) NOT NULL DEFAULT '' COMMENT 'mailing address: province/state',
  s_mail_postalcode varchar(60) NOT NULL DEFAULT '' COMMENT 'mailing address: postal/zip code',
  s_mail_country varchar(60) NOT NULL COMMENT 'country for mail (if not Canada)',
  i_snailmail tinyint(1) NOT NULL DEFAULT '0' COMMENT 'bool true to send snailmail',
  s_phone1 varchar(60) NOT NULL DEFAULT '' COMMENT 'phone number e.g. home',
  s_phone2 varchar(60) NOT NULL DEFAULT '' COMMENT 'other phume number e.g. work',
  i_phone_private tinyint(1) NOT NULL DEFAULT '0' COMMENT 'future use; true=> do not publish phone1/phone2',
  s_cott_phone varchar(60) NOT NULL DEFAULT '' COMMENT 'cottage phone number',
  i_cott_phone_private tinyint(1) NOT NULL DEFAULT '0' COMMENT 'future use; true=> don''t publish s_cott_phone',
  s_email1 varchar(60) NOT NULL DEFAULT '' COMMENT 'primary email address',
  s_email2 varchar(60) NOT NULL DEFAULT '' COMMENT 'other email address',
  i_email_private tinyint(1) NOT NULL DEFAULT '0' COMMENT 'future use; true=> don''t publish email1/2',
  s_date_joined timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'date when user first registered',
  s_date_of_payment timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'date of last dues payment',
  i_membership_year smallint(4) unsigned NOT NULL DEFAULT '0' COMMENT 'highest year for which dues were paid',
  s_comment varchar(60) NOT NULL COMMENT 'comment for administrators',
  PRIMARY KEY (i_userid),
  UNIQUE KEY s_username (s_username),
  UNIQUE KEY s_fullname (s_lastname,s_firstname)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COMMENT='per-user attributes; fullname is CS for insert&update' ;

DELIMITER $$
--
-- Events
--
DROP EVENT IF EXISTS `trim_sessions`$$
CREATE EVENT trim_sessions ON SCHEDULE EVERY 1 DAY STARTS '2012-09-19 14:59:20' ON COMPLETION NOT PRESERVE ENABLE COMMENT 'once a day delete prior day sessions' DO DELETE LOW_PRIORITY FROM _mem_sys.sessions WHERE TIMESTAMPDIFF(DAY,i_time,NOW) > 1$$

DELIMITER ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
