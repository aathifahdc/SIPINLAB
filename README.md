# 🧪 SIPINLAB – Sistem Peminjaman Alat Laboratorium Komputer 
SIPINLAB adalah sistem berbasis web yang dirancang untuk mengelola peminjaman alat-alat laboratorium komputer secara efisien dan konsisten antar berbagai laboratorium di lingkungan kampus. Sistem ini dibangun menggunakan PHP dan MYSQL, dan menerapkan **procedure**, **trigger**, **transaction**, **function**, serta **backup otomatis menggunakan task scheduler**, sebagai bagian dari implementasi prinsip Pemrosesan Data Terdistribusi.

![image](https://github.com/user-attachments/assets/d653bf6f-03e7-4c94-bf0e-c71c7c695991)

## 📌 Detail Konsep
---

### 🧠 Procedure

Dalam SIPINLAB, **stored procedure** digunakan untuk menjalankan proses bisnis utama: peminjaman, pengembalian, dan pelaporan kerusakan alat. Setiap procedure juga telah dibungkus dengan transaksi database untuk menjamin keutuhan data.

![image](https://github.com/user-attachments/assets/1ec86980-c882-4a8a-a46f-e032ba3b083a)


#### ✅ 1. `pinjam_alat(...)`

Digunakan ketika mahasiswa mengajukan peminjaman alat.

```sql
CALL pinjam_alat(
    p_id_peminjam INT,
    p_tanggal_pinjam DATETIME,
    p_keterangan TEXT,
    p_id_alat INT,
    p_jumlah INT
);
```

Proses yang dilakukan:

* Mengecek ketersediaan alat
* Menyimpan data peminjaman dan detail peminjaman
* Mencatat log aktivitas
* Transaksi otomatis `ROLLBACK` jika gagal

#### ✅ 2. `kembalikan_alat(...)`

Digunakan saat mahasiswa mengembalikan alat.

```sql
CALL kembalikan_alat(
    p_id_peminjaman INT,
    p_id_pengguna INT,
    p_kondisi ENUM('baik', 'rusak_ringan', 'rusak_berat')
);
```

Fungsi:

* Memvalidasi status peminjaman
* Mengubah status menjadi “dikembalikan”
* Menambah jumlah alat yang tersedia
* Menyesuaikan kondisi alat jika rusak
* Mencatat log aktivitas

#### ✅ 3. `lapor_kerusakan(...)`

Melaporkan alat yang rusak oleh mahasiswa atau admin.

```sql
CALL lapor_kerusakan(
    p_id_alat INT,
    p_id_pelapor INT,
    p_deskripsi_kerusakan TEXT
);
```

Fungsi:

* Menyimpan laporan kerusakan ke tabel `laporan_kerusakan`
* Menambahkan catatan log aktivitas
* Transaksi otomatis `ROLLBACK` jika ada error

---

### 🔄 Transaction

Semua procedure utama di atas menggunakan **`START TRANSACTION`**, **`COMMIT`**, dan **`ROLLBACK`** untuk menjamin bahwa setiap proses berjalan secara **atomik** (semuanya berhasil atau dibatalkan seluruhnya).

Contoh penerapan transaksi di dalam procedure:

```sql
START TRANSACTION;

-- Beberapa operasi penting

COMMIT;

-- Jika terjadi kesalahan
ROLLBACK;
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pesan error';
```

Dengan pendekatan ini, SIPINLAB menjamin tidak akan ada kasus:

* Alat berkurang tapi peminjaman gagal tercatat
* Peminjaman tercatat tapi alat tidak dikurangi
* Kondisi alat berubah tanpa validasi yang sesuai

---

### 📺 Function

Function digunakan untuk mengambil informasi penting dari database tanpa mengubah datanya. Dalam sistem SIPINLAB, function ini digunakan untuk **mengecek ketersediaan alat** dan **status peminjaman mahasiswa**, sebagai bagian dari validasi proses bisnis.

![image](https://github.com/user-attachments/assets/bd0feb2c-31f9-4645-88cd-10ae8443e58d)


#### ✅ 1. `cek_ketersediaan_alat(p_id_alat)` → `INT`

Mengembalikan jumlah alat yang tersedia berdasarkan `id_alat`.

**Isi function:**

```sql
CREATE FUNCTION cek_ketersediaan_alat(p_id_alat INT) RETURNS int(11)
BEGIN
    DECLARE jumlah INT;

    SELECT jumlah_tersedia INTO jumlah
    FROM alat
    WHERE id = p_id_alat;

    RETURN jumlah;
END;
```

**Contoh pemakaian:**

```php
$stmt = $this->conn->prepare("SELECT cek_ketersediaan_alat(?) AS tersedia");
$stmt->execute([$idAlat]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

#### ✅ 2. `cek_status_peminjam(npm_peminjam)` → `VARCHAR(20)`

Mengecek apakah mahasiswa dengan NPM tertentu masih memiliki pinjaman aktif atau sudah bebas.
**Isi function:**

```sql
CREATE FUNCTION cek_status_peminjam(npm_peminjam VARCHAR(20)) RETURNS varchar(20)
BEGIN
    DECLARE status_peminjam VARCHAR(20);
    DECLARE peminjam_id INT;
    DECLARE memiliki_peminjaman_aktif BOOLEAN;
    
    SELECT id INTO peminjam_id
    FROM pengguna
    WHERE npm = npm_peminjam;
    
    SELECT COUNT(*) > 0 INTO memiliki_peminjaman_aktif
    FROM peminjaman
    WHERE id_peminjam = peminjam_id
    AND status IN ('menunggu', 'dipinjam', 'terlambat');
    
    IF memiliki_peminjaman_aktif THEN
        SET status_peminjam = 'memiliki_peminjaman';
    ELSE
        SET status_peminjam = 'bebas';
    END IF;
    
    RETURN status_peminjam;
END;
```

**Contoh pemakaian:**

```php
$stmt = $this->conn->prepare("SELECT cek_status_peminjam(?) AS status");
$stmt->execute([$npm]);
$status = $stmt->fetch(PDO::FETCH_ASSOC)['status'];
```

---

### 🚨 Trigger

SIPINLAB menggunakan trigger di level database untuk **mencegah kondisi tidak valid** selama proses peminjaman atau perubahan status peminjaman.

#### ✅ 1. `before_insert_detail_peminjaman`

Trigger ini mencegah mahasiswa meminjam alat jika:

* Alat yang diminta tidak tersedia dalam jumlah cukup
* Mahasiswa masih memiliki peminjaman sebelumnya yang belum dikembalikan

```sql
CREATE TRIGGER before_insert_detail_peminjaman 
BEFORE INSERT ON detail_peminjaman 
FOR EACH ROW 
BEGIN
    -- Cek jumlah alat tersedia
    IF (SELECT jumlah_tersedia FROM alat WHERE id = NEW.id_alat) < NEW.jumlah THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Jumlah alat tidak mencukupi untuk dipinjam';
    END IF;

    -- Cek apakah peminjam masih punya pinjaman aktif
    IF (
        SELECT COUNT(*) 
        FROM peminjaman p
        JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
        WHERE p.id_peminjam = (SELECT id_peminjam FROM peminjaman WHERE id = NEW.id_peminjaman)
        AND p.status = 'dipinjam' AND dp.status_kembali = 'belum'
    ) > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Peminjam masih memiliki alat yang belum dikembalikan';
    END IF;
END;
```

#### ✅ 2. `after_update_peminjaman`

Trigger ini:

* Mengurangi jumlah alat jika status peminjaman berubah menjadi `dipinjam`
* Mencatat log jika peminjaman dibatalkan

```sql
CREATE TRIGGER after_update_peminjaman 
AFTER UPDATE ON peminjaman 
FOR EACH ROW 
BEGIN
    IF NEW.status = 'dipinjam' AND OLD.status = 'menunggu' THEN
        UPDATE alat
        SET jumlah_tersedia = jumlah_tersedia - (
            SELECT jumlah 
            FROM detail_peminjaman 
            WHERE id_peminjaman = NEW.id
        )
        WHERE id = (
            SELECT id_alat 
            FROM detail_peminjaman 
            WHERE id_peminjaman = NEW.id
        );
    END IF;

    IF NEW.status = 'dibatalkan' AND OLD.status = 'menunggu' THEN
        INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail)
        VALUES (NEW.disetujui_oleh, 'Membatalkan peminjaman', CONCAT('Peminjaman ID: ', NEW.id));
    END IF;
END;
```

Trigger ini menjamin **validasi peminjaman dilakukan di level database**, bahkan jika aplikasi frontend tidak melakukan pengecekan.

---

### 💾 Backup Otomatis + Task Scheduler

Agar data tidak hilang, SIPINLAB menggunakan metode backup otomatis:

---

#### ✅ 1. Backup Otomatis di **Windows** via `backup.bat`

Skrip `.bat` ini digunakan untuk **menyimpan cadangan database secara otomatis** ke folder `backup/` setiap hari, dijalankan menggunakan **Task Scheduler (Penjadwal Tugas)** bawaan Windows.

📁 `backup.bat`:

```bat
@echo off
setlocal enabledelayedexpansion

set "backupDir=C:\laragon\www\sipinlab\backup"
set "mysqlDir=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin"

set "year=%date:~6,4%"
set "month=%date:~3,2%"
set "day=%date:~0,2%"
set "hour=%time:~0,2%"
set "minute=%time:~3,2%"

if "!hour:~0,1!"==" " set "hour=0!hour:~1,1!"

set "timestamp=!year!-!month!-!day!_!hour!-!minute!"

"%mysqlDir%\mysqldump.exe" -u SIPINLAB -psipinlab123 sipinlab > "%backupDir%\backup_!timestamp!.sql"

endlocal
```

📌 **Penjelasan Singkat:**

* File `.bat` ini membuat file backup `.sql` otomatis dengan nama berdasarkan waktu (`backup_YYYY-MM-DD_HH-MM.sql`)
* Dijalankan via **Task Scheduler** dengan penjadwalan harian, misalnya setiap jam 1 pagi.

📋 **Cara Menjadwalkan**:

1. Buka Task Scheduler → Buat Task Baru
2. Atur Triggers → Daily → Waktu 01:00
3. Atur Action → Start a program → arahkan ke `backup.bat`

```
---
Artinya: Jalankan backup setiap hari jam 01:00 pagi.

### 🧠 Manfaat Fitur Ini

* **Mencegah kehilangan data** saat server crash
* **Memberi rasa aman** karena backup selalu terbaru
* **Bisa di-restore kapan pun**, karena hasilnya berupa file `.sql`

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

