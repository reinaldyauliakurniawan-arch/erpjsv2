# ERP Just Speak — User Manual / Manual Book

> Panduan Penggunaan Aplikasi ERP Just Speak
> User Guide for the ERP Just Speak Application

Sistem ERP berbasis web untuk pengelolaan operasional dan keuangan **Just Speak** — lembaga kursus bahasa. Aplikasi ini menangani seluruh siklus bisnis mulai dari pendaftaran siswa, penjadwalan kelas, kehadiran, pembayaran, hingga pelaporan akuntansi dan payroll tutor.

---

## Daftar Isi / Table of Contents

- [1. Peran & Akses (Roles & Access)](#1-peran--akses-roles--access)
- [2. Panduan Admin — Operasional](#2-panduan-admin--operasional)
  - [2.1 Dashboard Admin](#21-dashboard-admin)
  - [2.2 Manajemen Program Kursus](#22-manajemen-program-kursus)
  - [2.3 Manajemen Siswa](#23-manajemen-siswa)
  - [2.4 Enrollment (Pendaftaran)](#24-enrollment-pendaftaran)
  - [2.5 Kelas & Sesi (Class Session)](#25-kelas--sesi-class-session)
  - [2.6 Penjadwalan (Schedule)](#26-penjadwalan-schedule)
  - [2.7 Kehadiran (Attendance)](#27-kehadiran-attendance)
  - [2.8 Room Booking](#28-room-booking)
  - [2.9 Import & Export Data](#29-import--export-data)
  - [2.10 Tracker](#210-tracker)
  - [2.11 Settings](#211-settings)
- [3. Panduan CFO — Keuangan](#3-panduan-cfo--keuangan)
  - [3.1 Dashboard Keuangan](#31-dashboard-keuangan)
  - [3.2 Chart of Accounts (CoA)](#32-chart-of-accounts-coa)
  - [3.3 Jurnal Umum (General Journal)](#33-jurnal-umum-general-journal)
  - [3.4 Jurnal Penyesuaian (Adjusting Journal)](#34-jurnal-penyesuaian-adjusting-journal)
  - [3.5 Laporan Keuangan](#35-laporan-keuangan)
  - [3.6 Payroll Tutor](#36-payroll-tutor)
  - [3.7 Aset Tetap (Fixed Assets)](#37-aset-tetap-fixed-assets)
  - [3.8 RAB (Rencana Anggaran Biaya)](#38-rab-rencana-anggaran-biaya)
- [4. Panduan Tutor](#4-panduan-tutor)
  - [4.1 Dashboard Tutor](#41-dashboard-tutor)
  - [4.2 Input Kehadiran](#42-input-kehadiran)
  - [4.3 Ketersediaan Mengajar (Availability)](#43-ketersediaan-mengajar-availability)
  - [4.4 Practice (Tugas)](#44-practice-tugas)
  - [4.5 Tracker Siswa](#45-tracker-siswa)
- [5. Panduan Siswa](#5-panduan-siswa)
  - [5.1 Dashboard Siswa](#51-dashboard-siswa)
  - [5.2 Practice & Submit Tugas](#52-practice--submit-tugas)
  - [5.3 Tracker Pribadi](#53-tracker-pribadi)
- [6. Alur Bisnis (Business Flows)](#6-alur-bisnis-business-flows)
- [7. Technical Reference]((#7-technical-reference)

---

## 1. Peran & Akses (Roles & Access)

Aplikasi ini memiliki empat peran pengguna. Setiap peran memiliki akses ke menu yang berbeda sesuai tanggung jawabnya.

| Peran / Role | URL | Deskripsi / Description |
|---|---|---|
| **Admin** | `/admin` | Mengelola operasional harian: siswa, program, enrollment, kelas, jadwal, kehadiran, import/export, tracker, dan pengaturan sistem |
| **CFO** | `/finance` | Mengelola keuangan: chart of accounts, jurnal, laporan keuangan, payroll tutor, aset tetap, RAB, dan jurnal penyesuaian |
| **Tutor** | `/tutor` | Portal pengajar: dashboard, input kehadiran, ketersediaan mengajar, room booking, tugas/practice, dan monitoring siswa |
| **Siswa** | `/student` | Portal siswa: dashboard, akses tugas/practice, dan progress tracking |

Untuk login, akses halaman utama aplikasi di browser. Admin dapat membuat akun baru melalui menu **Settings**.

---

## 2. Panduan Admin — Operasional

### 2.1 Dashboard Admin

- **Akses**: Login sebagai Admin → buka `/admin/dashboard`
- **Isi**: Ringkasan data operasional — jumlah siswa aktif, enrollment, kelas, tutor, dan statistik kehadiran
- **Fungsi**: Sebagai halaman utama untuk memantau kondisi operasional lembaga secara keseluruhan

### 2.2 Manajemen Program Kursus

- **Akses**: Sidebar → **Programs**
- **Fungsi**: CRUD program kursus yang ditawarkan lembaga

| Field / Kolom | Keterangan / Description |
|---|---|
| Nama Program / Name | Nama kursus (contoh: English Beginner, Conversation Class) |
| Tipe / Type | Private, Semi-Private, atau Group |
| Harga / Price | Biaya per program |
| Min. Kuota / Min. Quota | Jumlah siswa minimum agar kelas otomatis aktif (untuk Group/Semi-Private) |
| Total Pertemuan / Total Meetings | Jumlah sesi dalam satu program |
| Status | Active/Inactive |

**Langkah tambah program / How to add a program:**
1. Buka menu **Programs** di sidebar
2. Klik tombol **Create New Program**
3. Isi semua field yang diperlukan
4. Klik **Save**

### 2.3 Manajemen Siswa

- **Akses**: Sidebar → **Students**
- **Fungsi**: Melihat daftar siswa, data profil, dan history enrollment

> **Catatan**: Siswa tidak ditambahkan manual oleh admin. Akun siswa otomatis dibuat saat admin melakukan **Enrollment** (lihat bagian 2.4).

### 2.4 Enrollment (Pendaftaran)

- **Akses**: Sidebar → **Enrollments**
- **Fungsi**: Mendaftarkan siswa ke program kursus

**Langkah enroll siswa baru / How to enroll a new student:**
1. Buka **Enrollments** → Klik **Create New Enrollment**
2. Pilih **Program** yang diinginkan
3. Untuk siswa baru, isi data nama, email, dan nomor telepon di bagian "New Student"
4. Untuk siswa yang sudah terdaftar, cari nama siswa di field "Existing Student"
5. Pilih **Tutor** yang akan mengajar
6. Atur **Jadwal** — pilih hari, time block, dan ruangan. Sistem akan otomatis cek konflik
7. Pilih **Metode Pembayaran**:
   - **Full Upfront** — Bayar penuh di awal
   - **Installment** — Cicilan — isi detail jumlah dan tanggal jatuh tempo tiap cicilan
8. Pilih **Payment Channel**: Cash atau Bank
9. Klik **Save**

**Status Enrollment:**

| Status / Status | Arti / Meaning |
|---|---|
| Active | Enrollment aktif, siswa bisa mengikuti kelas |
| Waitlist | Menunggu kuota terpenuhi dan/atau tutor dikonfirmasi |
| Expired | Masa berlaku enrollment habis |
| Graduate | Siswa telah menyelesaikan program |
| Cancelled | Enrollment dibatalkan |
| Refunded | Pembayaran dikembalikan |

**Fitur tambahan di halaman Enrollment:**
- **Expire** — Manual batalkan enrollment yang masih aktif (sistem auto-recognize remaining revenue)
- **Graduate** — Tandai siswa sebagai lulus
- **Assign/Remove Tutor** — Tambah atau hapus tutor dari enrollment
- **Update Tutor Status** — Konfirmasi atau ubah status tutor (Pending → Confirmed)
- **Bayar Cicilan** — Tandai cicilan sebagai lunas (untuk metode installment)

> **Info**: Saat enrollment dibuat, sistem otomatis mencatat jurnal pembayaran ke Chart of Accounts. Pembayaran masuk ke akun Cash/Bank dan dicatat sebagai Deferred Revenue (kewajiban).

### 2.5 Kelas & Sesi (Class Session)

- **Akses**: Sidebar → **Class Sessions**
- **Fungsi**: Mengelola sesi kelas, menambahkan/mengeluarkan siswa dan tutor

**Tipe Kelas:**

| Tipe / Type | Keterangan / Description |
|---|---|
| Private | 1 tutor, 1 siswa — langsung aktif saat enroll |
| Semi-Private | 1 tutor, 2-3 siswa — aktif setelah min. quota terpenuhi + tutor confirmed |
| Group | 1+ tutor, banyak siswa — aktif setelah min. quota terpenuhi + tutor confirmed |

**Fitur di halaman Class Session:**
- **Assign Enrollment** — Tambah siswa ke sesi kelas
- **Remove Enrollment** — Hapus siswa dari sesi kelas
- **Assign/Remove Tutor** — Kelola tutor yang mengajar di sesi
- **Tambah Jadwal** — Tambah jadwal baru ke sesi
- **Info** — Lihat detail sesi, siswa, dan tutor

### 2.6 Penjadwalan (Schedule)

- **Akses**: Sidebar → **Schedule**
- **Fungsi**: Melihat dan mengelola jadwal seluruh kelas

Sistem menggunakan **Hari (Day)** dan **Time Block** sebagai dasar penjadwalan. Saat menambahkan jadwal baru:
1. Pilih **Hari** (Senin–Minggu)
2. Pilih **Time Block** (slot waktu yang tersedia)
3. Pilih **Ruangan** (classroom)
4. Sistem otomatis cek apakah slot dan ruangan tersedia (tidak bentrok dengan jadwal lain)

> **Penting**: Untuk kelas Private, ruangan tidak bisa dibagi. Untuk kelas Group/Semi-Private, ruangan bisa digunakan bersama selama belum melebihi kapasitas.

### 2.7 Kehadiran (Attendance)

- **Akses**: Sidebar → **Attendance**
- **Fungsi**: Melihat dan mengelola rekap kehadiran seluruh kelas

Tutor input kehadiran melalui portal mereka (lihat [4.2](#42-input-kehadiran)). Admin bisa:
- Lihat daftar kehadiran semua kelas
- Edit atau hapus data kehadiran jika diperlukan

> **Info**: Setiap kali kehadiran dicatat untuk enrollment tertentu, sistem otomatis melakukan **revenue recognition** — mengubah sebagian Deferred Revenue menjadi Revenue berdasarkan harga per pertemuan.

### 2.8 Room Booking

- **Akses**: Menu di sidebar
- **Fungsi**: Reservasi ruangan kelas
- Admin dan Tutor bisa melakukan room booking. Sistem memvalidasi agar tidak ada double booking pada ruangan yang sama di waktu yang sama.

### 2.9 Import & Export Data

- **Akses**: Sidebar → **Imports** (Admin) atau **Finance → Imports** (CFO)

**Data yang bisa di-import:**
- Classrooms, Programs, Tutors, Students, Enrollments, Installments, Schedules, Tutor Availability, Class Sessions, RABs, Fixed Assets, Tracker Columns, Chart of Accounts, Journals

**Export yang tersedia:**
- Data Kehadiran, Jurnal, Payroll, Trial Balance, Profit & Loss, Balance Sheet, CoA, Deferred Revenue
- **Template Import** — Download template Excel untuk setiap tipe import

**Langkah import data / How to import:**
1. Buka menu **Imports**
2. Download **template** yang sesuai (format .xlsx)
3. Isi data di template sesuai format
4. Upload file di halaman import
5. Sistem akan memproses dan menampilkan hasilnya

### 2.10 Tracker

- **Akses**: Sidebar → **Tracker**
- **Fungsi**: Membuat kolom tracking kustom untuk memantau progress siswa
- Admin bisa menambah kolom tracker baru (misalnya: "Speaking Score", "Grammar Test") dan mengisi nilai per siswa

### 2.11 Settings

- **Akses**: Sidebar → **Settings**
- **Fungsi**:
  - **Manajemen User** — Buat, edit, dan hapus akun pengguna (Admin, CFO, Tutor, Student)
  - **Warna Label** — Kustomisasi warna tampilan untuk program/enrollment

---

## 3. Panduan CFO — Keuangan

### 3.1 Dashboard Keuangan

- **Akses**: Login sebagai CFO → buka `/finance`
- **Isi**: Ringkasan keuangan — pendapatan, pengeluaran, grafik revenue by program
- **Fungsi**: Memantau kondisi keuangan lembaga secara keseluruhan

### 3.2 Chart of Accounts (CoA)

- **Akses**: `/finance/accounts`
- **Fungsi**: CRUD akun-akun keuangan

Sistem memiliki CoA default (via seeder) dengan struktur:

| Kode / Code | Nama / Name | Tipe / Type |
|---|---|---|
| 1001 | Cash | Asset |
| 1002 | Bank | Asset |
| 2002 | Deferred Revenue | Liability |
| 2003 | Tutor Payable | Liability |
| 4101 | Revenue - Tuition Fees | Revenue |
| 4102 | Revenue - Admin Fee | Revenue |
| 5101 | Expense - Tutor Fee | Expense |
| 5102 | Expense - Discount/Promo | Expense |
| 5103 | Expense - Refund | Expense |

CFO bisa menambah akun baru sesuai kebutuhan lembaga.

### 3.3 Jurnal Umum (General Journal)

- **Akses**: `/finance/journals`
- **Fungsi**: Mencatat transaksi keuangan dalam format jurnal double-entry

**Langkah buat jurnal / How to create a journal:**
1. Buka **Journals** → Klik **Create New Journal**
2. Isi **Tanggal** transaksi
3. Isi **Deskripsi** transaksi
4. Isi **Reference** (kode unik, misal: INV-001)
5. Tambahkan **Item** — pilih akun, masukkan nominal debit dan/atau credit
6. Pastikan **Total Debit = Total Credit** (sistem akan menolak jika tidak balance)
7. Klik **Save**

**Fitur jurnal:**
- **Reverse** — Membuat jurnal pembalik untuk membatalkan jurnal yang salah
- **Filter** — Filter berdasarkan tipe (general, payment, payroll, revenue_recognition)

> **Proteksi**: Setiap jurnal dilindungi **idempotency** — tidak akan pernah ada dua jurnal dengan reference yang sama. Ini mencegah duplikasi akibat klik ganda atau error jaringan.

### 3.4 Jurnal Penyesuaian (Adjusting Journal)

- **Akses**: `/finance/adjusting-journals`
- **Fungsi**: Mencatat jurnal penyesuaian di akhir periode

CFO bisa:
- **Manual** — Buat jurnal penyesuaian satu per satu
- **Auto-Generate** — Sistem otomatis membuat jurnal penyesuaian bulanan berdasarkan konfigurasi

### 3.5 Laporan Keuangan

- **Akses**: `/finance/reports`
- **Fungsi**: Generate dan lihat laporan keuangan lembaga

| Laporan / Report | Deskripsi / Description |
|---|---|
| **Trial Balance** | Neraca saldo semua akun per periode yang dipilih |
| **Adjusted Trial Balance** | Neraca saldo setelah jurnal penyesuaian |
| **General Ledger** | Buku besar detail — semua transaksi per akun |
| **Profit & Loss** | Laporan laba rugi per periode |
| **Balance Sheet** | Neraca / posisi keuangan (aset, kewajiban, ekuitas) |
| **Cash Flow Statement** | Laporan arus kas (operating, investing, financing) |
| **Equity Statement** | Laporan perubahan modal/ekuitas |
| **Deferred Revenue** | Pendapatan diterima di muka yang belum diakui |
| **Fixed Assets** | Daftar aset tetap & depresiasi |

**Langkah lihat laporan / How to view a report:**
1. Buka **Reports** di menu finance
2. Pilih jenis laporan yang diinginkan
3. Pilih **periode** (tanggal mulai dan selesai)
4. Laporan akan ditampilkan
5. Export ke Excel jika diperlukan (tombol Export tersedia di setiap laporan)

**Opening Balance**:
- CFO bisa input saldo awal melalui menu **Reports → Opening Balance**
- Ini diperlukan agar laporan menampilkan saldo yang benar sejak awal operasi

### 3.6 Payroll Tutor

- **Akses**: `/finance/payroll`
- **Fungsi**: Menghitung dan membayar gaji tutor berdasarkan kehadiran

**Alur payroll / Payroll flow:**
1. **Create Payroll Run** — Pilih bulan yang akan diproses
2. Sistem menghitung seluruh **unpaid attendances** per tutor untuk bulan tersebut
3. **Approve** — Setelah di-approve, sistem otomatis:
   - Membuat jurnal pembayaran per tutor (Tutor Payable DR → Bank CR)
   - Menandai semua attendance sebagai "paid"
4. **Reverse** (jika ada kesalahan) — Membuat jurnal pembalik dan reset status paid

> **Info**: Tutor yang kehadirannya masih **pending rate** (belum punya tarif) tidak akan masuk ke hitungan payroll. CFO bisa assign rate melalui dashboard finance.

### 3.7 Aset Tetap (Fixed Assets)

- **Akses**: `/finance/assets`
- **Fungsi**: Mengelola aset tetap lembaga (furnitur, AC, proyektor, dll)

CFO bisa:
- Tambah aset tetap baru
- Lihat daftar aset dan nilai bukunya
- **Generate Depreciation** — Otomatis hitung depresiasi dan buat jurnal penyesuaian

### 3.8 RAB (Rencana Anggaran Biaya)

- **Akses**: `/finance/rab`
- **Fungsi**: Perencanaan anggaran biaya lembaga
- CFO bisa membuat rencana anggaran, dan memantau realisasinya melalui menu **RAB Realisasi**

---

## 4. Panduan Tutor

### 4.1 Dashboard Tutor

- **Akses**: Login sebagai Tutor → buka `/tutor/dashboard`
- **Isi**: Ringkasan jadwal mengajar hari ini, kelas yang diampu, dan overview kehadiran

### 4.2 Input Kehadiran

- **Akses**: Sidebar → **Attendance**
- **Fungsi**: Mencatat kehadiran siswa di setiap sesi kelas

**Langkah input kehadiran / How to record attendance:**
1. Buka menu **Attendance**
2. Cari sesi kelas berdasarkan tanggal atau nama kelas
3. Klik **Record Attendance** pada sesi yang sesuai
4. Tandai kehadiran setiap siswa (Hadir / Tidak Hadir)
5. Klik **Save**

> **Info**: Setelah attendance disimpan, sistem otomatis mengurangi sisa pertemuan (remaining meetings) dan melakukan revenue recognition.

Tutor juga bisa:
- Melihat **history** kehadiran yang sudah diinput
- **Hapus** kehadiran jika salah input

### 4.3 Ketersediaan Mengajar (Availability)

- **Akses**: Sidebar → **Availability**
- **Fungsi**: Mengatur jadwal ketersediaan mengajar

Tutor bisa mengatur slot waktu di mana mereka tersedia untuk mengajar. Saat admin melakukan enrollment, sistem akan mengecek ketersediaan tutor ini.

**Langkah / How to:**
1. Buka menu **Availability**
2. Tambah slot: pilih hari dan time block
3. Status akan otomatis berubah ke **Occupied** saat ada kelas yang menggunakan slot tersebut
4. Tutor bisa menghapus atau edit ketersediaan

### 4.4 Practice (Tugas)

- **Akses**: Sidebar → **Practice**
- **Fungsi**: Membuat dan mengelola tugas/latihan untuk siswa

Tutor bisa:
- **Buat t baru** — Isi judul, deskripsi, dan kelas target
- **Edit tugas** — Update konten tugas
- Siswa bisa mengakses dan submit tugas melalui portal mereka

### 4.5 Tracker Siswa

- **Akses**: Sidebar → **Tracker**
- **Fungsi**: Memantau progress tracking siswa yang ditugaskan ke tutor

---

## 5. Panduan Siswa

### 5.1 Dashboard Siswa

- **Akses**: Login sebagai Siswa → buka `/student/dashboard`
- **Isi**: Ringkasan enrollment aktif, jadwal kelas, sisa pertemuan, dan progress

### 5.2 Practice & Submit Tugas

- **Akses**: Sidebar → **Practice**
- **Fungsi**: Melihat dan mengerjakan tugas dari tutor

**Langkah / How to:**
1. Buka menu **Practice**
2. Lihat daftar tugas yang diberikan tutor
3. Klik **Open** untuk mulai mengerjakan
4. Setelah selesai, klik **Submit** untuk mengirim jawaban

### 5.3 Tracker Pribadi

- **Akses**: Sidebar → **Tracker**
- **Fungsi**: Melihat progress tracking pribadi — nilai dan pencapaian yang dicatat oleh admin/tutor

---

## 6. Alur Bisnis (Business Flows)

### Alur Enrollment

```
Admin pilih Program → Input data Siswa → Pilih Tutor → Atur Jadwal & Ruangan
        ↓
Private Class → Langsung aktif, buat class session baru
Group/Semi-Private → Cek min_quota & tutor confirmed
        ↓
  Kuota terpenuhi + tutor confirmed → Aktifkan enrollment + semua waitlist
  Belum terpenuhi → Masuk ke waitlist
        ↓
Pembayaran (Lunas/Cicilan) → Jurnal: Cash/Bank ↑ → Deferred Revenue ↑
        ↓
Setiap kehadiran dicatat → Revenue Recognition (Deferred Revenue → Revenue)
        ↓
Enrollment expired → Auto-recognize remaining revenue + update status
```

### Alur Payroll

```
CFO buat Payroll Run → System hitung unpaid attendances per tutor
        ↓
CFO Approve → Jurnal: Tutor Payable DR → Bank CR + tandai semua "paid"
        ↓
Jika ada kesalahan → Reverse → Jurnal pembalik + reset status paid
```

### Alur Revenue Recognition

```
Enrollment aktif → Pembayaran masuk → Deferred Revenue (kewajiban)
        ↓
Setiap pertemuan terlaksana → Auto recognize: DR Deferred Revenue → CR Revenue
        ↓
Enrollment expired → Auto recognize sisa pertemuan yang belum terpakai
```

---

## 7. Technical Reference

### Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | Laravel 13 (PHP 8.3+) |
| Frontend | Blade Templates, Tailwind CSS 4, DaisyUI 5, Alpine.js |
| Build Tool | Vite 8 |
| Database | SQLite (default) / MySQL / PostgreSQL |
| Authentication | Laravel Breeze |
| Testing | PHPUnit 12 |
| Charts | Chart.js |
| Data Tables | Tabulator Tables |

### Instalasi & Setup

**Prasyarat:**
- PHP >= 8.3
- Composer
- Node.js & npm

```bash
# Clone
git clone https://github.com/reinaldyauliakurniawan-arch/erpjustspeak.git
cd erpjustspeak

# Setup otomatis
composer run setup

# Seeder Chart of Accounts
php artisan db:seed --class=ChartOfAccountsSeeder

# Development server (4 proses paralel: serve, queue, pail, vite)
composer dev
```

**Konfigurasi Database (MySQL/PostgreSQL):**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_just_speak
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Struktur Proyek

```
app/
├── Console/Commands/         # Artisan commands (scheduler, backfill, reminders)
├── Enums/                    # ClassType, PaymentStatus, TutorStatus, AccountCode, dll
├── Exceptions/               # BalanceMismatch, Idempotency, DomainException, dll
├── Http/
│   ├── Controllers/
│   │   ├── Admin/            # Operasional & keuangan (24 controllers)
│   │   ├── Tutor/            # Portal tutor (7 controllers)
│   │   ├── Student/          # Portal siswa (3 controllers)
│   │   └── Auth/             # Autentikasi (Breeze)
│   ├── Middleware/            # RoleMiddleware
│   └── Requests/             # Form validation
├── Models/                   # 30+ Eloquent models
├── Services/                 # AccountingService, EnrollmentService, PayrollService, AttendanceService
└── View/Components/          # Blade layout components

resources/views/
├── admin/                    # Halaman admin (operasional + keuangan)
├── tutor/                    # Portal tutor
├── student/                  # Portal siswa
└── auth/                     # Login, register, dll
```

### Artisan Commands

| Command | Deskripsi |
|---------|-----------|
| `app:check-expirations` | Cek enrollment H-7 & H-3, auto-recognize revenue untuk yang sudah expired |
| `app:payment-reminders` | Reminder cicilan overdue & yang akan jatuh tempo (H-3) |
| `app:generate-monthly-adjusting-journals` | Auto-generate jurnal penyesuaian bulanan |
| `app:backfill-attendance-journals` | Backfill jurnal kehadiran yang belum tercatat |
| `app:test-all-routes` | Verifikasi semua route |

### Testing

```bash
composer test
# atau
php artisan test
```

Tersedia feature test (Auth, Attendance, Enrollment, Payroll, Journal) dan unit test (AccountingService, EnrollmentService).

### Dependensi

**PHP:** Laravel 13.7+, Laravel Breeze, Laravel Pail, Laravel Pint, PHPUnit 12
**JavaScript:** Tailwind CSS 4, DaisyUI 5, Alpine.js, Chart.js, Tabulator Tables, Axios, Vite 8

---

## 📄 License

MIT License

---

<p align="center">
Dibangun dengan ❤ menggunakan <a href="https://laravel.com">Laravel</a>
</p>
