    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <a class="brand footer-brand" href="<?= e(url()) ?>">
                    <span class="brand-icon">🏸</span>
                    <span>Badminton Booking</span>
                </a>
                <p>Đặt sân cầu lông trực tuyến nhanh chóng, rõ ràng và thuận tiện.</p>
            </div>

            <div>
                <h2>Liên kết</h2>
                <a href="<?= e(url()) ?>">Trang chủ</a>
                <a href="<?= e(url('courts/')) ?>">Danh sách sân</a>
                <a href="<?= e(url('auth/login.php')) ?>">Đăng nhập</a>
            </div>

            <div>
                <h2>Liên hệ</h2>
                <p>123 Nguyễn Văn A, TP. Hồ Chí Minh</p>
                <p>090 000 0000</p>
                <p>contact@badminton.test</p>
            </div>
        </div>

        <div class="container copyright">
            &copy; <?= date('Y') ?> Badminton Booking
        </div>
    </footer>

    <script src="<?= e(url('assets/js/main.js')) ?>"></script>
</body>
</html>
