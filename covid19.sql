-- COVID-19 Management System Database
-- Created: 2025-09-05
-- Description: Database schema for managing COVID-19 testing, vaccination, and hospital management

-- Create database
CREATE DATABASE IF NOT EXISTS covid19;
USE covid19;

-- Set charset and collation
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- Table: users
-- Description: Stores user information for admin, hospital staff, and patients
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hospital', 'patient') NOT NULL DEFAULT 'patient',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: hospitals
-- Description: Stores hospital information and approval status
-- =====================================================
CREATE TABLE IF NOT EXISTS hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    address TEXT NOT NULL,
    contact VARCHAR(20) NOT NULL,
    status ENUM('approved', 'pending') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: patients
-- Description: Stores patient-specific information linked to users
-- =====================================================
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address TEXT NOT NULL,
    dob DATE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: appointments
-- Description: Stores appointment information for tests and vaccinations
-- =====================================================
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    hospital_id INT NOT NULL,
    type ENUM('test', 'vaccination') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_patient_id (patient_id),
    INDEX idx_hospital_id (hospital_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: test_results
-- Description: Stores COVID-19 test results linked to appointments
-- =====================================================
CREATE TABLE IF NOT EXISTS test_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    result ENUM('positive', 'negative') NOT NULL,
    remarks TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_appointment_id (appointment_id),
    INDEX idx_result (result),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: vaccines
-- Description: Stores available vaccine types and their status
-- =====================================================
CREATE TABLE IF NOT EXISTS vaccines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    manufacturer VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('available', 'unavailable') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: vaccinations
-- Description: Stores vaccination records for patients
-- =====================================================
CREATE TABLE IF NOT EXISTS vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    vaccine_id INT NOT NULL,
    dose_number TINYINT NOT NULL CHECK (dose_number > 0),
    status ENUM('done', 'pending') NOT NULL DEFAULT 'pending',
    date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_patient_id (patient_id),
    INDEX idx_vaccine_id (vaccine_id),
    INDEX idx_dose_number (dose_number),
    INDEX idx_status (status),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Sample Data Insertion (Optional)
-- =====================================================

-- Insert sample admin user
INSERT IGNORE INTO users (name, email, password, role) VALUES 
('System Admin', 'admin@covid19system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Hospital User', 'hospital@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hospital'),
('Patient User', 'patient@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient');

-- Insert sample hospital
INSERT IGNORE INTO hospitals (name, address, contact, status) VALUES 
('City General Hospital', '123 Main Street, Downtown', '+1-555-0123', 'approved'),
('Metro Health Center', '456 Oak Avenue, Midtown', '+1-555-0456', 'approved');

-- Insert sample patient
INSERT IGNORE INTO patients (user_id, address, dob, phone) VALUES 
(3, '789 Patient Street, City', '1990-01-01', '+1-555-0789');

-- Insert sample vaccines
INSERT IGNORE INTO vaccines (name, manufacturer, description, status) VALUES 
('Pfizer-BioNTech', 'Pfizer Inc.', 'mRNA COVID-19 vaccine', 'available'),
('Moderna', 'Moderna Inc.', 'mRNA COVID-19 vaccine', 'available'),
('Johnson & Johnson', 'Janssen Pharmaceuticals', 'Viral vector COVID-19 vaccine', 'available'),
('AstraZeneca', 'AstraZeneca', 'Viral vector COVID-19 vaccine', 'available');

-- Insert sample appointments
INSERT IGNORE INTO appointments (patient_id, hospital_id, type, status, date) VALUES 
(1, 1, 'test', 'approved', '2024-01-15 10:00:00'),
(1, 1, 'vaccination', 'approved', '2024-01-20 14:00:00');

-- Insert sample test results
INSERT IGNORE INTO test_results (appointment_id, result, remarks) VALUES 
(1, 'negative', 'Normal test result');

-- Insert sample vaccination
INSERT IGNORE INTO vaccinations (patient_id, vaccine_id, dose_number, status, date) VALUES 
(1, 1, 1, 'done', '2024-01-20 14:00:00');

-- =====================================================
-- Useful Views (Optional)
-- =====================================================

-- View for patient appointments with hospital details
CREATE OR REPLACE VIEW patient_appointments AS
SELECT 
    a.id as appointment_id,
    u.name as patient_name,
    u.email as patient_email,
    h.name as hospital_name,
    h.address as hospital_address,
    a.type,
    a.status,
    a.date,
    a.created_at
FROM appointments a
JOIN patients p ON a.patient_id = p.id
JOIN users u ON p.user_id = u.id
JOIN hospitals h ON a.hospital_id = h.id;

-- View for vaccination records with patient details
CREATE OR REPLACE VIEW vaccination_records AS
SELECT 
    v.id as vaccination_id,
    u.name as patient_name,
    u.email as patient_email,
    vac.name as vaccine_name,
    vac.manufacturer,
    v.dose_number,
    v.status,
    v.date,
    v.created_at
FROM vaccinations v
JOIN patients p ON v.patient_id = p.id
JOIN users u ON p.user_id = u.id
JOIN vaccines vac ON v.vaccine_id = vac.id;

-- View for test results with patient and hospital details
CREATE OR REPLACE VIEW test_results_view AS
SELECT 
    tr.id as result_id,
    u.name as patient_name,
    u.email as patient_email,
    h.name as hospital_name,
    tr.result,
    tr.remarks,
    a.date as test_date,
    tr.updated_at
FROM test_results tr
JOIN appointments a ON tr.appointment_id = a.id
JOIN patients p ON a.patient_id = p.id
JOIN users u ON p.user_id = u.id
JOIN hospitals h ON a.hospital_id = h.id
WHERE a.type = 'test';

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- End of COVID-19 Management System Database Schema
