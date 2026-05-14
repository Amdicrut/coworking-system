/*
SQLyog Trial v13.1.9 (64 bit)
MySQL - 10.4.32-MariaDB : Database - coworking_db
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`coworking_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `coworking_db`;

/*Table structure for table `audit_log` */

DROP TABLE IF EXISTS `audit_log`;

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_ip` varchar(45) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_action_date` (`action`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `audit_log` */

insert  into `audit_log`(`id`,`user_ip`,`action`,`entity_type`,`entity_id`,`old_data`,`new_data`,`created_at`) values 
(1,'192.168.1.1','CREATE_CLIENT','clients',1,NULL,'{\"full_name\":\"Иван Петров\"}','2026-03-29 22:19:00'),
(2,'192.168.1.1','START_VISIT','visits',1,NULL,'{\"client_id\":1,\"start_time\":\"2026-04-28 09:00:00\"}','2026-04-27 22:19:00'),
(3,'192.168.1.2','STOP_VISIT','visits',1,NULL,'{\"total_amount\":850}','2026-04-27 22:19:00'),
(4,'192.168.1.1','UPDATE_TARIFF','tariffs',1,NULL,'{\"price\":200}','2026-04-21 22:19:00'),
(5,'192.168.1.3','CREATE_CLIENT','clients',18,NULL,'{\"full_name\":\"Евгений Соловьёв\"}','2026-04-03 22:19:00'),
(6,'192.168.1.1','DELETE_CLIENT','clients',12,NULL,'{\"reason\":\"Неактивен\"}','2026-04-08 22:19:00'),
(7,'192.168.1.2','UPDATE_SERVICE','extra_services',1,NULL,'{\"price\":120}','2026-04-23 22:19:00'),
(8,'192.168.1.1','GENERATE_FORECAST','load_forecasts',NULL,NULL,'{\"days\":7}','2026-04-26 22:19:00'),
(9,'192.168.1.1','APPLY_DYNAMIC_PRICE','dynamic_prices',1,NULL,'{\"multiplier\":1.3}','2026-04-26 22:19:00'),
(10,'192.168.1.3','CREATE_BOOKING','bookings',1,NULL,'{\"client_id\":1,\"workspace_id\":6}','2026-04-25 22:19:00');

/*Table structure for table `bookings` */

DROP TABLE IF EXISTS `bookings`;

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `workspace_id` int(11) DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `total_amount` decimal(10,2) DEFAULT NULL,
  `paid` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `workspace_id` (`workspace_id`),
  KEY `idx_date_status` (`booking_date`,`status`),
  KEY `idx_client` (`client_id`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `bookings` */

/*Table structure for table `clients` */

DROP TABLE IF EXISTS `clients`;

CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `total_visits` int(11) DEFAULT 0,
  `total_hours` decimal(10,2) DEFAULT 0.00,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `last_visit_date` datetime DEFAULT NULL,
  `r_score` tinyint(4) DEFAULT 1,
  `f_score` tinyint(4) DEFAULT 1,
  `m_score` tinyint(4) DEFAULT 1,
  `rfm_segment` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  KEY `idx_phone` (`phone`),
  KEY `idx_rfm` (`r_score`,`f_score`,`m_score`),
  KEY `idx_last_visit` (`last_visit_date`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `clients` */

insert  into `clients`(`id`,`full_name`,`phone`,`email`,`birthday`,`registration_date`,`total_visits`,`total_hours`,`total_spent`,`last_visit_date`,`r_score`,`f_score`,`m_score`,`rfm_segment`,`is_active`,`notes`,`created_at`,`updated_at`) values 
(1,'Иван Петров','+79001234567','ivan@example.com',NULL,'2026-04-28 22:18:51',25,0.00,15000.00,'2026-04-28 22:18:51',3,3,3,'Champions',1,NULL,'2026-04-28 22:18:51','2026-04-28 22:18:51'),
(2,'Мария Сидорова','+79007654321','maria@example.com',NULL,'2026-04-28 22:18:51',12,0.00,8000.00,'2026-04-28 22:18:51',3,2,2,'Loyal',1,NULL,'2026-04-28 22:18:51','2026-04-28 22:18:51'),
(3,'Алексей Иванов','+79009876543','alex@example.com',NULL,'2026-04-28 22:18:51',5,0.00,3000.00,'2026-04-13 22:18:51',2,2,1,'Potential',1,NULL,'2026-04-28 22:18:51','2026-04-28 22:18:51'),
(4,'Елена Смирнова','+79005556677','elena@example.com',NULL,'2026-04-28 22:18:51',2,0.00,800.00,'2026-03-14 22:18:51',1,1,1,'At Risk',1,NULL,'2026-04-28 22:18:51','2026-04-28 22:18:51'),
(5,'Дмитрий Козлов','+79001112233','dmitry@example.com',NULL,'2026-04-28 22:18:51',30,0.00,25000.00,'2026-04-28 22:18:51',3,3,3,'Champions',1,NULL,'2026-04-28 22:18:51','2026-04-28 22:18:51');

/*Table structure for table `dynamic_prices` */

DROP TABLE IF EXISTS `dynamic_prices`;

CREATE TABLE `dynamic_prices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tariff_id` int(11) DEFAULT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `hour_of_day` tinyint(4) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `dynamic_price` decimal(10,2) NOT NULL,
  `multiplier` decimal(3,2) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `applied_from` date DEFAULT NULL,
  `applied_to` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tariff_id` (`tariff_id`),
  KEY `idx_slot` (`day_of_week`,`hour_of_day`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `dynamic_prices_ibfk_1` FOREIGN KEY (`tariff_id`) REFERENCES `tariffs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `dynamic_prices` */

insert  into `dynamic_prices`(`id`,`tariff_id`,`day_of_week`,`hour_of_day`,`base_price`,`dynamic_price`,`multiplier`,`reason`,`applied_from`,`applied_to`,`is_active`,`created_at`) values 
(1,1,2,12,200.00,260.00,1.30,'Высокая загрузка','2026-04-29',NULL,1,'2026-04-28 22:19:00'),
(2,1,2,13,200.00,260.00,1.30,'Высокая загрузка','2026-04-29',NULL,1,'2026-04-28 22:19:00'),
(3,1,2,14,200.00,260.00,1.30,'Высокая загрузка','2026-04-29',NULL,1,'2026-04-28 22:19:00'),
(4,1,2,15,200.00,260.00,1.30,'Высокая загрузка','2026-04-29',NULL,1,'2026-04-28 22:19:00'),
(5,1,3,12,200.00,260.00,1.30,'Высокая загрузка','2026-04-30',NULL,1,'2026-04-28 22:19:00'),
(6,1,3,15,200.00,260.00,1.30,'Высокая загрузка','2026-04-30',NULL,1,'2026-04-28 22:19:00'),
(7,1,4,10,200.00,140.00,0.70,'Низкая загрузка','2026-05-01',NULL,1,'2026-04-28 22:19:00'),
(8,1,4,15,200.00,140.00,0.70,'Низкая загрузка','2026-05-01',NULL,1,'2026-04-28 22:19:00'),
(9,1,7,13,200.00,140.00,0.70,'Низкая загрузка','2026-05-04',NULL,1,'2026-04-28 22:19:00'),
(10,1,7,14,200.00,140.00,0.70,'Низкая загрузка','2026-05-04',NULL,1,'2026-04-28 22:19:00');

/*Table structure for table `extra_services` */

DROP TABLE IF EXISTS `extra_services`;

CREATE TABLE `extra_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `price_type` enum('fixed','per_hour','per_day') DEFAULT 'fixed',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `extra_services` */

insert  into `extra_services`(`id`,`name`,`description`,`price`,`price_type`,`is_active`,`sort_order`,`created_at`) values 
(1,'Проектор','Аренда проектора',300.00,'fixed',1,1,'2026-04-28 22:18:51'),
(2,'Кофе','Американский кофе',100.00,'fixed',1,2,'2026-04-28 22:18:51'),
(3,'Ланч','Бизнес-ланч',350.00,'fixed',1,3,'2026-04-28 22:18:51'),
(4,'Парковка','Машиноместо',200.00,'per_hour',1,4,'2026-04-28 22:18:51'),
(5,'Конференц-зал','Аренда на час',1000.00,'per_hour',1,5,'2026-04-28 22:18:51'),
(6,'Кофе американо','Американский кофе',120.00,'fixed',1,1,'2026-04-28 22:19:00'),
(7,'Капучино','Кофе с молоком',150.00,'fixed',1,2,'2026-04-28 22:19:00'),
(8,'Латте','Кофе с большим количеством молока',160.00,'fixed',1,3,'2026-04-28 22:19:00'),
(9,'Чай зелёный','Зелёный чай',80.00,'fixed',1,4,'2026-04-28 22:19:00'),
(10,'Чай чёрный','Классический чёрный чай',80.00,'fixed',1,5,'2026-04-28 22:19:00'),
(11,'Круассан','Свежий круассан',120.00,'fixed',1,6,'2026-04-28 22:19:00'),
(12,'Сэндвич','Сэндвич с курицей',250.00,'fixed',1,7,'2026-04-28 22:19:00'),
(13,'Салат Цезарь','Салат с курицей',350.00,'fixed',1,8,'2026-04-28 22:19:00'),
(14,'Проектор','Аренда проектора',500.00,'per_hour',1,9,'2026-04-28 22:19:00'),
(15,'Конференц-зал','Аренда конференц-зала',1000.00,'per_hour',1,10,'2026-04-28 22:19:00'),
(16,'Парковка','Машиноместо',200.00,'per_hour',1,11,'2026-04-28 22:19:00'),
(17,'Принтер','Печать документов',10.00,'per_day',1,12,'2026-04-28 22:19:00'),
(18,'Сканер','Сканирование документов',15.00,'per_day',1,13,'2026-04-28 22:19:00'),
(19,'Шреддер','Уничтожение документов',50.00,'fixed',1,14,'2026-04-28 22:19:00'),
(20,'Индивидуальный стол','Отдельный рабочий стол',300.00,'per_hour',1,15,'2026-04-28 22:19:00');

/*Table structure for table `load_forecasts` */

DROP TABLE IF EXISTS `load_forecasts`;

CREATE TABLE `load_forecasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `forecast_date` date NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `hour_of_day` tinyint(4) NOT NULL,
  `predicted_load` decimal(5,2) DEFAULT NULL,
  `confidence_lower` decimal(5,2) DEFAULT NULL,
  `confidence_upper` decimal(5,2) DEFAULT NULL,
  `recommended_price` decimal(10,2) DEFAULT NULL,
  `price_change_reason` varchar(255) DEFAULT NULL,
  `actual_load` decimal(5,2) DEFAULT NULL,
  `prediction_error` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_forecast_slot` (`forecast_date`,`hour_of_day`),
  KEY `idx_forecast_date` (`forecast_date`),
  KEY `idx_day_hour` (`day_of_week`,`hour_of_day`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `load_forecasts` */

insert  into `load_forecasts`(`id`,`forecast_date`,`day_of_week`,`hour_of_day`,`predicted_load`,`confidence_lower`,`confidence_upper`,`recommended_price`,`price_change_reason`,`actual_load`,`prediction_error`,`created_at`,`updated_at`) values 
(1,'2026-04-29',2,9,35.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(2,'2026-04-29',2,10,55.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(3,'2026-04-29',2,11,75.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(4,'2026-04-29',2,12,85.00,NULL,NULL,260.00,'Высокая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(5,'2026-04-29',2,13,80.00,NULL,NULL,260.00,'Высокая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(6,'2026-04-29',2,14,82.00,NULL,NULL,260.00,'Высокая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(7,'2026-04-29',2,15,88.00,NULL,NULL,260.00,'Высокая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(8,'2026-04-29',2,16,78.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(9,'2026-04-29',2,17,65.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(10,'2026-04-29',2,18,55.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(11,'2026-04-30',3,9,30.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(12,'2026-04-30',3,12,82.00,NULL,NULL,260.00,'Высокая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(13,'2026-04-30',3,15,85.00,NULL,NULL,260.00,'Высокая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(14,'2026-05-01',4,10,25.00,NULL,NULL,140.00,'Низкая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(15,'2026-05-01',4,15,20.00,NULL,NULL,140.00,'Низкая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(16,'2026-05-02',5,11,40.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(17,'2026-05-02',5,14,45.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(18,'2026-05-03',6,12,35.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(19,'2026-05-03',6,15,30.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(20,'2026-05-04',7,13,28.00,NULL,NULL,140.00,'Низкая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(21,'2026-05-04',7,14,25.00,NULL,NULL,140.00,'Низкая загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(22,'2026-05-05',1,11,60.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00'),
(23,'2026-05-05',1,14,70.00,NULL,NULL,200.00,'Стандартная загрузка',NULL,NULL,'2026-04-28 22:19:00','2026-04-28 22:19:00');

/*Table structure for table `load_history` */

DROP TABLE IF EXISTS `load_history`;

CREATE TABLE `load_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_date` date NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `hour_of_day` tinyint(4) NOT NULL,
  `total_seats` int(11) NOT NULL,
  `occupied_seats` int(11) NOT NULL,
  `load_ratio` decimal(5,2) DEFAULT NULL,
  `revenue_from_slot` decimal(10,2) DEFAULT 0.00,
  `new_clients_count` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_datetime` (`record_date`,`hour_of_day`),
  KEY `idx_weekday_hour` (`day_of_week`,`hour_of_day`),
  KEY `idx_date` (`record_date`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `load_history` */

insert  into `load_history`(`id`,`record_date`,`day_of_week`,`hour_of_day`,`total_seats`,`occupied_seats`,`load_ratio`,`revenue_from_slot`,`new_clients_count`,`notes`,`created_at`) values 
(1,'2026-04-28',3,9,50,15,30.00,3000.00,0,NULL,'2026-04-28 22:18:51'),
(2,'2026-04-28',3,10,50,25,50.00,5000.00,0,NULL,'2026-04-28 22:18:51'),
(3,'2026-04-28',3,11,50,35,70.00,7000.00,0,NULL,'2026-04-28 22:18:51'),
(4,'2026-04-28',3,12,50,40,80.00,8000.00,0,NULL,'2026-04-28 22:18:51'),
(5,'2026-04-28',3,13,50,38,76.00,7600.00,0,NULL,'2026-04-28 22:18:51'),
(6,'2026-04-28',3,14,50,42,84.00,8400.00,0,NULL,'2026-04-28 22:18:51'),
(7,'2026-04-28',3,15,50,45,90.00,9000.00,0,NULL,'2026-04-28 22:18:51'),
(8,'2026-04-28',3,16,50,40,80.00,8000.00,0,NULL,'2026-04-28 22:18:51'),
(9,'2026-04-28',3,17,50,35,70.00,7000.00,0,NULL,'2026-04-28 22:18:51'),
(10,'2026-04-28',3,18,50,28,56.00,5600.00,0,NULL,'2026-04-28 22:18:51'),
(11,'2026-04-28',3,19,50,20,40.00,4000.00,0,NULL,'2026-04-28 22:18:51'),
(12,'2026-04-28',3,20,50,12,24.00,2400.00,0,NULL,'2026-04-28 22:18:51');

/*Table structure for table `rfm_history` */

DROP TABLE IF EXISTS `rfm_history`;

CREATE TABLE `rfm_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `calculation_date` date NOT NULL,
  `days_since_last_visit` int(11) DEFAULT NULL,
  `total_visits_90d` int(11) DEFAULT NULL,
  `total_spent_90d` decimal(10,2) DEFAULT NULL,
  `r_score` tinyint(4) DEFAULT NULL,
  `f_score` tinyint(4) DEFAULT NULL,
  `m_score` tinyint(4) DEFAULT NULL,
  `rfm_segment` varchar(50) DEFAULT NULL,
  `rfm_score_total` tinyint(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_client_date` (`client_id`,`calculation_date`),
  KEY `idx_segment` (`rfm_segment`),
  CONSTRAINT `rfm_history_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `rfm_history` */

insert  into `rfm_history`(`id`,`client_id`,`calculation_date`,`days_since_last_visit`,`total_visits_90d`,`total_spent_90d`,`r_score`,`f_score`,`m_score`,`rfm_segment`,`rfm_score_total`,`created_at`) values 
(1,1,'2026-04-28',3,25,15000.00,3,3,3,'Champions',9,'2026-04-28 22:19:00'),
(2,2,'2026-04-28',3,12,8000.00,3,2,2,'Loyal',7,'2026-04-28 22:19:00'),
(3,3,'2026-04-28',2,5,3000.00,2,2,1,'Potential',5,'2026-04-28 22:19:00'),
(4,4,'2026-04-28',1,2,800.00,1,1,1,'At Risk',3,'2026-04-28 22:19:00'),
(5,5,'2026-04-28',3,30,25000.00,3,3,3,'Champions',9,'2026-04-28 22:19:00');

/*Table structure for table `system_config` */

DROP TABLE IF EXISTS `system_config`;

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('string','int','float','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `system_config` */

insert  into `system_config`(`id`,`config_key`,`config_value`,`config_type`,`description`,`updated_at`) values 
(1,'total_seats','50','int','Общее количество мест','2026-04-28 22:18:51'),
(2,'base_hourly_price','200','int','Базовая цена часа','2026-04-28 22:18:51'),
(3,'peak_load_threshold','80','int','Порог пиковой загрузки','2026-04-28 22:18:51'),
(4,'low_load_threshold','30','int','Порог низкой загрузки','2026-04-28 22:18:51'),
(5,'peak_multiplier','1.3','float','Множитель в часы пик','2026-04-28 22:18:51'),
(6,'low_multiplier','0.7','float','Множитель в часы спада','2026-04-28 22:18:51'),
(7,'rfm_recency_days_high','7','int','R высокий балл дней','2026-04-28 22:18:51'),
(8,'rfm_recency_days_mid','30','int','R средний балл дней','2026-04-28 22:18:51'),
(9,'rfm_frequency_high','10','int','F высокий балл визитов','2026-04-28 22:18:51'),
(10,'rfm_frequency_mid','3','int','F средний балл визитов','2026-04-28 22:18:51'),
(11,'rfm_monetary_high','10000','int','M высокий балл рублей','2026-04-28 22:18:51'),
(12,'rfm_monetary_mid','3000','int','M средний балл рублей','2026-04-28 22:18:51');

/*Table structure for table `tariffs` */

DROP TABLE IF EXISTS `tariffs`;

CREATE TABLE `tariffs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `tariff_type` enum('hourly','daily','weekly','monthly','night','weekend') DEFAULT 'hourly',
  `price` decimal(10,2) NOT NULL,
  `price_per_hour` decimal(10,2) DEFAULT NULL,
  `min_hours` int(11) DEFAULT 1,
  `max_hours` int(11) DEFAULT NULL,
  `valid_from` time DEFAULT NULL,
  `valid_to` time DEFAULT NULL,
  `valid_days` varchar(20) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`tariff_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tariffs` */

insert  into `tariffs`(`id`,`name`,`description`,`tariff_type`,`price`,`price_per_hour`,`min_hours`,`max_hours`,`valid_from`,`valid_to`,`valid_days`,`sort_order`,`is_active`,`created_at`) values 
(1,'Стандартный час','Почасовая оплата','hourly',200.00,200.00,1,NULL,NULL,NULL,NULL,1,1,'2026-04-28 22:18:51'),
(2,'Дневной абонемент','Безлимит на день','daily',800.00,NULL,1,NULL,NULL,NULL,NULL,2,1,'2026-04-28 22:18:51'),
(3,'Ночной тариф','С 22:00 до 08:00','night',100.00,100.00,1,NULL,NULL,NULL,NULL,3,1,'2026-04-28 22:18:51'),
(4,'Выходной день','Суббота или воскресенье','weekend',600.00,NULL,1,NULL,NULL,NULL,NULL,4,1,'2026-04-28 22:18:51'),
(5,'Месячный абонемент','Безлимит на месяц','monthly',8000.00,NULL,1,NULL,NULL,NULL,NULL,5,1,'2026-04-28 22:18:51'),
(6,'Стандартный час','Почасовая оплата','hourly',200.00,200.00,1,NULL,NULL,NULL,NULL,1,1,'2026-04-28 22:19:00'),
(7,'Дневной абонемент','Безлимит на день','daily',800.00,NULL,1,NULL,NULL,NULL,NULL,2,1,'2026-04-28 22:19:00'),
(8,'Ночной тариф','С 22:00 до 08:00','night',100.00,100.00,1,NULL,'22:00:00','08:00:00',NULL,3,1,'2026-04-28 22:19:00'),
(9,'Выходной день','Суббота или воскресенье','weekend',600.00,NULL,1,NULL,NULL,NULL,'6,7',4,1,'2026-04-28 22:19:00'),
(10,'Месячный абонемент','Безлимит на месяц','monthly',8000.00,NULL,1,NULL,NULL,NULL,NULL,5,1,'2026-04-28 22:19:00'),
(11,'Утренний тариф','С 08:00 до 12:00','hourly',150.00,150.00,1,NULL,'08:00:00','12:00:00','1,2,3,4,5',6,1,'2026-04-28 22:19:00'),
(12,'Вечерний тариф','С 18:00 до 22:00','hourly',250.00,250.00,1,NULL,'18:00:00','22:00:00','1,2,3,4,5',7,1,'2026-04-28 22:19:00'),
(13,'Недельный абонемент','7 дней безлимита','weekly',5000.00,NULL,1,NULL,NULL,NULL,NULL,8,1,'2026-04-28 22:19:00'),
(14,'Студенческий час','Для студентов','hourly',120.00,120.00,1,NULL,NULL,NULL,NULL,9,1,'2026-04-28 22:19:00'),
(15,'VIP час','Премиум место','hourly',500.00,500.00,1,NULL,NULL,NULL,NULL,10,1,'2026-04-28 22:19:00');

/*Table structure for table `visits` */

DROP TABLE IF EXISTS `visits`;

CREATE TABLE `visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `tariff_id` int(11) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_hours` decimal(10,2) DEFAULT 0.00,
  `hourly_rate_applied` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `extra_services_total` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `extra_services_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_services_json`)),
  `walk_in` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tariff_id` (`tariff_id`),
  KEY `idx_client` (`client_id`),
  KEY `idx_status_time` (`status`,`start_time`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_date` (`start_time`),
  CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `visits_ibfk_2` FOREIGN KEY (`tariff_id`) REFERENCES `tariffs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `visits` */

insert  into `visits`(`id`,`client_id`,`tariff_id`,`start_time`,`end_time`,`duration_hours`,`hourly_rate_applied`,`subtotal`,`extra_services_total`,`discount`,`total_amount`,`status`,`extra_services_json`,`walk_in`,`created_at`) values 
(1,1,NULL,'2026-04-28 20:18:51','2026-04-28 21:18:51',1.00,200.00,0.00,0.00,0.00,200.00,'completed',NULL,1,'2026-04-28 22:18:51'),
(2,2,NULL,'2026-04-28 19:18:51','2026-04-28 21:18:51',2.00,200.00,0.00,0.00,0.00,400.00,'completed',NULL,1,'2026-04-28 22:18:51'),
(3,3,NULL,'2026-04-28 22:18:51',NULL,0.00,200.00,0.00,0.00,0.00,0.00,'active',NULL,1,'2026-04-28 22:18:51'),
(4,4,NULL,'2026-04-27 22:18:51','2026-04-28 00:18:51',2.00,200.00,0.00,0.00,0.00,400.00,'completed',NULL,1,'2026-04-28 22:18:51'),
(5,5,NULL,'2026-04-28 22:18:51',NULL,0.00,200.00,0.00,0.00,0.00,0.00,'active',NULL,1,'2026-04-28 22:18:51');

/*Table structure for table `workspaces` */

DROP TABLE IF EXISTS `workspaces`;

CREATE TABLE `workspaces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` varchar(10) NOT NULL,
  `workspace_type` enum('open','private_office','meeting_room','lounge') DEFAULT 'open',
  `has_monitor` tinyint(1) DEFAULT 0,
  `has_power` tinyint(1) DEFAULT 1,
  `has_ethernet` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `number` (`number`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `workspaces` */

insert  into `workspaces`(`id`,`number`,`workspace_type`,`has_monitor`,`has_power`,`has_ethernet`,`is_active`,`created_at`) values 
(1,'1','open',0,1,1,1,'2026-04-28 22:18:51'),
(2,'2','open',0,1,1,1,'2026-04-28 22:18:51'),
(3,'3','open',0,1,1,1,'2026-04-28 22:18:51'),
(4,'4','open',0,1,1,1,'2026-04-28 22:18:51'),
(5,'5','open',0,1,1,1,'2026-04-28 22:18:51'),
(6,'6','open',1,1,1,1,'2026-04-28 22:18:51'),
(7,'7','open',1,1,1,1,'2026-04-28 22:18:51'),
(8,'8','open',1,1,1,1,'2026-04-28 22:18:51'),
(9,'9','open',1,1,1,1,'2026-04-28 22:18:51'),
(10,'10','open',1,1,1,1,'2026-04-28 22:18:51'),
(11,'11','open',0,1,1,1,'2026-04-28 22:18:51'),
(12,'12','open',0,1,1,1,'2026-04-28 22:18:51'),
(13,'13','open',0,1,1,1,'2026-04-28 22:18:51'),
(14,'14','open',0,1,1,1,'2026-04-28 22:18:51'),
(15,'15','open',0,1,1,1,'2026-04-28 22:18:51'),
(16,'16','private_office',1,1,1,1,'2026-04-28 22:18:51'),
(17,'17','private_office',1,1,1,1,'2026-04-28 22:18:51'),
(18,'18','private_office',1,1,1,1,'2026-04-28 22:18:51'),
(19,'19','meeting_room',1,1,1,1,'2026-04-28 22:18:51'),
(20,'20','meeting_room',1,1,1,1,'2026-04-28 22:18:51'),
(21,'21','lounge',0,1,1,1,'2026-04-28 22:18:51'),
(22,'22','lounge',0,1,1,1,'2026-04-28 22:18:51'),
(23,'23','lounge',0,1,1,1,'2026-04-28 22:18:51'),
(24,'24','lounge',0,1,1,1,'2026-04-28 22:18:51'),
(25,'25','lounge',0,1,1,1,'2026-04-28 22:18:51');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
