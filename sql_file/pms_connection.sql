-- ============================================================================
-- CANONICAL SCHEMA + DEMO DATA DUMP — Phase 17, produced 2026-09-05
-- ============================================================================
-- This is the authoritative database snapshot for this project, superseding
-- the pre-revision pair (this file's own prior contents, and new_sql_file/
-- pms_connection.sql — both stale, disagreeing dumps flagged in Phase 0).
--
-- Produced by: mysqldump against a local `pms_connection` database that had
-- every migration in db_migrations/01 through db_migrations/18 applied, in
-- that numeric order, on top of the ORIGINAL pre-revision baseline dump
-- (what used to live at this same path). db_migrations/20251106_add_columns.sql
-- is deliberately excluded — see db_migrations/README.md for why.
--
-- Contains the full demonstration dataset: 34 vehicles across 6 categories,
-- 5 locations with the 25-row location_fees matrix (Phase 8), rental_policies
-- v1.0 with its heading encoding corrected (migration 18), fuel_rates for
-- Phase 11's settlement math, and one real vehicle_reviews row (Phase 12) —
-- a genuine review submitted through review_create() against the one
-- completed booking this dataset has (booking 69), not a fabricated fixture.
--
-- To restore from scratch: import this file into an empty, matching-named
-- `pms_connection` database. No further migrations need to be replayed —
-- this dump already reflects all of them. db_migrations/ stays the source of
-- truth for HOW the schema was built and WHY each change was made; this file
-- is the fast path for standing up a new environment. See docs/DEPLOYMENT.md.
-- ============================================================================

-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: pms_connection
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_tokens`
--

DROP TABLE IF EXISTS `api_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_general_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `api_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_tokens`
--

