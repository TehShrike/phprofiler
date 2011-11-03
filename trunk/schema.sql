/*
SQLyog Enterprise v8.71 
MySQL - 5.1.47-log : Database - phprofiler
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*Table structure for table `page` */

CREATE TABLE `page` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(50) NOT NULL,
  `file` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `domain_file` (`domain`,`file`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Table structure for table `page_load` */

CREATE TABLE `page_load` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int(10) unsigned NOT NULL,
  `precision` tinyint(3) unsigned NOT NULL,
  `run_time` int(10) unsigned NOT NULL,
  `start` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Table structure for table `section` */

CREATE TABLE `section` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` int(10) unsigned NOT NULL,
  `name` varchar(50) NOT NULL,
  `parent_section_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_parent_name` (`page_id`,`parent_section_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*Table structure for table `section_load` */

CREATE TABLE `section_load` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_load_id` int(10) unsigned NOT NULL,
  `section_id` int(10) unsigned NOT NULL,
  `detail` varchar(50) NOT NULL,
  `run_time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
