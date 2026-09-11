# AUDIT RUMUS AKUNTANSI — `app/Services` & `app/Http/Controllers/Admin`

ERP produksi Just Speak. Audit menyeluruh rumus akuntansi: setiap angka harus
benar secara akuntansi dan konsisten antar-laporan. Semua perhitungan LIVE dari
`journal_items` (tidak ada cache / kolom saldo tersimpan).

Test yang mengunci semua perbaikan: `tests/Feature/AccountingFormulaAuditTest.php`.

---

## BUG 1 — Laporan Arus Kas dobel-hitung transaksi akrual

**Lokasi:** `FinancialReportService::cashFlow()` & `cashFlowSeries()` (sebelumnya
juga di `ReportController::cashFlow()` sebelum di-refactor).

**Masalah:** `netOperating` dihitung dengan menjumlahkan `net` semua akun
`cash_flow_category = 'operating'`, di mana `net` dibalik tandanya per tipe akun:
`isDebitNormal ? (debit − credit) : (credit − debit)`. Untuk transaksi akrual
murni yang tidak menyentuh kas, dua sisi jurnal SAMA-SAMA dihitung sebagai arus
kas.

**Bukti (skenario konkret):**
Jurnal honor tutor dicatat tapi belum dibayar tunai —
`Dr Beban Gaji Tutor (5001) 500.000 / Cr Utang Tutor (2003) 500.000`:

| Akun | tipe | `net` (rumus lama) |
|---|---|---|
| 5001 Beban | Expense (debit-normal) | `debit − credit` = **+500.000** |
| 2003 Utang | Liability (credit-normal) | `credit − debit` = **+500.000** |
| **netOperating** | | **+1.000.000** ❌ |

Kas belum bergerak sama sekali → seharusnya `netChange = 0`.

**Tambahan yang ditemukan saat audit:** di DB produksi, akun kunci
`2002` (Pendapatan Diterima Dimuka), `2003` (Utang Tutor), `1006` (Akumulasi
Penyusutan), `5108` (Beban Penyusutan) **`cash_flow_category`-nya KOSONG**.
Akibatnya rumus lama juga **melewatkan** arus kas dari pembayaran siswa
(`Dr Bank / Cr 2002` → sisi 2002 diabaikan).

**Fix:**
1. Metode tidak langsung yang benar:
   - **Operasi** = Laba Bersih + Penyusutan/amortisasi (beban non-kas,
     ditambah kembali) + Δ modal kerja (Σ `kredit − debit` akun operating yang
     Asset/Liability: Piutang, Deferred Revenue, Utang Tutor, dll).
   - **Investasi** = Σ `kredit − debit` akun investing (Beban Penyusutan &
     Akumulasi Penyusutan otomatis saling meniadakan → tinggal belanja/pelepasan
     aset tetap).
   - **Pendanaan** = Σ `kredit − debit` akun Equity (setoran modal / prive).
2. `netChange` diambil dari **SALDO KAS AKTUAL** (`cashEnding − cashOpening`,
   akun 1001+1002) — dijamin benar apa pun kondisi data.
3. `unclassified` = `netChange − (netOperating + netInvesting + netFinancing)` —
   menangkap akun non-kas yang belum diberi kategori atau jurnal yang tak balance.
4. Kategori diturunkan di PHP (`cashFlowCategory()`) dengan fallback tipe+nama
   akun, jadi laporan tetap benar walau `cash_flow_category` di DB kosong.
5. Migrasi `2026_09_09_100000_fix_cash_flow_categories.php` mengisi
   `cash_flow_category` yang kosong di produksi.
6. `cashFlowSeries()` sekarang langsung dari mutasi akun kas per sub-periode
   (Σ `debit − credit` akun 1001/1002) — Σ seluruh bucket = `netChange`.

**Validasi wajib (di tiap skenario test):**
`cashOpening + netChange === cashEnding` (persis) dan
`netOperating + netInvesting + netFinancing + unclassified === netChange`.