LOCK TABLES `api_tokens` WRITE;
/*!40000 ALTER TABLE `api_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_damage_photos`
--

DROP TABLE IF EXISTS `booking_damage_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_damage_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `damage_id` int NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_damage_photos_damage_fk` (`damage_id`),
  CONSTRAINT `booking_damage_photos_damage_fk` FOREIGN KEY (`damage_id`) REFERENCES `booking_damages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_damage_photos`
--

LOCK TABLES `booking_damage_photos` WRITE;
/*!40000 ALTER TABLE `booking_damage_photos` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_damage_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_damages`
--

DROP TABLE IF EXISTS `booking_damages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_damages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `estimated_amount` decimal(10,2) NOT NULL,
  `adjusted_amount` decimal(10,2) DEFAULT NULL,
  `adjustment_reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('proposed','confirmed','waived') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'proposed',
  `created_by_admin_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `booking_damages_booking_fk` (`booking_id`),
  CONSTRAINT `booking_damages_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_damages`
--

LOCK TABLES `booking_damages` WRITE;
/*!40000 ALTER TABLE `booking_damages` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_damages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_status_history`
--

DROP TABLE IF EXISTS `booking_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `booking_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `from_status` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `to_status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `changed_by_type` enum('admin','user','system') COLLATE utf8mb4_general_ci NOT NULL,
  `changed_by_id` int DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking_history` (`booking_id`,`created_at`),
  CONSTRAINT `booking_status_history_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=108 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_status_history`
--

LOCK TABLES `booking_status_history` WRITE;
/*!40000 ALTER TABLE `booking_status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `booking_ref` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pickup_at` datetime NOT NULL,
  `dropoff_at` datetime NOT NULL,
  `rental_date` date GENERATED ALWAYS AS (cast(`pickup_at` as date)) STORED,
  `return_date` date GENERATED ALWAYS AS (cast(`dropoff_at` as date)) STORED,
  `pickup_time` time GENERATED ALWAYS AS (cast(`pickup_at` as time)) STORED,
  `dropoff_time` time GENERATED ALWAYS AS (cast(`dropoff_at` as time)) STORED,
  `actual_return_at` datetime DEFAULT NULL,
  `picked_up_at` datetime DEFAULT NULL,
  `fuel_level_out` tinyint DEFAULT NULL,
  `days` int DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `total_amount` decimal(10,2) DEFAULT NULL,
  `rental_fee` decimal(10,2) DEFAULT NULL,
  `security_deposit` decimal(10,2) NOT NULL DEFAULT '0.00',
  `deposit_status` enum('not_collected','held','partially_refunded','refunded','forfeited') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'not_collected',
  `deposit_refund_amount` decimal(10,2) DEFAULT NULL,
  `deposit_settled_at` datetime DEFAULT NULL,
  `outstanding_balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `overtime_amount` decimal(10,2) DEFAULT NULL,
  `overtime_adjustment_reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fuel_charge_amount` decimal(10,2) DEFAULT NULL,
  `fuel_adjustment_reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `refund_percentage` tinyint DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancel_reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_number` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pickup_location_id` int DEFAULT NULL,
  `dropoff_location_id` int DEFAULT NULL,
  `location_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending_review','info_requested','approved','paid','active','returned','completed','cancelled','declined') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending_review',
  `payment_status` enum('unpaid','partially_paid','paid','refunded') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_at` datetime DEFAULT NULL,
  `voucher_id` int DEFAULT NULL,
  `age` int NOT NULL DEFAULT '18',
  `license_file` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `verification_photo_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `customer_address_snapshot` text COLLATE utf8mb4_general_ci,
  `policy_version_accepted` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `policy_accepted_at` datetime DEFAULT NULL,
  `paid` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `bookings_user_fk` (`user_id`),
  KEY `bookings_voucher_fk` (`voucher_id`),
  KEY `idx_rental_date` (`rental_date`),
  KEY `idx_return_date` (`return_date`),
  KEY `idx_pickup_at` (`pickup_at`),
  KEY `idx_dropoff_at` (`dropoff_at`),
  KEY `idx_vehicle_window` (`vehicle_id`,`pickup_at`,`dropoff_at`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_status_pickup` (`status`,`pickup_at`),
  KEY `idx_pickup_location` (`pickup_location_id`),
  KEY `idx_dropoff_location` (`dropoff_location_id`),
  CONSTRAINT `bookings_dropoff_location_fk` FOREIGN KEY (`dropoff_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_pickup_location_fk` FOREIGN KEY (`pickup_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_voucher_fk` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_fuel_level_out` CHECK (((`fuel_level_out` is null) or (`fuel_level_out` between 0 and 8))),
  CONSTRAINT `chk_min_duration` CHECK ((`dropoff_at` >= (`pickup_at` + interval 24 hour)))
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversation_messages`
--

DROP TABLE IF EXISTS `conversation_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conversation_id` int NOT NULL,
  `sender_type` enum('admin','user') COLLATE utf8mb4_general_ci NOT NULL,
  `sender_id` int NOT NULL,
  `body` text COLLATE utf8mb4_general_ci NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cmsg_conv` (`conversation_id`,`created_at`),
  KEY `idx_cmsg_unread` (`conversation_id`,`sender_type`,`read_at`),
  CONSTRAINT `cmsg_conv_fk` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversation_messages`
--

LOCK TABLES `conversation_messages` WRITE;
/*!40000 ALTER TABLE `conversation_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversation_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `conversations`
--

DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `booking_id` int DEFAULT NULL,
  `subject` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('open','closed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'open',
  `last_message_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conv_user` (`user_id`,`last_message_at`),
  KEY `idx_conv_booking` (`booking_id`),
  CONSTRAINT `conversations_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `conversations`
--

LOCK TABLES `conversations` WRITE;
/*!40000 ALTER TABLE `conversations` DISABLE KEYS */;
/*!40000 ALTER TABLE `conversations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fuel_rates`
--

DROP TABLE IF EXISTS `fuel_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fuel_rates` (
  `category` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `rate_per_eighth` decimal(10,2) NOT NULL,
  PRIMARY KEY (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fuel_rates`
--

LOCK TABLES `fuel_rates` WRITE;
/*!40000 ALTER TABLE `fuel_rates` DISABLE KEYS */;
INSERT INTO `fuel_rates` VALUES ('Minivan',450.00),('Pickup',400.00),('Scooter',150.00),('Sedan',300.00),('SUV',450.00),('Van',500.00);
/*!40000 ALTER TABLE `fuel_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `location_fees`
--

DROP TABLE IF EXISTS `location_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `location_fees` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pickup_location_id` int NOT NULL,
  `dropoff_location_id` int NOT NULL,
  `fee` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_location_pair` (`pickup_location_id`,`dropoff_location_id`),
  KEY `location_fees_dropoff_fk` (`dropoff_location_id`),
  CONSTRAINT `location_fees_dropoff_fk` FOREIGN KEY (`dropoff_location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `location_fees_pickup_fk` FOREIGN KEY (`pickup_location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `location_fees`
--

LOCK TABLES `location_fees` WRITE;
/*!40000 ALTER TABLE `location_fees` DISABLE KEYS */;
INSERT INTO `location_fees` VALUES (1,1,1,0.00),(2,2,2,0.00),(3,3,3,0.00),(4,4,4,0.00),(5,5,5,0.00),(6,1,2,300.00),(7,2,1,300.00),(8,1,3,300.00),(9,3,1,300.00),(10,1,4,300.00),(11,4,1,300.00),(12,2,3,300.00),(13,3,2,300.00),(14,2,4,300.00),(15,4,2,300.00),(16,3,4,300.00),(17,4,3,300.00),(18,3,5,300.00),(19,5,3,300.00),(20,1,5,500.00),(21,5,1,500.00),(22,2,5,500.00),(23,5,2,500.00),(24,4,5,500.00),(25,5,4,500.00);
/*!40000 ALTER TABLE `location_fees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `address` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `locations`
--

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
INSERT INTO `locations` VALUES (1,'Cubao Branch (demo)','Gen. Araneta Ave','Quezon City',1,1),(2,'Project 4 Branch (demo)','Katipunan Ave','Quezon City',1,2),(3,'QC Circle Branch (demo)','Elliptical Rd','Quezon City',1,3),(4,'Marikina Branch (demo)','Sumulong Hwy','Marikina',1,4),(5,'Novaliches Branch (demo)','Quirino Hwy','Quezon City',1,5);
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('admin','user') COLLATE utf8mb4_general_ci NOT NULL,
  `recipient_id` int NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `body` text COLLATE utf8mb4_general_ci,
  `link_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `booking_id` int DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_recipient` (`recipient_type`,`recipient_id`,`is_read`,`created_at`),
  KEY `notifications_booking_fk` (`booking_id`),
  CONSTRAINT `notifications_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `purpose` enum('rental','deposit','overtime','damage','fuel','refund') COLLATE utf8mb4_general_ci NOT NULL,
  `provider` enum('manual','paymongo','stripe') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'manual',
  `provider_ref` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` char(3) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'PHP',
  `status` enum('pending','processing','succeeded','failed','refunded','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `raw_response` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_provider_ref` (`provider`,`provider_ref`),
  KEY `idx_payments_booking` (`booking_id`,`status`),
  KEY `idx_payments_purpose` (`purpose`),
  CONSTRAINT `payments_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rental_policies`
--

DROP TABLE IF EXISTS `rental_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rental_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `version` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `effective_from` date NOT NULL,
  `content_html` mediumtext COLLATE utf8mb4_general_ci NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rental_policies`
--

LOCK TABLES `rental_policies` WRITE;
/*!40000 ALTER TABLE `rental_policies` DISABLE KEYS */;
INSERT INTO `rental_policies` VALUES (1,'1.0','2026-09-04','<h5>PMS Car Rental — Rental Policy v1.0</h5>\n<p class=\"text-body-secondary small\">Effective 4 September 2026. This is a practical application policy, not a legal contract.</p>\n<ol>\n  <li><strong>Minimum rental &amp; how days are counted.</strong> The minimum rental is 24 hours, measured on date <em>and</em> time. One rental day is exactly 24 hours; billable days are the total hours divided by 24, rounded up (a 30-hour rental is 2 days).</li>\n  <li><strong>Pricing.</strong> Rental fee = daily rate &times; billable days. Any voucher discount is applied to that subtotal. The amount shown on the booking summary is what you pay.</li>\n  <li><strong>Payment.</strong> Payment is due after the reservation is approved. An approved booking left unpaid for 48 hours is cancelled automatically.</li>\n  <li><strong>Cancellation &amp; refunds.</strong> Full refund if you cancel at least 24 hours before pickup; 50% refund inside 24 hours; no refund once the rental has started.</li>\n  <li><strong>Return deadline, grace &amp; overtime.</strong> Return by the agreed drop-off time. A 60-minute grace period applies. Past that: 10% of the daily rate per started hour for the first 6 hours late; beyond 6 hours late, one additional full rental day (and another for each further 24 hours).</li>\n  <li><strong>Fuel.</strong> Return the vehicle at the fuel level you received it. A shortfall is charged per eighth of a tank at the vehicle&rsquo;s category rate.</li>\n  <li><strong>Security deposit.</strong> A refundable security deposit is collected with the rental payment and returned after a clean return and inspection. Charges for overtime, fuel or damage are deducted from it first; anything beyond the deposit is billed separately.</li>\n  <li><strong>Damage.</strong> You are responsible for damage beyond normal wear. Any damage charge is assessed by an admin during the return inspection before it is applied &mdash; nothing is charged automatically.</li>\n  <li><strong>Required documents.</strong> A valid driver&rsquo;s licence, a verification photo, and a current home address are required before a booking can be approved.</li>\n  <li><strong>Customer responsibilities &amp; prohibited use.</strong> Only the named renter may drive. No use for hire, racing, off-road driving, or transport of illegal goods. Smoking in the vehicle incurs a cleaning fee.</li>\n</ol>',1,'2026-09-04 12:25:29');
/*!40000 ALTER TABLE `rental_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `return_inspections`
--

DROP TABLE IF EXISTS `return_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `return_inspections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `fuel_level_in` tinyint DEFAULT NULL,
  `condition_notes` text COLLATE utf8mb4_general_ci,
  `has_damage` tinyint(1) NOT NULL DEFAULT '0',
  `damage_notes` text COLLATE utf8mb4_general_ci,
  `status` enum('draft','finalized') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `finalized_at` datetime DEFAULT NULL,
  `created_by_admin_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  CONSTRAINT `return_inspections_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_fuel_level_in` CHECK (((`fuel_level_in` is null) or (`fuel_level_in` between 0 and 8)))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `return_inspections`
--

LOCK TABLES `return_inspections` WRITE;
/*!40000 ALTER TABLE `return_inspections` DISABLE KEYS */;
/*!40000 ALTER TABLE `return_inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `transaction_ref` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_ref` (`transaction_ref`),
  KEY `booking_id` (`booking_id`),
  CONSTRAINT `transactions_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reset_code` varchar(6) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reset_code_expires` datetime DEFAULT NULL,
  `profile_picture_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `license_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `verification_photo_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `verification_status` enum('none','pending','verified','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'none',
  `verified_at` datetime DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address_line1` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `address_line2` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `postal_code` varchar(16) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_reviews`
--

DROP TABLE IF EXISTS `vehicle_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` tinyint NOT NULL,
  `body` text COLLATE utf8mb4_general_ci,
  `status` enum('visible','hidden') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'visible',
  `admin_response` text COLLATE utf8mb4_general_ci,
  `admin_response_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id` (`booking_id`),
  KEY `vehicle_reviews_user_fk` (`user_id`),
  KEY `idx_vehicle_reviews_vehicle_status` (`vehicle_id`,`status`),
  CONSTRAINT `vehicle_reviews_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vehicle_reviews_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vehicle_reviews_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_vehicle_reviews_rating` CHECK ((`rating` between 1 and 5))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_reviews`
--

LOCK TABLES `vehicle_reviews` WRITE;
/*!40000 ALTER TABLE `vehicle_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `seats` int NOT NULL DEFAULT '4',
  `fuel` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Gasoline',
  `transmission` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Automatic',
  `price_per_day` int NOT NULL DEFAULT '0',
  `security_deposit` decimal(10,2) DEFAULT NULL,
  `units_total` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `thumbnail` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `slug` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicles`
--

LOCK TABLES `vehicles` WRITE;
/*!40000 ALTER TABLE `vehicles` DISABLE KEYS */;
INSERT INTO `vehicles` VALUES (1,'Honda Civic','Sedan',4,'Gasoline','Manual',3400,NULL,4,1,'Civic.jpg','2025-11-05 07:49:25',NULL),(2,'Toyoto Corolla','Sedan',4,'Gasoline','Manual',2199,NULL,2,1,'ToyotaCorolla.jfif','2025-11-05 07:55:57',NULL),(3,'Mazda 6','Sedan',4,'Gasoline','Automatic',2700,NULL,3,1,'Mazda6.jpg','2025-11-05 07:57:56',NULL),(4,'Ford Everest','SUV',7,'Diesel','Automatic',4500,NULL,4,1,'FordEverest.jpg','2025-11-05 08:18:08',NULL),(5,'Mitsubishi Montero Sport','SUV',7,'Gasoline','Automatic',4400,NULL,2,1,'Mitsubushi.jpg','2025-11-05 08:18:41',NULL),(6,'Nissan Terra','SUV',7,'Gasoline','Automatic',4570,NULL,4,1,'NissanTerra.jpg','2025-11-05 08:19:26',NULL),(7,'Nissan Urvan','Van',15,'Diesel','Automatic',4500,NULL,1,1,'Nissan Urvan.jfif','2025-11-05 08:23:30',NULL),(8,'Hyundai H350','Van',15,'Diesel','Automatic',4500,NULL,2,1,'Hyundai H350.jfif','2025-11-05 08:24:06',NULL),(9,'Maxus V80','Van',15,'Gasoline','Automatic',4800,NULL,1,1,'Maxus V80.jpg','2025-11-05 08:25:01',NULL),(10,'Honda Click 125i','Scooter',2,'Diesel','Automatic',300,NULL,1,1,'Honda Click 125i.jfif','2025-11-05 08:33:49',NULL),(11,'Vespa Sprint','Scooter',2,'Diesel','Automatic',450,NULL,3,1,'Vespa Sprint.jpg','2025-11-05 08:34:17',NULL),(12,'Yamaha NMAX','Scooter',2,'Diesel','Automatic',700,NULL,1,1,'Yamaha NMAX.jfif','2025-11-05 08:34:45',NULL),(13,'Ford Ranged Raptor','Pickup',8,'Diesel','Automatic',5800,NULL,4,1,'2024-Ford-Ranger-Raptor-14.jpg','2025-11-05 08:36:12',NULL),(14,'Mitsubishi Strada','Pickup',8,'Diesel','Automatic',7800,NULL,2,1,'Mitsubishi Strada.png','2025-11-05 08:36:43',NULL),(15,'Nissan Navarra','Pickup',18,'Diesel','Automatic',7000,NULL,1,1,'Nissan Navara.jfif','2025-11-05 08:37:10',NULL),(16,'Hyundai Elantra','Sedan',4,'Gasoline','Automatic',8990,NULL,1,1,'Hyundai Elantra.jfif','2025-11-05 09:11:52',NULL),(17,'Nissan Almera','Sedan',4,'Hybrid','Manual',4400,NULL,2,1,'Nissan Almera.jpg','2025-11-05 09:23:22',NULL),(18,'Kia Forte','Sedan',4,'Diesel','Automatic',4900,NULL,4,1,'Kia Forte.jpg','2025-11-05 09:24:13',NULL),(19,'Chevrolet Cruze','Sedan',4,'Gasoline','Automatic',5500,NULL,6,1,'Chevrolet Cruze.jfif','2025-11-05 09:24:43',NULL),(20,'Toyota RAV4','SUV',6,'Gasoline','Automatic',4444,NULL,2,1,'Toyota RAV4.jpg','2025-11-05 09:26:38',NULL),(21,'Honda CR-V','SUV',6,'Gasoline','Automatic',6800,NULL,4,1,'Honda CR-V.jpg','2025-11-05 09:27:08',NULL),(22,'Subaru Forester','SUV',6,'Diesel','Automatic',7777,NULL,3,1,'Subaru Forester.jpg','2025-11-05 09:27:56',NULL),(23,'Chevrolet Trailblazer','SUV',6,'Diesel','Automatic',6990,NULL,5,1,'Chevrolet Trailblazer.jfif','2025-11-05 09:28:26',NULL),(24,'Foton TransVan','Van',6,'Diesel','Automatic',7850,NULL,3,1,'Foton TransVan.jpg','2025-11-05 09:29:16',NULL),(25,'Maxus G10','Van',6,'Diesel','Automatic',5890,NULL,3,1,'Maxus G10.jpg','2025-11-05 09:29:42',NULL),(26,'Hyundai Staria','Van',6,'Diesel','Automatic',8990,NULL,3,1,'Hyundai Staria.jfif','2025-11-05 09:31:45',NULL),(27,'Suzuki Ertiga','Minivan',6,'Diesel','Automatic',6000,NULL,2,1,'Suzuki Ertiga.jfif','2025-11-05 09:32:38',NULL),(28,'Kia Carens','Minivan',6,'Gasoline','Automatic',8000,NULL,2,1,'Kia Carens.jpg','2025-11-05 09:33:24',NULL),(29,'Hyundai Custin','Minivan',10,'Diesel','Automatic',8650,NULL,2,1,'Hyundai Custin.jfif','2025-11-05 09:34:19',NULL),(30,'Toyota Sienta','Minivan',10,'Diesel','Automatic',8750,NULL,4,1,'Toyota Sienta.jpg','2025-11-05 09:34:57',NULL),(31,'Honda PCX160','Scooter',2,'Diesel','Automatic',600,NULL,3,1,'Honda PCX160.jfif','2025-11-05 09:36:12',NULL),(32,'Yamaha Aerox','Scooter',2,'Diesel','Automatic',700,NULL,4,1,'Yamaha Aerox.jpeg','2025-11-05 09:37:20',NULL),(34,'Honda Beat','Scooter',2,'Diesel','Automatic',800,NULL,8,1,'Honda Beat.jfif','2025-11-05 09:38:48',NULL),(37,'Toyota','SUV',4,'Gasoline','Automatic',1000,NULL,4,1,'Toyota-Innova.jpg','2025-11-06 15:13:27',NULL);
/*!40000 ALTER TABLE `vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vouchers`
--

DROP TABLE IF EXISTS `vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vouchers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `discount_pct` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `single_use` tinyint(1) DEFAULT '1',
  `usage_count` int DEFAULT '0',
  `usage_limit` int DEFAULT '1',
  `used_at` datetime DEFAULT NULL,
  `used_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `used_by` (`used_by`),
  CONSTRAINT `vouchers_ibfk_1` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vouchers`
--

LOCK TABLES `vouchers` WRITE;
/*!40000 ALTER TABLE `vouchers` DISABLE KEYS */;
INSERT INTO `vouchers` VALUES (3,'SAVE300',300.00,0,0,1,3,5,'2026-08-13 01:26:06',6,'2025-11-06 15:07:16'),(4,'BOOK50',0.00,50,1,1,2,3,'2026-08-21 18:06:20',7,'2026-08-20 08:24:03');
/*!40000 ALTER TABLE `vouchers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'pms_connection'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-05 16:11:52
