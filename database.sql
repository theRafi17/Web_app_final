-- Create database
CREATE DATABASE IF NOT EXISTS smart_parking;
USE smart_parking;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Parking spots table
CREATE TABLE IF NOT EXISTS parking_spots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    spot_number VARCHAR(20) NOT NULL UNIQUE,
    floor_number INT NOT NULL,
    type ENUM('standard', 'handicap', 'electric', 'vip') NOT NULL,
    hourly_rate DECIMAL(10,2) NOT NULL,
    is_occupied TINYINT(1) DEFAULT 0,
    vehicle_number VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    spot_id INT NOT NULL,
    vehicle_number VARCHAR(20) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    amount DECIMAL(10,2) DEFAULT NULL,
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (spot_id) REFERENCES parking_spots(id)
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_method ENUM('cash', 'card', 'paypal', 'bank_transfer') NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
);

-- Insert default admin user
INSERT INTO users (name, email, password, is_admin) VALUES 
('Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Insert sample parking spots
INSERT INTO parking_spots (spot_number, floor_number, type, hourly_rate) VALUES
('A1', 1, 'standard', 5.00),
('A2', 1, 'standard', 5.00),
('A3', 1, 'handicap', 5.00),
('B1', 2, 'standard', 6.00),
('B2', 2, 'electric', 7.00),
('B3', 2, 'vip', 10.00),
('C1', 3, 'standard', 5.00),
('C2', 3, 'handicap', 5.00),
('C3', 3, 'electric', 7.00);

-- Create indexes for better performance
CREATE INDEX idx_bookings_user_id ON bookings(user_id);
CREATE INDEX idx_bookings_spot_id ON bookings(spot_id);
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_payments_booking_id ON payments(booking_id);
CREATE INDEX idx_payments_payment_date ON payments(payment_date);
CREATE INDEX idx_parking_spots_type ON parking_spots(type);
CREATE INDEX idx_parking_spots_is_occupied ON parking_spots(is_occupied); 