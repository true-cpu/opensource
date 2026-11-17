CREATE DATABASE IF NOT EXISTS huit_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE huit_db;

CREATE TABLE IF NOT EXISTS branches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branch_name VARCHAR(120) NOT NULL UNIQUE,
  address VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS teachers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  expertise VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'teacher') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_teacher FOREIGN KEY (teacher_id)
    REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schedules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  subject_name VARCHAR(180) NOT NULL,
  room VARCHAR(80) NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  shift_or_periods VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_schedules_day CHECK (day_of_week BETWEEN 1 AND 7),
  CONSTRAINT fk_schedules_teacher FOREIGN KEY (teacher_id)
    REFERENCES teachers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_schedules_branch FOREIGN KEY (branch_id)
    REFERENCES branches(id) ON DELETE RESTRICT,
  INDEX idx_schedules_day (day_of_week),
  INDEX idx_schedules_teacher_day (teacher_id, day_of_week),
  INDEX idx_schedules_branch_day (branch_id, day_of_week)
) ENGINE=InnoDB;
