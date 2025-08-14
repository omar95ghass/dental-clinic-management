--
-- Dental Clinic Project - Database Schema
--
-- This script creates the necessary tables for the dental clinic management system.
-- It is designed to be run on a MySQL database.
--

-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `dental_clinic` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dental_clinic`;

-- Table for users (doctors, receptionists)
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('doctor', 'receptionist', 'admin') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table for patients
CREATE TABLE `patients` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `first_name` VARCHAR(255) NOT NULL,
    `father_name` VARCHAR(255) NOT NULL,
    `last_name` VARCHAR(255) NOT NULL,
    `date_of_birth` DATE NULL,
    `phone_number` VARCHAR(20) NOT NULL UNIQUE,
    `address` TEXT NULL,
    `visit_status` ENUM('first_time', 'review') NOT NULL DEFAULT 'first_time',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table for medical records (general patient notes)
CREATE TABLE `medical_records` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL,
    `notes` TEXT NULL,
    `chronic_diseases` TEXT NULL,
    `allergies` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
);

-- Table for medical record attachments (e.g., X-rays, lab results)
CREATE TABLE `medical_record_attachments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
);

-- Table for sessions (individual patient visits)
CREATE TABLE `sessions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL,
    `doctor_id` INT UNSIGNED NOT NULL,
    `session_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `session_notes` TEXT NULL,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Table for treatment types (e.g., Endodontic, Prosthodontic)
CREATE TABLE `treatment_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `default_cost` DECIMAL(10, 2) NOT NULL
);

-- Table for treatment steps (e.g., Pulp Chamber Opening, Canal Preparation)
CREATE TABLE `treatment_steps` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `treatment_type_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_working_length` BOOLEAN NOT NULL DEFAULT 0, -- To identify steps that require canal length details
    FOREIGN KEY (`treatment_type_id`) REFERENCES `treatment_types`(`id`) ON DELETE CASCADE
);

-- Table for individual treatments within a session
CREATE TABLE `treatments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT UNSIGNED NOT NULL,
    `tooth_number` INT NOT NULL, -- Dental notation 11-48
    `treatment_type_id` INT UNSIGNED NOT NULL,
    `cost` DECIMAL(10, 2) NOT NULL,
    `additional_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`treatment_type_id`) REFERENCES `treatment_types`(`id`) ON DELETE CASCADE
);

-- Table for treatment steps applied to a specific treatment
CREATE TABLE `treatment_details` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `treatment_id` INT UNSIGNED NOT NULL,
    `treatment_step_id` INT UNSIGNED NOT NULL,
    `working_length_details` JSON NULL, -- Store canal lengths here as JSON: {"mb": "21mm", "mo": "20mm"}
    FOREIGN KEY (`treatment_id`) REFERENCES `treatments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`treatment_step_id`) REFERENCES `treatment_steps`(`id`) ON DELETE CASCADE
);

-- Table for drugs
CREATE TABLE `drugs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE,
    `dosage_options` JSON NULL, -- Store available dosages as JSON array: ["bid", "tid"]
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table for prescriptions
CREATE TABLE `prescriptions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT UNSIGNED NOT NULL,
    `drug_id` INT UNSIGNED NOT NULL,
    `dosage` VARCHAR(50) NOT NULL,
    `is_printed` BOOLEAN NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`drug_id`) REFERENCES `drugs`(`id`) ON DELETE CASCADE
);

-- Table for patient payments
CREATE TABLE `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL,
    `payment_date` DATE NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
);

-- Table for appointments
CREATE TABLE `appointments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT UNSIGNED NOT NULL,
    `appointment_date` DATETIME NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
);
