--
-- Dental Clinic Project - Updated Database Schema
--
-- This script creates the necessary tables for the dental clinic management system.
-- It is designed to be run on a MySQL database.
-- Updated to include detailed treatment steps and working length management.
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
    `step_order` INT NOT NULL DEFAULT 1, -- Order of steps within a treatment type
    FOREIGN KEY (`treatment_type_id`) REFERENCES `treatment_types`(`id`) ON DELETE CASCADE
);

-- Table for canal types (MB, MO, DB, etc.)
CREATE TABLE `canal_types` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(10) NOT NULL UNIQUE, -- MB, MO, DB, DL, P, etc.
    `description` VARCHAR(255) NULL
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
    `step_notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`treatment_id`) REFERENCES `treatments`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`treatment_step_id`) REFERENCES `treatment_steps`(`id`) ON DELETE CASCADE
);

-- Table for working lengths (canal measurements)
CREATE TABLE `working_lengths` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `treatment_detail_id` INT UNSIGNED NOT NULL,
    `canal_type_id` INT UNSIGNED NOT NULL,
    `length` VARCHAR(10) NOT NULL, -- e.g., "21mm", "20.5mm"
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`treatment_detail_id`) REFERENCES `treatment_details`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`canal_type_id`) REFERENCES `canal_types`(`id`) ON DELETE CASCADE
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

-- Insert default treatment types
INSERT INTO `treatment_types` (`name`, `default_cost`) VALUES
('لبية', 150.00),
('محافظة', 80.00),
('تعويض ثابت', 300.00),
('تعويض متحرك', 250.00),
('جراحة', 200.00),
('تقويم', 100.00),
('تنظيف', 50.00);

-- Insert default treatment steps for endodontic treatment (لبية)
INSERT INTO `treatment_steps` (`treatment_type_id`, `name`, `is_working_length`, `step_order`) VALUES
(1, 'فتح حجرة اللب', 0, 1),
(1, 'تحضير الأقنية', 0, 2),
(1, 'تحديد طول عامل', 1, 3),
(1, 'تشكيل الأقنية', 0, 4),
(1, 'حشو الأقنية', 0, 5),
(1, 'الحشو النهائي', 0, 6);

-- Insert default treatment steps for conservative treatment (محافظة)
INSERT INTO `treatment_steps` (`treatment_type_id`, `name`, `is_working_length`, `step_order`) VALUES
(2, 'إزالة التسوس', 0, 1),
(2, 'تحضير التجويف', 0, 2),
(2, 'وضع القاعدة', 0, 3),
(2, 'الحشو النهائي', 0, 4),
(2, 'التشطيب والتلميع', 0, 5);

-- Insert default canal types
INSERT INTO `canal_types` (`name`, `description`) VALUES
('MB', 'Mesio-Buccal'),
('MO', 'Mesio-Occlusal'),
('DB', 'Disto-Buccal'),
('DL', 'Disto-Lingual'),
('P', 'Palatal'),
('ML', 'Mesio-Lingual'),
('C', 'Central');

-- Insert default drugs
INSERT INTO `drugs` (`name`, `dosage_options`) VALUES
('أموكسيسيلين 500mg', '["bid", "tid"]'),
('إيبوبروفين 400mg', '["bid", "tid", "qid"]'),
('باراسيتامول 500mg', '["bid", "tid", "qid"]'),
('كلافوكس 625mg', '["bid", "tid"]'),
('فولتارين 50mg', '["bid", "tid"]');

-- Insert default users (admin user)
INSERT INTO `users` (`username`, `password`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'); -- password: password


-- Financial tables for payments and billing
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'bank_transfer', 'check') DEFAULT 'cash',
    payment_date DATETIME NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Update appointments table to include separate time field and status
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS appointment_time TIME,
ADD COLUMN IF NOT EXISTS status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Insert sample payment methods data
INSERT IGNORE INTO payments (patient_id, amount, payment_method, payment_date, notes) VALUES
(1, 50000, 'cash', '2024-01-15 10:30:00', 'دفعة أولى للمعالجة اللبية'),
(1, 25000, 'cash', '2024-01-20 14:15:00', 'دفعة ثانية'),
(2, 75000, 'card', '2024-01-18 11:00:00', 'دفع كامل للمعالجة المحافظة');

-- Insert sample appointments with time
INSERT IGNORE INTO appointments (patient_id, appointment_date, appointment_time, notes, status) VALUES
(1, '2024-02-01', '09:00:00', 'مراجعة المعالجة اللبية', 'scheduled'),
(2, '2024-02-01', '10:30:00', 'فحص دوري', 'scheduled'),
(3, '2024-02-02', '14:00:00', 'استكمال المعالجة', 'scheduled');


-- Settings tables for system configuration
CREATE TABLE IF NOT EXISTS clinic_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(100),
    doctor_name VARCHAR(255),
    specialization VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default clinic info
INSERT IGNORE INTO clinic_info (name, address, phone, email, doctor_name, specialization) VALUES
('عيادة الأسنان المتخصصة', 'دمشق - سوريا', '+963-11-1234567', 'info@dentalclinic.sy', 'د. أحمد محمد', 'طب وجراحة الفم والأسنان');

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('appointment_duration', '30'),
('working_hours_start', '09:00'),
('working_hours_end', '17:00'),
('currency', 'SYP'),
('language', 'ar'),
('backup_frequency', 'daily'),
('session_timeout', '60');

