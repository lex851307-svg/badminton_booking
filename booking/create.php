<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$request_data = is_post_request() ? $_POST : $_GET;
$court_id = filter_var($request_data['court_id'] ?? null, FILTER_VALIDATE_INT);
$booking_date = $request_data['date'] ?? '';
$raw_slot_ids = $request_data['slot_ids'] ?? [];

// Giữ tương thích với liên kết đặt một khung giờ cũ.
if (!$raw_slot_ids && isset($request_data['slot_id'])) {
    $raw_slot_ids = [$request_data['slot_id']];
}

if (!is_array($raw_slot_ids)) {
    $raw_slot_ids = [$raw_slot_ids];
}

$slot_ids = array_values(array_unique(array_filter(
    array_map(static fn ($value) => filter_var($value, FILTER_VALIDATE_INT), $raw_slot_ids)
)));

if (
    !$court_id
    || !is_string($booking_date)
    || !is_valid_date($booking_date)
    || !$slot_ids
    || count($slot_ids) > 18
) {
    set_flash('error', 'Thông tin đặt sân không hợp lệ.');
    redirect('courts/');
}

if ($booking_date < date('Y-m-d')) {
    set_flash('error', 'Không thể đặt sân cho ngày đã qua.');
    redirect('courts/detail.php?id=' . $court_id);
}

$court_stmt = $conn->prepare(
    "SELECT court_id, court_name, location, price_per_hour
     FROM courts
     WHERE court_id = ? AND status = 'active'
     LIMIT 1"
);
$court_stmt->execute([$court_id]);
$court = $court_stmt->fetch();

if (!$court) {
    set_flash('error', 'Sân không còn hoạt động.');
    redirect('courts/');
}

$placeholders = implode(',', array_fill(0, count($slot_ids), '?'));
$slot_stmt = $conn->prepare(
    "SELECT slot_id, start_time, end_time
     FROM time_slots
     WHERE slot_id IN ($placeholders)
     ORDER BY start_time"
);
$slot_stmt->execute($slot_ids);
$selected_slots = $slot_stmt->fetchAll();

if (count($selected_slots) !== count($slot_ids)) {
    set_flash('error', 'Có khung giờ không hợp lệ.');
    redirect('courts/detail.php?id=' . $court_id . '&date=' . $booking_date);
}

$total_price = 0.0;

foreach ($selected_slots as $slot) {
    if ($booking_date === date('Y-m-d') && $slot['end_time'] <= date('H:i:s')) {
        set_flash('error', 'Có khung giờ đã kết thúc. Vui lòng chọn lại.');
        redirect('courts/detail.php?id=' . $court_id . '&date=' . $booking_date);
    }

    $hours = (strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 3600;
    $total_price += (float) $court['price_per_hour'] * $hours;
}

$error = '';

if (is_post_request()) {
    verify_csrf();

    try {
        $conn->beginTransaction();

        $availability_params = array_merge([$court_id, $booking_date], $slot_ids);
        $availability_stmt = $conn->prepare(
            "SELECT slot_id
             FROM bookings
             WHERE court_id = ?
               AND booking_date = ?
               AND slot_id IN ($placeholders)
               AND booking_status IN ('pending', 'confirmed')
             FOR UPDATE"
        );
        $availability_stmt->execute($availability_params);
        $unavailable_slots = $availability_stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($unavailable_slots) {
            throw new DomainException('Selected time slots are no longer available.');
        }

        $insert_stmt = $conn->prepare(
            "INSERT INTO bookings (
                booking_code, user_id, court_id, slot_id, booking_date, total_price
             ) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $created_booking_ids = [];

        foreach ($selected_slots as $slot) {
            $hours = (strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 3600;
            $slot_price = (float) $court['price_per_hour'] * $hours;
            $booking_code = 'BK' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));

            $insert_stmt->execute([
                $booking_code,
                current_user_id(),
                $court_id,
                $slot['slot_id'],
                $booking_date,
                $slot_price,
            ]);
            $created_booking_ids[] = (int) $conn->lastInsertId();
        }

        if (count($created_booking_ids) > 1) {
            $group_id = min($created_booking_ids);
            $group_placeholders = implode(',', array_fill(0, count($created_booking_ids), '?'));
            $group_stmt = $conn->prepare(
                "UPDATE bookings SET group_id = ? WHERE booking_id IN ($group_placeholders)"
            );
            $group_stmt->execute(array_merge([$group_id], $created_booking_ids));
        }

        $conn->commit();
        set_flash(
            'success',
            'Đặt thành công ' . count($created_booking_ids) . ' khung giờ.'
        );
        redirect('booking/detail.php?booking_id=' . min($created_booking_ids));
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        if ($e instanceof DomainException || $e->getCode() === '23000') {
            $error = 'Một hoặc nhiều khung giờ không còn trống. Vui lòng chọn lại.';
        } else {
            error_log('Create group booking failed: ' . $e->getMessage());
            $error = 'Không thể tạo đặt sân lúc này. Vui lòng thử lại.';
        }
    }
}

