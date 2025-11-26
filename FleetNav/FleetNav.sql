/*
SQLyog Community v13.3.0 (64 bit)
MySQL - 10.4.32-MariaDB : Database - fleetnav
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`fleetnav` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `fleetnav`;

/*Table structure for table `accounts` */

DROP TABLE IF EXISTS `accounts`;

CREATE TABLE `accounts` (
  `accountID` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(50) DEFAULT NULL,
  `firstName` varchar(50) DEFAULT NULL,
  `lastName` varchar(50) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `accountType` varchar(25) DEFAULT NULL,
  `contactNo` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(100) DEFAULT NULL,
  `profileImg` longblob DEFAULT NULL,
  PRIMARY KEY (`accountID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `accounts` */

/*Table structure for table `action_logs` */

DROP TABLE IF EXISTS `action_logs`;

CREATE TABLE `action_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `accountID` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_details` text DEFAULT NULL,
  `TIMESTAMP` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `accountID` (`accountID`),
  CONSTRAINT `action_logs_ibfk_1` FOREIGN KEY (`accountID`) REFERENCES `accounts` (`accountID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `action_logs` */

/*Table structure for table `deliveries` */

DROP TABLE IF EXISTS `deliveries`;

CREATE TABLE `deliveries` (
  `deliveryID` int(11) NOT NULL AUTO_INCREMENT,
  `productName` varchar(100) DEFAULT NULL,
  `productDescription` varchar(100) DEFAULT NULL,
  `assignedTruck` int(11) DEFAULT NULL,
  `origin` varchar(200) DEFAULT NULL,
  `destination` varchar(200) DEFAULT NULL,
  `estimatedTimeOfArrival` datetime DEFAULT NULL,
  `deliveryStatus` varchar(15) DEFAULT 'Inactive',
  PRIMARY KEY (`deliveryID`),
  KEY `assignedTruck` (`assignedTruck`),
  CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`assignedTruck`) REFERENCES `trucks` (`truckID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `deliveries` */

/*Table structure for table `trucks` */

DROP TABLE IF EXISTS `trucks`;

CREATE TABLE `trucks` (
  `truckID` int(11) NOT NULL AUTO_INCREMENT,
  `truckName` varchar(50) DEFAULT NULL,
  `plateNumber` varchar(15) DEFAULT NULL,
  `truckStatus` varchar(15) DEFAULT 'Available',
  `odometerOrMileage` int(11) DEFAULT NULL,
  `registrationDate` date DEFAULT NULL,
  `assignedDriver` int(11) DEFAULT NULL,
  `truckImg` longblob DEFAULT NULL,
  PRIMARY KEY (`truckID`),
  KEY `assignedDriver` (`assignedDriver`),
  CONSTRAINT `trucks_ibfk_1` FOREIGN KEY (`assignedDriver`) REFERENCES `accounts` (`accountID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*Data for the table `trucks` */

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