**Test:**
`pure_accrual_journal_does_not_move_cash_flow`,
`cash_flow_reconciles_to_actual_cash_movement_in_every_scenario`,
`operating_cash_flow_equals_net_profit_plus_depreciation_plus_working_capital`.
Diverifikasi juga terhadap DB produksi: identitas `open + change = end` = 0,
`unclassified` = 0.

---

## BUG 2 — Contra-revenue (kode 4111) MENAMBAH laba, bukan mengurangi

**Lokasi:** `FinancialReportService::profitLoss()` & `trendSeries()`,
`ReportController::balanceSheet()` (inline P&L), `EquityStatementController`,
`FinanceController` (turunan `netRevenue`).

**Masalah:** `4111` bertipe `Revenue` tapi bersaldo DEBIT (potongan/diskon).
Kolom `amount` dihitung `credit − debit` untuk semua akun Revenue → untuk 4111
nilainya NEGATIF. Rumusnya `netProfit = totalRevenue − totalContra − totalExpense`
dengan `totalContra` negatif → `− (negatif)` = **menambah** diskon ke laba.

**Bukti:** Pendapatan 10.000.000, lalu diskon 1.500.000
(`Dr Diskon Penjualan (4111) 1.500.000 / Cr Kas`):
- `totalContra` (lama) = `credit − debit` = **−1.500.000**
- `netProfit` (lama) = `10.000.000 − (−1.500.000) − 0` = **11.500.000** ❌
  (seharusnya 8.500.000)

**Fix:** `totalContra` dibalik jadi **POSITIF** (`−1 × Σ amount`), lalu
`netRevenue = totalRevenue − totalContra` dan
`netProfit = netRevenue − totalExpense`. Ditambahkan key `netRevenue` ke hasil
`profitLoss()` supaya semua konsumen memakai angka yang sama. `trendSeries()`
mengakumulasi contra sebagai potongan positif (`debit − credit`) lalu
dikurangkan. `EquityStatementController` di-refactor memakai service.

**Test:** `contra_revenue_reduces_net_profit_and_net_revenue_everywhere`
(P&L, Neraca tetap balance, tren revenue & laba ikut turun).

---

## BUG 3 — Neraca tidak balance untuk sembarang tanggal

**Lokasi:** `FinancialReportService::balanceSheet()` (& versi inline lama di
`ReportController`).

**Masalah:** Tanpa jurnal penutup, laba/rugi masih "menempel" di akun
Revenue/Expense. Neraca melipat ke ekuitas **hanya laba TAHUN BERJALAN**
(`profitLoss(startOfYear, asOf)`). Kalau ada transaksi P&L tahun-tahun
sebelumnya, `Aset ≠ Liabilitas + Ekuitas`.

**Bukti:** 2025 ada laba 5.000.000 (belum ditutup). Per 2026-06-30:
`totalAsset` mencakup kas dari laba 2025, tapi `totalEquity` cuma
`ekuitas disetor + laba 2026` → selisih 5.000.000. ❌

**Fix:** Lipat **laba akumulatif SEJAK AWAL** (`profitLoss(LEDGER_INCEPTION,
asOf).netProfit`) ke ekuitas. Ditambah baris sintetis "Laba Ditahan & Laba
Berjalan" di tabel Neraca supaya baris-baris ikut menjumlah ke total. Service
mengembalikan `isBalanced`, `retainedAndCurrent`, `netProfitCurrentYear`.

**Identitas akuntansi yang dijamin:** karena setiap jurnal balance
(`Σdebit = Σkredit`), maka
`totalAsset = totalLiability + equityDisetor + (Σrevenue − Σexpense)` untuk
tanggal berapa pun.

**Test:** `balance_sheet_is_balanced_for_any_date_including_across_years`
(5 tanggal berbeda, lintas tahun). Diverifikasi terhadap DB produksi per
2026-12-31: `A = L + E`, selisih 0.

---

## BUG 4 — Laba Perubahan Ekuitas tidak nyambung dengan Neraca

