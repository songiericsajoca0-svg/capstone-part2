CREATE DATABASE tricycle_booking;
USE tricycle_booking;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    contact VARCHAR(20) DEFAULT NULL,
    role ENUM('passenger','admin') DEFAULT 'passenger',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_code VARCHAR(20) UNIQUE NOT NULL,
    passenger_id INT NOT NULL,
    pickup_landmark VARCHAR(100) NOT NULL,
    dropoff_landmark VARCHAR(100) NOT NULL,
    notes TEXT,
    driver_name VARCHAR(100) DEFAULT NULL,
    status ENUM('PENDING','ASSIGNED','PASSENGER PICKED UP','IN TRANSIT','COMPLETED','CANCELLED') DEFAULT 'PENDING',
    pickup_time DATETIME DEFAULT NULL,
    dropoff_time DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE
);