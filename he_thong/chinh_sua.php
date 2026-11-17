<?php
// he_thong/chinh_sua.php - Tab "Chỉnh sửa hệ thống": thông tin dãy trọ, hạn thanh toán, đơn giá và danh mục dịch vụ (chỉ Admin)
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::guard(['admin']);
$svcM = new Service();
$roomM = new Room();

if (is_post()) {
    csrf_check();
    $act = post('action');
    try {
        if ($act === 'settings') {
            $days = post('payment_days');
            if (!ctype_digit($days) || (int) $days < 1 || (int) $days > 60) { throw new RuntimeException('Hạn thanh toán phải từ 1 đến 60 ngày'); }
            if (post('ten_day_tro') === '') { throw new RuntimeException('Tên dãy trọ không được để trống'); }
            foreach (['ten_day_tro', 'dia_chi', 'dien_thoai', 'email', 'payment_days', 'meter_day'] as $k) { Setting::set($k, post($k)); }
            flash('success', 'Đã lưu thông tin hệ thống');
        } elseif ($act === 'landlord') {
            if (post('chu_tro_ngay_sinh') !== '' && !valid_date(post('chu_tro_ngay_sinh'))) { throw new RuntimeException('Ngày sinh của Bên A không hợp lệ'); }
            if (post('chu_tro_ngay_cap') !== '' && !valid_date(post('chu_tro_ngay_cap'))) { throw new RuntimeException('Ngày cấp giấy tờ của Bên A không hợp lệ'); }
            foreach (['chu_tro_ho_ten', 'chu_tro_ngay_sinh', 'chu_tro_ho_khau', 'chu_tro_cccd', 'chu_tro_ngay_cap', 'chu_tro_noi_cap', 'chu_tro_dien_thoai'] as $k) { Setting::set($k, post($k)); }
            flash('success', 'Đã lưu thông tin Bên A dùng trong hợp đồng thuê phòng');
        } elseif ($act === 'price') {
            $price = post('unit_price');
            if (!is_numeric($price) || $price < 0) { throw new RuntimeException('Đơn giá không hợp lệ'); }
            $svcM->updatePrice((int) post('id'), (float) $price);
            flash('success', 'Đã cập nhật đơn giá (áp dụng cho hóa đơn lập từ nay)');
        } elseif ($act === 'room_price') {
            $roomId = (int) post('room_id');
            $price = post('room_price');
            if ($roomId <= 0 || !preg_match('/^[0-9]+$/D', trim((string) $price)) || (float) $price < 1 || (float) $price > 999999999999) { throw new RuntimeException('Vui lòng chọn phòng và nhập giá thuê là số nguyên dương, tối đa 999.999.999.999 đồng'); }
            $room = $roomM->find($roomId);
            if (!$room) { throw new RuntimeException('Không tìm thấy phòng cần cập nhật'); }
            $roomM->update($roomId, ['price' => (int) $price]);
            flash('success', 'Đã cập nhật giá thuê phòng ' . $room['room_number'] . '. Giá hợp đồng và hóa đơn đã lập không tự thay đổi.');
        } elseif ($act === 'add') {
            $code = strtolower(post('code'));
            if (!preg_match('/^[a-z0-9_]{2,20}$/', $code)) { throw new RuntimeException('Mã dịch vụ gồm 2-20 ký tự chữ thường, số, gạch dưới'); }
            if ($svcM->codeExists($code)) { throw new RuntimeException('Mã dịch vụ đã tồn tại'); }
            if (post('service_name') === '' || !is_numeric(post('unit_price')) || post('unit_price') < 0) { throw new RuntimeException('Vui lòng nhập tên và đơn giá hợp lệ'); }
            $svcM->addService(['code' => $code, 'service_name' => post('service_name'), 'unit_price' => (float) post('unit_price'), 'unit' => post('unit'),
                               'type' => post('type') === 'Theo chỉ số' ? 'Theo chỉ số' : 'Cố định', 'description' => post('description'), 'is_active' => 1]);
            flash('success', 'Đã thêm dịch vụ');
        } elseif ($act === 'delete') {
            $r = $svcM->deleteService((int) post('id'));
            flash('success', $r === 'hidden' ? 'Dịch vụ đã được dùng trong hóa đơn nên chỉ được ẩn đi' : 'Đã xóa dịch vụ');
        } elseif ($act === 'show') {
            $svcM->update((int) post('id'), ['is_active' => 1]); flash('success', 'Đã hiện lại dịch vụ');
        }
    } catch (RuntimeException $e) { flash('error', $e->getMessage()); }
      catch (mysqli_sql_exception $e) { flash('error', mysql_error_text($e)); }
    redirect('he_thong/chinh_sua.php');
}
$pageTitle = 'Quản Lý Hệ Thống';
$pageSub = 'Phân quyền truy cập và chỉnh sửa yêu cầu hệ thống';
$active = 'he_thong';
require __DIR__ . '/../includes/header.php';
echo tabs(['pq' => ['Phân quyền truy cập', 'he_thong/phan_quyen.php'], 'cs' => ['Chỉnh sửa hệ thống', 'he_thong/chinh_sua.php'], 'nv' => ['Nhân viên kỹ thuật', 'he_thong/nhan_vien.php']], 'cs');
$services = $svcM->all();
$rooms = $roomM->all('room_number');
?>
<div class="grid g2">
  <form class="card" method="post" data-validate><div class="card-h">Thông tin hệ thống</div><div class="card-b"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <div class="field"><label>Tên dãy trọ *</label><input name="ten_day_tro" value="<?= h(Setting::get('ten_day_tro')) ?>" required data-label="Tên dãy trọ"></div>
    <div class="field"><label>Địa chỉ</label><input name="dia_chi" value="<?= h(Setting::get('dia_chi')) ?>"></div>
    <div class="form-row"><div class="field"><label>Điện thoại</label><input name="dien_thoai" value="<?= h(Setting::get('dien_thoai')) ?>"></div>
      <div class="field"><label>Email</label><input name="email" value="<?= h(Setting::get('email')) ?>"></div>
      <div class="field"><label>Hạn thanh toán (ngày kể từ lúc lập)</label><input type="number" name="payment_days" min="1" max="60" value="<?= h(Setting::get('payment_days', '10')) ?>"></div>
      <div class="field"><label>Ngày chốt chỉ số hàng tháng</label><input type="number" name="meter_day" min="1" max="31" value="<?= h(Setting::get('meter_day', '28')) ?>"></div></div>
    <button class="btn">Lưu thông tin</button></div></form>
  <form class="card" method="post" data-validate><div class="card-h">Thêm dịch vụ mới</div><div class="card-b"><?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="form-row"><div class="field"><label>Mã dịch vụ *</label><input name="code" required data-label="Mã dịch vụ" placeholder="vd: bai_xe"></div>
      <div class="field"><label>Tên dịch vụ *</label><input name="service_name" required data-label="Tên dịch vụ"></div>
      <div class="field"><label>Đơn giá (đ) *</label><input type="number" name="unit_price" min="0" step="1000" required data-label="Đơn giá"></div>
      <div class="field"><label>Đơn vị</label><input name="unit" placeholder="tháng, kWh, m³"></div>
      <div class="field"><label>Cách tính</label><select name="type"><option>Cố định</option><option>Theo chỉ số</option></select></div>
      <div class="field"><label>Mô tả</label><input name="description"></div></div>
    <button class="btn green">+ Thêm dịch vụ</button></div></form>
