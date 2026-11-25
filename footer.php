<?php // includes/footer.php - Phần cuối trang (đóng các thẻ mở ở header.php) ?>
    </section>
    <footer class="footer"><?= h(APP_NAME) ?> &middot; Bài tập lớn học phần Phát triển ứng dụng trên nền Web &middot; PHP &amp; MySQL</footer>
  </div>
</div>
<?php
$jsVer = file_exists(__DIR__ . '/../assets/js/app.js') ? filemtime(__DIR__ . '/../assets/js/app.js') : '1.0';
$enhJsVer = file_exists(__DIR__ . '/../assets/js/enhance.js') ? filemtime(__DIR__ . '/../assets/js/enhance.js') : '1.0';
$dpJsVer = file_exists(__DIR__ . '/../assets/js/datepicker.js') ? filemtime(__DIR__ . '/../assets/js/datepicker.js') : '1.0';
?>
<script src="<?= h(url('assets/js/app.js?v=' . $jsVer)) ?>"></script>
<script src="<?= h(url('assets/js/enhance.js?v=' . $enhJsVer)) ?>"></script>
<script src="<?= h(url('assets/js/datepicker.js?v=' . $dpJsVer)) ?>"></script>
</body>
</html>

