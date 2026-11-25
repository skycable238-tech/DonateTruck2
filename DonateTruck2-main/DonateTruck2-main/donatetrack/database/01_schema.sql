-- =====================================================================
-- DonateTrack: Community Donation Tracking and Distribution System
-- IMDBSYS / DBSYS32 - Phase 2: MySQL Database Development
-- Student: Newzel John C. Manginsay (26244558)  Case Study 14
-- File 1 of 3: schema (tables, keys, constraints, indexes)
-- Works on MySQL 8.0+ and MariaDB 10.4+ (XAMPP).
-- =====================================================================

DROP DATABASE IF EXISTS donatetrack;
CREATE DATABASE donatetrack
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE donatetrack;

-- ---------------------------------------------------------------------
-- users: login accounts and roles
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username       VARCHAR(50)  NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  full_name      VARCHAR(100) NOT NULL,
  role           ENUM('COORDINATOR','VOLUNTEER','DONOR') NOT NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_username (username),
  CONSTRAINT chk_users_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- donors: people / organizations that give (optional portal account)
-- ---------------------------------------------------------------------
CREATE TABLE donors (
  donor_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  donor_name  VARCHAR(120) NOT NULL,
  donor_type  ENUM('INDIVIDUAL','ORGANIZATION','BUSINESS') NOT NULL,
  email       VARCHAR(120) NULL,
  phone       VARCHAR(30)  NULL,
  address     VARCHAR(255) NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (donor_id),
  UNIQUE KEY uq_donors_email (email),          -- NULLs allowed, duplicates not
  UNIQUE KEY uq_donors_user  (user_id),        -- one portal account per donor
  KEY idx_donors_name (donor_name),
  CONSTRAINT fk_donors_user FOREIGN KEY (user_id)
    REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_donors_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- donation_categories: types of goods and their unit of measure
-- ---------------------------------------------------------------------
CREATE TABLE donation_categories (
  category_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_name  VARCHAR(80)  NOT NULL,
  unit           VARCHAR(20)  NOT NULL,
  description    VARCHAR(255) NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_categories_name (category_name),
  CONSTRAINT chk_categories_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- donations: each received donation and its status
-- ---------------------------------------------------------------------
CREATE TABLE donations (
  donation_id    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  donor_id       INT UNSIGNED  NOT NULL,
  category_id    INT UNSIGNED  NOT NULL,
  recorded_by    INT UNSIGNED  NOT NULL,
  donation_date  DATE          NOT NULL,
  quantity       DECIMAL(12,2) NOT NULL,
  status         ENUM('RECORDED','VERIFIED','PARTIAL','DISTRIBUTED','CANCELLED')
                 NOT NULL DEFAULT 'RECORDED',
  notes          VARCHAR(500)  NULL,
  cancel_reason  VARCHAR(255)  NULL,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (donation_id),
  KEY idx_donations_status_date (status, donation_date),
  KEY idx_donations_category (category_id),
  KEY idx_donations_donor (donor_id),
  CONSTRAINT fk_donations_donor    FOREIGN KEY (donor_id)
    REFERENCES donors (donor_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_donations_category FOREIGN KEY (category_id)
    REFERENCES donation_categories (category_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_donations_user     FOREIGN KEY (recorded_by)
    REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_donations_qty CHECK (quantity > 0)          -- BR-01
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- distributions: each release of goods to a recipient
-- ---------------------------------------------------------------------
CREATE TABLE distributions (
  distribution_id   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  donation_id       INT UNSIGNED  NOT NULL,
  released_by       INT UNSIGNED  NOT NULL,
  recipient_name    VARCHAR(120)  NOT NULL,
  recipient_area    VARCHAR(120)  NOT NULL,
  quantity_released DECIMAL(12,2) NOT NULL,
  distribution_date DATE          NOT NULL,
  remarks           VARCHAR(255)  NULL,
  created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (distribution_id),
  KEY idx_distributions_donation (donation_id),
  CONSTRAINT fk_distributions_donation FOREIGN KEY (donation_id)
    REFERENCES donations (donation_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_distributions_user FOREIGN KEY (released_by)
    REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_distributions_qty CHECK (quantity_released > 0)   -- BR-01
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- distribution_audit: append-only log. Table is part of the Phase 1
-- design; it is created now and will be filled by triggers in Phase 3.
-- ---------------------------------------------------------------------
CREATE TABLE distribution_audit (
  audit_id         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  donation_id      INT UNSIGNED  NOT NULL,
  distribution_id  INT UNSIGNED  NULL,
  performed_by     INT UNSIGNED  NOT NULL,
  action           VARCHAR(40)   NOT NULL,
  old_status       VARCHAR(20)   NULL,
  new_status       VARCHAR(20)   NULL,
  quantity_change  DECIMAL(12,2) NULL,
  performed_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_audit_donation (donation_id),
  CONSTRAINT fk_audit_donation FOREIGN KEY (donation_id)
    REFERENCES donations (donation_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_audit_distribution FOREIGN KEY (distribution_id)
    REFERENCES distributions (distribution_id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_audit_user FOREIGN KEY (performed_by)
    REFERENCES users (user_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;