</div>
<div class="card" style="margin-top:16px"><div class="card-h">Thông tin Bên A - người cho thuê (dùng trong hợp đồng)</div><div class="card-b">
  <p class="t-muted" style="margin-bottom:12px">Nhập thông tin của chủ thể đứng tên cho thuê theo mẫu PDF. Các trường để trống sẽ được in thành dòng chấm để bổ sung thủ công.</p>
  <form method="post" data-validate><?= csrf_field() ?><input type="hidden" name="action" value="landlord">
    <div class="form-row">
      <div class="field"><label>Ông/bà - Họ và tên</label><input name="chu_tro_ho_ten" value="<?= h(Setting::get('chu_tro_ho_ten')) ?>"></div>
      <div class="field"><label>Ngày sinh</label><input type="date" name="chu_tro_ngay_sinh" value="<?= h(Setting::get('chu_tro_ngay_sinh')) ?>"></div>
      <div class="field"><label>Nơi đăng ký hộ khẩu</label><input name="chu_tro_ho_khau" value="<?= h(Setting::get('chu_tro_ho_khau')) ?>"></div>
      <div class="field"><label>Số CCCD/CMND</label><input name="chu_tro_cccd" value="<?= h(Setting::get('chu_tro_cccd')) ?>"></div>
      <div class="field"><label>Ngày cấp</label><input type="date" name="chu_tro_ngay_cap" value="<?= h(Setting::get('chu_tro_ngay_cap')) ?>"></div>
      <div class="field"><label>Nơi cấp</label><input name="chu_tro_noi_cap" value="<?= h(Setting::get('chu_tro_noi_cap')) ?>"></div>
      <div class="field"><label>Số điện thoại Bên A</label><input name="chu_tro_dien_thoai" value="<?= h(Setting::get('chu_tro_dien_thoai') !== '' ? Setting::get('chu_tro_dien_thoai') : Setting::get('dien_thoai')) ?>"></div>
    </div><button class="btn">Lưu thông tin Bên A</button>
  </form>
