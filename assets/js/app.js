
// assets/js/app.js - Kiểm tra dữ liệu, xác nhận thao tác, tính tiền và quản lý ảnh
(function () {

  // =========================================================
  // 0) MENU ĐIỀU HƯỚNG TRÊN MÀN HÌNH NHỎ
  // =========================================================
  var sidebar = document.getElementById('sidebar');
  var sidebarToggle = document.querySelector('[data-sidebar-toggle]');

  if (sidebar && sidebarToggle) {
    function setSidebar(open) {
      sidebar.classList.toggle('open', !!open);
      sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      sidebarToggle.setAttribute('aria-label', open ? 'Đóng menu' : 'Mở menu');
    }

    sidebarToggle.addEventListener('click', function () {
      setSidebar(!sidebar.classList.contains('open'));
    });

    sidebar.addEventListener('click', function (event) {
      if (event.target.closest('a')) setSidebar(false);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') setSidebar(false);
    });

    document.addEventListener('click', function (event) {
      if (
        sidebar.classList.contains('open') &&
        !sidebar.contains(event.target) &&
        !sidebarToggle.contains(event.target)
      ) {
        setSidebar(false);
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) setSidebar(false);
    });
  }


  // =========================================================
  // 1) XÁC NHẬN THAO TÁC CÓ DATA-CONFIRM
  // =========================================================
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });


  // =========================================================
  // 2) LỌC NHANH DỮ LIỆU TRONG BẢNG
  // =========================================================
  document.querySelectorAll('input[data-filter]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var kw = inp.value.toLowerCase();

      document.querySelectorAll(
        inp.getAttribute('data-filter') + ' tbody tr'
      ).forEach(function (tr) {
        tr.style.display =
          tr.textContent.toLowerCase().indexOf(kw) >= 0 ? '' : 'none';
      });
    });
  });


  // =========================================================
  // 3) KIỂM TRA DỮ LIỆU BIỂU MẪU
  // =========================================================
  document.querySelectorAll('form[data-validate]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var msg = '';

      f.querySelectorAll('[required]').forEach(function (el) {
        if (!msg && el.value.trim() === '') {
          msg = 'Vui lòng nhập: ' +
            (el.getAttribute('data-label') || el.name);
          el.focus();
        }
      });

      var cccd = f.querySelector('[name=identity_card]');

      if (!msg && cccd && !/^[0-9]{9,12}$/.test(cccd.value.trim())) {
        msg = 'CCCD/CMND phải gồm 9-12 chữ số';
        cccd.focus();
      }

      var phone = f.querySelector('[name=phone]');

      if (!msg && phone && !/^[0-9]{9,11}$/.test(phone.value.trim())) {
        msg = 'Số điện thoại phải gồm 9-11 chữ số';
        phone.focus();
      }

      f.querySelectorAll('input[type=number][min]').forEach(function (el) {
        if (
          !msg &&
          el.value !== '' &&
          parseFloat(el.value) < parseFloat(el.min)
        ) {
          msg = (el.getAttribute('data-label') || el.name) +
            ' không được nhỏ hơn ' + el.min;
          el.focus();
        }
      });

      if (msg) {
        alert(msg);
        e.preventDefault();
      }
    });
  });


  

  // =========================================================
  // 5) SLIDESHOW ẢNH PHÒNG
  // =========================================================
  document.querySelectorAll('[data-room-gallery]').forEach(function (gallery) {
    var main = gallery.querySelector('[data-gallery-main]');
    var thumbsWrap = gallery.querySelector('[data-gallery-thumbs]');

    var thumbs = Array.prototype.slice.call(
      (thumbsWrap || gallery).querySelectorAll('[data-gallery-index]')
    );

    var prev = gallery.querySelector('[data-gallery-prev]');
    var next = gallery.querySelector('[data-gallery-next]');
    var count = gallery.querySelector('[data-gallery-count]');

    if (!main || thumbs.length < 2) return;

    var index = 0;
    var timer = null;

    var delay = Math.max(
      2500,
      parseInt(gallery.getAttribute('data-autoplay'), 10) || 4000
    );

    function stop() {
      if (timer) window.clearInterval(timer);

      timer = null;
      main.classList.remove('gallery-playing');
    }

    function render(nextIndex) {
      index = (nextIndex + thumbs.length) % thumbs.length;

      var active = thumbs[index];
      var thumbImage = active.querySelector('img');

      var nextSrc = thumbImage
        ? (thumbImage.currentSrc || thumbImage.src)
        : active.getAttribute('data-src');

      if (nextSrc && main.src !== nextSrc) {
        main.classList.add('is-changing');

        window.setTimeout(function () {
          main.src = nextSrc;

          if (thumbImage && thumbImage.alt) {
            main.alt = thumbImage.alt;
          }

          main.classList.remove('is-changing');
        }, 120);
      }

      thumbs.forEach(function (thumb, i) {
        thumb.classList.toggle('active', i === index);

        thumb.setAttribute(
          'aria-current',
          i === index ? 'true' : 'false'
        );
      });

      if (count) {
        count.textContent = (index + 1) + ' / ' + thumbs.length;
      }

      gallery.setAttribute(
        'data-gallery-active-index',
        String(index)
      );
    }

    function start() {
      stop();

      if (document.hidden) return;

      main.style.setProperty('--gallery-duration', delay + 'ms');

      void main.offsetWidth;

      main.classList.add('gallery-playing');

      timer = window.setInterval(function () {
        main.classList.remove('gallery-playing');

        render(index + 1);

        // Ảnh cuối tự quay về ảnh đầu.
        void main.offsetWidth;

        main.classList.add('gallery-playing');
      }, delay);
    }

    if (prev) {
      prev.addEventListener('click', function () {
        render(index - 1);
        start();
      });
    }

    if (next) {
      next.addEventListener('click', function () {
        render(index + 1);
        start();
      });
    }

    thumbs.forEach(function (thumb, i) {
      thumb.addEventListener('click', function () {
        render(i);
        start();
      });
    });

    // Tạm dừng khi chuyển sang tab khác.
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        stop();
      } else {
        start();
      }
    });

    render(0);
    start();
  });


  // =========================================================
  // 6) XEM TRƯỚC ẢNH BẰNG URL.CREATEOBJECTURL()
  // Tối ưu bộ nhớ, không chuyển ảnh thành Base64 bằng FileReader.
  // =========================================================

  // Mỗi input quản lý danh sách ObjectURL riêng.
  var roomPreviewObjectUrls = new WeakMap();

  // Giải phóng các URL cũ và xóa giao diện xem trước.
  function clearRoomImagePreview(input, preview) {
    if (!input) return;

    var urls = roomPreviewObjectUrls.get(input) || [];

    urls.forEach(function (url) {
      URL.revokeObjectURL(url);
    });

    roomPreviewObjectUrls.set(input, []);

    if (preview) {
      preview.innerHTML = '';
    }
  }

  document.querySelectorAll('[data-room-upload-form]').forEach(function (form) {
    var input = form.querySelector('[data-room-files]');
    var preview = form.querySelector('[data-room-file-preview]');

    if (!input || !preview) return;

    input.addEventListener('change', function () {

      // Giải phóng ảnh xem trước cũ.
      clearRoomImagePreview(input, preview);

      var files = Array.prototype.slice.call(input.files || []);

      if (!files.length) return;

      // Giới hạn 10 ảnh mỗi lần.
      if (files.length > 10) {
        alert('Mỗi lần chỉ được chọn tối đa 10 ảnh.');
        input.value = '';
        return;
      }

      var objectUrls = [];

      files.forEach(function (file) {

        // Chỉ nhận tệp hình ảnh.
        if (!file.type || file.type.indexOf('image/') !== 0) {
          return;
        }

        var item = document.createElement('div');
        item.className = 'upload-preview-item';

        var img = document.createElement('img');
        var name = document.createElement('span');

        name.textContent =
          file.name + ' · ' +
          (file.size / (1024 * 1024)).toFixed(1) + ' MB';

        // Tạo ObjectURL thay cho FileReader.
        var objectUrl = URL.createObjectURL(file);

        objectUrls.push(objectUrl);

        img.src = objectUrl;
        img.alt = file.name;

        item.appendChild(img);
        item.appendChild(name);

        preview.appendChild(item);
      });

      // Lưu danh sách URL để giải phóng khi cần.
      roomPreviewObjectUrls.set(input, objectUrls);
    });
  });


  // =========================================================
  // 7) QUẢN LÝ ẢNH PHÒNG TỨC THỜI
  // Thêm ảnh, xóa ảnh, cập nhật ảnh đại diện.
  // =========================================================
  document.querySelectorAll('[data-room-image-manager]').forEach(function (manager) {

    var form = manager.closest('form');

    var fileInput = form
      ? form.querySelector('[data-room-files]')
      : null;

    var grid = manager.querySelector('[data-room-existing-grid]');
    var cover = manager.querySelector('[data-room-live-cover]');
    var empty = manager.querySelector('[data-room-gallery-empty]');
    var count = manager.querySelector('[data-room-image-count]');

    var message = form
      ? form.querySelector('[data-room-image-message]')
      : null;

    var endpoint = manager.getAttribute('data-image-endpoint');
    var roomId = manager.getAttribute('data-room-id');
    var csrf = manager.getAttribute('data-csrf');

    var busy = false;

    if (!form || !fileInput || !grid || !endpoint || !roomId) {
      return;
    }

    // Ngăn lưu biểu mẫu khi đang xử lý ảnh.
    form.addEventListener('submit', function (event) {
      if (busy) {
        event.preventDefault();

        setMessage(
          'Đang xử lý thư viện ảnh. Vui lòng chờ thao tác hoàn tất rồi lưu thông tin phòng.',
          true
        );
      }
    });

    // Hiển thị thông báo.
    function setMessage(text, isError) {
      if (!message) return;

      message.textContent = text || '';

      message.className =
        'room-image-message' +
        (text ? (isError ? ' error' : ' success') : '');
    }

    // Gửi yêu cầu đến máy chủ.
    function request(fields, files) {
      var body = new FormData();

      Object.keys(fields).forEach(function (key) {
        body.append(key, fields[key]);
      });

      if (files) {
        files.forEach(function (file) {
          body.append('room_images[]', file, file.name);
        });
      }

      return fetch(endpoint, {
        method: 'POST',
        body: body,
        credentials: 'same-origin'
      }).then(function (response) {

        return response.json().catch(function () {
          throw new Error('Máy chủ trả về phản hồi không hợp lệ.');
        }).then(function (data) {

          if (!response.ok || !data.ok) {
            throw new Error(
              data.message || 'Không thể cập nhật thư viện ảnh.'
            );
          }

          return data;
        });
      });
    }

    // Cập nhật giao diện thư viện ảnh.
    function renderLibrary(data) {
      grid.innerHTML = '';

      (data.images || []).forEach(function (photo) {

        var card = document.createElement('div');
        card.className = 'room-existing-photo';

        card.setAttribute(
          'data-room-image-card',
          String(photo.id)
        );

        var img = document.createElement('img');

        img.src = photo.url;
        img.alt = photo.name || 'Ảnh phòng';

        card.appendChild(img);

        if (photo.is_cover) {
          var flag = document.createElement('b');

          flag.className = 'cover-flag';
          flag.textContent = 'Ảnh đại diện';

          card.appendChild(flag);
        }

        var del = document.createElement('button');

        del.type = 'button';
        del.className = 'room-image-delete';

        del.setAttribute(
          'data-delete-room-image',
          String(photo.id)
        );

        del.textContent = 'Xóa ảnh này';

        card.appendChild(del);
        grid.appendChild(card);
      });

      if (count) {
        count.textContent = '(' + (data.count || 0) + ' ảnh)';
      }

      if (cover && data.cover_url) {
        cover.src = data.cover_url;
      }

      if (empty) {
        empty.hidden = (data.count || 0) > 0;
      }
    }

    // Xử lý khi chọn ảnh để tải lên.
    fileInput.addEventListener('change', function () {

      if (busy) {
        setMessage(
          'Đang xử lý một thao tác ảnh khác. Vui lòng thử lại sau.',
          true
        );

        fileInput.value = '';

        clearRoomImagePreview(
          fileInput,
          form.querySelector('[data-room-file-preview]')
        );

        return;
      }

      var files = Array.prototype.slice.call(
        fileInput.files || []
      );

      if (!files.length) return;

      if (files.length > 10) {
        alert('Mỗi lần chỉ được chọn tối đa 10 ảnh.');

        fileInput.value = '';

        clearRoomImagePreview(
          fileInput,
          form.querySelector('[data-room-file-preview]')
        );

        return;
      }

      // Chỉ chấp nhận JPG, PNG, WEBP và tối đa 50MB/ảnh.
      var invalid = files.find(function (file) {
        return (
          !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
          file.size <= 0 ||
          file.size > 50 * 1024 * 1024
        );
      });

      if (invalid) {
        setMessage(
          'Tệp ' + invalid.name +
          ' không đúng định dạng JPG, PNG, WEBP hoặc vượt 50MB.',
          true
        );

        fileInput.value = '';

        clearRoomImagePreview(
          fileInput,
          form.querySelector('[data-room-file-preview]')
        );

        return;
      }

      setMessage(
        'Đang tải ' + files.length + ' ảnh lên thư viện…',
        false
      );

      busy = true;
      fileInput.disabled = true;

      request({
        action: 'upload',
        id_room: roomId,
        _csrf: csrf
      }, files)

        .then(function (data) {

          // Cập nhật thư viện sau khi tải ảnh thành công.
          renderLibrary(data);

          setMessage(
            data.message || 'Đã thêm ảnh thành công.',
            false
          );

          fileInput.value = '';

          var filePreview = form.querySelector(
            '[data-room-file-preview]'
          );

          // Giải phóng ObjectURL sau khi tải lên.
          clearRoomImagePreview(fileInput, filePreview);
        })

        .catch(function (error) {

          setMessage(error.message, true);

          fileInput.value = '';

          var failedPreview = form.querySelector(
            '[data-room-file-preview]'
          );

          // Giải phóng ObjectURL khi tải lên thất bại.
          clearRoomImagePreview(fileInput, failedPreview);
        })

        .finally(function () {
          fileInput.disabled = false;
          busy = false;
        });
    });

    // Xóa ảnh khỏi thư viện.
    grid.addEventListener('click', function (event) {

      var button = event.target.closest(
        '[data-delete-room-image]'
      );

      if (!button) return;

      var imageId = button.getAttribute(
        'data-delete-room-image'
      );

      if (!window.confirm(
        'Bạn chắc chắn muốn xóa ảnh này ngay khỏi thư viện phòng?'
      )) {
        return;
      }

      if (busy) return;

      busy = true;
      button.disabled = true;

      setMessage('Đang xóa ảnh…', false);

      request({
        action: 'delete',
        id_room: roomId,
        image_id: imageId,
        _csrf: csrf
      })

        .then(function (data) {

          renderLibrary(data);

          setMessage(
            data.message || 'Đã xóa ảnh thành công.',
            false
          );
        })

        .catch(function (error) {

          setMessage(error.message, true);

          button.disabled = false;
        })

        .finally(function () {
          busy = false;
        });
    });
  });

})();
