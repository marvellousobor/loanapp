-- Run this once in phpMyAdmin

DROP DATABASE IF EXISTS loanapp;
CREATE DATABASE loanapp;
USE loanapp;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    wallet_balance DECIMAL(12,2) DEFAULT 0.00,
    monthly_income DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE kyc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    nin VARCHAR(11) NOT NULL,
    bvn VARCHAR(11) NOT NULL,
    status ENUM('pending','verified') DEFAULT 'verified',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type ENUM('credit','debit') DEFAULT 'credit',
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

CREATE TABLE loan_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    max_loan_percent DECIMAL(5,2) DEFAULT 50.00,
    min_loan_amount DECIMAL(12,2) DEFAULT 5000.00,
    max_loan_amount DECIMAL(12,2) DEFAULT 5000000.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO loan_config (max_loan_percent, min_loan_amount, max_loan_amount)
VALUES (50.00, 5000.00, 5000000.00);

-- Default admin (password: Admin@1234)
INSERT INTO users (name, email, password, is_admin)
VALUES ('Admin', 'admin@loanapp.com', '$2y$10$TKh8H1.PfuA2Ck4C9kIIe.MOEKZkAbsqZkLmqS0M0jHm7NHNX8Ay', 1);
