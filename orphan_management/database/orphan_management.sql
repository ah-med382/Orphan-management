-- Create Database
CREATE DATABASE IF NOT EXISTS `orphan_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `orphan_management`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff', 'donor') NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Orphans Table
CREATE TABLE IF NOT EXISTS `orphans` (
  `orphan_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `admission_date` DATE NOT NULL,
  `photo` VARCHAR(255) DEFAULT NULL,
  `health_status` VARCHAR(255) DEFAULT 'Healthy',
  `education_level` VARCHAR(100) DEFAULT 'None',
  `guardian_information` TEXT DEFAULT NULL,
  `status` ENUM('Active', 'Sponsored', 'Adopted', 'Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Staff Table
CREATE TABLE IF NOT EXISTS `staff` (
  `staff_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `position` VARCHAR(100) NOT NULL,
  `salary` DECIMAL(10,2) NOT NULL,
  `joining_date` DATE NOT NULL,
  `profile_image` VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Donors Table
CREATE TABLE IF NOT EXISTS `donors` (
  `donor_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `address` TEXT DEFAULT NULL,
  `profile_image` VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Donations Table
CREATE TABLE IF NOT EXISTS `donations` (
  `donation_id` INT AUTO_INCREMENT PRIMARY KEY,
  `donor_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `donation_date` DATE NOT NULL,
  `notes` TEXT DEFAULT NULL,
  FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Sponsorships Table
CREATE TABLE IF NOT EXISTS `sponsorships` (
  `sponsorship_id` INT AUTO_INCREMENT PRIMARY KEY,
  `donor_id` INT NOT NULL,
  `orphan_id` INT NOT NULL,
  `sponsorship_amount` DECIMAL(10,2) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `status` ENUM('Active', 'Completed', 'Cancelled') DEFAULT 'Active',
  `orphan_left_at` DATE DEFAULT NULL,
  `notification_read` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE,
  FOREIGN KEY (`orphan_id`) REFERENCES `orphans` (`orphan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Adoption Requests Table
CREATE TABLE IF NOT EXISTS `adoption_requests` (
  `request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `applicant_name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `address` TEXT NOT NULL,
  `orphan_id` INT NOT NULL,
  `request_date` DATE NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  FOREIGN KEY (`orphan_id`) REFERENCES `orphans` (`orphan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Health Records Table
CREATE TABLE IF NOT EXISTS `health_records` (
  `record_id` INT AUTO_INCREMENT PRIMARY KEY,
  `orphan_id` INT NOT NULL,
  `diagnosis` TEXT NOT NULL,
  `treatment` TEXT NOT NULL,
  `vaccination` VARCHAR(255) DEFAULT NULL,
  `weight` VARCHAR(50) DEFAULT NULL,
  `height` VARCHAR(50) DEFAULT NULL,
  `other_details` TEXT DEFAULT NULL,
  `visit_date` DATE NOT NULL,
  FOREIGN KEY (`orphan_id`) REFERENCES `orphans` (`orphan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Education Records Table
CREATE TABLE IF NOT EXISTS `education_records` (
  `education_id` INT AUTO_INCREMENT PRIMARY KEY,
  `orphan_id` INT NOT NULL,
  `school_name` VARCHAR(150) NOT NULL,
  `grade` VARCHAR(50) NOT NULL,
  `performance` VARCHAR(100) NOT NULL,
  `target_grade` VARCHAR(50) DEFAULT NULL,
  `attendance_target` VARCHAR(50) DEFAULT NULL,
  `behavior` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`orphan_id`) REFERENCES `orphans` (`orphan_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Data (Password: password123)
-- Insert Users
INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@orphanage.com', '$2y$10$S4QKkCghWoxjL.0bdY5zfuqXVWpKuKpw8jxY5dNjeS2vT0owc596O', 'admin', 'active'),
(2, 'Sarah Jenkins', 'sarah@orphanage.com', '$2y$10$S4QKkCghWoxjL.0bdY5zfuqXVWpKuKpw8jxY5dNjeS2vT0owc596O', 'staff', 'active'),
(3, 'John Doe', 'john.doe@donor.com', '$2y$10$S4QKkCghWoxjL.0bdY5zfuqXVWpKuKpw8jxY5dNjeS2vT0owc596O', 'donor', 'active'),
(4, 'Robert Miller', 'robert.m@donor.com', '$2y$10$S4QKkCghWoxjL.0bdY5zfuqXVWpKuKpw8jxY5dNjeS2vT0owc596O', 'donor', 'active'),
(5, 'Jane Watson', 'jane.w@donor.com', '$2y$10$S4QKkCghWoxjL.0bdY5zfuqXVWpKuKpw8jxY5dNjeS2vT0owc596O', 'donor', 'active');

-- Insert Staff
INSERT INTO `staff` (`staff_id`, `user_id`, `full_name`, `phone`, `email`, `position`, `salary`, `joining_date`) VALUES
(1, 2, 'Sarah Jenkins', '+15550199', 'sarah@orphanage.com', 'Senior Caretaker', 3200.00, '2024-01-15');

-- Insert Donors
INSERT INTO `donors` (`donor_id`, `user_id`, `full_name`, `phone`, `email`, `address`) VALUES
(1, 3, 'John Doe', '+15550122', 'john.doe@donor.com', '123 Elm Street, Springfield'),
(2, 4, 'Robert Miller', '+15550133', 'robert.m@donor.com', '456 Oak Avenue, Metropolis'),
(3, 5, 'Jane Watson', '+15550144', 'jane.w@donor.com', '789 Maple Drive, Gotham');

-- Insert Orphans
INSERT INTO `orphans` (`orphan_id`, `full_name`, `gender`, `date_of_birth`, `admission_date`, `photo`, `health_status`, `education_level`, `guardian_information`, `status`) VALUES
(1, 'Billy Carter', 'Male', '2016-04-12', '2024-02-10', 'billy.jpg', 'Healthy', '3rd Grade', 'No known relatives. Transferred from child protection services.', 'Sponsored'),
(2, 'Emma Watson', 'Female', '2018-09-25', '2024-05-18', 'emma.jpg', 'Recovering from Cold', 'Kindergarten', 'Maternal aunt unable to support due to extreme poverty.', 'Active'),
(3, 'James Parker', 'Male', '2014-11-05', '2023-08-01', NULL, 'Asthmatic', '5th Grade', 'Parents deceased in a motor vehicle accident.', 'Active'),
(4, 'Lily Evans', 'Female', '2015-02-28', '2023-10-12', 'lily.jpg', 'Healthy', '4th Grade', 'Abandoned at local church. No identification details found.', 'Adopted');

-- Insert Donations
INSERT INTO `donations` (`donation_id`, `donor_id`, `amount`, `payment_method`, `donation_date`, `notes`) VALUES
(1, 1, 500.00, 'Credit Card', '2026-06-01', 'General monthly orphanage support donation.'),
(2, 2, 250.00, 'PayPal', '2026-06-10', 'For orphans holiday clothing budget.'),
(3, 3, 1000.00, 'Bank Transfer', '2026-06-15', 'School supplies and books distribution.'),
(4, 1, 300.00, 'Credit Card', '2026-07-01', 'Sponsorship billing for Billy Carter.');

-- Insert Sponsorships
INSERT INTO `sponsorships` (`sponsorship_id`, `donor_id`, `orphan_id`, `sponsorship_amount`, `start_date`, `end_date`, `status`) VALUES
(1, 1, 1, 150.00, '2026-02-10', NULL, 'Active');

-- Insert Adoption Requests
INSERT INTO `adoption_requests` (`request_id`, `applicant_name`, `phone`, `email`, `address`, `orphan_id`, `request_date`, `status`) VALUES
(1, 'Jane Watson', '+15550144', 'jane.w@donor.com', '789 Maple Drive, Gotham', 2, '2026-06-20', 'Pending');

-- Insert Health Records
INSERT INTO `health_records` (`record_id`, `orphan_id`, `diagnosis`, `treatment`, `visit_date`) VALUES
(1, 1, 'Routine Checkup', 'No issues. Vision and hearing standard.', '2026-05-12'),
(2, 3, 'Mild Asthma Flareup', 'Prescribed Albuterol inhaler twice daily as needed.', '2026-06-05');

-- Insert Education Records
INSERT INTO `education_records` (`education_id`, `orphan_id`, `school_name`, `grade`, `performance`, `updated_at`) VALUES
(1, 1, 'Springfield Elementary', '3rd Grade', 'Excellent in Mathematics, needs support in reading.', '2026-06-15 10:00:00'),
(2, 3, 'Springfield Elementary', '5th Grade', 'Good overall progress, outstanding in science projects.', '2026-06-15 10:30:00');
