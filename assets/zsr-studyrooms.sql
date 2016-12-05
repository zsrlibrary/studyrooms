--
-- Database: `zsr_studyrooms`
--

# CREATE DATABASE `zsr_studyrooms`;

-- --------------------------------------------------------

--
-- Table structure for table `reservation`
--

CREATE TABLE `reservation` (
  `reservation_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `start_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `end_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `session_id` int(11) NOT NULL,
  `reminder` varchar(25) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reservation_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_name` varchar(100) NOT NULL DEFAULT '',
  `equipment` varchar(255) NOT NULL DEFAULT '',
  `location` varchar(50) NOT NULL DEFAULT '',
  `capacity` varchar(10) NOT NULL DEFAULT '',
  `info_url` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `offline_startdate` date DEFAULT NULL,
  `offline_enddate` date DEFAULT NULL,
  PRIMARY KEY (`room_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `session`
--

CREATE TABLE `session` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `notes` text NOT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_meta`
--

CREATE TABLE `user_meta` (
  `username` varchar(50) NOT NULL DEFAULT '',
  `meta_key` varchar(100) NOT NULL DEFAULT '',
  `meta_value` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`username`,`meta_key`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
