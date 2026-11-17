# Sense Cafe POS

Phần mềm quản lý & tính tiền cho Sense coffee & acoustic (304 Hai Bà Trưng, P.6, Đà Lạt).

- `index.html`: toàn bộ ứng dụng (một file HTML, bản v7). Đang chạy dưới dạng Claude Artifact với database có sẵn.
- Chạy cục bộ: mở `index.html` bằng trình duyệt; khi không có database, dữ liệu lưu trong localStorage (`bangramon-v3`).
- Chức năng: sơ đồ bàn, gọi món, bếp/quầy, tính tiền + VietQR, khuyến mãi, kho & công thức, khách thân thiết, đêm nhạc, đặt bàn, chấm công, báo cáo.
- Lưu ý: PIN nhân viên lưu dạng văn bản thường, chưa phải bảo mật thực sự.

## Triển khai trên hosting (sensegroup.vn/pos)
Gốc repo là bản chạy trên hosting (index.html + api.php, MySQL). `config.php` do install.php tạo và không nằm trong git. Bản cũ cho Claude artifact nằm ở `claude-artifact/`.
