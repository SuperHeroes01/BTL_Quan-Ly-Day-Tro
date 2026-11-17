
<?php
// login.php - Đăng nhập hệ thống và điều hướng theo vai trò.

require_once __DIR__ . '/includes/bootstrap.php';

if (Auth::check()) {
    redirect('home.php');
}

$error = '';
$username = '';

if (is_post()) {
    csrf_check();

    $username = post('username');
    $password = post('password');

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu';
    } else {
        $res = (new User())->login($username, $password);

        if ($res['ok']) {

            // Lưu thông tin tài khoản vào session
            // Đồng thời Auth::login() sẽ tự ghi lịch sử đăng nhập
            Auth::login($res['user']);

            // Chuyển đến trang chủ
            redirect('home.php');
        }

        $error = $res['msg'];
    }
}

$brand = Setting::get('ten_day_tro', APP_NAME);
$messages = flash();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Đăng nhập - <?= h(APP_NAME) ?></title>

    <link rel="stylesheet"
          href="<?= h(url('assets/css/style.css?v=6.3')) ?>">
</head>

<body class="auth-page">

<div class="auth-layout">

    <section class="auth-visual">

        <div class="auth-brand">
            <span class="auth-mark">⌂</span>

            <span>
                <b><?= h(APP_NAME) ?></b>
                <small><?= h($brand) ?></small>
            </span>
        </div>

        <div class="auth-copy">
            <span class="eyebrow">QUẢN LÝ NHÀ TRỌ THÔNG MINH</span>

            <h1>Không gian sống tốt bắt đầu từ quản lý tốt.</h1>

            <p>
                Theo dõi phòng trọ, hợp đồng, hóa đơn và dịch vụ
                trong cùng một hệ thống.
            </p>
        </div>

        <img src="<?= h(url('assets/images/login-hero.svg')) ?>"
             alt="Minh họa phòng trọ hiện đại"
             class="auth-art">

        <div class="auth-features">
            <span>✓ Quản lý phòng</span>
            <span>✓ Theo dõi hóa đơn</span>
            <span>✓ Hỗ trợ người thuê</span>
        </div>

    </section>

    <section class="auth-form-wrap">

        <form class="auth-form" method="post" data-validate>

            <?= csrf_field() ?>

            <div class="auth-heading">
                <span class="auth-tag">WELCOME BACK</span>

                <h2>Đăng nhập</h2>

                <p>
                    Nhập thông tin để tiếp tục sử dụng hệ thống.
                </p>
            </div>

            <?php foreach ($messages as $m): ?>
                <div class="alert <?= h($m[0]) ?>">
                    <?= h($m[1]) ?>
                </div>
            <?php endforeach; ?>

            <?php if ($error): ?>
                <div class="alert error">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <div class="field">
                <label>Tên đăng nhập</label>

                <input
                    name="username"
                    value="<?= h($username) ?>"
                    placeholder="Nhập tên đăng nhập..."
                    required
                    autocomplete="username"
                    autofocus>
            </div>

            <div class="field">
                <label>Mật khẩu</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Nhập mật khẩu..."
                    required
                    autocomplete="current-password">
            </div>

            <div class="auth-forgot">
                <a href="<?= h(url('quen_mat_khau.php')) ?>">
                    Quên mật khẩu?
                </a>
            </div>

            <button class="btn auth-submit" type="submit">
                Đăng nhập
            </button>

            <div class="auth-links">
                <span>Chưa có tài khoản?</span>
                <a href="<?= h(url('register.php')) ?>">
                    Đăng ký ngay
                </a>
            </div>

            <div class="auth-bottom">
                <a href="<?= h(url('index.php')) ?>">
                    ← Xem danh sách phòng trống
                </a>
            </div>

        </form>

    </section>

</div>

</body>
</html>