**Lokasi:** `EquityStatementController::index()`.

**Masalah:** (a) bug contra sama seperti BUG 2; (b) "Modal Awal" =
saldo mentah akun Equity s.d. akhir tahun lalu — TIDAK termasuk akumulasi laba
ditahan. Akibatnya `Modal Akhir` di laporan ini ≠ `Total Ekuitas` di Neraca.

**Fix:** Refactor memakai `FinancialReportService`. `modalAwal` =
`ekuitas disetor s.d. akhir tahun lalu + netProfitToDate(akhir tahun lalu)`.
Ditambah baris "Setoran Modal" tahun berjalan. `modalAkhir` sekarang SELALU
sama dengan `balanceSheet(31 Des tahun itu).totalEquity`.

**Test:** `equity_statement_end_balance_matches_the_balance_sheet_equity`.

---

## BUG 5 — Pengakuan pendapatan menyisakan sisa pembulatan

**Lokasi:** `RevenueRecognitionService` (`revenuePerMeeting`,
`totalRevenueRecognizedSoFar`, `splitForNextMeeting`) & `EnrollmentLedgerService`
(`targetPosition`).

**Masalah:** `revenuePerMeeting = bcdiv(total_amount, total_meetings, 2)`.
Untuk 1.000.000 / 3 = 333.333,33 → setelah 3 pertemuan hanya 999.999,99 yang
diakui. Sisa 0,01 tersangkut di Deferred Revenue **selamanya** (di atas ambang
`EPS = 0,01` tidak terpenuhi). Untuk ribuan enrollment, saldo Deferred Revenue
mengakumulasi sisa-sisa recehan yang salah.

**Bukti:** Enrollment 3 pertemuan @ kontrak 1.000.000, semua pertemuan jalan →
Deferred Revenue enrollment tersisa 0,01; total kredit akun Pendapatan =
999.999,99 (bukan 1.000.000).

**Fix:** Pertemuan **TERAKHIR** menyerap sisa: `revenue = total_amount −
(yang sudah diakui)`. `totalRevenueRecognizedSoFar()` mengembalikan TEPAT
`total_amount` begitu semua pertemuan diproses. Hasil: Σ revenue seluruh
pertemuan == `total_amount` persis; Deferred Revenue kembali nol.

**Test:** `full_revenue_recognition_equals_contract_amount_exactly`
(revenue diakui persis 1.000.000, Deferred = 0, `isInSync()` = true).

---

## BUG 6 — Total penyusutan meleset dari basis penyusutan

**Lokasi:** `DepreciationService::postMonth()` (memakai
`FixedAsset::monthly_depreciation` float mentah setiap bulan).

**Masalah:** 10.000.000 / 24 = 416.666,6667 → dibulatkan 2 desimal per bulan
→ 24 × 416.666,67 = 10.000.000,08. Akumulasi penyusutan berakhir 8 sen DI ATAS
basis; nilai buku turun di bawah nilai residu.

**Bukti:** Aset cost 10.000.000, residu 0, masa manfaat 24 bulan, sudah lewat 30
bulan → total kredit Akumulasi Penyusutan = 10.000.000,08 (bukan 10.000.000).

**Fix:** `depreciationForMonth()` — beban dibulatkan 2 desimal per bulan, BULAN
TERAKHIR menyerap sisa: `base − perMonth × (life − 1)`. Total akumulasi ==
`(cost − salvage)` persis; nilai buku akhir tepat di residu.

**Test:** `total_depreciation_equals_depreciable_base_exactly`.

---

## Yang dicek & BENAR (tidak ada bug)

- **`AccountingService::createJournal`** — validasi `Σdebit = Σkredit` sudah
  benar. Dirapikan: normalisasi debit/credit (string bcmath / float) ke 2
  desimal sebelum dibandingkan & disimpan sebagai `total_amount`.
