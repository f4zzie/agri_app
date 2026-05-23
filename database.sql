-- ============================================================
-- AgriTrack – Complete Database Schema
-- v2.0  (Farmer-Buyer Marketplace)
-- ============================================================
-- Import this file in phpMyAdmin → SQL tab, then run.
-- ============================================================

CREATE DATABASE IF NOT EXISTS agri_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE agri_app;

-- --------------------------------------------------------
-- Drop tables in reverse dependency order
-- --------------------------------------------------------
DROP TABLE IF EXISTS inquiries;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS crops;
DROP TABLE IF EXISTS users;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
CREATE TABLE users (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  name               VARCHAR(100)  NOT NULL,
  email              VARCHAR(150)  UNIQUE NOT NULL,
  password_hash      VARCHAR(255)  DEFAULT NULL,
  avatar             VARCHAR(255)  DEFAULT NULL,
  google_id          VARCHAR(100)  DEFAULT NULL,
  email_verified     TINYINT(1)    DEFAULT 0,
  role               ENUM('farmer','buyer') DEFAULT 'farmer',
  phone              VARCHAR(20)   DEFAULT NULL,
  county             VARCHAR(100)  DEFAULT NULL,
  verification_token VARCHAR(100)  DEFAULT NULL,
  created_at         TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: crops  (product listings by farmers)
-- --------------------------------------------------------
CREATE TABLE crops (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  user_id          INT           NOT NULL,
  name             VARCHAR(100)  NOT NULL,
  variety          VARCHAR(100)  DEFAULT NULL,
  category         VARCHAR(100)  DEFAULT NULL,
  price            DECIMAL(10,2) DEFAULT NULL,
  unit             VARCHAR(50)   DEFAULT 'kg',
  quantity         INT           DEFAULT NULL,
  planting_date    DATE          DEFAULT NULL,
  expected_harvest DATE          DEFAULT NULL,
  status           ENUM('available','out_of_stock','planted','growing','harvested','failed') DEFAULT 'available',
  notes            TEXT          DEFAULT NULL,
  image            VARCHAR(255)  DEFAULT NULL,
  created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_crops_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: inquiries  (buyers contact farmers about products)
-- --------------------------------------------------------
CREATE TABLE inquiries (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  crop_id      INT           NOT NULL,
  buyer_id     INT           NOT NULL,
  farmer_id    INT           NOT NULL,
  message      TEXT          NOT NULL,
  buyer_name   VARCHAR(100)  DEFAULT NULL,
  buyer_email  VARCHAR(150)  DEFAULT NULL,
  buyer_phone  VARCHAR(20)   DEFAULT NULL,
  status       ENUM('pending','read','responded') DEFAULT 'pending',
  created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_inq_crop   FOREIGN KEY (crop_id)   REFERENCES crops(id)  ON DELETE CASCADE,
  CONSTRAINT fk_inq_buyer  FOREIGN KEY (buyer_id)  REFERENCES users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_inq_farmer FOREIGN KEY (farmer_id) REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: activity_log
-- --------------------------------------------------------
CREATE TABLE activity_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT          NOT NULL,
  action     VARCHAR(100) NOT NULL,
  details    TEXT         DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: password_resets
-- --------------------------------------------------------
CREATE TABLE password_resets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(150) NOT NULL,
  token      VARCHAR(100) NOT NULL,
  expires_at TIMESTAMP    NOT NULL,
  used       TINYINT(1)   DEFAULT 0,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: login_attempts  (rate limiting)
-- --------------------------------------------------------
CREATE TABLE login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(150) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
