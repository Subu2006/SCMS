-- phpMyAdmin SQL Dump
-- Smart College Management System (SCMS) - God Level ERP
-- 3NF Normalized Architecture

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `scms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `scms`;

-- 1. Users Table (Core Auth)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','faculty','student') NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password for all mock users is 'password123'
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`) VALUES
(1, 'System Admin', 'admin@scms.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'Dr. Alan Turing', 'alan@scms.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty'),
(3, 'John Doe', 'student@scms.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
(4, 'Jane Smith', 'jane@scms.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student');

-- 2. Faculty Table
CREATE TABLE `faculty` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `faculty` (`id`, `user_id`, `department`) VALUES (1, 2, 'Computer Science');

-- 3. Students Table
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `enrollment_no` varchar(50) NOT NULL,
  `dept` varchar(100) NOT NULL,
  `semester` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `enrollment_no` (`enrollment_no`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `students` (`id`, `user_id`, `enrollment_no`, `dept`, `semester`) VALUES
(1, 3, 'CS2026001', 'Computer Science', 5),
(2, 4, 'CS2026002', 'Computer Science', 5);

-- 4. Courses Table
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `credits` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `courses` (`id`, `name`, `faculty_id`, `credits`) VALUES
(1, 'Web Technologies', 1, 4),
(2, 'Data Structures', 1, 4);

-- 5. Attendance Table (Feeds Risk Engine)
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `total_classes` int(11) NOT NULL DEFAULT 0,
  `attended_classes` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `attendance` (`student_id`, `course_id`, `total_classes`, `attended_classes`) VALUES
(1, 1, 40, 25), (1, 2, 40, 30), (2, 1, 40, 38), (2, 2, 40, 39);

-- 6. Marks Table (Feeds AI Planner)
CREATE TABLE `marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `internal_marks` float NOT NULL DEFAULT 0,
  `external_marks` float NOT NULL DEFAULT 0,
  `total_marks` float GENERATED ALWAYS AS (`internal_marks` + `external_marks`) STORED,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `marks` (`student_id`, `course_id`, `internal_marks`, `external_marks`) VALUES
(1, 1, 12, 25), (1, 2, 15, 30), (2, 1, 28, 65), (2, 2, 29, 68);

-- 7. Fees Table
CREATE TABLE `fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('Paid','Pending','Overdue') NOT NULL,
  `due_date` date NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `fees` (`student_id`, `amount`, `status`, `due_date`) VALUES
(1, 55000.00, 'Pending', '2026-05-01'), (2, 55000.00, 'Paid', '2026-05-01');

COMMIT;