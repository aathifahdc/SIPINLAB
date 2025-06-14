````markdown
# 🧪 SIPINLAB – Sistem Peminjaman Alat Laboratorium Komputer Terdistribusi
SIPINLAB adalah sistem berbasis web yang dirancang untuk mengelola peminjaman alat-alat laboratorium komputer secara efisien dan konsisten antar berbagai laboratorium di lingkungan kampus. Sistem ini dibangun menggunakan PHP dan MYSQL, dan menerapkan **procedure**, **trigger**, **transaction**, **function**, serta **backup otomatis menggunakan task scheduler**, sebagai bagian dari implementasi prinsip Pemrosesan Data Terdistribusi.

![Home](assets/img/home.png)

## 📌 Detail Konsep

### ⚠️ Disclaimer
Fitur-fitur seperti **stored procedure**, **trigger**, **transaction**, dan **function** dalam sistem ini didesain sesuai dengan kebutuhan SIPINLAB. Penerapannya bisa berbeda pada sistem lain tergantung kebutuhan masing-masing.

---

### 🧠 Procedure 
Procedure digunakan untuk memastikan proses seperti peminjaman dan pengembalian alat berjalan sesuai aturan dan konsisten.

Beberapa procedure utama:
* `pinjam_alat(p_npm, p_id_alat, p_tgl_pinjam)`
* `kembalikan_alat(p_id_peminjaman, p_tgl_kembali)`
* `lapor_kerusakan(p_id_alat, p_deskripsi)`

Contoh pemanggilan:
```php
$stmt = $this->conn->prepare("CALL pinjam_alat(?, ?, ?)");
$stmt->execute([$npm, $idAlat, $tanggal]);
````

---

### 🚨 Trigger

Trigger digunakan untuk mencegah kondisi tidak valid, seperti meminjam alat yang sedang tidak tersedia atau belum dikembalikan.

Beberapa contoh trigger:

* `cek_ketersediaan` – Mencegah peminjaman jika jumlah tersedia 0
* `validasi_duplikat_peminjaman` – Mencegah peminjaman ganda alat yang sama oleh mahasiswa yang sama

Contoh:

```sql
CREATE TRIGGER before_pinjam
BEFORE INSERT ON peminjaman
FOR EACH ROW
BEGIN
    IF (SELECT jumlah_tersedia FROM alat WHERE id_alat = NEW.id_alat) <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Alat tidak tersedia';
    END IF;
END;
```

---

### 🔄 Transaction

Setiap proses penting (seperti peminjaman dan pengembalian) dibungkus dalam transaksi agar tidak ada perubahan parsial.

```php
try {
    $this->conn->beginTransaction();

    $stmt = $this->conn->prepare("CALL pinjam_alat(?, ?, ?)");
    $stmt->execute([$npm, $idAlat, $tanggal]);

    $this->conn->commit();
} catch (PDOException $e) {
    $this->conn->rollBack();
}
```

---

### 📺Function

Function digunakan untuk mengambil informasi penting tanpa mengubah data, seperti ketersediaan alat atau status peminjaman mahasiswa.

Function yang digunakan:

* `cek_ketersediaan_alat(p_id_alat)` → mengembalikan jumlah tersedia
* `cek_status_peminjam(p_npm)` → mengembalikan status aktif/tidak aktif

Contoh:

```php
$stmt = $this->conn->prepare("SELECT cek_ketersediaan_alat(?) AS tersedia");
$stmt->execute([$idAlat]);
```

---

### 💾 Backup Otomatis + Task Scheduler

Sistem melakukan backup database secara otomatis ke direktori lokal (`/backup`) dengan penjadwalan harian.

`backup/auto_backup.php`:

```php
$date = date('Y-m-d_H-i-s');
$backupFile = __DIR__ . "/peminjaman_backup_$date.sql";
$command = "\"C:\\path\\to\\mysqldump.exe\" -u user -p password sipinlab > \"$backupFile\"";
exec($command);
```

Penjadwalan dilakukan menggunakan **Task Scheduler (Windows)** atau `cron` (Linux).

---

## 🧩 Relevansi dengan Pemrosesan Data Terdistribusi

SIPINLAB mengimplementasikan prinsip-prinsip **Pemrosesan Data Terdistribusi**:

* **Konsistensi**: Proses peminjaman dan pengembalian dikontrol langsung oleh procedure dan trigger di database.
* **Reliabilitas**: Setiap transaksi dilindungi oleh blok `transaction`, dengan rollback jika terjadi kesalahan.
* **Integritas**: Logika validasi berada di lapisan database, sehingga tetap aman walaupun diakses dari berbagai lab/node.

---

## 🧍 Aktor Sistem

* **Mahasiswa**: Mengajukan dan mengembalikan peminjaman alat.
* **Admin Lab**: Menyetujui peminjaman, mencatat pengembalian dan kerusakan.
---

## 🔁 Alur Sistem Singkat

1. Mahasiswa login dan memilih alat
2. Ajukan peminjaman
3. Admin lab menyetujui atau menolak
4. Mahasiswa mengembalikan alat
5. Admin mencatat pengembalian atau kerusakan



## ✅ Penutup

SIPINLAB tidak hanya menyelesaikan masalah peminjaman alat secara praktis dan efisien, namun juga menjadi bukti nyata penerapan teknologi **Pemrosesan Data Terdistribusi** di lingkungan akademik. Proyek ini mengintegrasikan berbagai komponen database tingkat lanjut secara harmonis dengan sistem aplikasi web.

```

---
