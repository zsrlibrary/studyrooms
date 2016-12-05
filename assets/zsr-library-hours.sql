--
-- Database: `zsr_hours`
--

# CREATE DATABASE `zsr_hours`;

-- --------------------------------------------------------

--
-- Table structure for table `library_hours`
--

CREATE TABLE IF NOT EXISTS `library_hours` (
  `date` date NOT NULL DEFAULT '0000-00-00',
  `hours` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