$page_title = 'Xác nhận đặt sân';
$extra_css = ['booking.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<section class="booking-section">
    <div class="container booking-container">
        <a class="back-link" href="<?= e(url('courts/detail.php?id=' . $court_id . '&date=' . $booking_date)) ?>">← Chọn lại khung giờ</a>

        <div class="booking-layout">
            <div class="booking-summary">
                <span class="summary-icon">🏸</span>
                <h1>Xác nhận đặt sân</h1>
                <p>Bạn đã chọn <?= count($selected_slots) ?> khung giờ. Vui lòng kiểm tra trước khi xác nhận.</p>

                <?php if ($error): ?><div class="form-error" role="alert"><?= e($error) ?></div><?php endif; ?>

                <dl class="booking-details">
                    <div><dt>Sân</dt><dd><?= e($court['court_name']) ?></dd></div>
                    <div><dt>Khu vực</dt><dd><?= e($court['location']) ?></dd></div>
                    <div><dt>Ngày đặt</dt><dd><?= e(date('d/m/Y', strtotime($booking_date))) ?></dd></div>
                    <div><dt>Số khung giờ</dt><dd><?= count($selected_slots) ?></dd></div>
                </dl>

                <div class="selected-slot-list">
                    <?php foreach ($selected_slots as $slot): ?>
                        <?php $slot_hours = (strtotime($slot['end_time']) - strtotime($slot['start_time'])) / 3600; ?>
                        <div>
                            <span><?= e(substr($slot['start_time'], 0, 5)) ?>–<?= e(substr($slot['end_time'], 0, 5)) ?></span>
                            <strong><?= number_format((float) $court['price_per_hour'] * $slot_hours, 0, ',', '.') ?>đ</strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="booking-total">
                    <span>Tổng tiền</span>
                    <strong><?= number_format($total_price, 0, ',', '.') ?>đ</strong>
                </div>

                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="court_id" value="<?= (int) $court_id ?>">
                    <input type="hidden" name="date" value="<?= e($booking_date) ?>">
                    <?php foreach ($slot_ids as $slot_id): ?>
                        <input type="hidden" name="slot_ids[]" value="<?= (int) $slot_id ?>">
                    <?php endforeach; ?>
                    <button class="button button-primary confirm-button" type="submit">Xác nhận đặt sân</button>
                </form>
            </div>

            <aside class="booking-note">
                <h2>Lưu ý</h2>
                <ul>
                    <li>Các khung giờ được lưu trong cùng một nhóm.</li>
                    <li>Nếu một khung giờ không còn trống, toàn bộ yêu cầu sẽ không được tạo.</li>
                    <li>Nhóm đặt sân sẽ ở trạng thái chờ thanh toán.</li>
                </ul>
            </aside>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
