CREATE DATABASE IF NOT EXISTS smart_parking;
USE smart_parking;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS parking_spots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spot_number VARCHAR(10) NOT NULL UNIQUE,
    floor_number INT NOT NULL,
    is_occupied BOOLEAN DEFAULT FALSE,
    vehicle_number VARCHAR(20) NULL,
    type ENUM('standard', 'handicap', 'electric', 'Car', 'Bike', 'VIP') DEFAULT 'standard',
    hourly_rate DECIMAL(10,2) DEFAULT 5.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    spot_id INT NOT NULL,
    vehicle_number VARCHAR(20) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (spot_id) REFERENCES parking_spots(id)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_method ENUM('cash', 'card', 'paypal', 'bank_transfer') NOT NULL,
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- Insert sample parking spots
INSERT INTO `parking_spots` (`id`, `spot_number`, `floor_number`, `type`, `hourly_rate`, `is_occupied`) VALUES
(1, 'A1', 0, 'Car', 50.00, 0),
(2, 'A2', 0, 'Car', 50.00, 0),
(3, 'B1', 0, 'Bike', 20.00, 0),
(4, 'B2', 0, 'Bike', 20.00, 0),
(5, 'V1', 0, 'VIP', 100.00, 0),
(6, 'A3', 1, 'Car', 60.00, 0),
(7, 'A4', 1, 'Car', 60.00, 0),
(8, 'B3', 1, 'Bike', 25.00, 0),
(9, 'B4', 1, 'Bike', 25.00, 0),
(10, 'V2', 1, 'VIP', 120.00, 0),
(11, 'A5', 2, 'Car', 70.00, 0),
(12, 'A6', 2, 'Car', 70.00, 0),
(13, 'B5', 2, 'Bike', 30.00, 0),
(14, 'B6', 2, 'Bike', 30.00, 0),
(15, 'V3', 2, 'VIP', 150.00, 0);

-- Add Default user with admin and normal user
INSERT INTO users (name, email, password, is_admin) VALUES
('Admin User', 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Public User', 'user@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0);

-- Admin
-- Password: password
-- User
-- Password: password

-- Insert sample parking spots
-- INSERT INTO parking_spots (spot_number, floor_number, type) VALUES
-- ('A1', 1, 'standard'),
-- ('A2', 1, 'standard'),
-- ('A3', 1, 'handicap'),
-- ('B1', 1, 'standard'),
-- ('B2', 1, 'electric'),
-- ('B3', 1, 'standard'),
-- ('C1', 2, 'standard'),
-- ('C2', 2, 'handicap'),
-- ('C3', 2, 'standard');

-- Update existing parking spots with different hourly rates
-- UPDATE parking_spots SET hourly_rate = 5.00 WHERE type = 'standard';
-- UPDATE parking_spots SET hourly_rate = 8.00 WHERE type = 'premium';
-- UPDATE parking_spots SET hourly_rate = 10.00 WHERE type = 'reserved';