- **`PayrollService`** — semua jurnal 2 baris (`Dr/Cr` seimbang); tidak ada
  penjumlahan lintas-akun. `approve` ↔ `reverse` sudah simetris (diaudit di
  ronde sebelumnya, `ConcurrencyAndSymmetryAuditTest`).
- **`EnrollmentLedgerService::postedPosition` / `reconcile`** — membaca per-akun
  (`debit − credit` / `credit − debit`), tidak dobel-hitung. `targetPosition`
  internally balanced: `Kas + Piutang = Deferred + Pendapatan` di semua kasus.
  Ikut fix pembulatan BUG 5.
- **`FinanceController::dashboard`** — kartu akumulatif "Ringkasan Laba–Rugi"
  menjumlahkan SELURUH akun Revenue (`credit − debit`, sudah termasuk 4111 yang
  bersaldo debit) → `netRevenue` sudah benar; `profitTotal = revenueTotal −
  expenseTotal` konsisten dengan `profitLoss()` yang sudah diperbaiki.
- **Saldo normal** — Asset/Expense debit-normal, Liability/Equity/Revenue
  credit-normal — konsisten di `profitLoss`, `balanceSheet`, `cashFlow`,
  `trialBalance`, `generalLedger`, `adjustedTrialBalance`.
  Test: `normal_balances_follow_debit_credit_convention`.

---

## Perubahan file

| File | Perubahan |
|---|---|
| `app/Services/FinancialReportService.php` | Cash flow metode tidak langsung; contra positif + `netRevenue`; neraca lipat laba akumulatif + `isBalanced`; `cashFlowSeries` dari mutasi kas; `cashFlowCategory()` fallback |
| `app/Services/RevenueRecognitionService.php` | Pertemuan terakhir menyerap sisa pembulatan |
| `app/Services/EnrollmentLedgerService.php` | `targetPosition` revenue == total_amount saat semua pertemuan selesai |
| `app/Services/DepreciationService.php` | `depreciationForMonth()` — bulan terakhir menyerap sisa |
| `app/Services/AccountingService.php` | Normalisasi 2 desimal debit/credit/total_amount |
| `app/Http/Controllers/Admin/ReportController.php` | `balanceSheet` pakai key baru service |
| `app/Http/Controllers/Admin/EquityStatementController.php` | Refactor ke service; modalAwal termasuk laba akumulatif; baris setoran modal |
| `app/Http/Controllers/Admin/FinanceController.php` | Pakai `netRevenue` dari service |
| `database/seeders/ChartOfAccountsSeeder.php` | Tambah akun 4111, 1005, 3001, 3002, 5105 (COA standar) |
| `database/migrations/2026_09_09_100000_fix_cash_flow_categories.php` | Isi `cash_flow_category` yang kosong di produksi |
| `resources/views/admin/reports/cash_flow.blade.php` | Label "Metode Tidak Langsung" |
| `resources/views/admin/reports/equity_statement.blade.php` | Baris "Setoran Modal" |
| `tests/Feature/AccountingFormulaAuditTest.php` | Test pengunci semua fix di atas |

---

## FITUR — Labor Efficiency Ratio (LER) di Dashboard Finance

Ditambahkan konsep **Labor Efficiency Ratio** dari Greg Crabtree (*Simple
Numbers, Straight Talk, Big Profits*) ke Dashboard Finance: rasio yang
mengukur "setiap Rp 1 yang dikeluarkan untuk tenaga kerja, menghasilkan
berapa Rp margin kotor". Rasio (mis. "1.9x"), BUKAN persentase — istilah asli
Crabtree "power rating".

### DLER (Direct Labor Efficiency Ratio) — SUDAH diimplementasikan

`FinancialReportService::directLaborEfficiency($from, $to)`:

```
Biaya Tutor (Direct Labor) = akun 5001 (Beban Gaji Tutor / honor lepas)
                            + akun 5006 (Beban Gaji Tutor Tetap)
Margin Kotor                = Pendapatan Bersih (netRevenue dari profitLoss())
                             − Biaya Tutor
DLER                        = Margin Kotor ÷ Biaya Tutor
```

