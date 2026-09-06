USE badminton_booking;

ALTER TABLE bookings
    ADD COLUMN group_id INT UNSIGNED NULL AFTER booking_id,
    ADD KEY index_booking_group (group_id),
    ADD CONSTRAINT fk_bookings_group
        FOREIGN KEY (group_id) REFERENCES bookings(booking_id)
        ON UPDATE CASCADE ON DELETE SET NULL;