</div></div>
<div class="card"><div class="card-h">Cập nhật giá thuê phòng trọ</div><div class="card-b">
  <p class="t-muted" style="margin-bottom:12px">Admin có thể tự nhập bất kỳ giá thuê nguyên đồng nào cho từng phòng (ví dụ 3000000, 3800000 hoặc 4000000), không bị ép theo bậc 1.000 đồng. Đây chỉ là giá niêm yết. Hóa đơn mới dùng giá hợp đồng có hiệu lực; hợp đồng hiện tại và hóa đơn cũ không tự thay đổi.</p>
  <div class="tw"><table class="t"><thead><tr><th>Mã phòng</th><th>Diện tích</th><th>Trạng thái</th><th>Giá thuê hiện tại (đ/tháng)</th><th>Cập nhật</th></tr></thead><tbody>
  <?php foreach ($rooms as $room): ?><tr><td><b><?= h($room['room_number']) ?></b></td><td><?= $room['area'] ? h($room['area']) . ' m²' : '—' ?></td><td><?= status_badge($room['status']) ?></td><td>
    <form class="inline" method="post" style="display:flex;gap:6px;align-items:center;min-width:280px"><?= csrf_field() ?><input type="hidden" name="action" value="room_price"><input type="hidden" name="room_id" value="<?= (int) $room['id_room'] ?>">
      <input type="number" name="room_price" min="1" max="999999999999" step="1" value="<?= h($room['price']) ?>" aria-label="Giá thuê phòng (đồng)" style="width:160px;padding:7px;border:1px solid #cbd5e1;border-radius:6px"><button class="btn sm">Lưu giá phòng</button></form>
  </td></tr><?php endforeach; if (!$rooms): ?><tr><td colspan="5" class="t-muted">Chưa có phòng.</td></tr><?php endif; ?></tbody></table></div>
</div></div>
<div class="card"><div class="card-h">Cấu hình giá dịch vụ</div><div class="tw"><table class="t"><thead><tr><th>Dịch vụ</th><th>Mã</th><th>Cách tính</th><th>Đơn giá</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($services as $s): ?>
  <tr><td><b><?= h($s['service_name']) ?></b><br><small class="t-muted"><?= h($s['description']) ?></small></td><td><?= h($s['code']) ?></td><td><?= h($s['type']) ?></td>
    <td><form class="inline" method="post" style="display:flex;gap:4px"><?= csrf_field() ?><input type="hidden" name="action" value="price"><input type="hidden" name="id" value="<?= (int) $s['id_service'] ?>">
      <input type="number" name="unit_price" min="0" step="500" value="<?= h($s['unit_price']) ?>" style="width:110px;padding:5px;border:1px solid #cbd5e1;border-radius:6px"> <span class="t-muted">/<?= h($s['unit']) ?></span><button class="btn sm">Lưu</button></form></td>
    <td><?= $s['is_active'] ? badge('Đang dùng', 'green') : badge('Đã ẩn', 'gray') ?></td>
    <td><div class="actions"><?php if (!$s['is_active']): ?><form class="inline" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="show"><input type="hidden" name="id" value="<?= (int) $s['id_service'] ?>"><button class="btn sm gray">Hiện lại</button></form><?php endif; ?>
      <form class="inline" method="post" data-confirm="Xóa dịch vụ <?= h($s['service_name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id_service'] ?>"><button class="btn sm red">Xóa</button></form></div></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