**Keputusan desain — COGS = Biaya Tutor.** Just Speak adalah bisnis jasa
(mengajar); dicek di database aktual (`accounts` + `journal_items`) — TIDAK
ADA akun Cost of Goods Sold non-tutor yang pernah dipakai mencatat transaksi
(akun `5402 HPP Produk` ada di chart of accounts tapi 0 baris jurnal). Maka
mengikuti model Crabtree sendiri untuk bisnis jasa — biaya tenaga kerja
langsung (yang mengajar) ADALAH "cost of sales"-nya — Margin Kotor dihitung
sebagai Pendapatan Bersih dikurangi Biaya Tutor saja. Status tutor lepas vs.
tetap SAMA-SAMA masuk Direct Labor (keduanya >50% waktunya langsung mengajar,
cuma beda cara hitung honor), sesuai instruksi.

Tampilan mengikuti filter periode dashboard yang sudah ada (endpoint
`GET /finance/dashboard-data`), memakai kartu `app-card` yang sama gayanya
dengan kartu lain di halaman. Indikator warna: DLER ≥ 2 hijau ("Sehat"),
1,5–2 kuning ("Perlu Perhatian"), < 1,5 merah ("Bahaya"). `dler` bernilai
`null` (ditampilkan "N/A") kalau belum ada biaya tutor tercatat di periode
terpilih — supaya tidak menampilkan "efisiensi tak hingga" yang salah.

Copy panel ditulis untuk CFO, BUKAN developer: tidak ada rujukan ke nama
file/kode akun di UI. Panel berjudul "Efisiensi Tenaga Kerja (LER)" dengan
DLER ditandai eksplisit sebagai satu komponen ("Tenaga pengajar (DLER)"),
supaya CFO tidak salah paham DLER itu satu-satunya angka — ada ikon info
dengan tooltip singkat DLER vs MLER untuk orang awam.

**Total LER = DLER + MLER.** Ditambahkan `combineLaborEfficiency(?dler,
?mler)` di `FinancialReportService` sebagai satu tempat untuk logic
null-propagation: hasilnya `null` kalau SALAH SATU komponen belum ada —
supaya panel TIDAK diam-diam menampilkan DLER-saja sebagai "Total LER" yang
menyesatkan. `managementLaborEfficiency()` SENGAJA selalu mengembalikan
`null` untuk saat ini (lihat bagian MLER di bawah), jadi `laborEfficiency()`
— method gabungan yang dipakai `FinanceController` — saat ini selalu
menghasilkan `total_ler = null`. Begitu MLER bisa dihitung, `total_ler`
otomatis terisi tanpa perlu ubah view. Baris "Total LER" di UI menampilkan
pesan eksplisit "Belum bisa ditampilkan — menunggu data MLER di atas." kalau
`null`, bukan disembunyikan diam-diam.

**File yang diubah:** `app/Services/FinancialReportService.php` (method
`directLaborEfficiency()` + `managementLaborEfficiency()` (selalu `null`
untuk saat ini) + `combineLaborEfficiency()` + `laborEfficiency()` yang
menggabungkan ketiganya, konstanta `DIRECT_LABOR_CODES`),
`app/Http/Controllers/Admin/FinanceController.php` (tambah key `ler` —
sekarang dari `laborEfficiency()` — di `periodFigures()`, otomatis ikut ke
`dashboard()` dan endpoint AJAX `dashboardData()`),
`resources/views/admin/finance/dashboard.blade.php` (kartu baru + state
Alpine `dler`/`grossMargin`/`directLaborCost`/`mler`/`totalLer`).

