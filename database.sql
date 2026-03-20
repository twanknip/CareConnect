-- ========================================
-- MediPortal Database Schema
-- MySQL Database Setup Script
-- Run this in PHPMyAdmin
-- ========================================

-- Create Database
CREATE DATABASE IF NOT EXISTS `mediportal` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `mediportal`;

-- ========================================
-- Users Table (Staff/Doctors)
-- ========================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'doctor', 'nurse', 'staff') DEFAULT 'staff',
  `department` VARCHAR(100),
  `is_active` BOOLEAN DEFAULT TRUE,
  `last_login` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Patients Table
-- ========================================
CREATE TABLE IF NOT EXISTS `patients` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `patient_id` VARCHAR(50) NOT NULL UNIQUE,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255),
  `phone` VARCHAR(20),
  `age` INT,
  `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Other',
  `date_of_birth` DATE,
  `blood_type` VARCHAR(10),
  `allergies` TEXT,
  `medical_history` TEXT,
  `current_medications` TEXT,
  `assigned_doctor_id` INT,
  `last_visit` DATE,
  `next_appointment` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`assigned_doctor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_patient_id` (`patient_id`),
  INDEX `idx_doctor` (`assigned_doctor_id`),
  INDEX `idx_email` (`email`),
  FULLTEXT `ft_search` (`first_name`, `last_name`, `email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Chat Messages Table
-- ========================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `sender_id` INT NOT NULL,
  `recipient_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_sender` (`sender_id`),
  INDEX `idx_recipient` (`recipient_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Login Attempts Table (for security)
-- ========================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `username` VARCHAR(100),
  `ip_address` VARCHAR(45),
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `success` BOOLEAN DEFAULT FALSE,
  INDEX `idx_username` (`username`),
  INDEX `idx_ip` (`ip_address`),
  INDEX `idx_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Sample Data - Users
-- ========================================
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `department`, `is_active`) 
VALUES 
  ('admin', 'admin@hospital.com', 'password', 'Admin User', 'admin', 'Administration', TRUE),
  ('doctor', 'doctor@hospital.com', 'doctor123', 'Dr. Jane Doe', 'doctor', 'Cardiology', TRUE),
  ('nurse', 'nurse@hospital.com', 'nurse456', 'Nurse Maria Garcia', 'nurse', 'Cardiology', TRUE);

-- ========================================
-- Sample Data - Patients
-- ========================================
INSERT INTO `patients` (`patient_id`, `first_name`, `last_name`, `email`, `phone`, `age`, `gender`, `date_of_birth`, `blood_type`, `allergies`, `medical_history`, `current_medications`, `assigned_doctor_id`)
VALUES 
  ('P001', 'John', 'Smith', 'john.smith@email.com', '(555) 123-4567', 45, 'Male', '1978-05-15', 'O+', 'Penicillin', 'Hypertension, Type 2 Diabetes', 'Lisinopril, Metformin', 2),
  ('P002', 'Sarah', 'Johnson', 'sarah.j@email.com', '(555) 234-5678', 32, 'Female', '1991-12-08', 'A-', 'None', 'Asthma', 'Albuterol inhaler', 2),
  ('P003', 'Michael', 'Chen', 'mchen@email.com', '(555) 345-6789', 58, 'Male', '1965-03-22', 'B+', 'Sulfonamides', 'Coronary Artery Disease, High Cholesterol', 'Atorvastatin, Aspirin, Metoprolol', 2),
  ('P004', 'Emily', 'Rodriguez', 'emily.r@email.com', '(555) 456-7890', 28, 'Female', '1995-07-14', 'AB+', 'Latex', 'Thyroid condition (Hypothyroidism)', 'Levothyroxine', 2),
  ('P005', 'David', 'Williams', 'dwilliams@email.com', '(555) 567-8901', 51, 'Male', '1972-11-03', 'O-', 'NSAIDs', 'Arthritis, Back Pain', 'Ibuprofen, Physical Therapy', 2),
  ('P006', 'Lisa', 'Anderson', 'lisa.anderson@email.com', '(555) 678-9012', 62, 'Female', '1961-09-30', 'B-', 'Penicillin, Aspirin', 'COPD, Osteoporosis', 'Albuterol, Alendronate', 2),
  ('P007', 'Thomas', 'Martin', 'tmartin@email.com', '(555) 789-0123', 39, 'Male', '1984-01-25', 'A+', 'None', 'Hypertension', 'Amlodipine', 2),
  ('P008', 'Jennifer', 'Lee', 'jlee@email.com', '(555) 890-1234', 44, 'Female', '1979-08-12', 'AB-', 'Sulfonamides', 'Migraine, Depression', 'Sumatriptan, Sertraline', 2);

-- ========================================
-- Sample Data - Messages
-- ========================================
INSERT INTO `messages` (`sender_id`, `recipient_id`, `message`, `is_read`)
VALUES 
  (2, 3, 'Hi Maria, can you check on patient P001?', TRUE),
  (3, 2, 'Already done! Vitals are stable.', TRUE),
  (2, 1, 'Admin, we need to update the system.', FALSE),
  (1, 2, 'Sure, I will schedule maintenance.', FALSE);

-- ========================================
-- Views (Optional)
-- ========================================

-- Patient Summary View
CREATE OR REPLACE VIEW `patient_summary` AS
SELECT 
  p.id,
  p.patient_id,
  CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
  p.email,
  p.age,
  p.blood_type,
  p.next_appointment,
  u.full_name AS doctor_name,
  u.email AS doctor_email
FROM `patients` p
LEFT JOIN `users` u ON p.assigned_doctor_id = u.id;

-- User Activity View
CREATE OR REPLACE VIEW `user_summary` AS
SELECT 
  u.id,
  u.username,
  u.full_name,
  u.role,
  u.department,
  u.last_login,
  COUNT(m.id) AS message_count
FROM `users` u
LEFT JOIN `messages` m ON u.id = m.sender_id OR u.id = m.recipient_id
GROUP BY u.id;

-- ========================================
-- Indexes for Performance
-- ========================================
ALTER TABLE `messages` ADD INDEX `idx_conversation` (`sender_id`, `recipient_id`, `created_at`);
ALTER TABLE `patients` ADD INDEX `idx_full_name` (`first_name`, `last_name`);

-- ========================================
-- Database Ready!
-- ========================================
-- Now the database is set up and ready to use
-- All tables have sample data
-- No hardcoded values in PHP - everything comes from config.php
-- ========================================
