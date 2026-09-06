-- Badminton Booking: full database installation
-- Includes 001_password_reset_tokens.sql and 002_booking_groups.sql.
-- For a new installation, import only this file in phpMyAdmin.

CREATE DATABASE IF NOT EXISTS badminton_booking
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE badminton_booking;

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    status ENUM('active', 'blocked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
    reset_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_password_reset_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    KEY index_reset_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE courts (
    court_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    court_name VARCHAR(100) NOT NULL,
    location VARCHAR(150) NOT NULL,
    description TEXT NULL,
    price_per_hour DECIMAL(10, 2) UNSIGNED NOT NULL,
    image_path VARCHAR(255) NULL,
    status ENUM('active', 'maintenance', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE time_slots (
    slot_id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    UNIQUE KEY unique_time_slot (start_time, end_time)
) ENGINE=InnoDB;

CREATE TABLE bookings (
    booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT UNSIGNED NULL,
    booking_code VARCHAR(20) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    court_id INT UNSIGNED NOT NULL,
    slot_id SMALLINT UNSIGNED NOT NULL,
    booking_date DATE NOT NULL,
    total_price DECIMAL(10, 2) UNSIGNED NOT NULL,
    booking_status ENUM('pending', 'confirmed', 'cancelled', 'completed')
        NOT NULL DEFAULT 'pending',
    note VARCHAR(255) NULL,
    active_reservation TINYINT
        GENERATED ALWAYS AS (
            CASE
                WHEN booking_status IN ('pending', 'confirmed') THEN 1
                ELSE NULL
            END
        ) STORED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_bookings_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_court
        FOREIGN KEY (court_id) REFERENCES courts(court_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_slot
        FOREIGN KEY (slot_id) REFERENCES time_slots(slot_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_group
        FOREIGN KEY (group_id) REFERENCES bookings(booking_id)
        ON UPDATE CASCADE ON DELETE SET NULL,

    UNIQUE KEY unique_active_booking (
        court_id,
        booking_date,
        slot_id,
        active_reservation
    ),
    KEY index_user_bookings (user_id, booking_date),
    KEY index_booking_group (group_id),
    KEY index_booking_date (booking_date)
) ENGINE=InnoDB;

CREATE TABLE payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL UNIQUE,
    payment_method ENUM('cash', 'bank_transfer', 'qr_code') NOT NULL,
    amount DECIMAL(10, 2) UNSIGNED NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded')
        NOT NULL DEFAULT 'pending',
    transaction_code VARCHAR(100) NULL,
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_payments_booking
        FOREIGN KEY (booking_id) REFERENCES bookings(booking_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO time_slots (start_time, end_time) VALUES
    ('06:00:00', '07:00:00'),
    ('07:00:00', '08:00:00'),
    ('08:00:00', '09:00:00'),
    ('09:00:00', '10:00:00'),
    ('10:00:00', '11:00:00'),
    ('14:00:00', '15:00:00'),
    ('15:00:00', '16:00:00'),
    ('16:00:00', '17:00:00'),
    ('17:00:00', '18:00:00'),
    ('18:00:00', '19:00:00'),
    ('19:00:00', '20:00:00'),
    ('20:00:00', '21:00:00'),
    ('21:00:00', '22:00:00');

INSERT INTO courts (
    court_name,
    location,
    description,
    price_per_hour,
    image_path
) VALUES
    (
        'Sân A1',
        'Khu A',
        'Sân tiêu chuẩn trong nhà, phù hợp cho luyện tập và thi đấu.',
        120000,
        'assets/images/court-a1.jpg'
    ),
    (
        'Sân A2',
        'Khu A',
        'Sân trong nhà có hệ thống thông gió và khu vực nghỉ ngơi.',
        120000,
        'assets/images/court-a2.jpg'
    ),
    (
        'Sân B1',
        'Khu B',
        'Sân có mặt thảm cao su tiêu chuẩn, đủ ánh sáng cho buổi tối.',
        150000,
        'assets/images/court-b1.jpg'
    );
