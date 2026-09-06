<?php

function find_booking_group(PDO $conn, int $booking_id, ?int $user_id = null): array
{
    $anchor_stmt = $conn->prepare(
        'SELECT COALESCE(group_id, booking_id) AS group_key, user_id
         FROM bookings
         WHERE booking_id = ?
         LIMIT 1'
    );
    $anchor_stmt->execute([$booking_id]);
    $anchor = $anchor_stmt->fetch();

    if (!$anchor || ($user_id !== null && (int) $anchor['user_id'] !== $user_id)) {
        return [];
    }

    $sql = "SELECT
                b.booking_id,
                b.group_id,
                b.booking_code,
                b.user_id,
                b.court_id,
                b.booking_date,
                b.total_price,
                b.booking_status,
                b.created_at,
                c.court_name,
                c.location,
                ts.slot_id,
                ts.start_time,
                ts.end_time,
                u.full_name,
                u.email,
                u.phone,
                p.payment_id,
                p.payment_method,
                p.payment_status,
                p.transaction_code,
                p.paid_at
            FROM bookings b
            JOIN courts c ON c.court_id = b.court_id
            JOIN time_slots ts ON ts.slot_id = b.slot_id
            JOIN users u ON u.user_id = b.user_id
            LEFT JOIN payments p ON p.booking_id = b.booking_id
            WHERE (b.booking_id = ? OR b.group_id = ?)
              AND b.user_id = ?
            ORDER BY ts.start_time";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $anchor['group_key'],
        $anchor['group_key'],
        $anchor['user_id'],
    ]);

    return $stmt->fetchAll();
}

function booking_group_ids(array $bookings): array
{
    return array_map(
        static fn (array $booking): int => (int) $booking['booking_id'],
        $bookings
    );
}

function booking_group_total(array $bookings): float
{
    return array_sum(array_map(
        static fn (array $booking): float => (float) $booking['total_price'],
        $bookings
    ));
}

function booking_group_has_status(array $bookings, string $status): bool
{
    foreach ($bookings as $booking) {
        if ($booking['booking_status'] === $status) {
            return true;
        }
    }

    return false;
}

function booking_group_all_status(array $bookings, string $status): bool
{
    if (!$bookings) {
        return false;
    }

    foreach ($bookings as $booking) {
        if ($booking['booking_status'] !== $status) {
            return false;
        }
    }

    return true;
}
