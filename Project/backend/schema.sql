-- ============================================================
-- BLOCKCHAIN CERTIFICATE SYSTEM — DATABASE SCHEMA
-- Landmark University, Omu-Aran
-- Author: Odunuga, Ifeoluwa Jedidiah (22CD009420)
-- Paste this entire file into phpMyAdmin → SQL tab → Go
-- ============================================================

CREATE DATABASE IF NOT EXISTS cert_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cert_system;

CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    email       VARCHAR(150) UNIQUE NOT NULL,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('super_admin','admin') DEFAULT 'admin',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    matric_number   VARCHAR(20) UNIQUE NOT NULL,
    full_name       VARCHAR(200) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    department      VARCHAR(150) NOT NULL,
    faculty         VARCHAR(150) NOT NULL,
    degree_class    ENUM('First Class','Second Class Upper','Second Class Lower','Third Class','Pass') NOT NULL,
    graduation_year YEAR NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS certificates (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    certificate_id      VARCHAR(50) UNIQUE NOT NULL,
    student_id          INT NOT NULL,
    issued_by           INT NOT NULL,
    sha256_hash         VARCHAR(64) NOT NULL,
    ipfs_cid            VARCHAR(100),
    blockchain_tx_hash  VARCHAR(100),
    blockchain_address  VARCHAR(50),
    qr_code_data        TEXT,
    status              ENUM('pending','issued','revoked') DEFAULT 'pending',
    issued_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at          TIMESTAMP NULL,
    revoke_reason       TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (issued_by)  REFERENCES admins(id)
);

CREATE TABLE IF NOT EXISTS verification_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    certificate_id  VARCHAR(50) NOT NULL,
    verifier_ip     VARCHAR(45),
    verifier_email  VARCHAR(150),
    organization    VARCHAR(200),
    result          ENUM('valid','invalid','revoked') NOT NULL,
    verified_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('university_name',    'Landmark University'),
('university_address', 'Omu-Aran, Kwara State, Nigeria'),
('contract_address',   ''),
('ipfs_gateway',       'https://ipfs.io/ipfs/'),
('certificate_prefix', 'LMU');

-- Default login: admin@lmu.edu.ng / Admin@1234
INSERT IGNORE INTO admins (full_name, email, password, role) VALUES
('Super Admin', 'admin@lmu.edu.ng',
 '$2y$12$WnqoeJ6T167pR68Olh4ciefLkfind1eIPtFMdLpLLEdOx1TDzMZZC',
 'super_admin');