**Test:** `tests/Feature/LaborEfficiencyRatioTest.php` — skenario konkret
(2 jurnal pengakuan pendapatan 6.000.000 + 4.000.000, jurnal honor tutor
lepas 2.000.000 + gaji tutor tetap 1.500.000, plus jurnal beban sewa yang
sengaja dicampur untuk memastikan TIDAK ikut terhitung sebagai Direct Labor)
→ assert Margin Kotor = 6.500.000, DLER = 6.500.000 ÷ 3.500.000 = 1,86x, dan
ambang warna "Perlu Perhatian". Test kedua mengunci kasus pembagi nol (`dler`
= `null`, bukan 0). Test ketiga mengunci panel tampil di halaman dashboard
untuk role `cfo`, termasuk `mler`/`total_ler` yang tetap `null` meski `dler`
sudah ada. Test tambahan mengunci `combineLaborEfficiency()` secara terpisah
(null kalau salah satu komponen null; menjumlahkan kalau keduanya ada) dan
`laborEfficiency()` end-to-end (Total LER tetap `null` selama MLER masih
`null`, walau DLER-nya sendiri sudah punya nilai nyata).

### MLER (Management Labor Efficiency Ratio) — BELUM diimplementasikan, perlu keputusan CFO

**Temuan dari investigasi tabel `accounts` di database aktual** (bukan
tebakan): ada akun `5002 "Beban Gaji Karyawan"`, dan di data RAB (anggaran)
yang sudah dimigrasi dari spreadsheet CFO sendiri
(`database/seeders/Traits/HasOperationsSeeders.php`), akun ini memang
dianggarkan CFO untuk aktivitas **"Gaji Staff Admin"** di bawah divisi SDM.
Jadi SECARA NAMA akun ini memang cocok untuk gaji admin/manajemen — bukan
akun sewa/listrik yang dipaksakan jadi proxy.

**Tapi:** dicek jumlah baris `journal_items` untuk akun `5002` di buku besar
aktual = **0 (nol)**. Akun ini ada di chart of accounts dan sudah dianggarkan
di RAB, tapi **belum pernah sekali pun dipakai mencatat transaksi gaji
sungguhan**. Dicek juga: `PayrollService` (modul payroll di aplikasi) HANYA
memproses honor tutor (akun 5001/5006) — tidak ada alur otomatis yang
memposting gaji staff admin/manajemen ke akun 5002 sama sekali. Kemungkinan
gaji staff admin selama ini dibayar/dicatat di luar ERP.

Kalau MLER dipaksa dihitung sekarang: pembagi (biaya manajemen) akan selalu
Rp 0 untuk setiap periode sampai hari ini → rasio "tak hingga" atau tidak
terdefinisi, yang menyesatkan (bukan berarti manajemen "sangat efisien").
Sesuai instruksi, MLER **sengaja tidak dibuat** sampai data ini jelas.

**Rekomendasi ke CFO (perlu keputusan, bukan keputusan teknis sepihak):**
1. Mulai posting gaji staff admin/manajemen ke akun `5002 Beban Gaji
   Karyawan` secara konsisten setiap bulan (manual jurnal via halaman
   Jurnal, atau modul payroll baru khusus staff non-tutor) — begitu ada
   histori data beberapa bulan, MLER bisa ditambahkan dengan pola yang
   sama persis dengan DLER.
2. Perlu dikonfirmasi ke CFO: apakah `5002` dimaksudkan HANYA untuk staff
   admin/manajemen (front office, HR, keuangan, dll — yang tidak langsung
   mengajar), atau tercampur dengan staff operasional lain? Nama akun
   "Karyawan" (generik) tidak setegas "Admin/Manajemen" — kalau perlu lebih
   presisi, bisa ditambah akun baru khusus `Beban Gaji Admin/Manajemen`
   dengan `cash_flow_category = operating`, mengikuti pola akun 5001/5006
   yang sudah eksplisit.
3. Setelah data tersedia, `Contribution Margin` (pembilang MLER, per definisi
   instruksi) perlu didefinisikan eksplisit juga — di P&L saat ini belum ada
   konsep itu sama sekali (hanya Pendapatan − Beban = Laba flat, tanpa
   pemisahan COGS/Overhead). Itu keputusan desain terpisah yang juga perlu
   dikonfirmasi CFO sebelum MLER dibangun.
