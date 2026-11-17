# RULES RAG — DATABASE CII (PT CHUTEX INTERNATIONAL INDONESIA)

> **Berkas tunggal.** Dokumen ini memuat aturan pengambilan data, peta keputusan, kamus nilai,
> peta join, serta daftar **seluruh 167 tabel dan 1.920 kolom** beserta keterangannya.
> AI **wajib membaca dokumen ini lebih dulu** sebelum menyusun atau menjalankan query ke database CII.

| Atribut | Nilai |
|---|---|
| Versi dokumen | 1.2 — dibuat 16 November 2026, diperbarui 17 November 2026 |
| Perubahan 1.1 | Resep kehadiran berbasis roster shift (R24) menggantikan MIN/MAX tap; tabel audit dilarang dipakai (R25) |
| Perubahan 1.2 | Batas maksimal lembur (R31): 4 jam/hari dan 18 jam/minggu untuk hari kerja; lembur pada istirahat Sabtu/Minggu dan/atau hari libur resmi tidak dihitung |
| Sumber skema | dump `CII.sql` (Navicat, 12 Nov 2026 15:30) dari server `MSCHUTEX` |
| Verifikasi nilai | koneksi read-only langsung ke database CII, 16 Nov 2026 |
| Versi SQL Server | 12.0.6024.0 (SQL Server 2014) |
| Cakupan | 167 tabel · 1920 kolom · 24 tabel akses dibatasi |
| Sifat | baca-saja (read-only) |
| Bahasa | Indonesia |

**Cara memuat ke sistem RAG**

1. Jadikan berkas ini sebagai dokumen berprioritas tertinggi (selalu di-inject ke konteks, di atas hasil retrieval lain).
2. Potong per judul `###` (satu tabel = satu chunk) bila basis vektor membutuhkan chunk kecil;
   **Bagian 1–4 harus selalu ikut** di setiap pemanggilan, tidak boleh dipotong.
3. Setiap jawaban AI harus menyebut tabel.kolom yang dipakai (lihat R20) agar bisa diaudit.

## 0. RINGKASAN UNTUK AI (baca 60 detik)

- Database: **CII** (HR, absensi, payroll, produksi/monitoring, ekspatriat, aplikasi HRIS).
- Karyawan aktif ada di **`BIODATA`**; karyawan keluar ada di **`PKWT` dengan `TKK` terisi** + **`BIODATA_KELUAR`**.
- Absensi: **`att_log`** (log mentah, join lewat **`pin = BIODATA.BARCODE`**) + **`employee_shifts`** ⋈ **`shifts`** (roster). Jam masuk/keluar = tap terdekat `work_start`/`work_end` (R24), **bukan** MIN/MAX.
- Tabel audit **dilarang**: `att_log_audit`, `AUDIT`, `overtimes_payroll_audit`, `payroll_run_details_audit` (R25).
- Lembur: **`overtimes`** (operasional) vs **`overtimes_payroll`** (payroll). Jangan dicampur.
- Batas lembur: **maksimal 4 jam/hari dan 18 jam/minggu** untuk hari kerja; lembur pada waktu istirahat Sabtu/Minggu dan/atau hari libur resmi **tidak dihitung** (R31).
- Departemen: **`DEPT`**, kolom nama **`DEPARTEMENT`**, kunci **`ID_DEPT`**.
- Gaji: **`payroll_run_details`**, kolom karyawan **`employee_npk`** — tetapi **akses read-only ditolak**.
- 24 tabel **menolak `SELECT *`** (lihat Bagian 6). Penolakan bersifat per kolom: `PKWT` tetap bisa
  dibaca dengan kolom eksplisit. Itu batas akses, bukan tabel hilang.
- SQL Server 2014: **tanpa** `TRIM`, `STRING_SPLIT`, `CONCAT_WS`, `OPENJSON`.
- Riwayat percakapan sesi WAJIB dipakai untuk pertanyaan lanjutan (R26) — jangan menjawab "tidak punya konteks".
- Turnover = **keluar ÷ aktif × 100** (R27); `AKTIF = 0` → tampil `-`, bukan `0.00`.
- Line: **LINE 1–42 = Factory 2**, **LINE 43–84 = Factory 1**, non-line = "Lainnya" (R28). Top N = agregasi per unit (R29).

## 1. ATURAN WAJIB (R1–R31)

Aturan berikut bersifat mengikat. Bila satu aturan bertentangan dengan kebiasaan umum SQL,
**aturan di dokumen ini yang menang**, karena disusun dari data nyata database CII.

### R1. Baca dokumen ini SEBELUM menyusun SQL.

**Kenapa:** Dokumen ini adalah satu-satunya sumber kebenaran skema CII (167 tabel, 1.920 kolom). Urutan wajib: Bagian 1 (aturan) → Bagian 2 (peta keputusan) → Bagian 3 (kamus nilai) → Bagian 5 (kolom tabel yang dipakai).

**Yang harus dilakukan:** Jangan menulis nama tabel/kolom yang tidak muncul di Bagian 5.

### R2. Hanya SELECT. Dilarang mengubah data.

**Kenapa:** Akun yang dipakai read-only, dan laporan tidak boleh mengubah produksi.

**Yang harus dilakukan:** Dilarang: INSERT, UPDATE, DELETE, MERGE, DROP, ALTER, TRUNCATE, EXEC, SELECT INTO, BACKUP. Boleh: SELECT, WITH (CTE), turunan tabel sementara di memori.

### R3. Jangan mengarang tabel atau kolom.

**Kenapa:** Nama yang 'terdengar benar' sering tidak ada (contoh: `karyawan`, `absensi`, `gaji`, `fingerprint`).

**Yang harus dilakukan:** Kalau tidak ada di Bagian 5: katakan 'tidak ada tabel/kolom tersebut di database CII', lalu tawarkan padanan terdekat dari dokumen ini.

### R4. Bedakan 'tidak ada', 'tidak berhak', dan 'hanya sebagian boleh'.

**Kenapa:** 24 tabel menolak `SELECT *` untuk akun read-only (error 229/230). Sebagian masih bisa dibaca bila kolomnya disebut eksplisit: `PKWT` menolak `SELECT *`/`COUNT(*)` karena kolom `KTP`, tetapi `NPK, NAMA, TMK, TKK, BAGIAN` berhasil dibaca.

**Yang harus dilakukan:** Error 229/230 = batas kolom/hak akses, bukan tabel hilang. Coba ulang dengan daftar kolom eksplisit (hindari kolom sensitif). Kalau tetap ditolak, sampaikan: 'data tersedia tetapi akses dibatasi'. Jangan menyimpulkan tabel tidak ada dan jangan mengarang angka.

### R5. Selalu batasi periode dan jumlah baris.

**Kenapa:** `att_log` 1,5 juta baris dan `overtimes` 898 ribu baris; query tanpa filter membebani server.

**Yang harus dilakukan:** Sertakan filter tanggal. Untuk pratinjau pakai TOP 100 dan SEBUTKAN batasnya. Angka total tidak boleh diambil dari hasil yang dibatasi TOP.

### R6. Karyawan diidentifikasi dengan NPK, bukan nama.

**Kenapa:** Nama bisa kembar; NPK unik (`C-00001`).

**Yang harus dilakukan:** Join karyawan selalu lewat `NPK`. Pencarian nama hanya untuk membantu menemukan NPK, lalu tampilkan NPK-nya.

### R7. Karyawan AKTIF vs KELUAR ditentukan tanggal keluar, bukan kolom STATUS.

**Kenapa:** `BIODATA` hanya berisi karyawan aktif (semua STATUS='A'); `BIODATA_KELUAR.STATUS` juga 'A'.

**Yang harus dilakukan:** Aktif = ada di `BIODATA`. Keluar = `PKWT.TKK IS NOT NULL` (diperkuat `BIODATA_KELUAR`). Jangan pernah memakai `STATUS` untuk memisahkan keduanya.

### R8. Absensi join lewat BARCODE, bukan NPK.

**Kenapa:** `att_log.pin` berisi barcode mesin; 3.432 PIN berbeda pada Okt 2026 seluruhnya cocok dengan `BIODATA.BARCODE`.

**Yang harus dilakukan:** `att_log.pin = BIODATA.BARCODE`. Cast kedua sisi ke varchar dengan lebar sama.

### R9. `inoutmode` BUKAN penanda masuk/keluar.

**Kenapa:** 13 Nov 2026: mode 1 = 3.605 tap, mode 2 = 3.236, mode 0 = 97, mode 6 = 25, mode 4 = 7, mode 3 = 1 tap saja. Lima karyawan contoh (PAD PRINTING, CUTTING) punya KEDUA tap dengan mode 1. Okt 2026: 1 = 80.017, 2 = 65.377, 3 = 262.

**Yang harus dilakukan:** Jangan menentukan arah dari `inoutmode` dan jangan memakai MIN/MAX tap sebagai jam kerja. Arah ditentukan lewat roster shift (R24). `inoutmode` hanya boleh dilaporkan sebagai fakta mentah.

### R10. `JUMLAH_JAM_LEMBUR` adalah teks: pisahkan angka dan kode.

**Kenapa:** Berisi angka jam DAN kode status (MA/BR/CT/OUT/P1/SD/PE/H/HD). Menjumlahkan kolom ini langsung mencampur jam dengan huruf dan menghasilkan total salah.

**Yang harus dilakukan:** Filter: `TRY_CAST(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2)) IS NOT NULL` baru dijumlahkan. Kode huruf dilaporkan terpisah sebagai status tidak masuk.

### R11. Jangan mencampur `overtimes` dan `overtimes_payroll`.

**Kenapa:** Dua tabel berbeda: `overtimes` 898.019 baris (Feb-Des 2026, operasional, DAY campur bahasa) vs `overtimes_payroll` 531.759 baris (1 Jun-31 Okt 2026, feed payroll, DAY Inggris).

**Yang harus dilakukan:** Pertanyaan kehadiran/lembur operasional → `overtimes`. Pertanyaan komponen gaji → `overtimes_payroll`. Sebutkan tabel mana yang dipakai di jawaban.

### R12. Buang tanggal masa depan.

**Kenapa:** `overtimes` sudah terisi sampai 31 Des 2026; 47.587 baris bertanggal setelah 16 Nov 2026.

**Yang harus dilakukan:** Selalu tambahkan batas atas: `OVERTIME_DATE <= CAST(GETDATE() AS date)` (atau tanggal yang diminta), dan sebutkan bila periode yang diminta melewati hari ini.

### R13. Pakai rentang setengah terbuka untuk tanggal.

**Kenapa:** `>= mulai AND < akhir` menghindari baris hilang/duplikat pada kolom datetime.

**Yang harus dilakukan:** Untuk kolom `date` boleh `BETWEEN`, untuk `datetime` wajib `>=` dan `<`.

### R14. Kolom angka yang disimpan sebagai teks wajib dikonversi eksplisit.

**Kenapa:** `JUMLAH_JAM_LEMBUR`, `PKWT.USIA`, `IS_STAFF`, `IS_EXPAT`, `BIODATA_KELUAR.BARCODE` (int) vs `BIODATA.BARCODE` (nvarchar) berbeda tipe.

**Yang harus dilakukan:** Gunakan `TRY_CAST`/`CONVERT` dan bandingkan tipe yang sama di kedua sisi join.

### R15. Jangan memakai kolom yang tidak konsisten sebagai filter.

**Kenapa:** `DEPT.SECTION` selalu 'CHUTEX'; `BIODATA.SECTION` berisi angka ('109'); `BAG`/`BAGIAN`/`DEPT` adalah teks bebas yang tidak selalu sama dengan master.

**Yang harus dilakukan:** Nama departemen resmi = `DEPT.DEPARTEMENT` lewat `ID_DEPT`. Jangan filter SECTION.

### R16. NULL bukan 0 dan bukan 'tidak ada'.

**Kenapa:** `PKWT.TKK IS NULL` berarti masih aktif; `BAG` sering NULL pada karyawan keluar.

**Yang harus dilakukan:** Gunakan LEFT JOIN + `IS NULL` secara sadar; bedakan `COUNT(*)` dan `COUNT(kolom)`; jangan `COALESCE` nilai NULL menjadi 0 pada kolom tanggal/status.

### R17. Cek duplikasi sebelum menyimpulkan jumlah.

**Kenapa:** Join ke tabel master dapat menggandakan baris per NPK.

**Yang harus dilakukan:** Setelah query, bandingkan `COUNT(*)` dengan `COUNT(DISTINCT NPK)`. Kalau berbeda, dedup dulu dan sebutkan metode dedupnya.

### R18. Hormati batasan SQL Server 2014.

**Kenapa:** Fungsi baru tidak tersedia: `TRIM`, `STRING_SPLIT`, `CONCAT_WS`, `OPENJSON`.

**Yang harus dilakukan:** Pakai `LTRIM(RTRIM(x))`, `CHARINDEX` untuk pencarian di daftar, `CONVERT` untuk tanggal.

### R19. Data pribadi dan rahasia tidak boleh ditampilkan.

**Kenapa:** Kolom KTP, NO_KK, NOREK/bank_account, NPWP, HP, email, alamat, foto/wajah, tanda tangan, password, token.

**Yang harus dilakukan:** Jangan pilih kolom tersebut untuk laporan umum. Kalau benar-benar diperlukan, tampilkan tersamar (mis. `C-00001`, `****1234`) dan sebutkan bahwa data disamarkan.

### R20. Setiap angka wajib bisa ditelusuri.

**Kenapa:** Pengguna (HOD IT) memverifikasi laporan; angka tanpa sumber tidak dapat diterima.

**Yang harus dilakukan:** Sertakan: tabel.kolom sumber, periode, jumlah baris, dan filter yang dipakai. Kalau hasil 0 baris, sebutkan kemungkinan penyebab (filter nilai, format, periode) sebelum mengatakan 'tidak ada data'.

### R21. Kalau pertanyaan ambigu, tanya satu hal yang paling menentukan.

**Kenapa:** Ambigu yang sering: aktif vs keluar, periode, departemen, satuan (hari/jam).

**Yang harus dilakukan:** Contoh: 'Karyawan aktif atau termasuk yang sudah keluar?' Jangan menebak dua-duanya sekaligus.

### R22. Sebutkan kesegaran data.

**Kenapa:** Tabel tidak selalu terisi sampai hari ini; `payroll_periods` November 2026 masih terbuka.

**Yang harus dilakukan:** Sertakan `MAX(tanggal)` tabel terkait di catatan, dan jangan menyebut 'hari ini' tanpa memeriksanya.

### R23. Jangan memakai `SELECT *` di jawaban akhir.

**Kenapa:** Banyak tabel >30 kolom dan memuat data sensitif.

**Yang harus dilakukan:** Sebutkan kolom yang dipilih. `SELECT *` hanya untuk eksplorasi internal, bukan untuk hasil.

### R24. Jam masuk/keluar WAJIB dari roster shift (`employee_shifts` + `shifts`): ambil `scan_date` TERDEKAT ke `work_start`/`work_end`.

**Kenapa:** Roster menyimpan jam kerja resmi per orang per hari, sedangkan mesin hanya merekam tap tanpa arah yang konsisten. MIN/MAX tap salah untuk shift malam (tap 20:29 lalu 05:37 besok hari terbaca 'masuk 05:37') dan untuk yang lembur (tap ketiga dianggap jam keluar).

**Yang harus dilakukan:** Jam masuk = tap dengan |scan_date - work_start| terkecil; jam keluar = tap dengan |scan_date - work_end| terkecil. Shift malam (`work_end < work_start`) berarti `work_end` jatuh di hari+1. Jendela: masuk `work_start-6j .. +6j`, keluar `work_end-4j .. +12j`; tap keluar harus setelah tap masuk. Kecualikan shift `is_holiday='1'` (SECURITY LIBUR 00:00-23:59) dan ditandai `sumber_jadwal='LIBUR'` (bukan alpa). NPK yang TIDAK punya baris roster pada tanggal itu memakai jadwal normal 08:00-17:00 dan ditandai `sumber_jadwal='DEFAULT'`; jamnya tetap dihitung dengan cara tap terdekat, bukan MIN/MAX. Resep lengkap: Bagian 2.3.

### R25. Empat tabel audit DILARANG dibaca: `att_log_audit`, `AUDIT`, `overtimes_payroll_audit`, `payroll_run_details_audit`.

**Kenapa:** Tabel audit menyimpan salinan/perubahan dan rekap olahan lama; angka di dalamnya bisa ganda atau tidak sinkron dengan tabel induk, sehingga laporan menjadi tidak resmi.

**Yang harus dilakukan:** Jangan SELECT keempat tabel itu, termasuk sebagai pembanding atau validasi. Kehadiran → R24; lembur → `overtimes`/`overtimes_payroll`; gaji → `payroll_run_details`. Kalau diminta, jelaskan larangannya dan tawarkan sumber pengganti.

### R26. WAJIB memakai riwayat percakapan: pertanyaan lanjutan merujuk jawaban sebelumnya.

**Kenapa:** Pengguna bertanya lanjutan tanpa mengulang periode/departemen, contoh nyata: setelah tabel turnover Oktober ditampilkan, user bertanya **"kenapa line 66 aktifnya 0?"**. Pertanyaan itu tidak memuat nama tabel, periode, atau definisi apa pun. Kalau riwayat diabaikan, model menjawab seolah tidak pernah menampilkan tabel tersebut ("saya tidak punya konteks") — ini keluhan langsung pengguna (17 Nov 2026).

**Yang harus dilakukan:**
- Riwayat percakapan sesi (pesan user + jawaban AI sebelumnya, termasuk query yang dipakai) DIKIRIM ke model dan dianggap konteks resmi. Jangan menyatakan tidak punya riwayat.
- Kalau pertanyaan memakai rujukan ("itu", "nya", "tersebut", "tadi", "barusan", "kenapa X 0?") → ambil pertanyaan sebelumnya yang dirujuk, pertahankan tabel/JOIN/filter periode yang sama, lalu ubah seminimal mungkin untuk menjawab.
- Kalau yang ditanyakan adalah satu angka dari tabel sebelumnya, jalankan query verifikasi untuk objek itu (bukan NO_QUERY), lalu jelaskan angka tersebut dengan membandingkan ke baris yang sudah tampil.
- Kalau rujukannya benar-benar tidak jelas (ada dua kandidat), tanya satu hal: "maksudnya LINE 66 pada tabel turnover Oktober?" — jangan menebak dan jangan mengulang semua data.

### R27. Turnover = karyawan keluar ÷ karyawan aktif × 100 (penyebut aktif, bukan headcount awal).

**Kenapa:** Ini formula yang dipakai laporan HR yang sudah diterima pengguna. Verifikasi dari laporan Oktober 2026: QC 21/119 = 17,65%; PACKING 14/55 = 25,45%; IRONING 12/37 = 32,43%; LINE 41 5/16 = 31,25%. Semua cocok dengan `keluar / aktif × 100`. Formula lain (mis. rata-rata awal+akhir) akan menghasilkan angka berbeda dan dianggap salah.

**Yang harus dilakukan:**
- Kolom hasil: `MASUK` (jumlah karyawan dengan `PKWT.TMK` di dalam periode), `KELUAR` (`PKWT.TKK` di dalam periode), `AKTIF` (karyawan yang ada di `BIODATA` pada unit itu), `TURNOVER_PCT`.
- `TURNOVER_PCT = 100.0 * KELUAR / AKTIF`, dibulatkan 2 desimal (`DECIMAL(10,2)`), pembagian memakai `100.0 *` supaya tidak integer division.
- **Kalau `AKTIF = 0`: JANGAN tulis `0.00` atau `.00`.** Penyebut nol berarti turnover tidak terdefinisi, bukan nol persen. Pakai `NULL`/`-` dan jelaskan di kalimat: "LINE 66 tidak punya karyawan aktif pada saat data diambil, jadi turnover tidak bisa dihitung (penyebut 0)".
- `AKTIF` adalah snapshot **hari ini** (`BIODATA` hanya berisi karyawan aktif), bukan posisi akhir periode lampau. Sebutkan asumsi ini sekali di catatan kaki jawaban.
- Jangan menghitung `MASUK` dari `BIODATA.TMK` (kolom itu tidak menjamin periode masuk) dan jangan memakai `PKWT_OUT` (arsip lama).

### R28. Pemetaan LINE → Factory: LINE 1–42 = **Factory 2**, LINE 43–84 = **Factory 1**, sisanya "Lainnya".

**Kenapa:** Pabrik memakai dua gedung: nomor line rendah di Factory 2, nomor line tinggi di Factory 1. Pengguna menetapkan batas ini secara eksplisit (17 Nov 2026). Tanpa aturan ini, laporan per factory tidak bisa dibuat dan AI cenderung menebak atau mengabaikan factory sama sekali.

**Yang harus dilakukan:**
- Nomor line diambil dari teks departemen `DEPT.DEPARTEMENT` yang memuat pola `LINE <nomor>` (contoh nyata: `SEWING LINE 66`, `LEADER SEWING LINE 18`). Ekstraksi aman di SQL Server 2014: `TRY_CAST(SUBSTRING(DEPARTEMENT, PATINDEX('%LINE %', DEPARTEMENT) + 5, 3) AS int)`.
- `NO_LINE BETWEEN 1 AND 42` → `'Factory 2'`; `NO_LINE BETWEEN 43 AND 84` → `'Factory 1'`.
- Departemen yang BUKAN line (QC, PACKING, IRONING, CUTTING, SNAP, ACCOUNTING, dst.) dan line di luar 1–84 → `'Lainnya'` (non-line). Ini bukan kesalahan data: departemen penunjang memang tidak punya nomor line.
- Nomor line yang sama bisa muncul di beberapa departemen (mis. `SEWING LINE 66` dan `LEADER SEWING LINE 66`) — **gabungkan menjadi satu baris `LINE 66`**, jangan tampilkan per sebutan departemen.
- Factory adalah atribut turunan. Jangan menambah tabel/kolom baru, dan jangan menyimpannya ke database (koneksi read-only).

### R29. "Top N per departemen" = agregasi per unit, bukan deretan baris LINE mentah.

**Kenapa:** Pada jawaban 17 Nov 2026, permintaan "top 10 departement" dijawab dengan baris `LINE 38`, `LINE 66`, `LINE 65`, `LINE 84` — itu unit line, bukan departemen, dan departemen non-line (QC, PACKING, IRONING, CUTTING, SNAP) tercampur tanpa penjelasan. Pengguna langsung mempertanyakan dasar "top 10"-nya.

**Yang harus dilakukan:**
- Tentukan level agregasi dari pertanyaan: **per departemen** → `DEPT.DEPARTEMENT` (dengan line digabung per nomor sesuai R28); **per factory** → hanya 3 baris (`Factory 1`, `Factory 2`, `Lainnya`); **per line** → hanya kalau user menyebut line.
- `TOP n` harus dihitung dari hasil agregasi (`TOP 10` → 10 baris kelompok), lalu `ORDER BY` kolom angka yang diminta. Kalau user tidak menyebut kolom urut, urutkan `KELUAR DESC` lalu `AKTIF DESC`.
- Sertakan kolom `FACTORY` di samping nama unit supaya jelas baris itu milik factory mana.
- Jangan pernah memakai `TOP 10` pada tabel mentah karyawan lalu mengelompokkan sesudahnya — hasilnya bukan top 10 kelompok.
- Sebutkan di jawaban: level agregasi yang dipakai, kolom pengurut, dan periode.

### R30. Angka 0 / kosong wajib diverifikasi dan dijelaskan, bukan dicetak mentah.

**Kenapa:** Baris `LINE 66 — masuk 22, keluar 6, aktif 0, turnover .00` adalah angka yang benar secara aritmetika tetapi menyesatkan tanpa penjelasan; pengguna menanyakannya dan AI tidak bisa menjawab karena tidak memeriksa ulang.

**Yang harus dilakukan:**
- Kalau satu unit menunjukkan `AKTIF = 0` padahal ada `MASUK`/`KELUAR` pada periode itu, jalankan satu query pemeriksa (mis. hitung karyawan `BIODATA` di semua departemen yang mengandung `LINE 66`, plus hitung yang keluar) sebelum menyimpulkan.
- Kemungkinan penyebab yang harus disebut, bukan ditebak: line sudah ditutup/digabung, seluruh karyawannya keluar pada periode itu, karyawan dipindah ke departemen lain (`employee_mutations`), atau departemen tidak lagi ada di `BIODATA`.
- Jangan menulis "data tidak ditemukan" kalau yang terjadi hanya penyebut nol atau salah level agregasi.

### R31. Batas maksimal lembur: **4 jam/hari** dan **18 jam/minggu** untuk hari kerja.

**Kenapa:** Ini ketentuan perusahaan yang ditetapkan pengguna (17 Nov 2026). Tanpa aturan ini, AI akan menjumlahkan seluruh jam lembur — termasuk lembur hari istirahat — lalu menyimpulkan pelanggaran yang tidak ada, atau sebaliknya melaporkan "aman" padahal batas harian terlampaui.

**Bunyi ketentuan:** Paling lama **4 jam dalam sehari** dan **18 jam dalam seminggu**, **tidak termasuk** kerja lembur pada waktu istirahat hari Sabtu dan Minggu dan/atau hari libur resmi.

**Yang harus dilakukan:**
- **Batas harian** = total jam lembur satu NPK pada satu tanggal **hari kerja** ≤ **4 jam**.
- **Batas mingguan** = total jam lembur satu NPK dalam satu minggu (Senin–Minggu, hanya hari kerja) ≤ **18 jam**.
- **Dikecualikan dari hitungan batas** (tetap dilaporkan terpisah sebagai "lembur hari istirahat"): tanggal yang jatuh **Sabtu/Minggu**, tanggal yang ada di `holidays.holiday_date`, dan tanggal dengan shift `shifts.is_holiday = '1'` (mis. `SECURITY LIBUR`, lihat R24).
- **Hari kerja ditentukan dari tanggal, bukan kolom `DAY`** — `overtimes.DAY` campur Inggris/Indonesia (R15). Cara deterministik di SQL Server 2014: `DATEDIFF(day, '19000101', OVERTIME_DATE) % 7` → `0`=Senin … `5`=Sabtu, `6`=Minggu (tidak bergantung `DATEFIRST`/bahasa sesi). Awal minggu = hari Senin: `DATEADD(day, -(DATEDIFF(day,'19000101', OVERTIME_DATE) % 7), OVERTIME_DATE)`.
- **Hanya angka yang dijumlahkan:** `TRY_CAST(LTRIM(RTRIM(JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2)) IS NOT NULL` (R10); kode huruf (MA/BR/CT/OUT/P1/SD/PE/H/HD) bukan jam lembur.
- **Sumber:** `overtimes` untuk lembur operasional; sebutkan tabel yang dipakai di jawaban (R11). Jangan memakai `overtimes_payroll` untuk kepatuhan harian/mingguan, dan jangan menyentuh tabel audit (R25).
- Saat melaporkan pelanggaran, tampilkan **NPK, tanggal/minggu, jam hari itu, jam minggu itu, dan selisih terhadap batas**. Jangan menyimpulkan sanksi, potongan, atau tindakan disiplin — itu di luar lingkup RAG.
- Kalau pengguna hanya bertanya "berapa lembur X", **jangan** langsung menilai patuh/tidak patuh; tampilkan jamnya, lalu sebutkan batas R31 sebagai konteks bila relevan.

## 2. PETA KEPUTUSAN: PERTANYAAN → TABEL → KOLOM → JOIN

Gunakan tabel ini untuk memilih tabel. Kolom join di sini sudah diverifikasi.
Kalau pertanyaan tidak ada di daftar, cari tabelnya di Bagian 5 berdasarkan domain.

| Pertanyaan / kebutuhan | Tabel utama | Kolom filter | Join | Catatan |
|---|---|---|---|---|
| Daftar karyawan keluar (resign/PHK) per periode | `PKWT` (utama) + `BIODATA_KELUAR` + `DEPT` | `PKWT.TKK IS NOT NULL` | `PKWT.NPK = BIODATA_KELUAR.NPK`; `BIODATA_KELUAR.ID_DEPT = DEPT.ID_DEPT` | Jangan pakai `BIODATA.STATUS`. Jangan pakai `BIODATA_KELUAR.STATUS` (isinya 'A'). Lihat Resep 1. |
| Daftar karyawan aktif / headcount | `BIODATA` + `DEPT` | `BIODATA.STATUS = 'A'` | `BIODATA.ID_DEPT = DEPT.ID_DEPT` | Karyawan aktif TIDAK ada di `BIODATA_KELUAR` (overlap 0 baris). |
| Karyawan masuk (new hire) per periode | `PKWT` + `BIODATA` (atau `BIODATA_KELUAR`) + `DEPT` | `PKWT.TMK` dalam rentang | `PKWT.NPK = BIODATA.NPK` / `= BIODATA_KELUAR.NPK` | Karyawan baru yang sudah keluar tetap muncul jika pakai `PKWT` saja. |
| Absensi harian: jam masuk & jam keluar per karyawan | `BIODATA` (basis) + `employee_shifts` + `shifts` + `att_log` | `employee_shifts.shift_date = @tgl`; `att_log.scan_date` dalam jendela shift | `employee_shifts.npk = BIODATA.NPK`; `employee_shifts.shift_id = shifts.id`; `att_log.pin = BIODATA.BARCODE` | Jam masuk/keluar = tap terdekat `work_start`/`work_end` (R24). Tanpa roster → jadwal normal 08:00-17:00 bertanda `DEFAULT`; shift libur bertanda `LIBUR`. Lihat Resep 3. |
| Rekap absensi olahan lama (`AUDIT`) | — | — | — | DILARANG (R25). Jangan dipakai sebagai sumber maupun pembanding; hitung ulang dengan Resep 3. |
| Lembur per karyawan/periode | `overtimes` (operasional) atau `overtimes_payroll` (payroll) | rentang `OVERTIME_DATE` + `TRY_CAST(JUMLAH_JAM_LEMBUR AS DECIMAL) IS NOT NULL` | `overtimes.NPK = BIODATA.NPK` | Wajib buang kode huruf dan baris bertanggal masa depan. |
| Batas/kepatuhan jam lembur (4 jam/hari, 18 jam/minggu) | `overtimes` + `holidays` | rentang `OVERTIME_DATE`; hanya hari kerja (bukan Sabtu/Minggu, bukan `holidays.holiday_date`) | `overtimes.NPK = BIODATA.NPK`; anti-join `holidays.holiday_date = OVERTIME_DATE` | Butuh dua level: harian (`> 4`) dan mingguan Senin–Minggu (`> 18`). Lembur hari istirahat/libur resmi TIDAK dihitung (R31). |
| Cuti/izin | `leave_requests` + `leave_types` + `BIODATA` | `status='approved'`, rentang tanggal | `leave_requests.leave_type_id = leave_types.id`; `leave_requests.npk = BIODATA.NPK` | Status huruf kecil. |
| Gaji/slip per karyawan | `payroll_run_details` + `payroll_runs` + `payroll_periods` | `payroll_run_details.employee_npk`; `payroll_periods.is_closed = 1` | `run_id = payroll_runs.id`; `payroll_runs.period_id = payroll_periods.id` | Akses akun read-only ditolak → sampaikan tidak bisa diakses, jangan mengarang angka. |
| Departemen & jumlah karyawan per dept | `DEPT` + `BIODATA` | `BIODATA.STATUS='A'` | `BIODATA.ID_DEPT = DEPT.ID_DEPT` | Nama dept di `DEPT.DEPARTEMENT`. `DEPT.SECTION` selalu 'CHUTEX'. |
| Turnover karyawan per departemen/line/factory per periode | `PKWT` + `BIODATA` + `BIODATA_KELUAR` + `DEPT` | `PKWT.TMK` (masuk) / `PKWT.TKK` (keluar) dalam periode; aktif `BIODATA.STATUS='A'` | `PKWT.NPK = BIODATA.NPK` atau `= BIODATA_KELUAR.NPK`; `ID_DEPT = DEPT.ID_DEPT` | Turnover % = keluar/aktif×100 (R27). Factory dari nomor line di `DEPARTEMENT` (R28). Top N = agregasi per unit (R29). Resep 6. |
| Turnover per factory (LINE 1–42 vs 43–84) | sama seperti turnover | sama | sama | Hanya 3 baris: Factory 1, Factory 2, Lainnya (R28). Jangan tampilkan baris LINE mentah kalau yang diminta per departemen (R29). |
| Kenapa nilai 0 / kosong pada baris yang sudah ditampilkan | tabel penyebab | verifikasi ulang objek yang ditanya | sesuai pertanyaan sebelumnya | Pertanyaan lanjutan → pakai riwayat + query terakhir sebagai dasar (R26), lalu jelaskan penyebabnya (R30). |
| Mutasi antar departemen | `employee_mutations` | rentang `date` | `from_dept`/`to_dept` → `DEPT.ID_DEPT` | `from_dept` NULL = karyawan baru; `to_dept` NULL = keluar. |
| Order produksi / status order | `mon_orders` + `mon_work_orders` | filter tanggal/status order | `mon_orders.id = mon_work_orders.order_id` (verifikasi nama kolom di Bagian 5) | Banyak kolom; pilih eksplisit, jangan SELECT *. |
| PO pembelian / supplier | `mon_purchase_orders` + `mon_ms_suppliers` | rentang tanggal PO | `po_id` header-detail; supplier via kode/nama | Kata kunci item bisa ada di dua kolom (uraian & spesifikasi). |
| Efisiensi line/QC | `line_efficiencies`, `qc_efficiencies`, `pad_efficiencies`, `cutting_efficiencies`, `heat_efficiencies` | rentang tanggal + line | `employee_*_assignments` untuk penempatan karyawan | Lima tabel berbeda; pilih sesuai jenis efisiensi. |
| Pelanggaran karyawan | `employee_violations`, `sewing_violations`, `qc_violations` | `period_id`/`tanggal` | `employee_violations.NPK = BIODATA.NPK` | `employee_violations` per periode; `sewing_violations` per tanggal. |
| Ekspatriat & masa berlaku izin | `expat_master`, `expat_documents`, `epos`, `chu_family` | `visa_expiry`/`kitas_expiry`/`rptka_expiry` | `expat_documents` via ID ekspatriat | Banyak yang dibatasi permission. |

### 2.1 RESEP 1 — Daftar karyawan keluar (paling sering ditanyakan)

**Jawaban singkat:** ambil dari **`PKWT`** dengan syarat **`TKK IS NOT NULL`** (tanggal keluar terisi),
lalu join **`BIODATA_KELUAR`** lewat **`NPK`** untuk nama/bagian, dan **`DEPT`** lewat **`ID_DEPT`** untuk nama departemen.

Alasannya (terverifikasi 16 Nov 2026):

- `PKWT` berisi 5.798 baris: **3.639 aktif** (`TKK IS NULL`) + **2.159 keluar** (`TKK` terisi).
- `BIODATA` hanya berisi 3.639 karyawan aktif; **tidak ada** karyawan keluar di dalamnya.
- `BIODATA_KELUAR` berisi 2.204 baris arsip keluar. **`STATUS`-nya tetap `'A'`**, jadi `STATUS` tidak bisa dipakai.
- Kecocokan: **2.162 baris keluar berhasil dipadankan** ke `BIODATA_KELUAR`; **42 baris arsip** tidak punya baris `PKWT`.
  Karena itu **`PKWT` = sumber utama**, `BIODATA_KELUAR` = pelengkap identitas.

> Angka di atas adalah snapshot 16 November 2026. Proses HR berjalan setiap hari, jadi jumlahnya
> bergerak. Jangan memakai angka di dokumen ini sebagai nilai terkini — selalu hitung ulang dengan SQL.
> Untuk `PKWT`, sebut kolom eksplisit: `COUNT(*)` dan `SELECT *` ditolak karena kolom `KTP`.

```sql
-- Daftar karyawan keluar pada rentang tanggal (R14: kolom date, aman pakai BETWEEN)
SELECT
    p.NPK,
    COALESCE(k.NAMA_KARYAWAN, p.NAMA) AS NAMA_KARYAWAN,
    k.ID_DEPT,
    d.DEPARTEMENT                      AS DEPARTEMEN,
    p.BAGIAN,
    p.TMK                              AS TANGGAL_MASUK,
    p.TKK                              AS TANGGAL_KELUAR
FROM dbo.PKWT p
LEFT JOIN dbo.BIODATA_KELUAR k ON k.NPK = p.NPK
LEFT JOIN dbo.DEPT d           ON d.ID_DEPT = k.ID_DEPT
WHERE p.TKK IS NOT NULL
  AND p.TKK >= '2026-01-01'      -- awal periode
  AND p.TKK <  '2026-12-01'      -- awal bulan berikutnya (R13)
ORDER BY p.TKK DESC, p.NPK;
```

**Varian yang sering diminta:**

```sql
-- 1) Hitungan per bulan keluar
SELECT CONVERT(char(7), p.TKK, 120) AS BULAN, COUNT(DISTINCT p.NPK) AS JUMLAH_KELUAR
FROM dbo.PKWT p
WHERE p.TKK IS NOT NULL AND p.TKK >= '2026-01-01' AND p.TKK < '2027-01-01'
GROUP BY CONVERT(char(7), p.TKK, 120)
ORDER BY BULAN;

-- 2) Hitungan per departemen terakhir
SELECT d.DEPARTEMENT, COUNT(DISTINCT p.NPK) AS JUMLAH_KELUAR
FROM dbo.PKWT p
JOIN dbo.BIODATA_KELUAR k ON k.NPK = p.NPK
LEFT JOIN dbo.DEPT d      ON d.ID_DEPT = k.ID_DEPT
WHERE p.TKK IS NOT NULL AND p.TKK >= '2026-01-01' AND p.TKK < '2026-12-01'
GROUP BY d.DEPARTEMENT
ORDER BY JUMLAH_KELUAR DESC;

-- 3) Cek total aktif vs keluar (angka kendali)
SELECT
  SUM(CASE WHEN TKK IS NULL     THEN 1 ELSE 0 END) AS AKTIF,
  SUM(CASE WHEN TKK IS NOT NULL THEN 1 ELSE 0 END) AS KELUAR,
  COUNT(*)                                          AS TOTAL
FROM dbo.PKWT;

-- 4) Karyawan keluar yang tidak punya baris PKWT (data arsip saja)
SELECT k.NPK, k.NAMA_KARYAWAN, k.ID_DEPT
FROM dbo.BIODATA_KELUAR k
WHERE NOT EXISTS (SELECT 1 FROM dbo.PKWT p WHERE p.NPK = k.NPK);
```

**Jangan lakukan ini:**

Blok berikut **sengaja salah** — contoh yang tidak boleh ditiru dan **tidak untuk dijalankan**.

```sql
-- SALAH: STATUS tidak menandai keluar (BIODATA_KELUAR.STATUS = 'A')
SELECT * FROM dbo.BIODATA_KELUAR WHERE STATUS = 'K';

-- SALAH: BIODATA tidak memuat karyawan keluar, hasilnya selalu kosong
SELECT * FROM dbo.BIODATA WHERE STATUS <> 'A';

-- SALAH: PKWT_OUT hanya arsip lama 63 baris, bukan sumber daftar keluar
SELECT * FROM dbo.PKWT_OUT WHERE TKK IS NOT NULL;
```

### 2.2 RESEP 2 — Karyawan aktif & headcount

```sql
SELECT d.DEPARTEMENT, COUNT(DISTINCT b.NPK) AS JUMLAH_KARYAWAN
FROM dbo.BIODATA b
LEFT JOIN dbo.DEPT d ON d.ID_DEPT = b.ID_DEPT
WHERE b.STATUS = 'A'          -- seluruh BIODATA memang 'A'
GROUP BY d.DEPARTEMENT
ORDER BY JUMLAH_KARYAWAN DESC;
```

Untuk memisahkan staff/non-staff: `BIODATA.IS_STAFF` bertipe teks, bandingkan `= '1'`, bukan `= 1`.

### 2.3 RESEP 3 — Kehadiran harian: jam masuk/keluar dari roster shift (R24)

Aturan inti: **jangan** memakai `MIN`/`MAX` tap. Ambil tap yang **paling dekat** ke `work_start`
(jam masuk) dan ke `work_end` (jam keluar). Sumber jam kerja berurutan:

1. **Roster** — `employee_shifts` ⋈ `shifts` untuk `shift_date` tersebut (`sumber_jadwal = 'ROSTER'`).
2. **Jadwal normal 08:00–17:00** — dipakai bila NPK **tidak punya baris** di `employee_shifts` pada tanggal itu
   (`sumber_jadwal = 'DEFAULT'`).
3. **LIBUR** — baris roster ada tetapi shift-nya `is_holiday = '1'`; jam kerja tidak dihitung (`sumber_jadwal = 'LIBUR'`).

> **Jalankan sebagai satu blok utuh.** `DECLARE` + semua CTE (`roster`, `jadwal`, `waktu`) + `SELECT` final harus dieksekusi bersama. Memotong setelah CTE `jadwal` menghasilkan error `Incorrect syntax near ','` / `near ')'` (102) karena CTE belum diikuti statement.

> Bila tool menolak `DECLARE` (sebagian query builder), hapus baris `DECLARE` lalu ganti keempat `@tgl` sisanya dengan tanggal literal, mis. `'2026-11-13'`, lalu hapus baris `DECLARE`.

```sql
DECLARE @tgl date = '2026-11-13';   -- ganti tanggal laporan

WITH roster AS (
  SELECT es.npk, s.name AS shift_name, s.work_start, s.work_end,
         CASE WHEN ISNULL(s.is_holiday, '0') = '1' THEN 1 ELSE 0 END AS libur
  FROM dbo.employee_shifts es
  JOIN dbo.shifts s ON s.id = es.shift_id
  WHERE es.shift_date = @tgl
),
jadwal AS (   -- semua karyawan aktif; tanpa roster → normal 08:00-17:00
  SELECT b.NPK, b.NAMA_KARYAWAN, b.BARCODE, d.DEPARTEMENT,
         CASE WHEN r.npk IS NULL THEN 'NORMAL 08:00-17:00 (default)' ELSE r.shift_name END AS shift_name,
         CASE WHEN r.npk IS NULL THEN CAST('08:00:00' AS time)
              WHEN r.libur = 1 THEN NULL ELSE r.work_start END AS work_start,
         CASE WHEN r.npk IS NULL THEN CAST('17:00:00' AS time)
              WHEN r.libur = 1 THEN NULL ELSE r.work_end END AS work_end,
         CASE WHEN r.npk IS NULL THEN 'DEFAULT'
              WHEN r.libur = 1 THEN 'LIBUR'
              ELSE 'ROSTER' END AS sumber_jadwal
  FROM dbo.BIODATA b
  LEFT JOIN dbo.DEPT d ON d.ID_DEPT = b.ID_DEPT
  LEFT JOIN roster  r ON r.npk = b.NPK
  WHERE b.STATUS = 'A'
),
waktu AS (   -- tanggal + jam mulai/selesai; shift malam berakhir di hari+1
  SELECT j.*,
         CASE WHEN j.work_start IS NULL THEN NULL
              ELSE DATEADD(minute, DATEDIFF(minute, CAST('00:00:00' AS time), j.work_start),
                           CAST(@tgl AS datetime)) END AS start_dt,
         CASE WHEN j.work_end IS NULL THEN NULL
              WHEN j.work_end > j.work_start
                THEN DATEADD(minute, DATEDIFF(minute, CAST('00:00:00' AS time), j.work_end),
                             CAST(@tgl AS datetime))
              ELSE DATEADD(day, 1, DATEADD(minute, DATEDIFF(minute, CAST('00:00:00' AS time), j.work_end),
                             CAST(@tgl AS datetime))) END AS end_dt
  FROM jadwal j
)
SELECT w.NPK, w.NAMA_KARYAWAN, w.DEPARTEMENT, w.shift_name, w.sumber_jadwal,
       w.work_start, w.work_end,
       si.scan_date AS jam_masuk, so.scan_date AS jam_keluar,
       CASE WHEN w.sumber_jadwal = 'LIBUR' THEN 'LIBUR'
            WHEN si.scan_date IS NULL AND so.scan_date IS NULL THEN 'TIDAK HADIR'
            WHEN si.scan_date IS NULL THEN 'TANPA TAP MASUK'
            WHEN so.scan_date IS NULL THEN 'TANPA TAP KELUAR'
            ELSE 'OK' END AS status_tap,
       DATEDIFF(minute, w.start_dt, si.scan_date) AS telat_menit,
       DATEDIFF(minute, so.scan_date, w.end_dt)   AS pulang_lebih_menit,
       (SELECT COUNT(*) FROM dbo.att_log a
        WHERE CONVERT(varchar(50), a.pin) = CONVERT(varchar(50), w.BARCODE)
          AND a.scan_date >= DATEADD(hour, -6, w.start_dt)
          AND a.scan_date <= DATEADD(hour, 12, w.end_dt)) AS jumlah_tap
FROM waktu w
OUTER APPLY (   -- tap terdekat ke jam mulai
  SELECT TOP 1 a.scan_date
  FROM dbo.att_log a
  WHERE CONVERT(varchar(50), a.pin) = CONVERT(varchar(50), w.BARCODE)
    AND a.scan_date >= DATEADD(hour, -6, w.start_dt)
    AND a.scan_date <= DATEADD(hour,  6, w.start_dt)
  ORDER BY ABS(DATEDIFF(minute, a.scan_date, w.start_dt)), a.scan_date
) AS si
OUTER APPLY (   -- tap terdekat ke jam selesai, wajib setelah tap masuk
  SELECT TOP 1 a.scan_date
  FROM dbo.att_log a
  WHERE CONVERT(varchar(50), a.pin) = CONVERT(varchar(50), w.BARCODE)
    AND a.scan_date >= DATEADD(hour, -4, w.end_dt)
    AND a.scan_date <= DATEADD(hour, 12, w.end_dt)
    AND (si.scan_date IS NULL OR a.scan_date > si.scan_date)
  ORDER BY ABS(DATEDIFF(minute, a.scan_date, w.end_dt)), a.scan_date
) AS so
ORDER BY w.NPK;
```

Cara membaca hasil:

- `sumber_jadwal = 'ROSTER'` → jam mengikuti shift resmi; `telat_menit`/`pulang_lebih_menit` bermakna.
- `sumber_jadwal = 'DEFAULT'` → karyawan tidak dijadwalkan lewat `employee_shifts`; jadwal acuan **08:00–17:00**.
  Jam tetap dihitung dengan cara yang sama (tap terdekat), bukan MIN/MAX.
- `sumber_jadwal = 'LIBUR'` → shift libur (`is_holiday='1'`, mis. `SECURITY LIBUR` 00:00–23:59); jam kerja tidak
  dihitung dan karyawan tidak boleh dihitung alpa.
- `telat_menit` negatif = datang lebih awal; `pulang_lebih_menit` negatif = pulang setelah jam selesai.
- Shift malam (`work_end < work_start`) berakhir di hari+1: `C-00756` (20:30–05:30) masuk 13 Nov 20:29,
  keluar 14 Nov 07:00.
- Tap tunggal tetap dilaporkan apa adanya: `TANPA TAP MASUK` atau `TANPA TAP KELUAR` — jangan diisi tap yang sama
  untuk kedua kolom.

Hasil uji 13 Nov 2026: **3.639 baris** (17,7 detik) — 90 `ROSTER`, 3.547 `DEFAULT`, 2 `LIBUR`;
status 3.433 `OK`, 157 `TIDAK HADIR`, 14 `TANPA TAP MASUK`, 33 `TANPA TAP KELUAR`.
Pada tanggal itu 47 karyawan memang hanya tap satu kali, jadi status tanpa pasangan itu fakta, bukan error query.

Bila hanya ingin karyawan yang punya jadwal shift, tambahkan `AND w.sumber_jadwal IN ('ROSTER','LIBUR')`.

### 2.4 RESEP 4 — Lembur per periode (hanya jam, kode dipisah)

```sql
SELECT o.NPK, b.NAMA_KARYAWAN, d.DEPARTEMENT,
       SUM(TRY_CAST(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2))) AS TOTAL_JAM_LEMBUR,
       COUNT(*) AS JUMLAH_HARI
FROM dbo.overtimes o
LEFT JOIN dbo.BIODATA b ON b.NPK = o.NPK
LEFT JOIN dbo.DEPT d    ON d.ID_DEPT = b.ID_DEPT
WHERE o.OVERTIME_DATE >= '2026-10-01'
  AND o.OVERTIME_DATE <  '2026-11-01'
  AND o.OVERTIME_DATE <= CAST(GETDATE() AS date)      -- R12: buang tanggal masa depan
  AND TRY_CAST(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2)) IS NOT NULL
GROUP BY o.NPK, b.NAMA_KARYAWAN, d.DEPARTEMENT
ORDER BY TOTAL_JAM_LEMBUR DESC;
```

Status tidak masuk dihitung terpisah, misalnya:

```sql
SELECT o.JUMLAH_JAM_LEMBUR AS KODE, COUNT(*) AS JUMLAH_BARIS
FROM dbo.overtimes o
WHERE o.OVERTIME_DATE >= '2026-10-01' AND o.OVERTIME_DATE < '2026-11-01'
  AND TRY_CAST(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2)) IS NULL
GROUP BY o.JUMLAH_JAM_LEMBUR ORDER BY JUMLAH_BARIS DESC;
```

Pemeriksaan batas lembur (R31) — lembur hari kerja saja, Sabtu/Minggu & hari libur dikecualikan:

```sql
-- JAM_HARI: total per NPK per tanggal (hari kerja saja)
WITH kerja AS (
    SELECT o.NPK, o.OVERTIME_DATE,
           SUM(TRY_CAST(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2))) AS JAM
    FROM dbo.overtimes o
    WHERE o.OVERTIME_DATE >= '2026-10-01'
      AND o.OVERTIME_DATE <  '2026-11-01'
      AND o.OVERTIME_DATE <= CAST(GETDATE() AS date)                 -- R12
      AND TRY_CAST(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2)) IS NOT NULL  -- R10
      AND DATEDIFF(day, '19000101', o.OVERTIME_DATE) % 7 NOT IN (5, 6)              -- bukan Sabtu/Minggu
      AND NOT EXISTS (SELECT 1 FROM dbo.holidays h WHERE h.holiday_date = o.OVERTIME_DATE) -- R31
    GROUP BY o.NPK, o.OVERTIME_DATE
)
SELECT NPK, OVERTIME_DATE, JAM AS JAM_HARI,
       CASE WHEN JAM > 4 THEN JAM - 4 ELSE 0 END AS LEBIH_HARI
FROM kerja
WHERE JAM > 4                                                        -- batas harian R31
ORDER BY JAM DESC;
```

```sql
-- JAM_MINGGU: total per NPK per minggu (Senin–Minggu), batas 18 jam
WITH kerja AS (
    SELECT o.NPK,
           DATEADD(day, -(DATEDIFF(day, '19000101', o.OVERTIME_DATE) % 7), o.OVERTIME_DATE) AS AWAL_MINGGU,
           SUM(TRY_CAST(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2))) AS JAM
    FROM dbo.overtimes o
    WHERE o.OVERTIME_DATE >= '2026-10-01'
      AND o.OVERTIME_DATE <  '2026-11-01'
      AND o.OVERTIME_DATE <= CAST(GETDATE() AS date)
      AND TRY_CAST(LTRIM(RTRIM(o.JUMLAH_JAM_LEMBUR)) AS DECIMAL(18,2)) IS NOT NULL
      AND DATEDIFF(day, '19000101', o.OVERTIME_DATE) % 7 NOT IN (5, 6)
      AND NOT EXISTS (SELECT 1 FROM dbo.holidays h WHERE h.holiday_date = o.OVERTIME_DATE)
    GROUP BY o.NPK, DATEADD(day, -(DATEDIFF(day, '19000101', o.OVERTIME_DATE) % 7), o.OVERTIME_DATE)
)
SELECT NPK, AWAL_MINGGU, JAM AS JAM_MINGGU,
       CASE WHEN JAM > 18 THEN JAM - 18 ELSE 0 END AS LEBIH_MINGGU
FROM kerja
WHERE JAM > 18                                                       -- batas mingguan R31
ORDER BY JAM DESC;
```

Lembur hari istirahat dilaporkan **terpisah** (tidak masuk hitungan batas): pakai filter kebalikannya
(`DATEDIFF(...) % 7 IN (5, 6)` atau `EXISTS` di `holidays`), jangan dijumlahkan dengan angka di atas.

### 2.5 RESEP 5 — Cuti / izin

```sql
SELECT lr.id, lr.npk, b.NAMA_KARYAWAN, lt.name AS JENIS_CUTI,
       lr.start_date, lr.end_date, lr.status
FROM dbo.leave_requests lr
LEFT JOIN dbo.leave_types lt ON lt.id = lr.leave_type_id
LEFT JOIN dbo.BIODATA b      ON b.NPK = lr.npk
WHERE lr.status = 'approved'                 -- huruf kecil (R8 pada kamus nilai)
  AND lr.start_date >= '2026-10-01' AND lr.start_date < '2026-11-01'
ORDER BY lr.start_date;
```

### 2.6 RESEP 6 — Turnover per departemen / line / factory (R27–R30)

**Jawaban singkat:** `MASUK` = `PKWT.TMK` dalam periode, `KELUAR` = `PKWT.TKK` dalam periode,
`AKTIF` = karyawan di `BIODATA` (snapshot hari ini), `TURNOVER %` = `100.0 * KELUAR / AKTIF`.
Nomor line diekstrak dari `DEPT.DEPARTEMENT`, lalu dipetakan: 1–42 → Factory 2, 43–84 → Factory 1,
selain itu → Lainnya (R28).

> **Status uji:** resep ini disusun dari skema terdokumentasi Bagian 5 dan angka pembanding laporan
> Oktober 2026 (QC 21/119, PACKING 14/55, IRONING 12/37, LINE 41 5/16 — semuanya cocok dengan formula
> R27). **Belum dijalankan dari VM pengembangan** karena DB CII `192.168.3.88:1433` tidak terjangkau dari
> sana; kalau ada error kolom, laporkan nama kolomnya — jangan mengarang angka pengganti.

```sql
WITH orang AS (                             -- semua orang + departemennya (aktif & keluar)
    SELECT b.NPK, b.ID_DEPT FROM dbo.BIODATA b
    UNION
    SELECT k.NPK, k.ID_DEPT FROM dbo.BIODATA_KELUAR k
),
dim AS (                                    -- normalisasi unit: nomor line dari nama departemen
    SELECT DISTINCT
           d.ID_DEPT,
           CASE WHEN l.NO_LINE BETWEEN 1 AND 84
                THEN 'LINE ' + CAST(l.NO_LINE AS varchar(3))
                ELSE d.DEPARTEMENT END                        AS UNIT,
           CASE WHEN l.NO_LINE BETWEEN 1 AND 42 THEN 'Factory 2'
                WHEN l.NO_LINE BETWEEN 43 AND 84 THEN 'Factory 1'
                ELSE 'Lainnya' END                            AS FACTORY
    FROM dbo.DEPT d
    CROSS APPLY (SELECT TRY_CAST(SUBSTRING(d.DEPARTEMENT,
                     PATINDEX('%LINE %', d.DEPARTEMENT) + 5, 3) AS int) AS NO_LINE) l
),
unit_orang AS (
    SELECT o.NPK, x.UNIT, x.FACTORY
    FROM orang o JOIN dim x ON x.ID_DEPT = o.ID_DEPT
),
masuk AS (
    SELECT u.UNIT, COUNT(DISTINCT p.NPK) AS MASUK
    FROM dbo.PKWT p JOIN unit_orang u ON u.NPK = p.NPK
    WHERE p.TMK >= '2026-10-01' AND p.TMK < '2026-11-01'
    GROUP BY u.UNIT
),
keluar AS (
    SELECT u.UNIT, COUNT(DISTINCT p.NPK) AS KELUAR
    FROM dbo.PKWT p JOIN unit_orang u ON u.NPK = p.NPK
    WHERE p.TKK >= '2026-10-01' AND p.TKK < '2026-11-01'
    GROUP BY u.UNIT
),
aktif AS (
    SELECT u.UNIT, COUNT(DISTINCT u.NPK) AS AKTIF
    FROM unit_orang u JOIN dbo.BIODATA b ON b.NPK = u.NPK
    GROUP BY u.UNIT
)
SELECT TOP 10 f.FACTORY, f.UNIT AS DEPARTEMENT,
       ISNULL(m.MASUK, 0)  AS MASUK,
       ISNULL(k.KELUAR, 0) AS KELUAR,
       ISNULL(a.AKTIF, 0)  AS AKTIF,
       CASE WHEN ISNULL(a.AKTIF, 0) = 0 THEN NULL        -- penyebut 0 → NULL, BUKAN 0.00 (R27)
            ELSE CAST(100.0 * ISNULL(k.KELUAR, 0) / a.AKTIF AS DECIMAL(10,2)) END AS TURNOVER_PCT
FROM aktif a
JOIN (SELECT DISTINCT UNIT, FACTORY FROM dim) f ON f.UNIT = a.UNIT
LEFT JOIN masuk  m ON m.UNIT = a.UNIT
LEFT JOIN keluar k ON k.UNIT = a.UNIT
WHERE ISNULL(m.MASUK, 0) + ISNULL(k.KELUAR, 0) > 0
ORDER BY KELUAR DESC, AKTIF DESC;
```

**Varian per factory** (kalau user minta "gabungkan jadi 2 factory"): sama seperti di atas, tetapi
`aktif`/`masuk`/`keluar` dikelompokkan `GROUP BY u.FACTORY` dan hasilnya hanya 3 baris
(`Factory 1`, `Factory 2`, `Lainnya`) — bukan baris per line.

**Pemeriksa satu line** (dipakai untuk pertanyaan lanjutan "kenapa LINE 66 aktifnya 0?" — R26/R30):

```sql
SELECT d.DEPARTEMENT,
       SUM(CASE WHEN b.NPK IS NULL THEN 0 ELSE 1 END) AS KARYAWAN_AKTIF,
       (SELECT COUNT(DISTINCT p.NPK) FROM dbo.PKWT p
         WHERE p.TKK >= '2026-10-01' AND p.TKK < '2026-11-01'
           AND EXISTS (SELECT 1 FROM dbo.BIODATA_KELUAR k
                        WHERE k.NPK = p.NPK AND k.ID_DEPT = d.ID_DEPT)) AS KELUAR_PERIODE
FROM dbo.DEPT d
LEFT JOIN dbo.BIODATA b ON b.ID_DEPT = d.ID_DEPT AND b.STATUS = 'A'
WHERE d.DEPARTEMENT LIKE '%LINE 66%'
GROUP BY d.ID_DEPT, d.DEPARTEMENT
ORDER BY KARYAWAN_AKTIF DESC;
```

Kalau semua baris `KARYAWAN_AKTIF = 0`, jawabannya: tidak ada karyawan aktif pada line tersebut
(line ditutup/digabung atau seluruh karyawannya keluar/dipindah) → turnover **tidak terdefinisi**,
bukan 0%. Sebutkan `employee_mutations` kalau perlu memastikan ada perpindahan.

### 2.7 Yang tidak boleh dilakukan (anti-pola)

| Anti-pola | Akibat | Benar |
|---|---|---|
| `WHERE STATUS='A'` untuk mencari karyawan keluar | `BIODATA_KELUAR.STATUS` juga 'A' → semua ikut | `PKWT.TKK IS NOT NULL` |
| Join `att_log.pin = BIODATA.NPK` | 0 baris, karena pin = barcode | `att_log.pin = BIODATA.BARCODE` |
| `SUM(JUMLAH_JAM_LEMBUR)` langsung | error konversi / total salah | `TRY_CAST` + filter numerik |
| `SELECT * FROM att_log` | 1,5 juta baris, timeout | filter `scan_date` + kolom eksplisit |
| `MIN(scan_date)`/`MAX(scan_date)` sebagai jam masuk/keluar | shift malam terbaca terbalik (tap 20:29 lalu 05:37 besok), tap lembur dianggap keluar | tap terdekat `work_start`/`work_end` (R24, Resep 3) |
| `inoutmode = 1` dianggap masuk, `3` keluar | 13 Nov 2026 mode 3 hanya 1 tap; 5 dari 5 karyawan contoh kedua tap-nya mode 1 | jangan dipakai untuk arah; pakai roster |
| `JOIN employee_shifts` (inner) | 3.547 karyawan tanpa roster hilang dari laporan | `LEFT JOIN` + jadwal normal 08:00-17:00 bertanda `DEFAULT` |
| Membaca `AUDIT`, `att_log_audit`, `overtimes_payroll_audit`, `payroll_run_details_audit` | angka audit/ganda, tidak resmi | tabel induk + Resep 3/4 (R25) |
| `WHERE SECTION='CHUTEX'` di DEPT | semua baris ikut (konstanta) | filter `ID_DEPT`/`DEPARTEMENT` |
| `WHERE DAY='Senin'` | melewatkan 118 ribu baris berbahasa Inggris | jangan filter hari, atau pakai `DATEPART` |
| `WHERE status='APPROVED'` | 0 baris (data huruf kecil) | `status='approved'` |
| Menyimpulkan 'tabel tidak ada' saat error 229/230 | salah diagnosa | sampaikan 'akses dibatasi' |
| `TURNOVER = 0.00` saat `AKTIF = 0` | penyebut nol dilaporkan sebagai 0% — menyesatkan | `NULL`/`-` + penjelasan (R27) |
| `TOP 10` diambil dari baris LINE mentah lalu disebut "top 10 departemen" | level agregasi salah; pengguna mempertanyakan dasarnya | agregasi per departemen/factory dulu, baru `TOP 10` (R29) |
| Menjawab "saya tidak punya riwayat/konteks" pada pertanyaan lanjutan | mengulang pekerjaan user; jawaban tidak nyambung | pakai riwayat + query terakhir (R26) |
| Menyebut QC/PACKING/IRONING sebagai "Factory 1/2" | departemen non-line bukan bagian dari line | `Lainnya` (R28) |

## 3. KAMUS NILAI (arti kode yang tersimpan)

Nilai di bawah diambil dari data nyata. Kolom 'status' menandai mana yang sudah dipastikan
dan mana yang masih perlu konfirmasi ke pemilik data — AI **tidak boleh** mengubah 'belum
terverifikasi' menjadi pernyataan pasti.

### `BIODATA.STATUS` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `A` | aktif |

Seluruh 3.639 baris bernilai 'A'.

### `BIODATA.IS_STAFF` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `0` | non-staff |
| `1` | staff |

Teks '0'/'1' (3.315 vs 324 baris).

### `BIODATA.IS_EXPAT` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `0` | bukan ekspatriat |
| `1` | ekspatriat |

Teks '0'/'1' (3.622 vs 17 baris).

### `BIODATA.JENIS_KEL` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `L` | laki-laki |
| `P` | perempuan |

### `PKWT.STATUS` — status: perlu konfirmasi HR

| Nilai tersimpan | Arti |
|---|---|
| `BM` | belum menikah |
| `M` | menikah |
| `CH` | cerai hidup |
| `CM` | cerai mati |

Status PERNIKAHAN, bukan status kerja. 36 baris NULL dan 22 kosong.

### `att_log.inoutmode` — status: terverifikasi sebagian (arah tidak terbaca)

| Nilai tersimpan | Arti |
|---|---|
| `1` | tap pertama/dominan (13 Nov 2026: 3.605 tap; Okt: 80.017) |
| `2` | tap kedua (13 Nov: 3.236; Okt: 65.377) |
| `3` | sangat jarang (13 Nov: 1 tap; Okt: 262) |
| `0` | 97 tap |
| `4` | 7 tap |
| `6` | 25 tap |

BUKAN penanda masuk/keluar: 5 dari 5 karyawan contoh punya kedua tap bermode 1. Jangan dipakai menentukan arah maupun jam kerja - pakai roster (R24).

### `att_log.verifymode` — status: belum terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `1` | metode utama (145.621 tap Okt 2026) |
| `2` | metode kedua (3.182) |

Arti tiap metode belum terverifikasi.

### `overtimes.JUMLAH_JAM_LEMBUR` — status: sebagian terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `<angka>` | jam lembur (mis. '1', '2.5') |
| `CT` | cuti (tetap dibayar) |
| `P1` | izin |
| `MA` | mangkir |
| `SD` | sakit |
| `BR` | belum terverifikasi |
| `OUT` | belum terverifikasi |
| `PE` | belum terverifikasi |
| `H` | izin setengah hari |
| `HD` | belum terverifikasi |
| `M` | belum terverifikasi |
| `NULL` | tidak ada data lembur |

Kolom bertipe nvarchar. Angka = jam. Huruf = status tidak masuk, BUKAN 0 jam. Jumlah baris kode: NULL 643.055, MA 19.339, BR 14.064, CT 13.884, OUT 12.584, P1 4.204, SD 3.959, PE 271, H 68, spasi 24, M 13, HD 12.

### `overtimes.DAY` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `Monday..Sunday` | nama hari bahasa Inggris (mayoritas ~118-121 rb) |
| `Senin..Minggu` | nama hari bahasa Indonesia (mis. 'Selasa' 9.080 baris) |

Satu kolom memuat dua bahasa. Jangan filter DAY='Senin' untuk menghitung hari kerja.

### `overtimes_payroll.DAY` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `Monday..Sunday` | nama hari bahasa Inggris |

Berbeda dari `overtimes` yang campur bahasa.

### `leave_requests.status` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `approved` | disetujui (460) |
| `pending` | menunggu (15) |

Huruf KECIL. Jangan pakai 'APPROVED'.

### `payroll_periods.is_closed` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `0` | periode masih berjalan |
| `1` | periode sudah ditutup |

Per 16 Nov 2026: November 2026 is_closed=0; Juni-Oktober 2026 is_closed=1.

### `AUDIT.STATUS` — status: sebagian terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `LBR` | lembur |

Nilai lain belum terverifikasi.

### `doorprize_winners.is_void` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `0` | pemenang sah |
| `1` | dibatalkan |

### `BIODATA_KELUAR.STATUS` — status: terverifikasi

| Nilai tersimpan | Arti |
|---|---|
| `A` | masih 'A' walau sudah keluar |

JANGAN pakai STATUS untuk menentukan karyawan keluar.

## 4. PETA JOIN TERVERIFIKASI

| Dari | Ke | Kunci | Kardinalitas / catatan |
|---|---|---|---|
| `PKWT` | `BIODATA` | `PKWT.NPK = BIODATA.NPK` | 1:0..1 (hanya karyawan aktif yang punya baris BIODATA) |
| `PKWT` | `BIODATA_KELUAR` | `PKWT.NPK = BIODATA_KELUAR.NPK` | 1:0..1 (hanya karyawan keluar) |
| `BIODATA` | `DEPT` | `BIODATA.ID_DEPT = DEPT.ID_DEPT` | N:1, 40 ID_DEPT berbeda pada sampel 200 baris |
| `BIODATA_KELUAR` | `DEPT` | `BIODATA_KELUAR.ID_DEPT = DEPT.ID_DEPT` | N:1; 111 dari 2.163 karyawan keluar tanpa dept → LEFT JOIN |
| `att_log` | `BIODATA` | `att_log.pin = BIODATA.BARCODE` | N:1; 3.432 PIN Okt 2026 semuanya cocok |
| `employee_shifts` | `BIODATA` | `employee_shifts.npk = BIODATA.NPK` | N:1; 12.569 dari 13.383 baris cocok, 814 NPK tidak ada di BIODATA → LEFT JOIN |
| `employee_shifts` | `shifts` | `employee_shifts.shift_id = shifts.id` | N:1 (FK resmi), 23 shift |
| `overtimes` | `BIODATA` | `overtimes.NPK = BIODATA.NPK` | N:1 |
| `overtimes_payroll` | `BIODATA` | `overtimes_payroll.NPK = BIODATA.NPK` | N:1 |
| `leave_requests` | `leave_types` | `leave_type_id = leave_types.id` | N:1 |
| `leave_requests` | `BIODATA` | `leave_requests.npk = BIODATA.NPK` | N:1 |
| `payroll_run_details` | `payroll_runs` | `run_id = payroll_runs.id` | N:1 (akses dibatasi) |
| `payroll_runs` | `payroll_periods` | `period_id = payroll_periods.id` | N:1 |
| `employee_mutations` | `DEPT` | `from_dept`/`to_dept` = `DEPT.ID_DEPT` | N:1 per sisi |
| `mon_work_orders` | `mon_orders` | lihat definisi kolom di Bagian 5 | 1:N |
| `mon_purchase_orders` | `mon_ms_suppliers` | kode/nama supplier | N:1 |

Aturan umum join: **samakan tipe kedua sisi**. Contoh jebakan nyata: `BIODATA.BARCODE` bertipe
`nvarchar(50)`, sedangkan `BIODATA_KELUAR.BARCODE` bertipe `int`.

## 5. DAFTAR TABEL & KOLOM (167 tabel, 1.920 kolom)

Setiap tabel memuat: fungsi, jumlah baris nyata, status akses, kunci, catatan penting,
lalu tabel kolom (nama, tipe, keterangan, contoh nilai nyata).
Nilai `(disamarkan)` berarti kolom memuat data pribadi yang sengaja tidak ditampilkan.
Tanda `—` pada kolom contoh berarti tidak ada nilai tidak-NULL pada 200 baris sampel, atau tabelnya
tidak dapat dibaca — **bukan** berarti kolomnya tidak ada.

### D1. Master Karyawan & Organisasi — 21 tabel

Identitas karyawan, status aktif/keluar, kontrak, departemen, mutasi, penempatan.

#### `BIODATA`

- **Fungsi:** Master karyawan AKTIF. Satu baris per NPK.
- **Jumlah baris (16 Nov 2026):** 3.639
- **Akses:** bisa dibaca
- **Kunci:** `NPK`
- **Penting:** Semua baris STATUS='A' (3.639 baris). Tidak memuat karyawan keluar.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `NPK` | `nvarchar(50)` | **PK** · Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00001`, `C-00003`, `C-00005` |
| `NAMA_KARYAWAN` | `nvarchar(50)` | Nama lengkap karyawan. Data pribadi. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `BAG` | `nvarchar(50)` | Bagian/line kerja karyawan menurut master (teks bebas, mis. 'LINE 21'). · boleh NULL | `OPERATOR`, `LINE 21`, `LINE 9` |
| `ID_DEPT` | `int` | ID departemen, join ke `DEPT.ID_DEPT` (int). | `2277`, `2039`, `2035` |
| `JENIS_KEL` | `nvarchar(50)` | Jenis kelamin: 'L' (laki-laki) / 'P' (perempuan). · boleh NULL | `P`, `L` |
| `BARCODE` | `nvarchar(50)` | Nomor barcode mesin absensi; dipakai sebagai `att_log.pin`. · boleh NULL | `(disamarkan)`, `108`, `(disamarkan)` |
| `SECTION` | `nvarchar(254)` | Section. Di `DEPT` selalu 'CHUTEX'; di `BIODATA` berisi angka → jangan dipakai sebagai filter. · boleh NULL | `109` |
| `STATUS` | `nvarchar(254)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `A` |
| `IS_STAFF` | `nvarchar(255)` | Penanda staff. Disimpan sebagai teks '0'/'1', bukan angka. · boleh NULL | `0`, `1` |
| `IS_EXPAT` | `nvarchar(255)` | Penanda ekspatriat. Teks '0'/'1'. · boleh NULL · default `0` | `0`, `1` |
| `IS_EXCLUDE` | `nvarchar(255)` | Penanda dikecualikan dari perhitungan (mis. headcount). · boleh NULL | `1` |

#### `BIODATA_KELUAR`

- **Fungsi:** Arsip karyawan yang sudah keluar (2.204 baris).
- **Jumlah baris (16 Nov 2026):** 2.204
- **Akses:** bisa dibaca
- **Kunci:** tidak ada primary key
- **Penting:** STATUS masih 'A' untuk 2.201 baris → JANGAN pakai STATUS untuk menyaring keluar. BARCODE bertipe int (di BIODATA nvarchar). BAG banyak NULL; pakai DEPT.DEPARTEMENT.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `NPK` | `nvarchar(50)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. · boleh NULL | `C-00042`, `C-00049`, `C-00052` |
| `NAMA_KARYAWAN` | `nvarchar(50)` | Nama lengkap karyawan. Data pribadi. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `BAG` | `nvarchar(50)` | Bagian/line kerja karyawan menurut master (teks bebas, mis. 'LINE 21'). · boleh NULL | `OPERATOR`, `QC`, `PACKING` |
| `ID_DEPT` | `int` | ID departemen, join ke `DEPT.ID_DEPT` (int). · boleh NULL | `316`, `2087`, `32` |
| `JENIS_KEL` | `nvarchar(50)` | Jenis kelamin: 'L' (laki-laki) / 'P' (perempuan). · boleh NULL | `P`, `L` |
| `BARCODE` | `int` | Nomor barcode mesin absensi; dipakai sebagai `att_log.pin`. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `SECTION` | `nvarchar(50)` | Section. Di `DEPT` selalu 'CHUTEX'; di `BIODATA` berisi angka → jangan dipakai sebagai filter. · boleh NULL | `CHUTEX`, `GARMENT 12`, `109` |
| `STATUS` | `nvarchar(50)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `A` |
| `IS_STAFF` | `nvarchar(255)` | Penanda staff. Disimpan sebagai teks '0'/'1', bukan angka. · boleh NULL | `1` |
| `IS_EXPAT` | `nvarchar(255)` | Penanda ekspatriat. Teks '0'/'1'. · boleh NULL | `0` |
| `IS_EXCLUDE` | `nvarchar(255)` | Penanda dikecualikan dari perhitungan (mis. headcount). · boleh NULL | — |

#### `BIODATA_OS`

- **Fungsi:** Cadangan master karyawan outsourcing.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `NPK`
- **Penting:** Kosong (0 baris) saat verifikasi.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `NPK` | `nvarchar(50)` | **PK** · Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `NAMA_KARYAWAN` | `nvarchar(50)` | Nama lengkap karyawan. Data pribadi. · boleh NULL | — |
| `BAG` | `nvarchar(50)` | Bagian/line kerja karyawan menurut master (teks bebas, mis. 'LINE 21'). · boleh NULL | — |
| `ID_DEPT` | `int` | ID departemen, join ke `DEPT.ID_DEPT` (int). | — |
| `JENIS_KEL` | `nvarchar(50)` | Jenis kelamin: 'L' (laki-laki) / 'P' (perempuan). · boleh NULL | — |
| `BARCODE` | `nvarchar(50)` | Nomor barcode mesin absensi; dipakai sebagai `att_log.pin`. · boleh NULL | — |
| `SECTION` | `nvarchar(254)` | Section. Di `DEPT` selalu 'CHUTEX'; di `BIODATA` berisi angka → jangan dipakai sebagai filter. · boleh NULL | — |
| `STATUS` | `nvarchar(254)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | — |

#### `BLOCKED`

- **Fungsi:** Daftar alamat IP/MAC yang diblokir.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** tidak ada primary key

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `IP_ADDRESS` | `varchar(255)` | Alamat IP. · boleh NULL | — |
| `MAC_ADDRESS` | `varchar(255)` | Mac alamat · boleh NULL | — |
| `BLOCKED` | `varchar(255)` |  boleh NULL | — |

#### `BLOCKING`

- **Fungsi:** Log aktivitas pemblokiran IP/MAC (LOG_DATE, ACTIVITY).
- **Jumlah baris (16 Nov 2026):** 9
- **Akses:** bisa dibaca
- **Kunci:** `ID`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `ID` | `int` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `11`, `2` |
| `IP_ADDRESS` | `varchar(255)` | Alamat IP. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `MAC_ADDRESS` | `varchar(255)` | Mac alamat | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `LOG_DATE` | `datetime` | Log tanggal | `2025-10-25T09:45:33.377000`, `2025-10-27T08:54:06.460000`, `2025-10-27T13:30:28.117000` |
| `ACTIVITY` | `varchar(255)` | Jenis aktivitas yang dicatat. | `Blocked due to failed login 3 times` |

#### `DEPT`

- **Fungsi:** Master departemen (310 baris). PK ID_DEPT, nama di DEPARTEMENT.
- **Jumlah baris (16 Nov 2026):** 310
- **Akses:** bisa dibaca
- **Kunci:** `ID_DEPT`
- **Penting:** SECTION selalu 'CHUTEX' (konstanta) → tidak berguna sebagai filter. Nama kolom 'DEPARTEMENT' (bukan DEPARTMENT).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `ID_DEPT` | `int` | **PK** · ID departemen, join ke `DEPT.ID_DEPT` (int). | `1`, `101`, `107` |
| `DEPARTEMENT` | `nvarchar(50)` | Nama departemen pada master `DEPT` (ejaan memang 'DEPARTEMENT'). · boleh NULL | `LEADER SEWING LINE 18`, `SECTION HEAD`, `ACCOUNTING` |
| `IS_SEWING` | `varchar(254)` | Penanda sewing · boleh NULL | `1`, `0` |
| `SECTION` | `nvarchar(254)` | Section. Di `DEPT` selalu 'CHUTEX'; di `BIODATA` berisi angka → jangan dipakai sebagai filter. · boleh NULL | `CHUTEX` |
| `id_parent_dept` | `nvarchar(255)` | ID departemen induk (disimpan sebagai teks). · boleh NULL | — |

#### `OUTSOURCE`

- **Fungsi:** Data kontrak karyawan outsourcing.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `NPK`
- **Penting:** Kosong (0 baris) saat verifikasi.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `NPK` | `nvarchar(255)` | **PK** · Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `NAMA` | `nvarchar(255)` | Nama (orang/entitas). Data pribadi. · boleh NULL | — |
| `JK` | `nvarchar(255)` | Jenis kelamin: 'L' / 'P'. · boleh NULL | — |
| `TGLLAHIR` | `date` | Tanggal lahir. · boleh NULL | — |
| `PDDK` | `nvarchar(255)` | Pendidikan terakhir (ejaan satu 'D': PDDK, bukan PENDIDIKAN). · boleh NULL | — |
| `AGAMA` | `nvarchar(255)` | Agama. · boleh NULL | — |
| `TMK` | `date` | Tanggal Masuk Kerja (awal kontrak). Batas bawah saat menghitung karyawan aktif. · boleh NULL | — |
| `USIA` | `nvarchar(255)` | Usia (disimpan sebagai teks). · boleh NULL | — |
| `TKK` | `datetime` | Tanggal Keluar Kerja. `TKK IS NULL` = masih aktif; terisi = sudah keluar. · boleh NULL | — |
| `BAGIAN` | `nvarchar(255)` | Bagian/line kerja (teks, sering tidak konsisten dengan master DEPT). · boleh NULL | — |
| `ALAMAT` | `nvarchar(255)` | Alamat tempat tinggal. Data pribadi. · boleh NULL | — |
| `KABUPATEN` | `nvarchar(255)` | Kabupaten/kota domisili. · boleh NULL | — |
| `KTP` | `nvarchar(254)` | Nomor KTP. Data pribadi sangat sensitif. · boleh NULL | — |
| `NO_KK` | `nvarchar(254)` | Nomor Kartu Keluarga. Data pribadi sangat sensitif. · boleh NULL · default `N'-'` | — |
| `IBU` | `nvarchar(255)` | Nama ibu kandung. Data pribadi. · boleh NULL | — |
| `HP` | `nvarchar(255)` | Nomor HP. Data pribadi. · boleh NULL | — |
| `STATUS` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | — |
| `TANGGUNGAN` | `nvarchar(254)` | Jumlah tanggungan keluarga. · boleh NULL | — |
| `KETERANGAN` | `nvarchar(255)` | Keterangan bebas. · boleh NULL | — |
| `TUTUPBUKU` | `nvarchar(254)` | Penanda tutup buku / periode akuntansi. · boleh NULL | — |
| `TMPTLAHIR` | `nvarchar(254)` | Tempat lahir. · boleh NULL | — |
| `NOREK` | `nvarchar(254)` | Nomor rekening bank. Data pribadi sangat sensitif. · boleh NULL | — |
| `JURUSAN` | `nvarchar(255)` | Jurusan pendidikan. · boleh NULL | — |
| `FASKES` | `nvarchar(255)` | Fasilitas kesehatan BPJS. · boleh NULL | — |

#### `PKWT`

- **Fungsi:** Kontrak kerja seluruh karyawan: TMK (masuk) dan TKK (keluar).
- **Jumlah baris (16 Nov 2026):** 5.798 (dihitung lewat kolom eksplisit; `SELECT *` ditolak)
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `NPK`
- **Penting:** 5.798 baris = 3.639 aktif (TKK NULL) + 2.159 keluar (TKK terisi). Sumber resmi daftar karyawan keluar. STATUS di sini = status pernikahan, BUKAN status kerja. Akses: `SELECT *` dan `COUNT(*)` DITOLAK (permission kolom KTP), tetapi kolom eksplisit (NPK, NAMA, TMK, TKK, BAGIAN) berhasil dibaca — selalu sebut kolom, jangan `SELECT *`.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `NPK` | `nvarchar(255)` | **PK** · Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-01073`, `C-03755`, `C-03810` |
| `NAMA` | `nvarchar(255)` | Nama (orang/entitas). Data pribadi. · boleh NULL | `(disamarkan)` |
| `JK` | `nvarchar(255)` | Jenis kelamin: 'L' / 'P'. · boleh NULL | `L`, `P` |
| `TGLLAHIR` | `date` | Tanggal lahir. · boleh NULL | — |
| `PDDK` | `nvarchar(255)` | Pendidikan terakhir (ejaan satu 'D': PDDK, bukan PENDIDIKAN). · boleh NULL | `SMA/SMK` |
| `AGAMA` | `nvarchar(255)` | Agama. · boleh NULL | — |
| `TMK` | `date` | Tanggal Masuk Kerja (awal kontrak). Batas bawah saat menghitung karyawan aktif. · boleh NULL | `2025-07-07`, `2026-05-11` |
| `USIA` | `nvarchar(255)` | Usia (disimpan sebagai teks). · boleh NULL | — |
| `TKK` | `date` | Tanggal Keluar Kerja. `TKK IS NULL` = masih aktif; terisi = sudah keluar. · boleh NULL | `2026-11-13` |
| `BAGIAN` | `nvarchar(255)` | Bagian/line kerja (teks, sering tidak konsisten dengan master DEPT). · boleh NULL | `SEWING REPAIR`, `QC` |
| `ALAMAT` | `nvarchar(255)` | Alamat tempat tinggal. Data pribadi. · boleh NULL | — |
| `KABUPATEN` | `nvarchar(255)` | Kabupaten/kota domisili. · boleh NULL | — |
| `KTP` | `nvarchar(254)` | Nomor KTP. Data pribadi sangat sensitif. · boleh NULL | — |
| `NO_KK` | `nvarchar(254)` | Nomor Kartu Keluarga. Data pribadi sangat sensitif. · boleh NULL · default `N'-'` | — |
| `IBU` | `nvarchar(255)` | Nama ibu kandung. Data pribadi. · boleh NULL | — |
| `HP` | `nvarchar(255)` | Nomor HP. Data pribadi. · boleh NULL | — |
| `STATUS` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `BM`, `M`, `CH` |
| `TANGGUNGAN` | `nvarchar(254)` | Jumlah tanggungan keluarga. · boleh NULL | — |
| `KETERANGAN` | `nvarchar(255)` | Keterangan bebas. · boleh NULL | — |
| `TUTUPBUKU` | `nvarchar(254)` | Penanda tutup buku / periode akuntansi. · boleh NULL | — |
| `TMPTLAHIR` | `nvarchar(254)` | Tempat lahir. · boleh NULL | — |
| `NOREK` | `nvarchar(254)` | Nomor rekening bank. Data pribadi sangat sensitif. · boleh NULL | — |
| `JURUSAN` | `nvarchar(255)` | Jurusan pendidikan. · boleh NULL | — |
| `FASKES` | `nvarchar(255)` | Fasilitas kesehatan BPJS. · boleh NULL | — |
| `leave_reasons` | `varchar(max)` | Alasan/alasan cuti yang tercatat (teks bebas). · boleh NULL | — |
| `file_surat_lamaran` | `nvarchar(255)` | Path/nama berkas SURAT LAMARAN di server. · boleh NULL | — |
| `file_cv` | `nvarchar(255)` | Path/nama berkas CV di server. · boleh NULL | — |
| `file_ktp` | `nvarchar(255)` | Path/nama berkas KTP di server. · boleh NULL | — |
| `file_kk` | `nvarchar(255)` | Path/nama berkas KK di server. · boleh NULL | — |
| `file_ijazah` | `nvarchar(255)` | Path/nama berkas IJAZAH di server. · boleh NULL | — |
| `file_akta_kelahiran` | `nvarchar(255)` | Path/nama berkas AKTA KELAHIRAN di server. · boleh NULL | — |
| `file_skck` | `nvarchar(255)` | Path/nama berkas SKCK di server. · boleh NULL | — |
| `file_surat_sehat` | `nvarchar(255)` | Path/nama berkas SURAT SEHAT di server. · boleh NULL | — |
| `file_pas_foto` | `nvarchar(255)` | Path/nama berkas PAS FOTO di server. · boleh NULL | — |
| `is_agreement_signed` | `nvarchar(255)` | Penanda kontrak sudah ditandatangani (teks '0'/'1'). · boleh NULL · default `'0'` | — |
| `file_penalty_letter` | `nvarchar(255)` | Path/nama berkas PENALTY LETTER di server. · boleh NULL | — |

#### `PKWT_OUT`

- **Fungsi:** Arsip PKWT karyawan keluar versi lama (63 baris).
- **Jumlah baris (16 Nov 2026):** 63 (dihitung lewat kolom eksplisit)
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** tidak ada primary key
- **Penting:** Hanya 44 baris nyambung ke BIODATA_KELUAR; jangan jadi sumber utama daftar keluar.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `NPK` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `NAMA` | `nvarchar(255)` | Nama (orang/entitas). Data pribadi. · boleh NULL | — |
| `JK` | `nvarchar(255)` | Jenis kelamin: 'L' / 'P'. · boleh NULL | — |
| `TGLLAHIR` | `date` | Tanggal lahir. · boleh NULL | — |
| `PDDK` | `nvarchar(255)` | Pendidikan terakhir (ejaan satu 'D': PDDK, bukan PENDIDIKAN). · boleh NULL | — |
| `AGAMA` | `nvarchar(255)` | Agama. · boleh NULL | — |
| `TMK` | `datetime` | Tanggal Masuk Kerja (awal kontrak). Batas bawah saat menghitung karyawan aktif. · boleh NULL | — |
| `USIA` | `nvarchar(255)` | Usia (disimpan sebagai teks). · boleh NULL | — |
| `TKK` | `date` | Tanggal Keluar Kerja. `TKK IS NULL` = masih aktif; terisi = sudah keluar. · boleh NULL | — |
| `BAGIAN` | `nvarchar(255)` | Bagian/line kerja (teks, sering tidak konsisten dengan master DEPT). · boleh NULL | — |
| `ALAMAT` | `nvarchar(255)` | Alamat tempat tinggal. Data pribadi. · boleh NULL | — |
| `KABUPATEN` | `nvarchar(255)` | Kabupaten/kota domisili. · boleh NULL | — |
| `KTP` | `nvarchar(254)` | Nomor KTP. Data pribadi sangat sensitif. · boleh NULL | — |
| `NO_KK` | `nvarchar(254)` | Nomor Kartu Keluarga. Data pribadi sangat sensitif. · boleh NULL · default `N'-'` | — |
| `IBU` | `nvarchar(255)` | Nama ibu kandung. Data pribadi. · boleh NULL | — |
| `HP` | `nvarchar(255)` | Nomor HP. Data pribadi. · boleh NULL | — |
| `STATUS` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | — |
| `TANGGUNGAN` | `nvarchar(254)` | Jumlah tanggungan keluarga. · boleh NULL | — |
| `KETERANGAN` | `nvarchar(255)` | Keterangan bebas. · boleh NULL | — |
| `TUTUPBUKU` | `nvarchar(254)` | Penanda tutup buku / periode akuntansi. · boleh NULL | — |
| `TMPTLAHIR` | `nvarchar(254)` | Tempat lahir. · boleh NULL | — |
| `NOREK` | `nvarchar(254)` | Nomor rekening bank. Data pribadi sangat sensitif. · boleh NULL | — |
| `JURUSAN` | `nvarchar(255)` | Jurusan pendidikan. · boleh NULL | — |
| `FASKES` | `nvarchar(255)` | Fasilitas kesehatan BPJS. · boleh NULL | — |

#### `REKAP`

- **Fungsi:** Rekap jumlah karyawan PKWT & magang per bulan/tahun (BULAN, TAHUN).
- **Jumlah baris (16 Nov 2026):** 20
- **Akses:** bisa dibaca
- **Kunci:** `ID`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `ID` | `int` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `PKWT` | `int` | Jumlah karyawan berstatus PKWT pada satu rekap (bukan NPK karyawan). | `0`, `1016`, `1295` |
| `MAGANG` | `int` | Jumlah karyawan magang pada rekap. | `0` |
| `BULAN` | `int` | Bulan (angka/nama). | `10`, `11`, `3` |
| `TAHUN` | `varchar(254)` | Tahun. | `2025`, `2026` |

#### `SETLIBUR`

- **Fungsi:** Master hari libur (TANGGAL, KETERANGAN).
- **Jumlah baris (16 Nov 2026):** 4
- **Akses:** bisa dibaca
- **Kunci:** `ID`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `ID` | `int` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `TANGGAL` | `date` | Tanggal kejadian/periode (date). · boleh NULL | `2025-06-06`, `2025-06-09`, `2025-08-17` |
| `KETERANGAN` | `varchar(254)` | Keterangan bebas. · boleh NULL | `Cuti Bersama Idul Adha`, `Hari Raya Idul Adha 1446 H`, `Maulid Nabi Muhammad SAW 1447 H` |

#### `categories`

- **Fungsi:** Kategori umum (dipakai dokumen/OCR dan konten).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | — |
| `slug` | `nvarchar(255)` | Slug unik untuk URL. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `employee_faces`

- **Fungsi:** Template wajah karyawan untuk pengenalan wajah.
- **Jumlah baris (16 Nov 2026):** 287
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Data biometrik: jangan ditampilkan.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `101` |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00007`, `C-00008`, `C-00011` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `face_img` | `nvarchar(max)` | Face gambar | `data:image/jpeg;base64,/9j/4AAQSkZJRg...`, `data:image/jpeg;base64,/9j/4AAQSkZJRg...`, `data:image/jpeg;base64,/9j/4AAQSkZJRg...` |
| `descriptor` | `nvarchar(max)` |  boleh NULL | `[-0.007044464349746704,0.065992489457...`, `[-0.007642132695764303,0.114465907216...`, `[-0.011160466820001602,0.090891905128...` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `registered_by` | `int` | Registered oleh · boleh NULL | — |
| `last_verified_at` | `datetime` | Last verified pada · boleh NULL | `2026-11-04T11:52:55.817000`, `2026-11-05T20:01:43.153000`, `2026-11-06T06:24:06.223000` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-29T14:30:51.483000`, `2026-10-29T15:20:39.327000`, `2026-10-30T11:43:06.740000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-04T11:52:55.817000`, `2026-11-05T08:21:19.480000`, `2026-11-05T20:01:43.157000` |
| `can_login` | `bit` |  · default `0` | `False`, `True` |
| `is_attendance_enabled` | `bit` | Penanda attendance enabled · default `'1'` | `True` |
| `location_scope` | `nvarchar(20)` | Lokasi scope · default `'all'` | `all` |
| `allowed_location_ids` | `nvarchar(max)` | Allowed lokasi ids · boleh NULL | — |

#### `employee_line_assignments`

- **Fungsi:** Penempatan karyawan pada line produksi.
- **Jumlah baris (16 Nov 2026):** 10.501
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `period_id` | `nvarchar(255)` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `3` |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00912`, `C-00037`, `C-00048` |
| `line_number` | `bigint` | Nomor line. · boleh NULL | `1`, `13`, `18` |
| `role` | `nvarchar(255)` | Nama role. | `operator`, `supervisor` |
| `start_date` | `date` | Tanggal mulai berlakunya record. | `2026-06-03`, `2026-06-04`, `2026-06-02` |
| `end_date` | `date` | Tanggal berakhir berlakunya record. · boleh NULL | `2026-06-03`, `2026-06-04`, `2026-06-02` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-15T09:44:32.283000`, `2026-06-15T09:44:32.287000`, `2026-06-15T09:44:32.297000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-23T14:04:48.727000`, `2026-06-23T14:04:48.733000`, `2026-06-23T14:04:48.747000` |
| `section_id` | `nvarchar(255)` | Section ID · boleh NULL | — |
| `work_hours` | `varchar(255)` | Kerja hours · boleh NULL | `9`, `8` |

#### `employee_mutations`

- **Fungsi:** Mutasi karyawan antar departemen (from_dept → to_dept).
- **Jumlah baris (16 Nov 2026):** 4.494
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** from_dept NULL = karyawan baru; to_dept NULL = keluar; from=to = pindah intern.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `100`, `101` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-04470`, `C-04546`, `C-04558` |
| `from_dept` | `nvarchar(255)` | From departemen | `2285`, `32`, `2287` |
| `to_dept` | `nvarchar(255)` | To departemen | `2290`, `32`, `2287` |
| `date` | `date` | Tanggal kejadian. | `2026-07-09`, `2026-07-02`, `2026-07-01` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-01T15:21:41.747000`, `2026-07-01T15:33:37.770000`, `2026-07-01T15:38:43.317000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-01T15:21:41.747000`, `2026-07-01T15:33:37.770000`, `2026-07-01T15:38:43.317000` |

#### `employee_shifts`

- **Fungsi:** Roster: shift yang dijadwalkan untuk seorang karyawan pada satu tanggal (sumber jam kerja resmi untuk kehadiran).
- **Jumlah baris (16 Nov 2026):** 13.383
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Satu baris per (npk, shift_date) - 0 duplikat. 13.383 baris; rentang 28 Sep 2006 - 30 Nov 2026 (ada baris masa depan = rencana). Cakupan tidak semua orang: Nov 2026 hanya 173 NPK, karena yang dijadwalkan lewat roster hanya departemen shift (PACKING, GUDANG, SECURITY, CUTTING, QC, UMUM, DRIVER). 814 baris berisi NPK yang tidak ada di BIODATA (didominasi 2026), jadi wajib LEFT JOIN.
- **Foreign key:** `shift_id` → `shifts.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `100`, `101` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00853`, `C-01802`, `C-01646` |
| `shift_id` | `bigint` | **FK** → `shifts.id` · Shift ID | `10`, `6`, `7` |
| `shift_date` | `date` | Shift tanggal | `2026-07-01`, `2026-07-02`, `2026-07-03` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-25T16:25:08.797000`, `2026-06-25T16:25:08.807000`, `2026-06-25T16:25:08.827000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-25T16:25:08.797000`, `2026-06-25T16:25:08.807000`, `2026-06-25T16:25:08.827000` |

#### `employees_contract`

- **Fungsi:** Siklus/dokumen kontrak karyawan.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `nvarchar(255)` | **PK** · Primary key auto-increment (IDENTITY). | — |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `contract_ke` | `nvarchar(255)` | Kontrak ke berapa (urutan perpanjangan). | — |
| `start_date` | `date` | Tanggal mulai berlakunya record. | — |
| `end_date` | `date` | Tanggal berakhir berlakunya record. | — |
| `month_duration` | `nvarchar(255)` | Durasi kontrak dalam bulan. | — |
| `status_contract` | `nvarchar(255)` | Status kontrak karyawan. | — |
| `salary` | `decimal(15,2)` | Gaji · default `'0'` | — |
| `allowance` | `decimal(15,2)` |  · default `'0'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `type` | `nvarchar(255)` | Jenis/kategori record. · boleh NULL | — |
| `daily_salary` | `decimal(15,2)` | Daily gaji · boleh NULL | — |
| `pph21` | `decimal(15,2)` |  boleh NULL | — |
| `day_duration` | `nvarchar(255)` |  boleh NULL | — |
| `file_contract` | `nvarchar(255)` | Path/nama berkas CONTRACT di server. · boleh NULL | — |

#### `notifications_contracts`

- **Fungsi:** Notifikasi kontrak yang akan berakhir.
- **Jumlah baris (16 Nov 2026):** 16.339
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `contract_id` → `employees_contract.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `contract_id` | `nvarchar(255)` | **FK** → `employees_contract.id` · Contract ID | `0193859f-11c8-461e-a0aa-5e3f9c4af457`, `0257cb96-bba3-43d1-a336-2a63d03c8b8b`, `050b6f01-3a6b-499d-8f14-1170325754e3` |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-02323`, `C-02327`, `C-02338` |
| `employee_name` | `nvarchar(255)` | Nama karyawan pada tabel payroll. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `contract_end_date` | `date` | Contract selesai tanggal | `2026-06-07`, `2026-06-08`, `2026-06-09` |
| `days_remaining` | `int` | Sisa hari sebelum kontrak berakhir. | `3`, `4`, `5` |
| `type` | `nvarchar(255)` | Jenis/kategori record. · default `'contract_expiring'` | `contract_expiring` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'unread'` | `read` |
| `notified_at` | `datetime` | Notified pada · default `getdate()` | `2026-06-04T11:02:53.270000`, `2026-06-04T11:02:53.497000`, `2026-06-04T11:02:53.503000` |
| `read_at` | `datetime` | Read pada · boleh NULL | `2026-06-05T14:17:10.120000`, `2026-06-04T11:08:12.530000`, `2026-06-04T16:01:49.313000` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-04T11:02:53.270000`, `2026-06-04T11:02:53.497000`, `2026-06-04T11:02:53.503000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-05T14:17:10.120000`, `2026-06-04T11:08:12.530000`, `2026-06-04T16:01:49.313000` |

#### `org_chart_settings`

- **Fungsi:** Pengaturan bagan organisasi per departemen (level, parent, label).
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `id_dept` | `nvarchar(50)` | ID departemen, join ke `DEPT.ID_DEPT` (int). · boleh NULL | — |
| `level` | `nvarchar(20)` | Level pada hirarki. | — |
| `parent_id` | `bigint` | ID induk (hirarki menu/departemen). · boleh NULL | — |
| `sort_order` | `int` | Urutan tampil. · default `'0'` | — |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `label` | `nvarchar(150)` | Label tampilan. · boleh NULL | — |

#### `parent_dept`

- **Fungsi:** Master induk/group departemen (28 baris).
- **Jumlah baris (16 Nov 2026):** 28
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `parent_dept_name` | `nvarchar(100)` | Nama departemen induk. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-26T09:29:46.897000`, `2026-05-26T09:29:46.953000`, `2026-05-26T09:29:46.967000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-05-26T09:29:46.897000`, `2026-05-26T09:29:46.953000`, `2026-05-26T09:29:46.967000` |

#### `sections`

- **Fungsi:** Master section/line produksi (65 baris) dengan rentang line_start..line_end.
- **Jumlah baris (16 Nov 2026):** 65
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `100`, `101`, `102` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `line_start` | `int` | Line awal dalam rentang section. | `67`, `43`, `1` |
| `line_end` | `int` | Line akhir dalam rentang section. | `78`, `12`, `24` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-19T15:35:34.967000`, `2026-05-19T15:35:34.970000`, `2026-05-19T15:35:34.977000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-05-19T15:35:34.967000`, `2026-05-19T15:35:34.970000`, `2026-05-19T15:35:34.977000` |
| `blank_line` | `int` | Penanda line kosong/tidak terpakai. · boleh NULL | — |

### D2. Absensi & Kehadiran — 14 tabel

Log mentah mesin absensi, rekap harian, shift, lokasi kerja, absensi wajah, QR.

#### `AUDIT`

- **Fungsi:** Rekap absensi harian hasil olahan lama (531.822 baris): JAM_PAGI/JAM_SIANG/JAM_MALAM, STATUS.
- **Jumlah baris (16 Nov 2026):** 531.822
- **Akses:** bisa dibaca
- **Kunci:** tidak ada primary key
- **Penting:** **DILARANG DIBACA** (R25). Tanpa PK; UNIQUE (NPK, TANGGAL). Digantikan metode roster (R24) - jangan dijadikan sumber jam masuk/keluar dan jangan dipakai sebagai pembanding.
- **Unik:** `NPK,TANGGAL`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `NPK` | `varchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00035`, `C-00036`, `C-00037` |
| `TANGGAL` | `varchar(255)` | Tanggal kejadian/periode (date). | `2026-06-01`, `2026-06-02`, `2026-06-03` |
| `SUBDIVISI` | `varchar(255)` |  boleh NULL | `LINE 08`, `LINE 01`, `LINE 09` |
| `JAM_PAGI` | `varchar(255)` |  boleh NULL · default `'-'` | `07:47`, `07:50`, `07:46` |
| `JAM_SIANG` | `varchar(255)` |  boleh NULL · default `'-'` | `17:04`, `17:08`, `17:10` |
| `JAM_MALAM` | `varchar(255)` |  boleh NULL · default `'-'` | — |
| `STATUS` | `varchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL · default `'MSK'` | `LBR` |
| `VOID` | `varchar(255)` |  boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-09T14:02:50.513000`, `2026-10-09T14:02:55.863000`, `2026-10-09T14:03:01.930000` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-28T14:31:06.547000`, `2026-07-28T14:31:06.627000`, `2026-07-28T14:31:06.757000` |
| `NAMA_KARYAWAN` | `varchar(255)` | Nama lengkap karyawan. Data pribadi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `KODE_BAGIAN` | `varchar(255)` |  boleh NULL | `a1` |
| `DEPT_GROUP` | `varchar(255)` | Group departemen (mis. 'sewing'). · boleh NULL | `sewing` |

#### `att_log`

- **Fungsi:** Log MENTAH mesin absensi fingerprint (1.525.216 baris).
- **Jumlah baris (16 Nov 2026):** 1.525.216
- **Akses:** bisa dibaca
- **Kunci:** `sn`, `scan_date`, `pin`
- **Penting:** pin = BIODATA.BARCODE, BUKAN NPK. PK gabungan (sn, scan_date, pin). Satu hari bisa banyak baris per karyawan → jangan COUNT(*) sebagai jumlah hadir.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `sn` | `varchar(30)` | **PK** · Serial number mesin absensi. | `(disamarkan)` |
| `scan_date` | `datetime` | **PK** · Waktu scan di mesin absensi (datetime, resolusi detik). | `2026-01-05T12:55:13`, `2026-01-05T12:56:15`, `2026-01-05T12:57:03` |
| `pin` | `varchar(32)` | **PK** · Nomor PIN mesin absensi = `BIODATA.BARCODE` (bukan NPK). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `verifymode` | `int` | Metode verifikasi mesin (1/2). Pemetaan resmi belum terverifikasi. | `1`, `2` |
| `inoutmode` | `int` | Mode tap mesin absensi (0-10). Pemetaan resmi belum terverifikasi; lihat Kamus Nilai. | `1`, `3` |
| `reserved` | `int` | Kolom cadangan mesin absensi (selalu 0). | `0` |
| `work_code` | `int` | Kode kerja mesin absensi (0 = normal). | `0` |
| `att_id` | `varchar(50)` | ID unik record absensi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |

#### `att_log_audit`

- **Fungsi:** Audit/perubahan data att_log.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `sn`, `scan_date`, `pin`
- **Penting:** **DILARANG DIBACA** (R25): jangan dipakai untuk laporan apa pun, termasuk verifikasi/pembanding. Aksesnya juga dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `sn` | `varchar(30)` | **PK** · Serial number mesin absensi. | — |
| `scan_date` | `datetime` | **PK** · Waktu scan di mesin absensi (datetime, resolusi detik). | — |
| `pin` | `varchar(32)` | **PK** · Nomor PIN mesin absensi = `BIODATA.BARCODE` (bukan NPK). | — |
| `verifymode` | `int` | Metode verifikasi mesin (1/2). Pemetaan resmi belum terverifikasi. | — |
| `inoutmode` | `int` | Mode tap mesin absensi (0-10). Pemetaan resmi belum terverifikasi; lihat Kamus Nilai. | — |
| `reserved` | `int` | Kolom cadangan mesin absensi (selalu 0). | — |
| `work_code` | `int` | Kode kerja mesin absensi (0 = normal). | — |
| `att_id` | `varchar(50)` | ID unik record absensi. | — |

#### `attendance_work_locations`

- **Fungsi:** Master lokasi kerja dan polygon GPS untuk validasi absensi.
- **Jumlah baris (16 Nov 2026):** 3
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `6`, `7`, `8` |
| `name` | `nvarchar(100)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `description` | `nvarchar(max)` | Deskripsi. · boleh NULL | `` |
| `polygon_geojson` | `nvarchar(max)` | Polygon area kerja dalam format GeoJSON. Urutan koordinat GeoJSON: [bujur, lintang]. | `{"type":"Feature","properties":{},"ge...`, `{"type":"Feature","properties":{},"ge...`, `{"type":"Feature","properties":{},"ge...` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-05T16:01:58.857000`, `2026-11-11T08:00:09.307000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-05T08:31:26.490000`, `2026-11-11T07:40:14.843000`, `2026-11-11T08:00:09.307000` |

#### `break_masters`

- **Fungsi:** Master jam istirahat.
- **Jumlah baris (16 Nov 2026):** 3
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `sesi` | `nvarchar(255)` | Nama sesi istirahat (mis. 'Break 1'). | `Break 1`, `Break 2`, `Break 3` |
| `time_start` | `time(7)` | Time mulai | `11:15:00`, `11:45:00`, `12:15:00` |
| `time_end` | `time(7)` | Time selesai | `12:15:00`, `12:45:00`, `13:15:00` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-26T16:41:46.007000`, `2026-06-26T16:42:16.497000`, `2026-06-26T16:42:51.817000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-26T16:42:16.497000`, `2026-06-26T16:42:34.990000`, `2026-06-26T16:42:51.817000` |

#### `canteen_break_windows`

- **Fungsi:** Jendela waktu istirahat kantin.
- **Jumlah baris (16 Nov 2026):** 3
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `time_start` | `time(7)` | Time mulai | `09:00:00`, `12:00:00`, `16:00:00` |
| `time_end` | `time(7)` | Time selesai | `09:30:00`, `13:30:00`, `16:30:00` |
| `sort_order` | `int` | Urutan tampil. · default `'0'` | `1`, `2`, `3` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-09T11:49:18.690000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-09T11:49:18.690000` |

#### `dept_breaktimes`

- **Fungsi:** Jam istirahat per departemen.
- **Jumlah baris (16 Nov 2026):** 286
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `id_break` → `break_masters.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `id_dept` | `nvarchar(255)` | ID departemen, join ke `DEPT.ID_DEPT` (int). | `1`, `101`, `107` |
| `id_break` | `bigint` | **FK** → `break_masters.id` · ID istirahat (penamaan lain dari break_id). | `2`, `3`, `1` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `face_attendance_logs`

- **Fungsi:** Log absensi berbasis wajah + GPS (lat/long, inside_polygon, confidence).
- **Jumlah baris (16 Nov 2026):** 1.111
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Berisi face_image dan IP: data sensitif, jangan diekspos.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `att_id` | `nvarchar(64)` | ID unik record absensi. · boleh NULL | `1793613728_FACE_21C7C3`, `1793614031_FACE_1550F3`, `1793614425_FACE_E1CB70` |
| `npk` | `nvarchar(30)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00827`, `C-02496`, `C-00826` |
| `pin` | `nvarchar(50)` | Nomor PIN mesin absensi = `BIODATA.BARCODE` (bukan NPK). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `action` | `nvarchar(50)` | Aksi yang dilakukan (create/update/delete/login). | `Clock In (Masuk)`, `Clock Out (Pulang)` |
| `scan_date` | `datetime` | Waktu scan di mesin absensi (datetime, resolusi detik). | `2026-11-09T07:47:32`, `2026-11-09T07:47:33`, `2026-11-09T07:49:36` |
| `latitude` | `decimal(10,7)` | Koordinat lintang GPS. · boleh NULL | `-7.6289809`, `-7.6290109`, `-7.6290254` |
| `longitude` | `decimal(10,7)` | Koordinat bujur GPS. · boleh NULL | `110.8277755`, `110.8282412`, `107.7481341` |
| `accuracy` | `float(53)` |  boleh NULL | `71.0`, `13.0`, `16.0` |
| `inside_polygon` | `bit` | Penanda lokasi berada di dalam polygon area kerja (1/0). · default `'0'` | `True` |
| `work_location_id` | `bigint` | ID lokasi kerja, join ke `attendance_work_locations.id`. · boleh NULL | `7`, `3`, `6` |
| `face_match` | `bit` |  · default `'0'` | `True` |
| `confidence` | `decimal(5,4)` | Tingkat keyakinan hasil pencocokan wajah. · boleh NULL | `1.0000` |
| `liveness_passed` | `bit` | Penanda lolos uji liveness. · default `'0'` | `True` |
| `status` | `nvarchar(10)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). | `SUCCESS` |
| `rejection_reason` | `nvarchar(255)` | Rejection alasan · boleh NULL | — |
| `ip_address` | `nvarchar(45)` | Alamat IP. · boleh NULL | `(disamarkan)` |
| `user_agent` | `nvarchar(max)` | User agent browser/perangkat. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-09T07:47:32`, `2026-11-09T07:47:33`, `2026-11-09T07:49:36` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-09T07:47:32`, `2026-11-09T07:47:33`, `2026-11-09T07:49:36` |
| `face_image` | `text` | Gambar wajah hasil capture. Sensitif. · boleh NULL | `/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAYEB...`, `/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX...`, `/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX...` |
| `is_suspicious` | `bit` | Penanda suspicious · boleh NULL | `False` |
| `suspicious_reason` | `nvarchar(100)` | Suspicious alasan · boleh NULL | `tanpa_nonce` |
| `nonce_verified` | `bit` |  boleh NULL | `False`, `True` |

#### `face_attendance_settings`

- **Fungsi:** Pengaturan jendela waktu presensi (menit sebelum/sesudah, Sabtu/Minggu/libur).
- **Jumlah baris (16 Nov 2026):** 1
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1` |
| `clock_in_before_minutes` | `int` | Menit sebelum jam shift saat absen masuk mulai diizinkan. · default `'15'` | `15` |
| `clock_out_after_minutes` | `int` | Menit setelah jam shift saat absen keluar masih diizinkan. · default `'15'` | `240` |
| `is_saturday_enabled` | `bit` | Absensi diizinkan pada Sabtu (1/0). · default `'0'` | `False` |
| `is_sunday_enabled` | `bit` | Absensi diizinkan pada Minggu (1/0). · default `'0'` | `False` |
| `is_holiday_enabled` | `bit` | Absensi diizinkan pada hari libur (1/0). · default `'0'` | `False` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-31T15:09:00.677000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-06T08:21:40.253000` |
| `clock_in_allowance_minutes` | `int` |  · default `'0'` | `5` |
| `clock_out_allowance_minutes` | `int` | Toleransi (menit) setelah jam shift untuk absen keluar. | `5` |
| `is_npk_required_enabled` | `bit` | Penanda NPK required enabled · default `'1'` | `True` |

#### `face_recognition_settings`

- **Fungsi:** Konfigurasi engine face recognition (threshold, liveness, dsb).
- **Jumlah baris (16 Nov 2026):** 1
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1` |
| `is_enabled` | `bit` | Penanda fitur aktif (1/0). · default `'1'` | `True` |
| `match_threshold` | `decimal(4,3)` |  · default `'0.5'` | `0.500` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-29T14:29:23.833000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-16T12:50:07.147000` |
| `is_qr_enabled` | `bit` | Penanda qr enabled · default `'1'` | `True` |
| `is_qr_login_enabled` | `bit` | Penanda qr login enabled · default `'1'` | `True` |
| `is_qr_payroll_enabled` | `bit` | Penanda qr payroll enabled · default `'1'` | `True` |
| `is_qr_thr_enabled` | `bit` | Penanda qr thr enabled · default `'1'` | `False` |
| `is_qr_cuti_enabled` | `bit` | Penanda qr cuti enabled · default `'1'` | `True` |
| `is_liveness_enabled` | `bit` | Penanda liveness enabled · default `'1'` | `False` |
| `liveness_presensi_enabled` | `bit` |  · default `'1'` | `True` |
| `liveness_login_enabled` | `bit` |  · default `'1'` | `True` |
| `liveness_slip_enabled` | `bit` |  · default `'1'` | `True` |
| `liveness_cuti_enabled` | `bit` |  · default `'1'` | `True` |
| `liveness_registrasi_enabled` | `bit` |  · default `'1'` | `True` |
| `is_npk_required_enabled` | `bit` | Penanda NPK required enabled · default `'1'` | `True` |
| `is_random_challenge_enabled` | `bit` | Penanda random challenge enabled · default `'0'` | `True` |
| `random_challenge_presensi_enabled` | `bit` |  · default `'1'` | `True` |
| `random_challenge_login_enabled` | `bit` |  · default `'1'` | `True` |
| `random_challenge_slip_enabled` | `bit` |  · default `'1'` | `False` |
| `random_challenge_cuti_enabled` | `bit` |  · default `'1'` | `False` |
| `random_challenge_registrasi_enabled` | `bit` |  · default `'1'` | `True` |
| `liveness_canteen_enabled` | `bit` | Liveness kantin enabled | `False` |
| `random_challenge_canteen_enabled` | `bit` | Random challenge kantin enabled | `False` |
| `canteen_verification_mode` | `nvarchar(30)` | Kantin verification mode · default `'liveness'` | `none` |
| `hris_v2_verification_mode` | `nvarchar(30)` |  boleh NULL | `none` |

#### `holidays`

- **Fungsi:** Master hari libur untuk perhitungan absensi.
- **Jumlah baris (16 Nov 2026):** 25
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `holiday_date` | `date` | Holiday tanggal | `2026-01-01`, `2026-01-16`, `2026-02-16` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `is_national` | `bit` | Penanda national · default `'1'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-04-01T10:36:25.157000`, `2026-04-01T10:36:25.187000`, `2026-04-01T10:36:25.163000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-04-01T10:36:25.157000`, `2026-04-01T10:36:25.187000`, `2026-04-01T10:36:25.163000` |

#### `qr_authorized_devices`

- **Fungsi:** Perangkat yang diizinkan memakai QR login.
- **Jumlah baris (16 Nov 2026):** 26
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `user_id` → `users.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `11`, `12`, `13` |
| `user_id` | `bigint` | **FK** → `users.id` · ID user aplikasi, join ke `users.id`. | `29`, `11`, `12` |
| `device_uuid` | `nvarchar(64)` | UUID perangkat. | `00ea3139-0009-4962-9226-5e629ea71dd5`, `0bd25620-82fa-4aea-bcb7-56425bc50caf`, `2a5397e5-2aae-4c06-9d23-b5afe4fe2b81` |
| `device_name` | `nvarchar(255)` | Nama perangkat. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `device_type` | `nvarchar(255)` | Perangkat jenis · default `'unknown'` | `desktop`, `mobile` |
| `platform` | `nvarchar(255)` |  boleh NULL | `Win32`, `Linux armv81` |
| `browser` | `nvarchar(255)` |  boleh NULL | `Chrome`, `Edge`, `Firefox` |
| `assigned_by` | `int` | Assigned oleh · boleh NULL | `1`, `4` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `last_used_at` | `datetime` | Last terpakai pada · boleh NULL | `2026-07-16T14:33:10.013000`, `2026-07-24T07:55:13.420000`, `2026-07-27T14:55:56.827000` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-09T16:33:36.523000`, `2026-07-10T07:30:15.953000`, `2026-07-10T07:30:18.760000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-09T16:33:36.523000`, `2026-07-15T10:38:10.250000`, `2026-07-16T08:11:12.523000` |

#### `qr_scan_logs`

- **Fungsi:** Log pemindaian QR untuk login/absensi.
- **Jumlah baris (16 Nov 2026):** 2.143
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `user_id` → `users.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `100`, `101` |
| `user_id` | `bigint` | **FK** → `users.id` · ID user aplikasi, join ke `users.id`. · boleh NULL | `18`, `13`, `20` |
| `npk_scanned` | `nvarchar(20)` | NPK yang dipindai QR-nya. | `C-01176`, `C-00800`, `C-00819` |
| `device_uuid` | `nvarchar(64)` | UUID perangkat. · boleh NULL | `c1ab5671-922e-4839-afb2-173f54023202`, `0bd25620-82fa-4aea-bcb7-56425bc50caf`, `e870b0fd-cd23-4a5c-8a29-617f7c8ce523` |
| `device_name` | `nvarchar(255)` | Nama perangkat. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `device_type` | `nvarchar(255)` | Perangkat jenis · default `'unknown'` | `desktop`, `mobile` |
| `platform` | `nvarchar(255)` |  boleh NULL | `Win32`, `Linux armv81`, `iPhone` |
| `browser` | `nvarchar(255)` |  boleh NULL | `Chrome`, `Edge`, `Safari` |
| `ip_address` | `nvarchar(45)` | Alamat IP. | `(disamarkan)` |
| `user_agent` | `nvarchar(max)` | User agent browser/perangkat. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). | `success`, `failed_user_not_found`, `failed_device_not_registered` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-09T14:48:04.917000`, `2026-07-09T14:49:18.723000`, `2026-07-09T14:53:57.207000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-09T14:48:04.917000`, `2026-07-09T14:49:18.723000`, `2026-07-09T14:53:57.207000` |

#### `shifts`

- **Fungsi:** Master shift kerja (jam mulai/selesai, gender, rentang tanggal berlaku).
- **Jumlah baris (16 Nov 2026):** 23
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** work_end lebih kecil dari work_start = shift malam (lintas hari).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `work_start` | `time(7)` | Jam mulai shift (time). | `18:30:00`, `07:00:00`, `00:00:00` |
| `work_end` | `time(7)` | Jam selesai shift (time). Bisa lebih kecil dari work_start untuk shift malam. | `03:30:00`, `15:00:00`, `16:00:00` |
| `gender` | `nvarchar(255)` | Jenis kelamin ('L'/'P'/'ALL'). · default `'ALL'` | `ALL`, `L` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-04-20T13:36:57.103000`, `2026-04-20T13:44:08.450000`, `2026-04-20T13:44:26.037000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-25T15:39:04.810000`, `2026-06-25T15:39:27.897000`, `2026-07-03T14:59:47` |
| `start_date` | `datetime` | Tanggal mulai berlakunya record. · boleh NULL | `2026-01-01T00:00:00`, `2026-06-01T00:00:00`, `2026-07-01T00:00:00` |
| `end_date` | `datetime` | Tanggal berakhir berlakunya record. · boleh NULL | `2030-12-31T00:00:00`, `2026-06-30T00:00:00`, `2026-07-10T00:00:00` |
| `is_holiday` | `nvarchar(255)` | Penanda shift khusus hari libur (1/0). · boleh NULL | `0`, `1` |

### D3. Lembur, Cuti, Izin & Keterlambatan — 10 tabel

Lembur operasional dan lembur payroll, cuti, izin, sakit, keterlambatan.

#### `employee_lates`

- **Fungsi:** Catatan keterlambatan karyawan (tanggal, jam datang, alasan).
- **Jumlah baris (16 Nov 2026):** 185
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-01035`, `C-03003`, `C-04003` |
| `date` | `date` | Tanggal kejadian. | `2026-10-22`, `2026-07-13`, `2026-08-19` |
| `arrival_time` | `time(7)` | Jam kedatangan (time). | `09:00:00`, `08:10:00`, `08:20:00` |
| `reason` | `nvarchar(max)` | Alasan. · boleh NULL | `BAN BOCOR`, `MOTOR MOGOK`, `BAN MOTOR BOCOR` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-03T16:57:31.447000`, `2026-07-03T16:57:31.440000`, `2026-07-03T16:57:31.453000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-03T16:57:31.447000`, `2026-07-03T16:57:31.440000`, `2026-07-03T16:57:31.453000` |

#### `ijin_meninggalkan_pekerjaans`

- **Fungsi:** Izin meninggalkan pekerjaan saat jam kerja (jam keluar/kembali).
- **Jumlah baris (16 Nov 2026):** 551
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `100`, `101` |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00822`, `C-00165`, `C-00650` |
| `tanggal` | `date` | Tanggal kejadian/periode (date). | `2026-07-15`, `2026-07-20`, `2026-08-03` |
| `jam_keluar` | `time(7)` | Jam keluar (time). | `11:15:00`, `11:45:00`, `12:00:00` |
| `rencana_kembali` | `time(7)` |  boleh NULL | `12:15:00`, `12:45:00`, `12:00:00` |
| `jam_kembali` | `time(7)` |  boleh NULL | `17:00:00`, `12:15:00`, `12:20:00` |
| `reason` | `nvarchar(max)` | Alasan. · boleh NULL | `KE BANK`, `KE RUMAH SAKIT`, `MENGURUS ATM TERBLOKIR` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-26T09:38:37.217000`, `2026-06-26T10:16:12.157000`, `2026-06-26T10:17:52.210000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-26T10:16:12.157000`, `2026-06-26T10:16:37.160000`, `2026-06-26T10:19:50.067000` |
| `id_break` | `bigint` | ID istirahat (penamaan lain dari break_id). · boleh NULL | `1`, `2`, `3` |
| `is_deduction` | `bit` | Penanda memotong jam kerja/upah (1/0). · default `'0'` | `True`, `False` |

#### `late_compensations`

- **Fungsi:** Kompensasi/penggantian akibat keterlambatan.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `date` | `date` | Tanggal kejadian. | — |
| `reason` | `nvarchar(max)` | Alasan. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `leave_balances`

- **Fungsi:** Saldo cuti per karyawan per periode (39.731 baris).
- **Jumlah baris (16 Nov 2026):** 39.731
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `leave_type_id` → `leave_types.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `27092`, `27093`, `27094` |
| `NPK` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00001`, `C-00003`, `C-00005` |
| `leave_type_id` | `bigint` | **FK** → `leave_types.id` · ID jenis cuti, join ke `leave_types.id`. | `12`, `13`, `14` |
| `year` | `int` | Tahun. | `2026` |
| `remained_days` | `int` | Sisa hari cuti. | `2`, `1`, `3` |
| `used_days` | `int` | Terpakai days | `0`, `1`, `2` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-07T09:20:35.590000`, `2026-10-07T09:20:35.667000`, `2026-10-07T09:20:35.677000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-07T10:20:39.917000`, `2026-10-07T09:20:35.693000`, `2026-10-07T09:20:35.720000` |
| `negative_leave` | `varchar(255)` | Negative cuti · boleh NULL | — |

#### `leave_reasons`

- **Fungsi:** Master alasan cuti/izin.
- **Jumlah baris (16 Nov 2026):** 55
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `int` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `leave_type_id` | `int` | ID jenis cuti, join ke `leave_types.id`. | `14`, `5`, `10` |
| `reason` | `varchar(255)` | Alasan. | `Keperluan keluarga`, `Masa berduka`, `Mengurus pemakaman` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · default `getdate()` | `2026-10-08T15:40:38.587000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · default `getdate()` | `2026-10-08T15:40:38.587000` |

#### `leave_requests`

- **Fungsi:** Pengajuan cuti/izin karyawan (460 approved, 15 pending).
- **Jumlah baris (16 Nov 2026):** 475
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** status huruf kecil ('approved'/'pending'), beda dengan kode huruf besar di tabel lain.
- **Foreign key:** `leave_type_id` → `leave_types.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `418`, `419`, `420` |
| `NPK` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00087`, `C-00862`, `C-00054` |
| `leave_type_id` | `bigint` | **FK** → `leave_types.id` · ID jenis cuti, join ke `leave_types.id`. | `6`, `20`, `5` |
| `reason` | `nvarchar(max)` | Alasan. | `CUTI MENIKAH`, `CUTI TAHUNAN`, `CUTI ORANG TUA MENINGGAL` |
| `start_date` | `date` | Tanggal mulai berlakunya record. | `2026-06-08`, `2026-06-02`, `2026-06-15` |
| `end_date` | `date` | Tanggal berakhir berlakunya record. | `2026-06-10`, `2026-07-29`, `2026-04-01` |
| `total_days` | `int` | Total hari pengajuan. | `2`, `1`, `3` |
| `approval_id` | `nvarchar(255)` | Approval ID | `C-00011` |
| `approval_level` | `int` | Tingkat approval saat ini (multi-level). | `1` |
| `approval_progress` | `int` | Approval progres | `1` |
| `approval_date` | `date` | Approval tanggal · boleh NULL | `2026-06-08`, `2026-06-02`, `2026-06-15` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'pending'` | `approved` |
| `token` | `nvarchar(255)` | Token acak untuk approval/reset. RAHASIA: jangan ditampilkan. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `void` | `nvarchar(255)` |  · default `'false'` | `0`, `false` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-07T10:46:17.073000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-07T10:46:17.073000` |
| `comment` | `nvarchar(255)` |  boleh NULL | — |
| `attach_files` | `nvarchar(max)` |  boleh NULL | — |
| `custom_dates` | `nvarchar(max)` |  boleh NULL | — |

#### `leave_types`

- **Fungsi:** Master jenis cuti (kuota, dsb).
- **Jumlah baris (16 Nov 2026):** 14
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `11`, `12` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `code` | `nvarchar(255)` | Kode ringkas. | `anak_meninggal`, `istri_keguguran`, `istri_melahirkan` |
| `default_days` | `nvarchar(255)` | Kuota hari bawaan untuk jenis cuti ini. | `2`, `1`, `0` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-07T09:18:30`, `2026-08-13T08:42:51.433000`, `2026-08-13T08:42:51.447000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-07T09:18:30`, `2026-08-13T08:42:51.433000`, `2026-08-13T08:42:51.447000` |
| `gender_type` | `varchar(255)` | Jenis kelamin jenis · boleh NULL | `A`, `L`, `P` |
| `count_calendar_days` | `bit` |  · default `'0'` | `False`, `True` |
| `max_requests` | `int` | Maksimum requests · boleh NULL | `2`, `1`, `3` |

#### `overtimes`

- **Fungsi:** Lembur operasional + penanda kehadiran harian (898.019 baris, Feb-Des 2026).
- **Jumlah baris (16 Nov 2026):** 898.019
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** JUMLAH_JAM_LEMBUR bertipe teks: angka = jam lembur, kode huruf = status tidak masuk. 47.587 baris bertanggal setelah hari ini (data terisi ke depan) → wajib filter tanggal. DAY campur Inggris dan Indonesia.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `193203`, `193204`, `193205` |
| `NPK` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00950`, `C-02439`, `C-02501` |
| `NAMA_KARYAWAN` | `nvarchar(255)` | Nama lengkap karyawan. Data pribadi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `BAGIAN` | `nvarchar(255)` | Bagian/line kerja (teks, sering tidak konsisten dengan master DEPT). | `LINE 02` |
| `OVERTIME_DATE` | `date` | Tanggal lembur/kehadiran. | `2026-02-01`, `2026-02-02`, `2026-02-03` |
| `JUMLAH_JAM_LEMBUR` | `nvarchar(255)` | Jam lembur ATAU kode status, bertipe teks. Angka = jam; huruf = tidak masuk. Lihat Kamus Nilai. · boleh NULL | `1` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-03-02T06:56:48.057000`, `2026-03-02T06:56:48.277000`, `2026-03-02T06:56:48` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-03-02T06:56:48.057000`, `2026-03-02T06:56:48.277000`, `2026-03-02T06:56:48` |
| `DAY` | `nvarchar(255)` | Nama hari. `overtimes` campur Inggris+Indonesia; `overtimes_payroll` Inggris. · boleh NULL | `Minggu`, `Rabu`, `Selasa` |
| `DEPT_GROUP` | `nvarchar(255)` | Group departemen (mis. 'sewing'). · boleh NULL | `sewing` |
| `is_request` | `nvarchar(255)` | Penanda baris berasal dari pengajuan lembur. · boleh NULL | — |

#### `overtimes_payroll`

- **Fungsi:** Lembur yang dipakai feed payroll (531.759 baris, 1 Jun-31 Okt 2026).
- **Jumlah baris (16 Nov 2026):** 531.759
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Beda tabel dengan overtimes. DAY berbahasa Inggris. Jangan digabung tanpa alasan.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `100`, `101` |
| `NPK` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00037`, `C-00048`, `C-00050` |
| `NAMA_KARYAWAN` | `nvarchar(255)` | Nama lengkap karyawan. Data pribadi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `BAGIAN` | `nvarchar(255)` | Bagian/line kerja (teks, sering tidak konsisten dengan master DEPT). | `LINE 01` |
| `OVERTIME_DATE` | `date` | Tanggal lembur/kehadiran. | `2026-06-01`, `2026-06-02`, `2026-06-03` |
| `JUMLAH_JAM_LEMBUR` | `nvarchar(255)` | Jam lembur ATAU kode status, bertipe teks. Angka = jam; huruf = tidak masuk. Lihat Kamus Nilai. · boleh NULL | `1`, `CT`, `P1` |
| `DAY` | `nvarchar(255)` | Nama hari. `overtimes` campur Inggris+Indonesia; `overtimes_payroll` Inggris. | `Monday`, `Tuesday`, `Friday` |
| `DEPT_GROUP` | `nvarchar(255)` | Group departemen (mis. 'sewing'). | `sewing` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-23T13:23:38.287000`, `2026-06-23T13:23:38.297000`, `2026-06-23T13:23:38.303000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-23T13:23:38.287000`, `2026-06-23T13:23:38.297000`, `2026-06-23T13:23:38.303000` |

#### `overtimes_payroll_audit`

- **Fungsi:** Audit perubahan overtimes_payroll.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** **DILARANG DIBACA** (R25) + akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `NPK` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `NAMA_KARYAWAN` | `nvarchar(255)` | Nama lengkap karyawan. Data pribadi. | — |
| `BAGIAN` | `nvarchar(255)` | Bagian/line kerja (teks, sering tidak konsisten dengan master DEPT). | — |
| `OVERTIME_DATE` | `date` | Tanggal lembur/kehadiran. | — |
| `JUMLAH_JAM_LEMBUR` | `nvarchar(255)` | Jam lembur ATAU kode status, bertipe teks. Angka = jam; huruf = tidak masuk. Lihat Kamus Nilai. · boleh NULL | — |
| `DAY` | `nvarchar(255)` | Nama hari. `overtimes` campur Inggris+Indonesia; `overtimes_payroll` Inggris. | — |
| `DEPT_GROUP` | `nvarchar(255)` | Group departemen (mis. 'sewing'). | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

### D4. Payroll, Insentif & Kompensasi — 26 tabel

Periode payroll, komponen gaji, slip, insentif, kompensasi, THR.

#### `bpjs_exceptions`

- **Fungsi:** Pengecualian potongan BPJS.
- **Jumlah baris (16 Nov 2026):** 277
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `100`, `101` |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-02198`, `C-03887`, `C-04016` |
| `component` | `nvarchar(100)` | Nama komponen (gaji/BPJS/biaya). | `bpjs_kesehatan`, `bpjs_ketenagakerjaan` |
| `percentage` | `decimal(8,2)` | Persentase (0-100). · default `'0'` | `0.00`, `1.00`, `2.00` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-29T09:23:40.283000`, `2026-06-29T09:24:06.757000`, `2026-06-29T09:34:44.287000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-25T14:56:25.373000`, `2026-06-29T09:23:40.283000`, `2026-06-29T09:24:06.757000` |

#### `compensation_approve`

- **Fungsi:** Approval proses kompensasi.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `run_id` | `bigint` | ID proses/run induk, join ke tabel run terkait. | — |
| `approval` | `nvarchar(max)` | Status/aksi approval. | — |
| `progress` | `nvarchar(max)` | Persentase progres proses (0-100). · boleh NULL | — |
| `approved_at` | `nvarchar(max)` | Waktu disetujui. · boleh NULL | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'pending'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `compensation_details`

- **Fungsi:** Rincian kompensasi per karyawan.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `npk` | `nvarchar(50)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `id_dept` | `nvarchar(255)` | ID departemen, join ke `DEPT.ID_DEPT` (int). · boleh NULL | — |
| `contract_id` | `bigint` | Contract ID | — |
| `cutoff_date` | `date` | Tanggal batas perhitungan. | — |
| `amount` | `decimal(15)` | Nominal uang. · default `'0'` | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). | — |
| `is_active` | `nvarchar(255)` | Penanda aktif (1/0). | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `month_duration` | `varchar(255)` | Durasi kontrak dalam bulan. · boleh NULL | — |
| `day_duration` | `varchar(255)` |  boleh NULL | — |
| `end_date` | `date` | Tanggal berakhir berlakunya record. · boleh NULL | — |

#### `compensations`

- **Fungsi:** Proses kompensasi per cutoff date (file pdf/csv/excel).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `cutoff_date` | `date` | Tanggal batas perhitungan. | — |
| `total_employee` | `int` | Jumlah karyawan dalam satu proses. · boleh NULL | — |
| `total_amount` | `decimal(15,2)` | Total nominal (mata uang lokal). · boleh NULL · default `'0'` | — |
| `file_pdf` | `nvarchar(max)` | Nama/path file PDF. · boleh NULL | — |
| `file_csv` | `nvarchar(max)` | Nama/path file CSV. · boleh NULL | — |
| `progress` | `int` | Persentase progres proses (0-100). · boleh NULL | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `is_closed` | `int` | Penanda periode/proses sudah ditutup (1) atau masih berjalan (0). · boleh NULL | — |
| `file_excel` | `nvarchar(max)` | Nama/path file Excel. · boleh NULL | — |

#### `dept_insentif_role`

- **Fungsi:** Pemetaan departemen ke role insentif.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `id_dept` | `bigint` | ID departemen, join ke `DEPT.ID_DEPT` (int). | — |
| `role` | `nvarchar(255)` | Nama role. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `exchange_rates`

- **Fungsi:** Kurs valuta asing untuk perhitungan biaya.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `rate_date` | `date` | Rate tanggal | — |
| `currency_from` | `nvarchar(3)` |  · default `'USD'` | — |
| `currency_to` | `nvarchar(3)` |  · default `'IDR'` | — |
| `kurs_jual` | `decimal(15,2)` |  boleh NULL | — |
| `kurs_beli` | `decimal(15,2)` |  boleh NULL | — |
| `kurs_tengah` | `decimal(15,2)` |  boleh NULL | — |
| `source` | `nvarchar(255)` |  · default `'bi.go.id'` | — |
| `raw_response` | `nvarchar(max)` | Mentah response · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `insentif_approvals`

- **Fungsi:** Approval insentif per periode.
- **Jumlah baris (16 Nov 2026):** 23
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `period_id` → `payroll_periods.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `period_id` | `bigint` | **FK** → `payroll_periods.id` · ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `7`, `8`, `6` |
| `payroll_component` | `nvarchar(255)` | Nama komponen payroll. | `heat_insentif`, `pad_insentif`, `sewing_insentif` |
| `approval` | `nvarchar(max)` | Status/aksi approval. · boleh NULL | `["[\"C-00008\",\"C-00003\",\"C-00001\...` |
| `progress` | `nvarchar(max)` | Persentase progres proses (0-100). · boleh NULL | `[{"npk":"[\"C-00008\",\"C-00003\",\"C...`, `[{"npk":"[\"C-00008\",\"C-00003\",\"C...` |
| `approved_at` | `nvarchar(max)` | Waktu disetujui. · boleh NULL | `[["2026-07-02T07:07:07.385993Z","2026...`, `[["2026-07-02T07:07:13.583826Z","2026...`, `[["2026-07-02T07:07:21.930927Z","2026...` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'pending'` | `finish`, `pending` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-10T16:10:03.633000`, `2026-11-09T09:29:40.517000`, `2026-06-15T09:44:32.670000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-09T09:29:40.517000`, `2026-07-03T10:44:13.847000`, `2026-07-03T10:44:16.987000` |

#### `insentif_role_formulas`

- **Fungsi:** Formula insentif per role/departemen.
- **Jumlah baris (16 Nov 2026):** 49
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `role` | `nvarchar(50)` | Nama role. | `operator`, `supervisor`, `helper` |
| `dept` | `nvarchar(50)` | Nama departemen (teks bebas pada beberapa tabel, bukan master). | `cutting`, `qc`, `heat` |
| `formula` | `nvarchar(255)` | Rumus perhitungan. | `insentif * 0.75`, `totalDeptInsentif / jumlahOperator`, `insentif * 1` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True`, `False` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `insentif_thresholds`

- **Fungsi:** Ambang batas hari untuk perhitungan insentif.
- **Jumlah baris (16 Nov 2026):** 3
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `insentif_type` | `nvarchar(255)` | Insentif jenis | `sewing_insentif` |
| `days` | `int` | Jumlah hari. | `1`, `2`, `3` |
| `minimum` | `decimal(5,2)` | Nilai minimum/ambang batas. | `50.00`, `70.00`, `85.00` |
| `type` | `nvarchar(255)` | Jenis/kategori record. | `Percentage` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-04-23T09:02:29.980000`, `2026-04-23T09:09:24.490000`, `2026-04-23T09:09:36.780000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-04-23T09:02:29.980000`, `2026-04-23T09:09:36.780000`, `2026-06-11T20:10:52.990000` |

#### `payroll_adjusments`

- **Fungsi:** Penyesuaian/adjustment payroll.
- **Jumlah baris (16 Nov 2026):** 657
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Nama tabel salah eja: 'adjusments'.
- **Foreign key:** `period_id` → `payroll_periods.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00054`, `C-00079`, `C-00087` |
| `period_id` | `bigint` | **FK** → `payroll_periods.id` · ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `3` |
| `adjusment` | `decimal(15,2)` |  · default `'0'` | `-119048.00`, `-714285.00`, `714285.00` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-26T14:38:27.773000`, `2026-06-29T13:41:29.757000`, `2026-06-29T14:52:39.753000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-29T14:54:22.157000`, `2026-06-29T14:54:32.663000`, `2026-06-29T14:54:39.273000` |
| `keterangan` | `nvarchar(255)` | Keterangan bebas. · boleh NULL | `POTONGAN LEBIH BAYAR (1 MEI)`, `POTONGAN LEBIH BAYAR (1MEI)`, `POTONGAN HARI RAYA IDUL FITRI 2026 ( ...` |

#### `payroll_approve`

- **Fungsi:** Approval proses payroll (21 baris).
- **Jumlah baris (16 Nov 2026):** 21
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `payroll_run_id` | `bigint` | Payroll run ID | `1`, `10`, `11` |
| `approval` | `nvarchar(max)` | Status/aksi approval. | `["[\"C-00011\",\"C-00001\"]"]`, `["[\"C-00011\",\"C-00776\",\"C-00001\...` |
| `progress` | `nvarchar(max)` | Persentase progres proses (0-100). · boleh NULL | `[{"npk":"[\"C-00011\",\"C-00001\"]","...`, `[{"npk":"[\"C-00011\",\"C-00001\"]","...`, `[{"npk":"[\"C-00011\",\"C-00001\"]","...` |
| `approved_at` | `nvarchar(max)` | Waktu disetujui. · boleh NULL | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'pending'` | `pending`, `finish` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-06T11:01:09.847000`, `2026-07-06T11:32:45.007000`, `2026-07-06T13:06:00.270000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-06T11:01:09.847000`, `2026-07-06T11:32:45.007000`, `2026-07-06T13:06:00.270000` |

#### `payroll_components`

- **Fungsi:** Master komponen gaji (code, type, formula, is_taxable).
- **Jumlah baris (16 Nov 2026):** 25
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `11`, `12` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `code` | `nvarchar(255)` | Kode ringkas. | `absence_deduction`, `adjusment`, `allowance` |
| `type` | `nvarchar(255)` | Jenis/kategori record. | `earning`, `deduction` |
| `calculation_method` | `nvarchar(255)` | Calculation metode | `formula` |
| `value` | `decimal(15,2)` | Nilai konfigurasi/komponen. · boleh NULL | — |
| `formula` | `nvarchar(max)` | Rumus perhitungan. · boleh NULL | `pph_21`, `{ "100": 11.00, "95": 8.00, "90": 6.0...`, `((((is_contract?(basic_salary+allowan...` |
| `description` | `nvarchar(255)` | Deskripsi. · boleh NULL | `Adjusment Payroll`, `BPJS Kesehatan`, `BPJS Ketenagakerjaan` |
| `category` | `nvarchar(255)` | Kategori. · boleh NULL | `Insentif`, `Potongan`, `Tunjangan` |
| `priority` | `int` |  · default `'0'` | `90`, `80`, `100` |
| `is_taxable` | `bit` | Penanda taxable · default `'0'` | `False`, `True` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `1900-01-01T00:00:00`, `2026-03-05T10:27:34.397000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `1900-01-01T00:00:00`, `2026-03-05T10:27:34.397000`, `2026-03-05T12:57:19.103000` |
| `orders` | `varchar(255)` |  boleh NULL | `8`, `7`, `1` |

#### `payroll_exports`

- **Fungsi:** Riwayat ekspor file payroll (13 baris).
- **Jumlah baris (16 Nov 2026):** 13
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `11`, `12` |
| `run_id` | `int` | ID proses/run induk, join ke tabel run terkait. | `10`, `11`, `13` |
| `progress` | `int` | Persentase progres proses (0-100). · default `'0'` | `100` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'processing'` | `approved`, `finished` |
| `file_excel` | `nvarchar(255)` | Nama/path file Excel. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `file_pdf` | `nvarchar(255)` | Nama/path file PDF. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `file_bank_active` | `nvarchar(max)` | Path/nama berkas BANK ACTIVE di server. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `file_bank_resign` | `nvarchar(max)` | Path/nama berkas BANK RESIGN di server. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `file_peng` | `nvarchar(255)` | Path/nama berkas PENG di server. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-07T10:01:27.033000`, `2026-07-06T16:00:54.050000`, `2026-07-06T19:38:21.273000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-06T17:35:28.637000`, `2026-07-06T19:40:45.767000`, `2026-07-07T08:33:29.517000` |
| `file_bank_mangkir` | `nvarchar(max)` | Path/nama berkas BANK MANGKIR di server. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |

#### `payroll_masters`

- **Fungsi:** Data gaji pokok/tunjangan/PPh21 per NPK + rekening bank.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Mengandung data sensitif; akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `bank_name` | `nvarchar(255)` | Bank nama · boleh NULL | — |
| `bank_account` | `nvarchar(255)` | Nomor rekening bank. Sensitif. · boleh NULL | — |
| `salary` | `decimal(15,2)` | Gaji · boleh NULL · default `'0'` | — |
| `allowance` | `decimal(15,2)` |  boleh NULL · default `'0'` | — |
| `pph21` | `decimal(15,2)` |  boleh NULL · default `'0'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `type` | `varchar(255)` | Jenis/kategori record. · boleh NULL | — |

#### `payroll_periods`

- **Fungsi:** Master periode payroll (8 periode; November 2026 masih terbuka).
- **Jumlah baris (16 Nov 2026):** 6
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** is_closed=1 berarti periode sudah ditutup; is_closed=0 masih berjalan.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `3`, `4`, `5` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `start_date` | `date` | Tanggal mulai berlakunya record. | `2026-06-01`, `2026-07-01`, `2026-08-01` |
| `end_date` | `date` | Tanggal berakhir berlakunya record. | `2026-06-30`, `2026-07-31`, `2026-08-31` |
| `is_closed` | `bit` | Penanda periode/proses sudah ditutup (1) atau masih berjalan (0). · default `'0'` | `True`, `False` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-08T08:11:35.190000`, `2026-07-08T14:53:23.663000`, `2026-08-05T13:06:10.603000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-08T08:11:35.190000`, `2026-07-08T14:53:23.663000`, `2026-08-05T13:06:10.603000` |

#### `payroll_run_details`

- **Fungsi:** Rincian gaji per karyawan per run payroll.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Kolom karyawan = employee_npk (BUKAN NPK). Akses dibatasi (permission).
- **Foreign key:** `run_id` → `payroll_runs.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `run_id` | `bigint` | **FK** → `payroll_runs.id` · ID proses/run induk, join ke tabel run terkait. | — |
| `employee_npk` | `nvarchar(255)` | NPK karyawan pada tabel payroll (bukan `NPK`). | — |
| `employee_name` | `nvarchar(255)` | Nama karyawan pada tabel payroll. | — |
| `components` | `nvarchar(max)` | Rincian komponen gaji dalam format JSON. | — |
| `total_salary` | `decimal(18,2)` | Total gaji karyawan pada satu baris payroll. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `employee_dept` | `nvarchar(255)` | Departemen karyawan pada tabel payroll. · boleh NULL | — |
| `employee_staff` | `nvarchar(255)` | Penanda staff pada tabel payroll. · boleh NULL | — |
| `employee_expat` | `nvarchar(255)` | Penanda ekspatriat pada tabel payroll. · boleh NULL | — |

#### `payroll_run_details_audit`

- **Fungsi:** Audit perubahan rincian payroll.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** **DILARANG DIBACA** (R25) + akses dibatasi (permission).
- **Foreign key:** `run_id` → `payroll_runs.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `run_id` | `bigint` | **FK** → `payroll_runs.id` · ID proses/run induk, join ke tabel run terkait. | — |
| `employee_npk` | `nvarchar(255)` | NPK karyawan pada tabel payroll (bukan `NPK`). | — |
| `employee_name` | `nvarchar(255)` | Nama karyawan pada tabel payroll. | — |
| `components` | `nvarchar(max)` | Rincian komponen gaji dalam format JSON. | — |
| `total_salary` | `decimal(18,2)` | Total gaji karyawan pada satu baris payroll. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `employee_dept` | `nvarchar(255)` | Departemen karyawan pada tabel payroll. · boleh NULL | — |

#### `payroll_runs`

- **Fungsi:** Satu kali proses payroll per periode (5 baris).
- **Jumlah baris (16 Nov 2026):** 5
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `period_id` → `payroll_periods.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `13`, `16`, `17` |
| `period_id` | `bigint` | **FK** → `payroll_periods.id` · ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `3`, `4`, `5` |
| `processed_at` | `datetime` | Processed pada | `2026-07-07T11:27:25.280000`, `2026-08-04T16:17:06.183000`, `2026-09-03T08:07:07.803000` |
| `total_payroll` | `decimal(18,2)` |  · default `'0'` | `10212615492.00`, `8281972070.00`, `8698223390.00` |
| `employee_count` | `int` | Karyawan count · default `'0'` | `3192`, `3334`, `3505` |
| `progress` | `int` | Persentase progres proses (0-100). · default `'0'` | `100` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `Payroll calculation completed` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-07T11:27:25.280000`, `2026-08-04T16:17:06.183000`, `2026-09-03T08:07:07.803000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-07T11:27:47.890000`, `2026-08-04T16:17:30.360000`, `2026-09-03T08:07:32.597000` |

#### `payroll_settings`

- **Fungsi:** Pengaturan payroll.
- **Jumlah baris (16 Nov 2026):** 7
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `2` |
| `component` | `nvarchar(255)` | Nama komponen (gaji/BPJS/biaya). | `cutting_insentif`, `heat_insentif`, `line_jumper_insentif` |
| `approval` | `nvarchar(255)` | Status/aksi approval. | `["C-00008","C-00003","C-00001"]`, `["C-00011","C-00776","C-00001"]` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-04-01T09:38:23.493000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-29T07:29:39.487000`, `2026-06-24T10:44:52.293000`, `2026-06-29T07:29:32.580000` |

#### `role_payrolls`

- **Fungsi:** Role yang berhak melihat/memproses payroll.
- **Jumlah baris (16 Nov 2026):** 11
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `user_id` → `users.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `11`, `13` |
| `user_id` | `bigint` | **FK** → `users.id` · ID user aplikasi, join ke `users.id`. | `1`, `11`, `12` |
| `payroll_role` | `nvarchar(255)` | Role yang terkait proses payroll. | `Payroll_ALL`, `Payroll_SEWING`, `Payroll_NONSEWING` |
| `created_by` | `bigint` | Pembuat record (user). · boleh NULL | `1`, `4` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-09T11:18:13.203000`, `2026-07-09T11:18:13.210000`, `2026-07-09T11:18:13.213000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-09T11:18:13.203000`, `2026-07-09T11:18:13.210000`, `2026-07-09T11:18:13.213000` |

#### `salary_approve`

- **Fungsi:** Approval penetapan gaji.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `id_pelamar` | `bigint` | ID pelamar | — |
| `expected_salary` | `decimal(15,2)` | Expected gaji | — |
| `approved_salary` | `decimal(15,2)` | Disetujui gaji · boleh NULL | — |
| `progress` | `nvarchar(max)` | Persentase progres proses (0-100). | — |
| `approved_at` | `nvarchar(max)` | Waktu disetujui. · boleh NULL | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'pending'` | — |
| `requested_by` | `nvarchar(255)` | Requested oleh · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `thr_approve`

- **Fungsi:** Approval THR.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `thr_run_id` | `int` | Thr run ID | — |
| `approval` | `nvarchar(max)` | Status/aksi approval. | — |
| `progress` | `nvarchar(max)` | Persentase progres proses (0-100). · boleh NULL | — |
| `approved_at` | `nvarchar(max)` | Waktu disetujui. · boleh NULL | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'pending'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `thr_exports`

- **Fungsi:** Riwayat ekspor THR.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `run_id` | `int` | ID proses/run induk, join ke tabel run terkait. | — |
| `progress` | `int` | Persentase progres proses (0-100). · default `'0'` | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'waiting'` | — |
| `file_excel` | `nvarchar(255)` | Nama/path file Excel. · boleh NULL | — |
| `file_pdf` | `nvarchar(255)` | Nama/path file PDF. · boleh NULL | — |
| `file_bank` | `nvarchar(255)` | Path/nama berkas BANK di server. · boleh NULL | — |
| `file_peng` | `nvarchar(255)` | Path/nama berkas PENG di server. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `thr_periods`

- **Fungsi:** Master periode THR.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | — |
| `cutoff_date` | `date` | Tanggal batas perhitungan. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `thr_run_details`

- **Fungsi:** Rincian THR per karyawan.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `run_id` | `bigint` | ID proses/run induk, join ke tabel run terkait. | — |
| `employee_npk` | `nvarchar(255)` | NPK karyawan pada tabel payroll (bukan `NPK`). | — |
| `employee_name` | `nvarchar(255)` | Nama karyawan pada tabel payroll. | — |
| `components` | `nvarchar(max)` | Rincian komponen gaji dalam format JSON. · boleh NULL | — |
| `total_salary` | `decimal(18,2)` | Total gaji karyawan pada satu baris payroll. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `thr_runs`

- **Fungsi:** Proses perhitungan THR.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `period_id` | `bigint` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | — |
| `processed_at` | `datetime` | Processed pada · boleh NULL | — |
| `total_thr` | `decimal(18,2)` |  · default `'0'` | — |
| `employee_count` | `int` | Karyawan count · default `'0'` | — |
| `progress` | `int` | Persentase progres proses (0-100). · default `'0'` | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'draft'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

### D5. Rekrutmen & Pelamar — 6 tabel

Lowongan, pelamar, kontak pelamar, undangan event.

#### `PELAMAR`

- **Fungsi:** Data pelamar kerja (26 kolom).
- **Jumlah baris (16 Nov 2026):** 7.695
- **Akses:** bisa dibaca
- **Kunci:** `ID`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `ID` | `int` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `101`, `102`, `103` |
| `NPK` | `nvarchar(254)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. · boleh NULL | `C-01023`, `C-01024`, `C-01025` |
| `NAMA` | `nvarchar(254)` | Nama (orang/entitas). Data pribadi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `JENIS_KELAMIN` | `nvarchar(254)` | Jenis kelamin pelamar ('L'/'P'). | `P`, `L`, `` |
| `TMPT_LAHIR` | `nvarchar(255)` |  boleh NULL | — |
| `TGL_LAHIR` | `date` | Tanggal lahir · boleh NULL | `1996-05-20`, `1999-07-15`, `2002-01-13` |
| `TMK` | `date` | Tanggal Masuk Kerja (awal kontrak). Batas bawah saat menghitung karyawan aktif. · boleh NULL | `2025-07-17`, `2025-08-04`, `2025-08-06` |
| `UMUR` | `nvarchar(254)` | Umur pelamar (teks). | `1 Tahun 5 Bulan 17 Hari`, `15 Tahun 9 Bulan 16 Hari`, `29 Tahun 1 Bulan 27 Hari` |
| `ALAMAT_LENGKAP` | `nvarchar(254)` | Alamat lengkap pelamar. Data pribadi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `KABUPATEN` | `nvarchar(254)` | Kabupaten/kota domisili. | `SUKOHARJO`, `WONOGIRI`, `KLATEN` |
| `ALAMAT_DOMISILI` | `nvarchar(254)` | Alamat domisili pelamar. Data pribadi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `PENDIDIKAN` | `nvarchar(254)` | Pendidikan terakhir pelamar. | `SMK`, `SMA`, `SMP` |
| `NAMA_SEKOLAH` | `nvarchar(254)` | Nama sekolah/institusi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `KABUPATEN_SEKOLAH` | `nvarchar(254)` | Kabupaten lokasi sekolah. | `SUKOHARJO`, ``, `WONOGIRI` |
| `JURUSAN` | `nvarchar(254)` | Jurusan pendidikan. | `IPS`, ``, `AKUNTANSI` |
| `TINGGI_BADAN` | `int` | Tinggi badan (cm). | `0`, `160`, `165` |
| `BERAT_BADAN` | `int` | Berat badan (kg). | `0`, `50`, `60` |
| `HP` | `nvarchar(254)` | Nomor HP. Data pribadi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `AGAMA` | `nvarchar(254)` | Agama. | `ISLAM`, `-`, `KRISTEN` |
| `NIK` | `nvarchar(254)` | NIK karyawan. Sensitif. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `NO_KK` | `nvarchar(254)` | Nomor Kartu Keluarga. Data pribadi sangat sensitif. · boleh NULL · default `N'-'` | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `IBU` | `nvarchar(254)` | Nama ibu kandung. Data pribadi. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `STATUS` | `nvarchar(254)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). | `BM`, `M`, `CH` |
| `TANGGUNGAN` | `int` | Jumlah tanggungan keluarga. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `IS_KONTRAK` | `nvarchar(254)` | Penanda kontrak · default `N'FALSE'` | `TRUE` |
| `IS_STAFF` | `nvarchar(255)` | Penanda staff. Disimpan sebagai teks '0'/'1', bukan angka. · boleh NULL | — |

#### `applicant_contacts`

- **Fungsi:** Kontak pelamar (telepon, nama, posisi).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `phone` | `nvarchar(255)` | Nomor telepon. Data pribadi. | — |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | — |
| `position` | `nvarchar(255)` | Nama posisi/jabatan. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `event_invitations`

- **Fungsi:** Undangan event rekrutmen.
- **Jumlah baris (16 Nov 2026):** 1.729
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `event_id` → `events.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `100`, `101` |
| `event_id` | `bigint` | **FK** → `events.id` · ID event, join ke `events.id`. | `1` |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00044`, `C-00055`, `C-00077` |
| `nama` | `nvarchar(255)` | Nama (orang/entitas). Data pribadi. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `departemen` | `nvarchar(255)` |  boleh NULL | `OPERATOR`, `-`, `` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `hadir`, `tidak_hadir` |
| `ucapan` | `nvarchar(max)` |  boleh NULL | `.`, `Ayoo dukung dan meriahkan acara perus...`, `BISMILAHHHH...
YAALLAHHHHH MOBIL YAA...` |
| `ip_address` | `nvarchar(45)` | Alamat IP. · boleh NULL | `(disamarkan)` |
| `responded_at` | `datetime` | Responded pada · boleh NULL | `2026-08-11T07:47:48.010000`, `2026-08-11T07:56:44.843000`, `2026-08-11T07:56:47.390000` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-10T16:27:01.617000`, `2026-08-11T07:47:48.013000`, `2026-08-11T07:56:44.843000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-11T07:47:48.013000`, `2026-08-11T07:56:44.843000`, `2026-08-11T07:56:47.390000` |

#### `job_vacancies`

- **Fungsi:** Lowongan kerja yang dipublikasikan.
- **Jumlah baris (16 Nov 2026):** 26
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `created_by` → `users.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `position` | `nvarchar(255)` | Nama posisi/jabatan. | `ADMIN`, `ADMIN QC`, `Admin Factory Manager` |
| `department_id` | `nvarchar(255)` | Departemen ID | `316`, `6`, `7` |
| `total_needed` | `int` |  · default `'1'` | `1`, `2`, `5` |
| `employment_type` | `nvarchar(255)` | Employment jenis · default `'full_time'` | `contract` |
| `job_description` | `nvarchar(max)` | Job deskripsi · boleh NULL | `<h1><strong>WE ARE HIRING</strong></h...`, `<h1><strong>WE ARE HIRING</strong></h...`, `<h1><strong>WE ARE HIRING</strong></h...` |
| `criteria` | `nvarchar(max)` |  boleh NULL | `["Berpengalaman sebagai Leader CT-PAT"]`, `["D3\/S1 Teknik Informatika, Sistem I...`, `["Mampu mengoperasikan Microsoft Offi...` |
| `required_documents` | `nvarchar(max)` |  boleh NULL | `["Surat Lamaran","CV Terbaru","Ijazah...`, `["Surat Lamaran","CV","Ijazah Terakhir"]`, `["CV Terbaru","Ijazah Terakhir","Sura...` |
| `open_date` | `date` | Open tanggal | `2026-07-01`, `2026-06-01`, `2026-07-29` |
| `close_date` | `date` | Close tanggal | `2027-01-01`, `2026-08-29`, `2026-09-30` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'draft'` | `open`, `closed` |
| `created_by` | `bigint` | **FK** → `users.id` · Pembuat record (user). · boleh NULL | `14`, `1` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-07T15:43:22.647000`, `2026-07-08T07:36:29.390000`, `2026-07-08T07:39:15.517000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-29T14:37:52.270000`, `2026-07-31T07:49:36.977000`, `2026-08-07T07:55:37.663000` |
| `deleted_at` | `datetime` | Deleted pada · boleh NULL | — |
| `recruitment_position_id` | `bigint` | Recruitment position ID · boleh NULL | `1`, `10`, `11` |

#### `pelamar_details`

- **Fungsi:** Rincian/berkas pelamar (53 kolom).
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `id_pelamar` | `nvarchar(255)` | ID pelamar · boleh NULL | — |
| `nomor_sim` | `nvarchar(255)` |  boleh NULL | — |
| `warga_negara` | `nvarchar(255)` |  boleh NULL | — |
| `ikut_kb` | `bit` |  boleh NULL | — |
| `bakat_hobby` | `nvarchar(max)` |  boleh NULL | — |
| `mode_transportasi` | `nvarchar(max)` |  boleh NULL | — |
| `jabatan` | `nvarchar(max)` |  boleh NULL | — |
| `department` | `nvarchar(max)` | Nama departemen (teks). · boleh NULL | — |
| `bpjs_tk` | `nvarchar(max)` |  boleh NULL | — |
| `bpjs_kes` | `nvarchar(max)` |  boleh NULL | — |
| `alamat_skrg` | `nvarchar(max)` |  boleh NULL | — |
| `kabupaten_kota_skrg` | `nvarchar(max)` |  boleh NULL | — |
| `status_domisili` | `nvarchar(max)` |  boleh NULL | — |
| `nama_ktk_darurat` | `nvarchar(max)` |  boleh NULL | — |
| `hubungan` | `nvarchar(max)` |  boleh NULL | — |
| `no_telp_darurat` | `nvarchar(max)` | Nomor telp darurat · boleh NULL | — |
| `motivasi` | `nvarchar(max)` |  boleh NULL | — |
| `kegiatan_ekstra` | `nvarchar(max)` |  boleh NULL | — |
| `pengalaman_kerja` | `nvarchar(max)` |  boleh NULL | — |
| `data_ayah` | `nvarchar(max)` |  boleh NULL | — |
| `data_ibu` | `nvarchar(max)` |  boleh NULL | — |
| `saudara_kandung` | `nvarchar(max)` |  boleh NULL | — |
| `data_anak` | `nvarchar(max)` |  boleh NULL | — |
| `riwayat_pendidikan` | `nvarchar(max)` |  boleh NULL | — |
| `file_surat_lamaran` | `nvarchar(max)` | Path/nama berkas SURAT LAMARAN di server. · boleh NULL | — |
| `file_cv` | `nvarchar(max)` | Path/nama berkas CV di server. · boleh NULL | — |
| `file_ktp` | `nvarchar(max)` | Path/nama berkas KTP di server. · boleh NULL | — |
| `file_kk` | `nvarchar(max)` | Path/nama berkas KK di server. · boleh NULL | — |
| `file_ijasah` | `nvarchar(max)` | Path/nama berkas IJASAH di server. · boleh NULL | — |
| `file_akta_kelahiran` | `nvarchar(max)` | Path/nama berkas AKTA KELAHIRAN di server. · boleh NULL | — |
| `file_skck` | `nvarchar(max)` | Path/nama berkas SKCK di server. · boleh NULL | — |
| `file_surat_sehat` | `nvarchar(max)` | Path/nama berkas SURAT SEHAT di server. · boleh NULL | — |
| `file_pas_foto` | `nvarchar(max)` | Path/nama berkas PAS FOTO di server. · boleh NULL | — |
| `status_apply` | `nvarchar(max)` |  boleh NULL | — |
| `is_test` | `nvarchar(max)` | Penanda test · boleh NULL | — |
| `tgl_test` | `date` | Tanggal test · boleh NULL | — |
| `is_kesehatan` | `nvarchar(max)` | Penanda kesehatan · boleh NULL | — |
| `tgl_kesehatan` | `date` | Tanggal kesehatan · boleh NULL | — |
| `is_interview` | `nvarchar(max)` | Penanda interview · boleh NULL | — |
| `tgl_interview` | `date` | Tanggal interview · boleh NULL | — |
| `tgl_diterima` | `datetime` | Tanggal diterima · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `result_test` | `nvarchar(max)` |  boleh NULL | — |
| `comment_test` | `nvarchar(max)` |  boleh NULL | — |
| `result_kesehatan` | `nvarchar(max)` |  boleh NULL | — |
| `comment_kesehatan` | `nvarchar(max)` |  boleh NULL | — |
| `result_interview` | `nvarchar(max)` |  boleh NULL | — |
| `comment_interview` | `nvarchar(max)` |  boleh NULL | — |
| `result_user` | `nvarchar(max)` | Result pengguna · boleh NULL | — |
| `comment_user` | `nvarchar(max)` | Comment pengguna · boleh NULL | — |
| `file_test` | `nvarchar(max)` | Path/nama berkas TEST di server. · boleh NULL | — |

#### `recruitment_positions`

- **Fungsi:** Posisi rekrutmen yang dibuka (26 baris).
- **Jumlah baris (16 Nov 2026):** 26
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `position` | `nvarchar(255)` | Nama posisi/jabatan. | `ADMIN`, `ADMIN QC`, `Admin Factory Manager` |
| `dept` | `nvarchar(255)` | Nama departemen (teks bebas pada beberapa tabel, bukan master). | `GUDANG`, `MEKANIK`, `QC` |
| `is_aktif` | `bit` | Penanda aktif · default `'1'` | `True`, `False` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-07T15:43:22.627000`, `2026-07-08T07:36:29.387000`, `2026-07-08T07:39:15.517000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-29T14:37:52.267000`, `2026-07-31T07:49:36.977000`, `2026-08-18T10:09:00.287000` |
| `is_staff` | `nvarchar(1)` | Penanda staff. Disimpan sebagai teks '0'/'1', bukan angka. · boleh NULL | `0`, `1` |

### D6. Kesehatan, Kesejahteraan & Kegiatan — 17 tabel

Klinik, medical check-up, kantin, menu, doorprize, event, quiz, survei.

#### `canteen_settings`

- **Fungsi:** Pengaturan kantin.
- **Jumlah baris (16 Nov 2026):** 1
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1` |
| `is_enabled` | `bit` | Penanda fitur aktif (1/0). · default `'1'` | `True` |
| `require_geomap` | `bit` |  · default `'0'` | `True` |
| `allowed_work_location_ids` | `nvarchar(max)` | Allowed kerja lokasi ids · boleh NULL | `[8]` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-11T10:53:52.193000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-11T10:53:56.873000` |

#### `canteens`

- **Fungsi:** Master kantin (nama, lokasi, PIC).
- **Jumlah baris (16 Nov 2026):** 2
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)` |
| `location` | `nvarchar(255)` | Lokasi · boleh NULL | `Kantin 1 Chutex`, `Kantin 2 Chutex` |
| `pic_name` | `nvarchar(255)` | Pic nama · boleh NULL | — |
| `pic_phone` | `nvarchar(255)` | Pic telepon · boleh NULL | — |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-09T11:42:12.727000`, `2026-11-09T11:42:33.610000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-09T11:42:12.727000`, `2026-11-09T11:42:33.610000` |
| `canteen_id` | `tinyint` | ID kantin, join ke `canteens.id`. · boleh NULL | `1`, `2` |
| `contact_person` | `nvarchar(255)` |  boleh NULL | — |
| `contact_phone` | `nvarchar(255)` | Contact telepon · boleh NULL | — |

#### `doorprize_scans`

- **Fungsi:** Log pemindaian QR doorprize.
- **Jumlah baris (16 Nov 2026):** 3.107
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `scanned_by` → `users.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `100`, `101` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00044`, `C-00055`, `C-00077` |
| `scanned_by` | `bigint` | **FK** → `users.id` · User/perangkat yang memindai. · boleh NULL | `1` |
| `scanned_at` | `datetime` | Waktu pemindaian. · default `getdate()` | `1900-01-01T00:00:00` |
| `is_winner` | `bit` | Penanda winner · default `'0'` | `False`, `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `1900-01-01T00:00:00` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `1900-01-01T00:00:00`, `2026-08-29T10:31:41.157000`, `2026-08-29T10:34:28.397000` |
| `event_id` | `int` | ID event, join ke `events.id`. | `1` |

#### `doorprize_winners`

- **Fungsi:** Pemenang doorprize per event (356 baris, ada is_void).
- **Jumlah baris (16 Nov 2026):** 356
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Pakai is_void=0 untuk pemenang sah.
- **Foreign key:** `drawn_by` → `users.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `100`, `101`, `102` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00040`, `C-00041`, `C-00055` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `department` | `nvarchar(255)` | Nama departemen (teks). · boleh NULL | `OPERATOR`, ``, `QC` |
| `photo` | `nvarchar(255)` | Path/gambar foto. Data biometrik/pribadi. · boleh NULL | — |
| `batch_label` | `nvarchar(255)` | Label batch undian. · boleh NULL | `Sesi 4`, `Sesi 1`, `Sesi 3` |
| `drawn_by` | `bigint` | **FK** → `users.id` · Nama/ID penarik undian. · boleh NULL | `1` |
| `won_at` | `datetime` | Waktu memenangkan undian. · default `getdate()` | `2026-08-29T10:29:15.937000`, `2026-08-29T10:29:15.943000`, `2026-08-29T10:29:15.947000` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-29T10:29:15.937000`, `2026-08-29T10:29:15.943000`, `2026-08-29T10:29:15.947000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-29T10:29:15.937000`, `2026-08-29T10:29:15.943000`, `2026-08-29T10:29:15.947000` |
| `is_void` | `bit` | Penanda data dibatalkan (1 = void, tidak sah). · default `'0'` | `False` |
| `void_reason` | `nvarchar(255)` | Alasan pembatalan. · boleh NULL | — |
| `voided_at` | `datetime` | Waktu pembatalan. · boleh NULL | — |
| `voided_by` | `bigint` | Voided oleh · boleh NULL | — |
| `event_id` | `int` | ID event, join ke `events.id`. | `1` |

#### `events`

- **Fungsi:** Master event perusahaan (tanggal, lokasi, jumlah hadir).
- **Jumlah baris (16 Nov 2026):** 1
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1` |
| `nama_event` | `nvarchar(255)` | Nama event. | `(disamarkan)` |
| `tanggal_event` | `date` | Tanggal event. | `2026-08-29` |
| `waktu_event` | `nvarchar(255)` | Waktu pelaksanaan event (teks). | `08.00 WIB - 15.00 WIB` |
| `lokasi_event` | `nvarchar(255)` | Lokasi event. | `PT. Chutex International Indonesia` |
| `dress_code` | `nvarchar(255)` | Dress kode · boleh NULL | `Karyawan : Merah Putih, Peserta Lomba...` |
| `detail_event` | `nvarchar(max)` |  boleh NULL | `Dalam rangka memperingati HUT ke-81 K...` |
| `view_folder` | `nvarchar(255)` | Folder/tampilan penyimpanan berkas event. | `hutri81` |
| `jumlah_hadir` | `int` | Jumlah hadir. · default `'0'` | `1652` |
| `jumlah_tidak_hadir` | `int` | Jumlah tidak hadir. · default `'0'` | `78` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'0'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-10T13:07:43.603000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-29T13:49:17.037000` |

#### `food_menus`

- **Fungsi:** Master menu makanan (13 kolom).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `canteen_id` → `canteens.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `canteen_id` | `bigint` | **FK** → `canteens.id` · ID kantin, join ke `canteens.id`. | — |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | — |
| `description` | `nvarchar(max)` | Deskripsi. · boleh NULL | — |
| `photo` | `nvarchar(255)` | Path/gambar foto. Data biometrik/pribadi. · boleh NULL | — |
| `price` | `decimal(12,2)` |  · default `'0'` | — |
| `available_start` | `date` | Available mulai · boleh NULL | — |
| `available_end` | `date` | Available selesai · boleh NULL | — |
| `quota` | `int` | Kuota · boleh NULL | — |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `available_dates` | `nvarchar(max)` |  boleh NULL | — |

#### `food_orders`

- **Fungsi:** Pesanan makanan karyawan per tanggal.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `food_menu_id` | `bigint` | ID menu makanan, join ke `food_menus.id`. | — |
| `canteen_id` | `bigint` | ID kantin, join ke `canteens.id`. | — |
| `order_date` | `date` | Tanggal pesanan. | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'pending'` | — |
| `notes` | `nvarchar(max)` | Catatan. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `health_tests`

- **Fungsi:** Hasil medical check-up (visus, tekanan darah, hepatitis, dll).
- **Jumlah baris (16 Nov 2026):** 1.973
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Data medis sensitif.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `100`, `101`, `102` |
| `nik` | `nvarchar(255)` | NIK karyawan. Sensitif. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `cacat` | `bit` |  · default `'0'` | `False`, `True` |
| `buta_warna` | `bit` |  · default `'0'` | `False`, `True` |
| `visus_mata_od` | `nvarchar(255)` |  boleh NULL | `1`, `3.25`, `4` |
| `tinggi` | `int` | Tinggi badan (cm). · boleh NULL | `150`, `158`, `155` |
| `berat` | `int` | Berat badan (kg). · boleh NULL | `45`, `48`, `55` |
| `abdoment` | `nvarchar(255)` |  boleh NULL | `DBN` |
| `gigi` | `nvarchar(255)` |  boleh NULL | `DBN`, `CARIES` |
| `cor_pulmo` | `nvarchar(255)` |  boleh NULL | `DBN`, `CBN` |
| `tht` | `nvarchar(255)` |  boleh NULL | `DBN` |
| `extreme` | `nvarchar(255)` |  boleh NULL | `DBN` |
| `tekanan_darah` | `nvarchar(255)` | Tekanan darah. · boleh NULL | `90/60`, `100/60`, `110/60` |
| `respirasi` | `int` |  boleh NULL | `18`, `16`, `17` |
| `denyut` | `int` |  boleh NULL | `78`, `76`, `79` |
| `suhu` | `decimal(18,2)` | Suhu tubuh. · boleh NULL | `36.40`, `36.50`, `36.60` |
| `paru` | `bit` |  · default `'0'` | `False`, `True` |
| `hepatitis` | `bit` |  · default `'0'` | `False`, `True` |
| `jantung` | `bit` |  · default `'0'` | `False` |
| `thypoid` | `bit` |  · default `'0'` | `False`, `True` |
| `alergi` | `bit` | Riwayat alergi. · default `'0'` | `False`, `True` |
| `ashma` | `bit` |  · default `'0'` | `False`, `True` |
| `lain` | `nvarchar(255)` |  boleh NULL | `ASAM LAMBUNG`, `ALERGI UDANG`, `HIPERTENSI` |
| `kesimpulan` | `bit` | Kesimpulan hasil pemeriksaan. · default `'1'` | `True`, `False` |
| `remark` | `nvarchar(255)` | Catatan tambahan. · boleh NULL | `BUTA WARNA PARSIAL`, `CLEFT LIP ( BIBIR SUMBING)`, `+ HEPATITIS B` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-10T13:09:29.267000`, `2026-07-10T13:12:06.283000`, `2026-07-10T13:12:58.523000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-10T13:28:23.723000`, `2026-07-10T13:29:36.423000`, `2026-07-10T13:29:49.120000` |
| `visus_mata_os` | `nvarchar(1)` |  boleh NULL | `1`, `3`, `4` |

#### `kunjungans`

- **Fungsi:** Kunjungan karyawan ke klinik (keluhan, diagnosa, tindakan, dokter).
- **Jumlah baris (16 Nov 2026):** 5.984
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `dokter_id` → `users.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `100`, `101`, `102` |
| `NPK` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. · boleh NULL | `C-00494`, `C-00724`, `C-00816` |
| `tanggal_kunjungan` | `date` | Tanggal kunjungan klinik. | `2026-05-28`, `2026-05-29`, `2026-05-26` |
| `jam_masuk` | `nvarchar(255)` |  boleh NULL | `09:31`, `09:35`, `11:21` |
| `jam_selesai` | `nvarchar(255)` |  boleh NULL | `11:45`, `11:51`, `09:08` |
| `keluhan` | `nvarchar(max)` | Keluhan pasien. Data medis sensitif. | `DISPEPSIA`, `CEPALGIA`, `ISPA` |
| `diagnosa` | `nvarchar(max)` | Diagnosa dokter. Data medis sensitif. · boleh NULL | `DISPEPSIA`, `ISPA`, `CEPALGIA` |
| `catatan_dokter` | `nvarchar(max)` | Catatan dokter. · boleh NULL | `Minum hangat`, `Minum yang cukup`, `Hindari makan pedes dan kecut` |
| `tindakan` | `nvarchar(max)` | Tindakan medis yang dilakukan. · boleh NULL | `OBAT`, `ANTALGIN`, `PARAFLU` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'menunggu'` | `selesai` |
| `dokter_id` | `bigint` | **FK** → `users.id` · ID dokter. · boleh NULL | `10`, `17` |
| `no_antrian` | `int` | Nomor antrian. | `44`, `1`, `10` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-26T08:27:09.217000`, `2026-05-26T08:31:24.057000`, `2026-05-26T09:07:12.773000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-05-26T08:28:59.367000`, `2026-05-26T08:32:17.247000`, `2026-05-26T09:08:32.630000` |
| `nama` | `nvarchar(255)` | Nama (orang/entitas). Data pribadi. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `dept` | `nvarchar(255)` | Nama departemen (teks bebas pada beberapa tabel, bukan master). · boleh NULL | `KANTIN DIAMOND`, `KANTIN PAWON`, `PROYEK` |

#### `quiz_options`

- **Fungsi:** Pilihan jawaban quiz.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `question_id` → `quiz_questions.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `question_id` | `bigint` | **FK** → `quiz_questions.id` · Question ID | — |
| `option_text` | `nvarchar(255)` | Option teks | — |
| `is_correct` | `bit` | Penanda correct · default `'0'` | — |
| `sort_order` | `int` | Urutan tampil. · default `'0'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `quiz_questions`

- **Fungsi:** Pertanyaan quiz.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `quiz_id` → `quizzes.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `quiz_id` | `bigint` | **FK** → `quizzes.id` · Quiz ID | — |
| `question_text` | `nvarchar(255)` | Question teks | — |
| `type` | `nvarchar(30)` | Jenis/kategori record. · default `'single_choice'` | — |
| `sort_order` | `int` | Urutan tampil. · default `'0'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `quiz_responses`

- **Fungsi:** Jawaban peserta quiz.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `session_id` → `quiz_sessions.id`; `question_id` → `quiz_questions.id`; `option_id` → `quiz_options.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `session_id` | `bigint` | **FK** → `quiz_sessions.id` · Session ID | — |
| `question_id` | `bigint` | **FK** → `quiz_questions.id` · Question ID · boleh NULL | — |
| `option_id` | `bigint` | **FK** → `quiz_options.id` · Option ID · boleh NULL | — |
| `answered_at` | `datetime` | Answered pada | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `answer_text` | `nvarchar(max)` | Answer teks · boleh NULL | — |

#### `quiz_sessions`

- **Fungsi:** Sesi pengerjaan quiz.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `quiz_id` → `quizzes.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `quiz_id` | `bigint` | **FK** → `quizzes.id` · Quiz ID | — |
| `participant_name` | `nvarchar(255)` | Participant nama | — |
| `participant_ip` | `nvarchar(255)` | Participant IP · boleh NULL | — |
| `joined_at` | `datetime` | Joined pada | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `quizzes`

- **Fungsi:** Master quiz (kosong saat verifikasi).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `title` | `nvarchar(255)` | Judul. | — |
| `description` | `nvarchar(max)` | Deskripsi. · boleh NULL | — |
| `qr_code` | `nvarchar(255)` | Qr kode | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'draft'` | — |
| `started_at` | `datetime` | Started pada · boleh NULL | — |
| `closed_at` | `datetime` | Closed pada · boleh NULL | — |
| `created_by` | `bigint` | Pembuat record (user). · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `mode` | `nvarchar(20)` |  · default `'quiz'` | — |

#### `resep_obats`

- **Fungsi:** Resep obat hasil kunjungan klinik.
- **Jumlah baris (16 Nov 2026):** 11.467
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `kunjungan_id` → `kunjungans.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `100`, `101`, `102` |
| `kunjungan_id` | `bigint` | **FK** → `kunjungans.id` · Kunjungan ID | `54`, `103`, `109` |
| `keterangan_obat` | `nvarchar(max)` | Keterangan tambahan obat. | `PCT`, `ANTASIDA`, `RANITIDINE` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-26T11:36:35.940000`, `2026-05-28T09:38:11.097000`, `2026-05-28T11:48:27.797000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-05-28T08:10:53.113000`, `2026-05-28T11:48:27.797000`, `2026-05-26T08:29:47.667000` |
| `qty` | `int` | Jumlah/kuantitas. · boleh NULL | `2`, `4` |

#### `satisfaction_questions`

- **Fungsi:** Pertanyaan survei kepuasan (35 baris).
- **Jumlah baris (16 Nov 2026):** 35
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `section` | `nvarchar(255)` | Section. Di `DEPT` selalu 'CHUTEX'; di `BIODATA` berisi angka → jangan dipakai sebagai filter. · boleh NULL | `E`, `F`, `G` |
| `question_text` | `nvarchar(max)` | Question teks | `Apa saran Anda agar seluruh karyawan ...`, `Apa yang Anda harapkan dari perusahaa...`, `Apa yang membuat Anda merasa kurang n...` |
| `question_type` | `nvarchar(255)` | Question jenis · default `'likert'` | `likert`, `open` |
| `sort_order` | `int` | Urutan tampil. · default `'0'` | `1`, `10`, `11` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-09T11:49:18.817000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-09T11:49:18.817000` |

#### `satisfaction_responses`

- **Fungsi:** Jawaban survei kepuasan (70 baris).
- **Jumlah baris (16 Nov 2026):** 70
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `question_id` → `satisfaction_questions.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `question_id` | `bigint` | **FK** → `satisfaction_questions.id` · Question ID | `1`, `10`, `11` |
| `score` | `tinyint` | Nilai/skor. · boleh NULL | `5`, `4`, `3` |
| `answer_text` | `nvarchar(max)` | Answer teks · boleh NULL | `1. tambah loker atau tempat penyimpan...`, `jam kerja yang tidak sesuai` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-11T22:39:14.063000`, `2026-11-11T22:39:14.073000`, `2026-11-11T22:39:14.080000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-11T22:39:14.063000`, `2026-11-11T22:39:14.073000`, `2026-11-11T22:39:14.080000` |

### D7. Produksi & Monitoring Order — 16 tabel

Order, work order, BOM, purchase order, shipment, rekonsiliasi, stok (mon_*).

#### `mon_boms`

- **Fungsi:** Bill of materials per order (68.380 baris).
- **Jumlah baris (16 Nov 2026):** 68.380
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `bom_id` | `nvarchar(255)` | Bom ID · boleh NULL | `BOM-0725-000001`, `BOM-0725-000002`, `BOM-0725-000003` |
| `code_prod` | `nvarchar(255)` | Kode produksi/OCF. Bisa memuat OCF tanpa kata 'OCF' (mis. 'CPO 25091 266C0001'). · boleh NULL | `CPO 25019 CONT 1`, `CPO 25021 CONT 1`, `CPO 25020 CONT 1` |
| `tgl_prod` | `date` | Tanggal prod · boleh NULL | `2025-07-15`, `2025-07-14` |
| `barang_code` | `nvarchar(255)` | Kode barang, join ke `mon_ms_barangs.barang_code`. · boleh NULL | `01CTIM00003`, `01CTIM00019`, `01CTIM00009` |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `satuan_code` | `nvarchar(255)` | Kode satuan (pcs, kg, yard). · boleh NULL | `YRD`, `PCE`, `KGM` |
| `uraian` | `nvarchar(255)` | Uraian/nama barang pada PO. Deskripsi item bisa juga ada di `spesifikasi`. · boleh NULL | — |
| `spesifikasi` | `nvarchar(max)` | Spesifikasi barang; sering memuat kata kunci yang tidak ada di `uraian`. · boleh NULL | `061 LIGHT BLUE`, `000 WHITE`, `005 GREY B10` |
| `cons` | `decimal(18,6)` |  boleh NULL · default `'0'` | `0.900000`, `1.000000`, `0.040000` |
| `scrap_percent` | `decimal(8,4)` | Scrap persentase · boleh NULL · default `'0'` | `0.0000`, `14.0300`, `14.4900` |
| `departemen` | `nvarchar(255)` |  boleh NULL | `Cutting`, `Sewing`, `Packing` |
| `komponen` | `nvarchar(255)` |  boleh NULL | `02FLBW00001`, `02FMBW00036`, `02FMBW00001` |
| `barang_jadi` | `nvarchar(255)` |  boleh NULL | `LADIES CREW NECK`, `MENS V-NECK BONDWISE`, `MENS CREW BONDWISE` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:13:14.667000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:13:14.667000` |

#### `mon_ms_barangs`

- **Fungsi:** Master barang produksi (1.672 baris, 19 kolom).
- **Jumlah baris (16 Nov 2026):** 1.672
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `barang_code` | `nvarchar(255)` | Kode barang, join ke `mon_ms_barangs.barang_code`. | `01CEIM00001`, `01CEIM00002`, `01CFLC00001` |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `satuan_code` | `nvarchar(255)` | Kode satuan (pcs, kg, yard). · boleh NULL | `YRD`, `KGM`, `PCE` |
| `barang_category` | `nvarchar(255)` | Barang kategori · boleh NULL | `Bahan Baku Import`, `Bahan Baku Lokal`, `Bahan Setengah Jadi` |
| `header_code` | `nvarchar(255)` | Header kode · boleh NULL | `01`, `29` |
| `hs_code` | `nvarchar(255)` | Hs kode · boleh NULL | — |
| `kode_sub_ap` | `nvarchar(255)` |  boleh NULL | `10414`, `10414 `, `10406` |
| `kode_sub_ar` | `nvarchar(255)` |  boleh NULL | `20301`, `20301 `, `20214` |
| `barang_status` | `nvarchar(255)` |  boleh NULL | `Active` |
| `create_by` | `nvarchar(255)` | Pembuat record (user). · boleh NULL | `Upload : ESTI ADM PPIC`, `ESTI ADM PPIC`, `Chutex` |
| `create_date` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2025-07-15T08:21:04.660000`, `2025-07-14T09:47:43.183000`, `2025-07-14T09:47:43.190000` |
| `modify_by` | `nvarchar(255)` | Pengubah terakhir (user). · boleh NULL | `Upload : ESTI ADM PPIC`, `ESTI ADM PPIC`, `CHUTEX` |
| `modify_date` | `datetime` | Waktu record diubah (datetime). · boleh NULL | `2025-07-17T15:16:01`, `2025-07-18T13:46:45`, `2025-07-21T10:46:47` |
| `satuan_hs` | `nvarchar(255)` |  boleh NULL | `YRD`, `KGM`, `PCE` |
| `konversi` | `decimal(18,4)` |  boleh NULL | `1.0000` |
| `kode` | `nvarchar(255)` |  boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:13:11.510000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:13:11.510000` |

#### `mon_ms_negaras`

- **Fungsi:** Master negara tujuan/origin.
- **Jumlah baris (16 Nov 2026):** 246
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `negara_code` | `nvarchar(10)` | Negara kode | `AD`, `AE`, `AF` |
| `negara_name` | `nvarchar(150)` | Negara nama · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `create_by` | `nvarchar(100)` | Pembuat record (user). · boleh NULL | `System` |
| `create_date` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2023-11-14T00:00:00` |
| `modify_by` | `nvarchar(100)` | Pengubah terakhir (user). · boleh NULL | — |
| `modify_date` | `datetime` | Waktu record diubah (datetime). · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:13:10.733000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:13:10.733000` |

#### `mon_ms_suppliers`

- **Fungsi:** Master supplier produksi (23 kolom).
- **Jumlah baris (16 Nov 2026):** 457
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `supplier_code` | `nvarchar(30)` | Kode supplier, join ke master supplier. | `ABADI`, `ACG`, `ADD` |
| `supplier_name` | `nvarchar(200)` | Nama supplier. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `npwp` | `nvarchar(50)` | Nomor NPWP. Sensitif. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `phone` | `nvarchar(50)` | Nomor telepon. Data pribadi. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `pic` | `nvarchar(100)` |  boleh NULL | `NICOLE`, `FITRI`, `(disamarkan)` |
| `email` | `nvarchar(150)` | Alamat email. Data pribadi. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `rekening` | `nvarchar(100)` |  boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `category` | `nvarchar(50)` | Kategori. · boleh NULL | `Supplier`, `Buyer`, `` |
| `kode_sub_ap` | `nvarchar(20)` |  boleh NULL | ``, `20213`, `20201` |
| `kode_sub_ar` | `nvarchar(20)` |  boleh NULL | ``, `20201`, `10201` |
| `negara_id` | `nvarchar(10)` | Negara ID · boleh NULL | `ID`, `-`, `HK` |
| `supplier_status` | `nvarchar(30)` |  boleh NULL | `Active`, `Void` |
| `create_by` | `nvarchar(100)` | Pembuat record (user). · boleh NULL | `ULVI ADM MR`, `NINO ADM PURCHASING`, `NUR PRATIWI ADM PURCAHING` |
| `create_date` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2025-07-10T16:37:13`, `2025-07-10T17:15:35`, `2025-07-11T15:13:25` |
| `modify_by` | `nvarchar(100)` | Pengubah terakhir (user). · boleh NULL | `IIN ADM ACCOUNTING`, `ALIFIA  ADM PURCHASING`, `ULVI ADM MR` |
| `modify_date` | `datetime` | Waktu record diubah (datetime). · boleh NULL | `2025-07-11T15:29:11`, `2025-07-22T13:28:29`, `2025-07-30T10:56:03` |
| `ppb` | `tinyint` |  boleh NULL · default `'0'` | `0` |
| `tpb` | `nvarchar(100)` |  boleh NULL | — |
| `nib` | `nvarchar(100)` |  boleh NULL | `(disamarkan)` |
| `tgl_tpb` | `date` | Tanggal tpb · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:13:10.907000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:13:10.907000` |

#### `mon_orders`

- **Fungsi:** Order produksi (58 kolom, inti monitoring order).
- **Jumlah baris (16 Nov 2026):** 1.194
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `uraian` | `nvarchar(255)` | Uraian/nama barang pada PO. Deskripsi item bisa juga ada di `spesifikasi`. | `25111`, `25112`, `25117` |
| `ocf_no` | `nvarchar(255)` | Nomor OCF. · boleh NULL | `266K0008`, `266K0009`, `256C0005` |
| `buyer_po` | `nvarchar(255)` | Buyer PO · boleh NULL | `009/SWAG.FU/VII/2025`, `CBS-000003-05-2025`, `26S_AB1QMA6S_11_ROI` |
| `buyer` | `nvarchar(255)` | Nama buyer/pembeli. · boleh NULL | `CHARLES'S WAIN - MUJI`, `KANMAX - GAP OLD NAVY`, `KANMAX - SARIWARNA` |
| `brand` | `nvarchar(255)` |  boleh NULL | `MUJI`, `OLD NAVY`, `BONDWISE` |
| `style` | `nvarchar(255)` |  boleh NULL | `878746 (D303/630531)`, `881293 (D303/655709)`, `AB1QMA6S` |
| `item` | `nvarchar(255)` |  boleh NULL | `T-SHIRT`, `GIRLS SS SHIRT`, `Polo Shirt S/S` |
| `qty_ord` | `decimal(18,2)` | Kuantitas ord · boleh NULL · default `'0'` | `30000.00`, `39996.00`, `4002.00` |
| `destination` | `nvarchar(255)` |  boleh NULL | `HONGKONG`, `US - ONLINE`, `JAKARTA` |
| `artwork` | `nvarchar(255)` |  boleh NULL | `HTL`, `PRINT`, `HEAT TRANSFER` |
| `sewing_process` | `nvarchar(255)` |  boleh NULL | — |
| `production_delivery` | `date` |  boleh NULL | `2025-10-15`, `2026-01-21`, `2026-03-27` |
| `buyer_delivery` | `date` |  boleh NULL | `2026-01-28`, `2026-04-11`, `2026-05-09` |
| `prod_start` | `date` | Prod mulai · boleh NULL | — |
| `prod_end` | `date` | Prod selesai · boleh NULL | `2026-06-24`, `2026-05-18`, `2026-06-12` |
| `shipment_mode` | `nvarchar(255)` |  boleh NULL | `46167`, `46195`, `46202` |
| `material_fab` | `nvarchar(255)` |  boleh NULL | — |
| `fabric` | `nvarchar(255)` |  boleh NULL | — |
| `sample` | `nvarchar(255)` |  boleh NULL | `SEA`, `TRUCK` |
| `thread` | `nvarchar(255)` |  boleh NULL | `46066`, `45908`, `OK` |
| `pad_htl` | `nvarchar(255)` |  boleh NULL | `VN STOCK`, `KD0322`, `KD0325` |
| `main_label` | `nvarchar(255)` |  boleh NULL | `OK` |
| `care_label` | `nvarchar(255)` |  boleh NULL | `TBA`, `46172` |
| `button_snap` | `nvarchar(255)` |  boleh NULL | `X`, `TBA`, `46172` |
| `tape` | `nvarchar(255)` |  boleh NULL | `HEAT TRANSFER`, `PRINT`, `OK` |
| `hangtag` | `nvarchar(255)` |  boleh NULL | `X` |
| `price_ticket` | `nvarchar(255)` |  boleh NULL | `X`, `RFID LABEL` |
| `size_strip` | `nvarchar(255)` |  boleh NULL | — |
| `polybag` | `nvarchar(255)` |  boleh NULL | — |
| `sticker` | `nvarchar(255)` |  boleh NULL | `X`, `46174`, `IH 5/12` |
| `hanger` | `nvarchar(255)` |  boleh NULL | `X` |
| `sizer` | `nvarchar(255)` |  boleh NULL | `X`, `46142`, `46172` |
| `carton_box` | `nvarchar(255)` |  boleh NULL | `46142`, `46172`, `X` |
| `vessel_book` | `nvarchar(255)` |  boleh NULL | `BARCODE`, `TBA`, `X` |
| `payment_terms` | `nvarchar(255)` |  boleh NULL | `X` |
| `column17` | `nvarchar(255)` |  boleh NULL | — |
| `fob` | `nvarchar(255)` |  boleh NULL | `46142`, `46172`, `X` |
| `price` | `decimal(18,4)` |  boleh NULL | `0.0000` |
| `column18` | `nvarchar(255)` |  boleh NULL | — |
| `column19` | `nvarchar(255)` |  boleh NULL | — |
| `cmt` | `nvarchar(255)` |  boleh NULL | `1 WEEK AFTER SHPMENT`, `NEXT DAY AFTER SWA GET PAYMENT FROM K...`, `30 DAYS AFTER RECON` |
| `price20` | `nvarchar(255)` |  boleh NULL | `1.46`, `1.52`, `2.66` |
| `column21` | `nvarchar(255)` |  boleh NULL | — |
| `sample2` | `nvarchar(255)` |  boleh NULL | — |
| `smv` | `decimal(18,4)` |  boleh NULL | `0.0000`, `1064177400.0000`, `147600000.0000` |
| `planned_qty` | `decimal(18,2)` | Planned kuantitas · boleh NULL | `0.00`, `0.80`, `1.33` |
| `sewing_start_date` | `date` | Sewing mulai tanggal · boleh NULL | `1970-01-01`, `1913-09-10`, `2045-08-20` |
| `remarks` | `nvarchar(max)` | Catatan tambahan. · boleh NULL | `CANCEL`, `B/DOWN QTY WILL SHARE ONCE FINISH CHE...`, `ORDER TRANSFER FROM VN` |
| `import_batch` | `nvarchar(255)` |  boleh NULL | `4b8763b2-794f-4b40-9195-2b320d19b2a1` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-27T14:06:01.080000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-27T14:06:01.080000` |
| `column1` | `varchar(255)` |  boleh NULL | — |
| `column2` | `varchar(255)` |  boleh NULL | `8836.76`, `14512`, `10000` |
| `column3` | `varchar(255)` |  boleh NULL | — |
| `catatan` | `nvarchar(max)` |  boleh NULL | `1283.9689004073`, `1062.5`, `1149.2957746479` |
| `sub_ref` | `varchar(255)` |  boleh NULL | `C4`, `C4a`, `B5` |
| `season` | `varchar(255)` |  boleh NULL | — |

#### `mon_prod_lines`

- **Fungsi:** Line produksi per order (57.063 baris).
- **Jumlah baris (16 Nov 2026):** 57.063
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** code_prod bisa memuat kode OCF tanpa kata 'OCF'.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `line_id` | `nvarchar(255)` | Line ID | `LN-0725-000001`, `LN-0725-000002`, `LN-0725-000003` |
| `code_prod` | `nvarchar(255)` | Kode produksi/OCF. Bisa memuat OCF tanpa kata 'OCF' (mis. 'CPO 25091 266C0001'). · boleh NULL | `CPO 25019 CONT 1`, `CPO 25021 CONT 1`, `CPO 25032` |
| `department_id` | `nvarchar(255)` | Departemen ID · boleh NULL | `Cutting`, `Sewing`, `Packing` |
| `tgl_produksi` | `date` | Tanggal produksi · boleh NULL | `2025-07-18`, `2025-07-15`, `2025-07-25` |
| `barang_code` | `nvarchar(255)` | Kode barang, join ke `mon_ms_barangs.barang_code`. · boleh NULL | `01SCRP00001`, `02FLBW00001`, `02FMBW00036` |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `barang_category` | `nvarchar(255)` | Barang kategori · boleh NULL | `Barang Jadi`, `Bahan Setengah Jadi`, `Scrap` |
| `jumlah` | `decimal(18,4)` | Jumlah. · boleh NULL | `290.0000`, `125.0000`, `202.0000` |
| `destination` | `nvarchar(255)` |  boleh NULL | `WAREHOUSE`, `Sewing`, `Packing` |
| `no_surat_jalan` | `nvarchar(255)` | Nomor surat jalan · boleh NULL | `SJ/CUTTING/0725/000001`, `SJ/CUTTING/0725/000002`, `SJ/CUTTING/0725/000003` |
| `create_by` | `nvarchar(255)` | Pembuat record (user). · boleh NULL | `LIANA ADM PRODUKSI`, `Chutex`, `CHUTEX` |
| `create_date` | `nvarchar(255)` | Waktu record dibuat (datetime). · boleh NULL | `2025-07-31 13:08`, `2025-07-31 13:13`, `2025-07-31 13:16` |
| `digit` | `tinyint` |  · default `'2'` | `0`, `2` |
| `total_nilai` | `decimal(18,4)` |  boleh NULL | `0.0000`, `1035349.9750`, `10765150.1376` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:15:35.667000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:15:35.667000` |

#### `mon_prod_qc`

- **Fungsi:** Hasil QC produksi.
- **Jumlah baris (16 Nov 2026):** 1
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `13` |
| `code_prod` | `nvarchar(100)` | Kode produksi/OCF. Bisa memuat OCF tanpa kata 'OCF' (mis. 'CPO 25091 266C0001'). | `266C0128` |
| `department_id` | `nvarchar(50)` | Departemen ID | `Packing` |
| `jumlah` | `int` | Jumlah. · default `'0'` | `2279` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-01T08:28:00.047000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-01T08:28:00.047000` |
| `cpo` | `nvarchar(100)` |  boleh NULL | — |

#### `mon_purchase_orders`

- **Fungsi:** Purchase order produksi (45 kolom, 52.203 baris).
- **Jumlah baris (16 Nov 2026):** 52.203
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `klaim_fsc` | `nvarchar(255)` |  boleh NULL | `03`, `01`, `PRD` |
| `dt_po_id` | `nvarchar(255)` | Dt PO ID · boleh NULL | `POD-0725-000002`, `POD-0725-000003`, `POD-0725-000005` |
| `po_id` | `nvarchar(255)` | ID purchase order (kunci join header-detail). · boleh NULL | `PO-0725-000045`, `PO-0725-000044`, `PO-0725-000055` |
| `jenis_po` | `nvarchar(255)` | Jenis PO · boleh NULL | `PO`, `MATERIAL SUPPLY`, `SEWA` |
| `no_po` | `nvarchar(255)` | Nomor purchase order. · boleh NULL | `PO/AKTIVA/2025/VII/0137`, `PO/AKTIVA/2025/VII/0136`, `PO/AKTIVA/2025/VII/0127` |
| `tgl_po` | `date` | Tanggal PO. · boleh NULL | `2025-07-14`, `2025-06-25`, `2025-06-04` |
| `supplier_name` | `nvarchar(255)` | Nama supplier. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `barang_code` | `nvarchar(255)` | Kode barang, join ke `mon_ms_barangs.barang_code`. · boleh NULL | `03SLOC00001`, `01CRIM00004`, `01CRIM00003` |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `satuan_order` | `nvarchar(255)` | Satuan pada baris order. · boleh NULL | `CON`, `PCE`, `KGM` |
| `uraian` | `nvarchar(255)` | Uraian/nama barang pada PO. Deskripsi item bisa juga ada di `spesifikasi`. · boleh NULL | ``, `25024`, `25027` |
| `spesifikasi` | `nvarchar(max)` | Spesifikasi barang; sering memuat kata kunci yang tidak ada di `uraian`. · boleh NULL | `UK. 2400 X 1200 X 850 MM  `, `UK. 800 X 600 X 850 X 950 MM  `, `000 WHITE STYLE : BB2WIA6S/CPO : 2502...` |
| `jumlah_order` | `decimal(18,4)` |  boleh NULL · default `'0'` | `24.0000`, `1.0000`, `200.0000` |
| `harga_satuan` | `decimal(18,4)` | Harga per satuan. · boleh NULL · default `'0'` | `7250.0000`, `14750.0000`, `19500.0000` |
| `harga_total` | `decimal(18,4)` | Harga total baris (qty x harga satuan). · boleh NULL · default `'0'` | `354000.0000`, `531000.0000`, `177000.0000` |
| `harga_fob` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000` |
| `total_fob` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000` |
| `ppn` | `nvarchar(255)` |  boleh NULL | `07`, `04`, `` |
| `pph` | `nvarchar(255)` |  boleh NULL | ``, `0.5` |
| `discount` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000`, `13.0000` |
| `biaya` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000` |
| `valas` | `nvarchar(255)` | Mata uang asing (valuta asing). · boleh NULL | `IDR`, `USD` |
| `note` | `nvarchar(max)` | Catatan · boleh NULL | `STYLE : AB1QMA6S`, `STYLE : BB2WIA6S`, `CPO : 25024 / OCF  256P0019, STYLE : ...` |
| `create_by` | `nvarchar(255)` | Pembuat record (user). · boleh NULL | `ALIFIA  ADM PURCHASING`, `NINO ADM PURCHASING`, `IBU WIWIK HOD PURCHASING` |
| `create_date` | `nvarchar(255)` | Waktu record dibuat (datetime). · boleh NULL | `2025-07-23 13:22`, `2025-07-23 12:52`, `2025-07-25 16:48` |
| `ncv` | `nvarchar(255)` |  boleh NULL | `No` |
| `jumlah_doc` | `decimal(18,4)` | Jumlah dokumen. · boleh NULL · default `'0'` | `0.0000`, `24.0000`, `20.0000` |
| `total_in` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000`, `24.0000`, `20.0000` |
| `out_req` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000`, `50.0000`, `168.0000` |
| `total_req` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000`, `50.0000`, `168.0000` |
| `out_prod` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000`, `168.0000`, `69.0000` |
| `total_prod` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000`, `168.0000`, `69.0000` |
| `out_doc` | `decimal(18,4)` | Out dokumen · boleh NULL · default `'0'` | `0.0000`, `1091.4250`, `20.0000` |
| `total_doc` | `decimal(18,4)` | Total dokumen · boleh NULL · default `'0'` | `0.0000`, `1091.4250`, `20.0000` |
| `digit` | `int` |  boleh NULL · default `'2'` | `2`, `0` |
| `total_order` | `decimal(18,4)` |  boleh NULL · default `'0'` | `58446495.3816` |
| `total_harga` | `decimal(18,4)` |  boleh NULL · default `'0'` | `82417810588.4422` |
| `sisa` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000`, `24.0000`, `8.0000` |
| `total_sisa` | `decimal(18,4)` |  boleh NULL · default `'0'` | `58446495.3816`, `58446471.3816`, `58446295.3816` |
| `total_gudang` | `decimal(18,4)` |  boleh NULL · default `'0'` | `0.0000`, `24.0000`, `1.0000` |
| `total_wip` | `decimal(18,4)` |  boleh NULL · default `'0'` | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:13:58.890000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:13:58.890000` |
| `tgl_pengiriman` | `datetime` | Tanggal pengiriman · boleh NULL | `2025-07-23T00:00:00`, `2025-07-22T00:00:00`, `2025-07-04T00:00:00` |

#### `mon_rekon_lock_fabrics`

- **Fungsi:** Lock rekonsiliasi kain.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).
- **Foreign key:** `lock_id` → `mon_rekon_locks.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `lock_id` | `bigint` | **FK** → `mon_rekon_locks.id` · Lock ID | — |
| `barang_code` | `nvarchar(100)` | Kode barang, join ke `mon_ms_barangs.barang_code`. · boleh NULL | — |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | — |
| `need_qty` | `decimal(18,2)` | Need kuantitas · default `'0'` | — |
| `order_qty` | `decimal(18,2)` | Order kuantitas · default `'0'` | — |
| `received_qty` | `decimal(18,2)` | Received kuantitas · default `'0'` | — |
| `out_req_qty` | `decimal(18,2)` | Out req kuantitas · default `'0'` | — |
| `out_doc_qty` | `decimal(18,2)` | Out dokumen kuantitas · default `'0'` | — |
| `stock_qty` | `decimal(18,2)` | Stock kuantitas · default `'0'` | — |
| `harga_total` | `decimal(18,2)` | Harga total baris (qty x harga satuan). · default `'0'` | — |
| `order_pct` | `decimal(9,2)` |  · default `'0'` | — |
| `received_pct` | `decimal(9,2)` |  · default `'0'` | — |
| `out_req_pct` | `decimal(9,2)` |  · default `'0'` | — |
| `out_doc_pct` | `decimal(9,2)` | Out dokumen pct · default `'0'` | — |
| `stock_pct` | `decimal(9,2)` |  · default `'0'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `mon_rekon_locks`

- **Fungsi:** Lock rekonsiliasi (52 kolom).
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `lock_date` | `date` | Lock tanggal | — |
| `lock_type` | `nvarchar(20)` | Lock jenis | — |
| `ocf_no` | `nvarchar(450)` | Nomor OCF. | — |
| `buyer` | `nvarchar(150)` | Nama buyer/pembeli. · boleh NULL | — |
| `style` | `nvarchar(255)` |  boleh NULL | — |
| `sub_ref` | `nvarchar(150)` |  boleh NULL | — |
| `uraian` | `nvarchar(150)` | Uraian/nama barang pada PO. Deskripsi item bisa juga ada di `spesifikasi`. · boleh NULL | — |
| `negara` | `nvarchar(100)` |  boleh NULL | — |
| `filters_json` | `nvarchar(max)` |  boleh NULL | — |
| `qty_contract` | `decimal(18,2)` | Kuantitas contract · default `'0'` | — |
| `qty_cutting` | `decimal(18,2)` | Kuantitas cutting · default `'0'` | — |
| `qty_sewing` | `decimal(18,2)` | Kuantitas sewing · default `'0'` | — |
| `qty_qc` | `decimal(18,2)` | Kuantitas qc · default `'0'` | — |
| `qty_packing` | `decimal(18,2)` | Kuantitas packing · default `'0'` | — |
| `qty_ct_pat` | `decimal(18,2)` | Kuantitas ct pat · default `'0'` | — |
| `qty_dest_sewing` | `decimal(18,2)` | Kuantitas dest sewing · default `'0'` | — |
| `qty_dest_packing` | `decimal(18,2)` | Kuantitas dest packing · default `'0'` | — |
| `qty_sabkon` | `decimal(18,2)` | Kuantitas sabkon · default `'0'` | — |
| `qty_sabkon_warehouse` | `decimal(18,2)` | Kuantitas sabkon warehouse · default `'0'` | — |
| `qty_shipment` | `decimal(18,2)` | Kuantitas shipment · default `'0'` | — |
| `loss_cutting` | `decimal(18,2)` |  · default `'0'` | — |
| `loss_sewing` | `decimal(18,2)` |  · default `'0'` | — |
| `loss_packing` | `decimal(18,2)` |  · default `'0'` | — |
| `total_loss` | `decimal(18,2)` |  · default `'0'` | — |
| `total_loss_pct` | `decimal(9,2)` |  · default `'0'` | — |
| `shipment_loss` | `decimal(18,2)` |  · default `'0'` | — |
| `balance_garment_stock` | `decimal(18,2)` | Saldo garment stock · default `'0'` | — |
| `short_ship_pcs` | `decimal(18,2)` |  · default `'0'` | — |
| `sisa_fabric_kg` | `decimal(18,2)` |  · default `'0'` | — |
| `sisa_fabric_value` | `decimal(18,2)` | Sisa fabric nilai · default `'0'` | — |
| `cm_per_pcs` | `decimal(18,4)` |  · default `'0'` | — |
| `grade_a` | `decimal(18,2)` |  boleh NULL | — |
| `grade_b` | `decimal(18,2)` |  boleh NULL | — |
| `grade_c` | `decimal(18,2)` |  boleh NULL | — |
| `grade_imported_at` | `datetime` | Grade imported pada · boleh NULL | — |
| `grade_imported_by` | `nvarchar(100)` | Grade imported oleh · boleh NULL | — |
| `locked_by` | `nvarchar(100)` | Locked oleh · boleh NULL | — |
| `locked_at` | `datetime` | Locked pada · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `note_prioritas` | `smallint` | Catatan prioritas · boleh NULL | — |
| `note_status` | `nvarchar(20)` | Catatan status · boleh NULL | — |
| `note_masalah` | `nvarchar(max)` | Catatan masalah · boleh NULL | — |
| `note_tindakan` | `nvarchar(max)` | Catatan tindakan · boleh NULL | — |
| `note_pic` | `nvarchar(150)` | Catatan pic · boleh NULL | — |
| `note_target` | `nvarchar(100)` | Catatan target · boleh NULL | — |
| `note_estimasi` | `decimal(18,2)` | Catatan estimasi · boleh NULL | — |
| `note_imported_at` | `datetime` | Catatan imported pada · boleh NULL | — |
| `note_imported_by` | `nvarchar(100)` | Catatan imported oleh · boleh NULL | — |
| `lock_mode` | `nvarchar(10)` |  · default `'ocf'` | — |
| `cpo_no` | `nvarchar(150)` | Cpo nomor · boleh NULL | — |

#### `mon_rekonsiliasis`

- **Fungsi:** Rekonsiliasi produksi (31 kolom, 37.450 baris).
- **Jumlah baris (16 Nov 2026):** 37.450
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `dt_po_id` | `nvarchar(255)` | Dt PO ID | `POD-0725-000002`, `POD-0725-000003`, `POD-0725-000005` |
| `valas` | `nvarchar(255)` | Mata uang asing (valuta asing). · boleh NULL | `IDR`, `USD` |
| `no_po` | `nvarchar(255)` | Nomor purchase order. · boleh NULL | `PO/IMPORT/2025/VII/0156`, `PO/LOKAL/2025/VII/0136-1`, `PO/AKTIVA/2025/VII/0137` |
| `jenis_po` | `nvarchar(255)` | Jenis PO · boleh NULL | `PO`, `MATERIAL SUPPLY` |
| `tgl_po` | `date` | Tanggal PO. · boleh NULL | `2025-07-15`, `2025-07-14`, `2025-06-25` |
| `termin` | `nvarchar(255)` |  boleh NULL | `Credit 30`, `CBD`, `COD` |
| `tgl_pengiriman` | `date` | Tanggal pengiriman · boleh NULL | `2025-07-23`, `2025-07-16`, `2025-08-06` |
| `supplier_name` | `nvarchar(255)` | Nama supplier. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `barang_code` | `nvarchar(255)` | Kode barang, join ke `mon_ms_barangs.barang_code`. · boleh NULL | `03SLOC00001`, `25SIMP00001`, `01CRIM00004` |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `satuan_order` | `nvarchar(255)` | Satuan pada baris order. · boleh NULL | `CON`, `PCE`, `KGM` |
| `satuan_code` | `nvarchar(255)` | Kode satuan (pcs, kg, yard). · boleh NULL | `CON`, `PCE`, `KGM` |
| `klaim_fsc` | `nvarchar(255)` |  boleh NULL | `03`, `25`, `01` |
| `uraian` | `nvarchar(255)` | Uraian/nama barang pada PO. Deskripsi item bisa juga ada di `spesifikasi`. · boleh NULL | `25024`, `25027`, `` |
| `spesifikasi` | `nvarchar(255)` | Spesifikasi barang; sering memuat kata kunci yang tidak ada di `uraian`. · boleh NULL | `STYLE :BB2WIA6S; CPO : 25026/OCF : 25...`, `000 WHITE STYLE : BB2WIA6S/CPO : 2502...`, `000 WHITE STYLE = AB1QMAS; CPO : 2502...` |
| `ncv` | `nvarchar(255)` |  boleh NULL | `No` |
| `jumlah_order` | `decimal(18,4)` |  · default `'0'` | `500.0000`, `50.0000`, `168.0000` |
| `jumlah_doc` | `decimal(18,4)` | Jumlah dokumen. · default `'0'` | `500.0000`, `50.0000`, `168.0000` |
| `out_req` | `decimal(18,4)` |  · default `'0'` | `500.0000`, `50.0000`, `69.0000` |
| `out_prod` | `decimal(18,4)` |  · default `'0'` | `0.0000`, `54.0000`, `98.0000` |
| `sisa` | `decimal(18,4)` |  · default `'0'` | `0.0000`, `-1.0000`, `-118.0000` |
| `saldo_wip` | `decimal(18,4)` |  · default `'0'` | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `digit` | `tinyint` |  · default `'2'` | `2`, `0` |
| `out_doc` | `decimal(18,4)` | Out dokumen · default `'0'` | `0.0000`, `0.2100`, `1091.4250` |
| `create_by` | `nvarchar(255)` | Pembuat record (user). · boleh NULL | `ALIFIA  ADM PURCHASING`, `NINO ADM PURCHASING`, `IBU WIWIK HOD PURCHASING` |
| `create_date` | `nvarchar(255)` | Waktu record dibuat (datetime). · boleh NULL | `2025-07-28 09:03`, `2025-08-07 09:17`, `2025-07-23 13:22` |
| `harga_total` | `decimal(18,4)` | Harga total baris (qty x harga satuan). · default `'0'` | `1800000.0000`, `1100000.0000`, `450.0000` |
| `saldo_gudang` | `decimal(18,4)` |  · default `'0'` | `0.0000`, `18.0000`, `2.0000` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:15:06.023000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:15:06.023000` |

#### `mon_shipments`

- **Fungsi:** Pengiriman/shipment (37 kolom).
- **Jumlah baris (16 Nov 2026):** 8.916
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `row_key` | `nvarchar(64)` | Baris key | `019cd13301f60af600ba29e1b1056fb1`, `027a74f189ee3020377eeb6a478b4abb`, `02d76422cab20e9acb5554fff9561be5` |
| `hs_code` | `nvarchar(255)` | Hs kode · boleh NULL | — |
| `doc_id` | `nvarchar(255)` | Dokumen ID · boleh NULL | `OT-0825-000028`, `OT-0825-000043`, `OT-0825-000007` |
| `jenis_doc` | `nvarchar(255)` | Jenis dokumen · boleh NULL | `BC 4.1`, `BC 2.6.1`, `BC 2.7` |
| `no_aju` | `nvarchar(255)` | Nomor aju · boleh NULL | `000027-278736-20250815-000005`, `000261-278736-20250813-000003`, `000261-278736-20250805-000001` |
| `no_doc` | `nvarchar(255)` | Nomor dokumen. · boleh NULL | `020318`, `032592`, `031639` |
| `tgl_doc` | `date` | Tanggal dokumen. · boleh NULL | `2025-08-13`, `2025-08-19`, `2025-08-15` |
| `no_bukti` | `nvarchar(255)` | Nomor bukti · boleh NULL | `BBK261/08/25/0003`, `BBK27/08/25/0007`, `BBK261/08/25/0001` |
| `tgl_bukti` | `date` | Tanggal bukti · boleh NULL | `2025-08-13`, `2025-08-19`, `2025-08-15` |
| `jenis_ps` | `nvarchar(255)` |  boleh NULL | `Pengembalian Sewa`, `Terima`, `Retur` |
| `no_ps` | `nvarchar(255)` | Nomor ps · boleh NULL | `SO/08/25/0027`, `CPO 25031 OCF 256P0024`, `CPO 25032 OCF 256P0025` |
| `no_invoice` | `nvarchar(255)` | Nomor invoice · boleh NULL | `INV/08/25/0028`, `SJ/PIPING/08/25/0003`, `SJ/PIPING/08/25/0001` |
| `file_number` | `nvarchar(255)` | Path/nama berkas NUMBER di server. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `supplier_name` | `nvarchar(255)` | Nama supplier. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `barang_code` | `nvarchar(255)` | Kode barang, join ke `mon_ms_barangs.barang_code`. · boleh NULL | `01CRIM00004`, `PKSA022500001`, `PKSA032500001` |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `barang_category` | `nvarchar(255)` | Barang kategori · boleh NULL | `Mesin`, `Barang Jadi`, `Bahan Baku Import` |
| `satuan_doc` | `nvarchar(255)` | Satuan dokumen · boleh NULL | `UNT`, `PCE`, `KGM` |
| `jumlah_doc` | `decimal(18,4)` | Jumlah dokumen. · default `'0'` | `3.0000`, `5.0000`, `1.0000` |
| `valas` | `nvarchar(255)` | Mata uang asing (valuta asing). · boleh NULL | `IDR`, `USD` |
| `nilai_barang` | `decimal(18,4)` |  · default `'0'` | `0.0000`, `8836.7600`, `68807.3500` |
| `nilai_fob` | `decimal(18,4)` |  · default `'0'` | `0.0000`, `186000.0000`, `310000.0000` |
| `jumlah_aktual` | `decimal(18,4)` |  · default `'0'` | `3.0000`, `5.0000`, `1.0000` |
| `keterangan` | `nvarchar(max)` | Keterangan bebas. · boleh NULL | ``, `Pengembalian Mesin Sewa` |
| `ps_id` | `nvarchar(255)` | Ps ID · boleh NULL | `SCK-0725-000001`, `ACG`, `SO-0825-000035` |
| `spesifikasi` | `nvarchar(255)` | Spesifikasi barang; sering memuat kata kunci yang tidak ada di `uraian`. · boleh NULL | `COL. BARK`, `COL. CABERNET`, `COL. SNOW WHITE` |
| `uraian` | `nvarchar(255)` | Uraian/nama barang pada PO. Deskripsi item bisa juga ada di `spesifikasi`. · boleh NULL | `25024`, `25027`, `25030` |
| `berat` | `decimal(18,4)` | Berat badan (kg). · boleh NULL | `229.5000`, `400.0000`, `200.0000` |
| `jumlah_barang` | `decimal(18,4)` |  · default `'0'` | `3.0000`, `5.0000`, `1.0000` |
| `satuan_order` | `nvarchar(255)` | Satuan pada baris order. · boleh NULL | `UNT`, `PCE`, `KGM` |
| `satuan_code` | `nvarchar(255)` | Kode satuan (pcs, kg, yard). · boleh NULL | `UNT`, `PCE`, `KGM` |
| `dt_so_id` | `nvarchar(255)` | Dt so ID · boleh NULL | `SOD-0825-000126`, `SOD-0825-000127`, `SOD-0925-000001` |
| `acc_bukti` | `nvarchar(255)` |  boleh NULL | `BPI-2025-08-000005`, `BPI-2025-08-000003`, `BPI-2025-08-000004` |
| `acc_tgl` | `datetime` | Acc tanggal · boleh NULL | `2025-08-13T00:00:00`, `2025-08-15T00:00:00`, `2025-07-28T00:00:00` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:15:41.670000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:15:41.670000` |

#### `mon_stage_remarks`

- **Fungsi:** Catatan per tahap produksi (24 baris).
- **Jumlah baris (16 Nov 2026):** 24
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `23`, `26`, `31` |
| `ocf_no` | `nvarchar(100)` | Nomor OCF. | `266C0029`, `266C0051`, `266C0054` |
| `department_id` | `nvarchar(50)` | Departemen ID | `Sewing`, `Packing`, `Balance Garment Stock` |
| `remark` | `nvarchar(max)` | Catatan tambahan. · boleh NULL | `panel reject`, `grade B`, `grade B already loading to warehouse` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-01T08:22:24.373000`, `2026-10-02T11:42:42.987000`, `2026-10-02T15:12:21.147000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-01T08:22:24.373000`, `2026-10-02T11:42:42.987000`, `2026-10-02T15:12:21.147000` |
| `cpo` | `nvarchar(100)` |  boleh NULL | — |

#### `mon_stock_fisik`

- **Fungsi:** Stok fisik (22 kolom).
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `tipe` | `nvarchar(20)` | Tipe/jenis record. | — |
| `dt_doc_id` | `nvarchar(100)` | Dt dokumen ID · boleh NULL | — |
| `barang_code` | `nvarchar(50)` | Kode barang, join ke `mon_ms_barangs.barang_code`. · boleh NULL | — |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | — |
| `satuan_code` | `nvarchar(50)` | Kode satuan (pcs, kg, yard). · boleh NULL | — |
| `barang_category` | `nvarchar(100)` | Barang kategori · boleh NULL | — |
| `spesifikasi` | `nvarchar(max)` | Spesifikasi barang; sering memuat kata kunci yang tidak ada di `uraian`. · boleh NULL | — |
| `jenis_doc` | `nvarchar(500)` | Jenis dokumen · boleh NULL | — |
| `keterangan` | `nvarchar(max)` | Keterangan bebas. · boleh NULL | — |
| `jenis_doc1` | `nvarchar(100)` |  boleh NULL | — |
| `no_doc` | `nvarchar(100)` | Nomor dokumen. · boleh NULL | — |
| `tgl_doc` | `datetime` | Tanggal dokumen. · boleh NULL | — |
| `saldo` | `decimal(24,7)` |  · default `'0'` | — |
| `harga_satuan` | `decimal(24,7)` | Harga per satuan. · boleh NULL | — |
| `harga_satuan_fob` | `decimal(24,7)` |  boleh NULL | — |
| `uraian` | `nvarchar(max)` | Uraian/nama barang pada PO. Deskripsi item bisa juga ada di `spesifikasi`. · boleh NULL | — |
| `nilai_harga` | `decimal(28,7)` |  boleh NULL | — |
| `nilai_harga_fob` | `decimal(28,7)` |  boleh NULL | — |
| `digit` | `tinyint` |  · default `'2'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `mon_subkons`

- **Fungsi:** Data subkontraktor (98 baris).
- **Jumlah baris (16 Nov 2026):** 98
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `id_order` | `nvarchar(255)` | ID order | `SCK-0126-000001`, `SCK-0226-000001`, `SCK-0326-000001` |
| `no_order` | `nvarchar(255)` | Nomor order · boleh NULL | `004/CII/IX/2025`, `002/CII/IX/2025`, `005/CII/IX/2025` |
| `tgl_order` | `date` | Tanggal order · boleh NULL | `2025-07-14`, `2025-09-10`, `2026-06-22` |
| `jenis` | `nvarchar(255)` | Jenis/kategori record. | `Kirim Subkon`, `Terima Subkon` |
| `supplier_code` | `nvarchar(255)` | Kode supplier, join ke master supplier. · boleh NULL | `SWA`, `JCT`, `MJY` |
| `supplier_name` | `nvarchar(255)` | Nama supplier. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `qty_material_order` | `decimal(18,4)` | Kuantitas material order · default `'0'` | `1.0000`, `2.0000`, `13.4400` |
| `qty_result_order` | `decimal(18,4)` | Kuantitas result order · default `'0'` | `2.0000`, `1.0000`, `1260.0000` |
| `qty_material_aktual` | `decimal(18,4)` | Kuantitas material aktual · default `'0'` | `0.0000`, `1.0000`, `10.0000` |
| `qty_result_aktual` | `decimal(18,4)` | Kuantitas result aktual · default `'0'` | `0.0000`, `2.0000`, `1.0000` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:17:06.670000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:17:06.670000` |

#### `mon_work_orders`

- **Fungsi:** Work order produksi (39 kolom, 62.816 baris).
- **Jumlah baris (16 Nov 2026):** 62.816
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `wo_id` | `nvarchar(255)` | Wo ID | `BOM-0126-000387`, `BOM-0126-000388`, `BOM-0126-000389` |
| `prod_id` | `nvarchar(255)` | Prod ID · boleh NULL | `PL-0126-000016`, `PL-0126-000017`, `PL-0126-000018` |
| `code_prod` | `nvarchar(255)` | Kode produksi/OCF. Bisa memuat OCF tanpa kata 'OCF' (mis. 'CPO 25091 266C0001'). · boleh NULL | `CPO 25114 OCF 266K0009 STYLE 881293 /...`, `CPO 25114 OCF 266K0009 STYLE 881293 /...`, `CPO 25114 OCF 266K0009 STYLE 881293 /...` |
| `product_code` | `nvarchar(255)` | Product kode · boleh NULL | `02FLGP00001`, `01FMBW00001` |
| `jumlah_prod` | `decimal(18,4)` |  · default `'0'` | `234.0000`, `171.0000`, `195.0000` |
| `tgl_prod` | `date` | Tanggal prod · boleh NULL | `2026-01-07`, `2026-01-14` |
| `tgl_doc` | `date` | Tanggal dokumen. · boleh NULL | `2026-03-31`, `2026-04-30` |
| `target_hari` | `decimal(18,2)` |  boleh NULL | `2.79`, `0.81`, `2.04` |
| `note` | `nvarchar(max)` | Catatan · boleh NULL | `STYLE 881293 / P2276157/B2 
/ CA RET...`, `STYLE 881293 / P2276157/B2 
/ CA RET...`, `STYLE 881293 / P2276157/B2 
/ CA RET...` |
| `warehouse_id` | `nvarchar(255)` | Warehouse ID · boleh NULL | `CP-0000-000000` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `UnFinish` |
| `plan_create_by` | `nvarchar(255)` | Plan create oleh · boleh NULL | `NENA HOD MR`, `ULVI ADM MR` |
| `plan_create_date` | `datetime` | Plan create tanggal · boleh NULL | `2026-01-07T13:24:43.253000`, `2026-01-07T13:25:40.420000`, `2026-01-07T13:26:11.730000` |
| `plan_modify_by` | `nvarchar(255)` | Plan modify oleh · boleh NULL | `CHUTEX` |
| `plan_modify_date` | `datetime` | Plan modify tanggal · boleh NULL | `2026-02-16T09:57:05`, `2026-02-16T09:57:13`, `2026-02-16T09:57:22` |
| `flag_type` | `nvarchar(255)` | Penanda jenis · boleh NULL | `normal` |
| `product_name` | `nvarchar(255)` | Product nama · boleh NULL | `(disamarkan)`, `(disamarkan)` |
| `bom_prod_id` | `nvarchar(255)` | Bom prod ID · boleh NULL | `PL-0126-000016`, `PL-0126-000017`, `PL-0126-000018` |
| `barang_code` | `nvarchar(255)` | Kode barang, join ke `mon_ms_barangs.barang_code`. · boleh NULL | `07LLOC00001`, `03SLOC00001`, `25SLOC00002` |
| `uraian` | `nvarchar(255)` | Uraian/nama barang pada PO. Deskripsi item bisa juga ada di `spesifikasi`. · boleh NULL | `25113`, `25114`, `25146` |
| `spesifikasi` | `nvarchar(255)` | Spesifikasi barang; sering memuat kata kunci yang tidak ada di `uraian`. · boleh NULL | `CALLA LILY 451`, `JOKER LABEL - SEW IN TEAR AWAY`, `SEWING THREAD 150/1 (1 CONE - 5.000 Y...` |
| `departemen` | `nvarchar(255)` |  boleh NULL | `Packing`, `Sewing`, `Cutting` |
| `cons` | `decimal(18,6)` |  · default `'0'` | `1.000000`, `0.010000`, `0.020000` |
| `scrap_percent` | `decimal(18,4)` | Scrap persentase · default `'0'` | `0.0000`, `17.4000`, `18.7400` |
| `bom_create_by` | `nvarchar(255)` | Bom create oleh · boleh NULL | `NENA HOD MR`, `ULVI ADM MR` |
| `bom_create_date` | `datetime` | Bom create tanggal · boleh NULL | `2026-01-07T09:06:54.777000`, `2026-01-07T09:06:54.823000`, `2026-01-07T09:06:54.887000` |
| `bom_modify_by` | `nvarchar(255)` | Bom modify oleh · boleh NULL | `NENA HOD MR`, `NENA MR MANAGER`, `ULVI ADM MR` |
| `bom_modify_date` | `datetime` | Bom modify tanggal · boleh NULL | `2026-01-29T14:33:00`, `2026-01-07T09:19:00`, `2026-01-15T16:56:00` |
| `auto_create` | `nvarchar(255)` |  boleh NULL | `no` |
| `komponen` | `nvarchar(255)` |  boleh NULL | `02FLGP00001`, `01FMBW00001` |
| `bom_jumlah_prod` | `decimal(18,4)` |  · default `'0'` | `538.0000`, `189.0000`, `97.0000` |
| `barang_name` | `nvarchar(255)` | Nama barang. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `satuan_code` | `nvarchar(255)` | Kode satuan (pcs, kg, yard). · boleh NULL | `PCE`, `CON`, `KGM` |
| `request` | `decimal(18,4)` |  · default `'0'` | `0.0000`, `12.0000`, `234.0000` |
| `total` | `decimal(18,4)` | Total. · default `'0'` | `0.0000`, `12.0000`, `234.0000` |
| `actual_cons` | `decimal(18,6)` |  · default `'0'` | `1.000000`, `0.000000`, `0.011921` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-28T09:15:52.700000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-28T09:15:52.700000` |

### D8. Efisiensi, QC & Pelanggaran — 16 tabel

Efisiensi line/pad/cutting/heat, QC, pelanggaran, evaluasi kerja.

#### `cutting_efficiencies`

- **Fungsi:** Efisiensi proses cutting.
- **Jumlah baris (16 Nov 2026):** 26
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `11`, `12`, `13` |
| `period_id` | `nvarchar(255)` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `7`, `6`, `8` |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. · boleh NULL | — |
| `efficiency` | `decimal(5,2)` | Nilai efisiensi (persen). | `79.00`, `77.00`, `85.00` |
| `date` | `date` | Tanggal kejadian. | `2026-09-22`, `2026-09-23`, `2026-09-24` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-09-24T08:06:19.267000`, `2026-09-24T11:26:49.377000`, `2026-09-25T14:43:46.193000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-09-25T14:43:46.190000`, `2026-09-25T14:43:46.193000`, `2026-09-29T13:06:14.853000` |

#### `employee_6s_assignments`

- **Fungsi:** Penempatan karyawan pada program 6S.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `period_id` → `payroll_periods.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `period_id` | `bigint` | **FK** → `payroll_periods.id` · ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | — |
| `section_id` | `int` | Section ID | — |
| `inspector` | `nvarchar(255)` | Nama/ID pemeriksa. | — |
| `inspection_date` | `date` | Inspection tanggal | — |
| `total_score` | `decimal(10,2)` | Total skor · default `'0'` | — |
| `percentage` | `decimal(5,2)` | Persentase (0-100). · default `'0'` | — |
| `file_path` | `nvarchar(255)` | Path file di server. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `npk` | `varchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. · boleh NULL | — |

#### `employee_cutting_assignments`

- **Fungsi:** Penempatan karyawan di cutting.
- **Jumlah baris (16 Nov 2026):** 4.869
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `323`, `324`, `325` |
| `period_id` | `nvarchar(255)` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `6` |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00665`, `C-00675`, `C-00677` |
| `role` | `nvarchar(255)` | Nama role. | `spreading_auto`, `bundling`, `manual_cutter` |
| `start_date` | `date` | Tanggal mulai berlakunya record. | `2026-09-22`, `2026-09-23` |
| `end_date` | `date` | Tanggal berakhir berlakunya record. · boleh NULL | `2026-09-22`, `2026-09-23` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-09-24T08:06:19.287000`, `2026-09-24T08:06:19.297000`, `2026-09-24T08:06:19.327000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-09-24T08:06:19.287000`, `2026-09-24T08:06:19.297000`, `2026-09-24T08:06:19.327000` |

#### `employee_pad_assignments`

- **Fungsi:** Penempatan karyawan di PAD.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `period_id` | `nvarchar(255)` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | — |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `dept` | `nvarchar(255)` | Nama departemen (teks bebas pada beberapa tabel, bukan master). | — |
| `role` | `nvarchar(255)` | Nama role. | — |
| `start_date` | `date` | Tanggal mulai berlakunya record. | — |
| `end_date` | `date` | Tanggal berakhir berlakunya record. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `employee_qc_assignments`

- **Fungsi:** Penempatan karyawan di QC.
- **Jumlah baris (16 Nov 2026):** 826
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `9598`, `9599`, `9600` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00617`, `C-00906`, `C-02482` |
| `period_id` | `bigint` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `8` |
| `line_number` | `int` | Nomor line. · boleh NULL | `68`, `13`, `16` |
| `role` | `nvarchar(255)` | Nama role. · boleh NULL | `endline`, `fqc`, `inline` |
| `start_date` | `date` | Tanggal mulai berlakunya record. · boleh NULL | `2026-11-02`, `2026-11-03`, `2026-11-04` |
| `end_date` | `date` | Tanggal berakhir berlakunya record. · boleh NULL | `2026-11-02`, `2026-11-03`, `2026-11-04` |
| `work_hours` | `decimal(5,2)` | Kerja hours · boleh NULL | `8.00`, `9.00` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). · boleh NULL | — |
| `section_id` | `int` | Section ID · boleh NULL | — |
| `buyer` | `nvarchar(255)` | Nama buyer/pembeli. · boleh NULL | `MUJI` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-13T15:07:18.277000`, `2026-11-13T15:07:18.243000`, `2026-11-13T15:07:18.257000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-13T15:07:18.277000`, `2026-11-13T15:07:18.243000`, `2026-11-13T15:07:18.257000` |
| `third_party` | `nvarchar(255)` |  boleh NULL | — |

#### `employee_violations`

- **Fungsi:** Pelanggaran karyawan per periode (persentase).
- **Jumlah baris (16 Nov 2026):** 142
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `period_id` | `bigint` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `6`, `3`, `7` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00901`, `C-00891`, `C-00898` |
| `percentage` | `decimal(5,2)` | Persentase (0-100). · default `'0'` | `10.00`, `30.00`, `50.00` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-01T15:36:45.127000`, `2026-07-01T15:40:35.937000`, `2026-07-01T15:53:08.477000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-01T15:36:45.127000`, `2026-07-01T15:40:35.937000`, `2026-07-01T15:53:08.477000` |

#### `evaluation_employee`

- **Fungsi:** Jawaban/skor evaluasi karyawan.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `jobscope_id` | `nvarchar(255)` | Jobscope ID | — |
| `score` | `int` | Nilai/skor. · default `'0'` | — |
| `evaluation_date` | `datetime` | Evaluation tanggal · boleh NULL | — |
| `employee_question` | `nvarchar(255)` | Karyawan question · boleh NULL | — |
| `employee_answer` | `nvarchar(255)` | Karyawan answer · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `evaluation_jobscope`

- **Fungsi:** Master job scope untuk evaluasi kerja (job_code, qr_code).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `job_code` | `nvarchar(255)` | Job kode · boleh NULL | — |
| `job_name` | `nvarchar(255)` | Job nama · boleh NULL | — |
| `description` | `nvarchar(max)` | Deskripsi. · boleh NULL | — |
| `qr_code` | `nvarchar(max)` | Qr kode · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `evaluation_questionnaire`

- **Fungsi:** Bank pertanyaan evaluasi per job scope (pilihan a-d).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `jobscope_id` | `nvarchar(255)` | Jobscope ID | — |
| `question` | `nvarchar(max)` | Teks pertanyaan. | — |
| `optiona` | `nvarchar(max)` | Pilihan jawaban A. | — |
| `optionb` | `nvarchar(max)` | Pilihan jawaban B. | — |
| `optionc` | `nvarchar(max)` | Pilihan jawaban C. | — |
| `optiond` | `nvarchar(max)` | Pilihan jawaban D. | — |
| `correct_answer` | `nvarchar(max)` | Kunci jawaban benar. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `heat_efficiencies`

- **Fungsi:** Efisiensi proses heat press.
- **Jumlah baris (16 Nov 2026):** 3.851
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `603`, `604`, `605` |
| `period_id` | `nvarchar(255)` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `3` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00666`, `C-00684`, `C-01246` |
| `role` | `nvarchar(255)` | Nama role. | `operator` |
| `efficiency` | `decimal(5,2)` | Nilai efisiensi (persen). · boleh NULL | `0.00`, `58.00`, `49.00` |
| `piece` | `decimal(10,2)` |  boleh NULL | `1052.00`, `1130.00`, `1229.00` |
| `date` | `date` | Tanggal kejadian. | `2026-06-02`, `2026-06-03`, `2026-06-04` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-25T15:02:59.790000`, `2026-06-25T15:02:59.797000`, `2026-06-25T15:02:59.800000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-02T08:59:40.387000`, `2026-07-02T08:59:40.397000`, `2026-07-02T08:59:40.477000` |
| `tim` | `int` |  boleh NULL | — |
| `group` | `nvarchar(20)` |  boleh NULL | — |

#### `line_efficiencies`

- **Fungsi:** Efisiensi line produksi per periode.
- **Jumlah baris (16 Nov 2026):** 415
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `106`, `107`, `108` |
| `period_id` | `nvarchar(255)` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `6`, `3`, `4` |
| `line_number` | `bigint` | Nomor line. | `25`, `13`, `8` |
| `efficiency` | `decimal(5,2)` | Nilai efisiensi (persen). | `85.00`, `70.00`, `50.00` |
| `date` | `date` | Tanggal kejadian. | `2026-06-03`, `2026-06-05`, `2026-09-25` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-15T09:46:06.073000`, `2026-06-15T09:46:06.077000`, `2026-06-23T14:04:48.597000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-23T14:04:48.590000`, `2026-06-23T14:04:48.597000`, `2026-06-23T14:04:48.600000` |
| `days` | `bigint` | Jumlah hari. · boleh NULL | `4`, `5`, `3` |

#### `line_jumper_operations`

- **Fungsi:** Operasi line jumper.
- **Jumlah baris (16 Nov 2026):** 62
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `100`, `101`, `102` |
| `period_id` | `bigint` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `7` |
| `npk` | `nvarchar(50)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00299`, `C-03261`, `C-00109` |
| `operation` | `nvarchar(255)` | Nama operasi. | `2. CLOSING SIDE SEAM`, `1. ATTACH SLEEVE TO BODY`, `2. RUNNING NECK RIB` |
| `operation_key` | `nvarchar(255)` | Kunci unik operasi. | `CLOSING SIDE SEAM`, `HEMING SLEEVE`, `ATTACH SLEEVE TO BODY` |
| `machine` | `nvarchar(100)` |  boleh NULL | `4TOL`, `SN`, `OVD` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-10-23T15:13:51.447000`, `2026-10-23T15:13:51.497000`, `2026-10-23T15:13:51.577000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-23T15:13:51.497000`, `2026-10-23T15:13:51.577000`, `2026-11-04T08:20:52.057000` |

#### `pad_efficiencies`

- **Fungsi:** Efisiensi PAD (2.609 baris).
- **Jumlah baris (16 Nov 2026):** 2.609
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `period_id` | `nvarchar(255)` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `3` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00656`, `C-01230`, `C-01249` |
| `role` | `nvarchar(255)` | Nama role. | `operator`, `helper`, `inkmaking` |
| `efficiency` | `decimal(5,2)` | Nilai efisiensi (persen). · boleh NULL | `0.00`, `23.00`, `10.00` |
| `piece` | `decimal(10,2)` |  boleh NULL | `2021.00`, `1018.00`, `1020.00` |
| `date` | `date` | Tanggal kejadian. | `2026-06-02`, `2026-06-03`, `2026-06-04` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-06-25T11:32:06.997000`, `2026-06-25T11:32:07.007000`, `2026-06-25T11:32:07.017000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-01T13:29:54.517000`, `2026-06-25T11:32:06.997000`, `2026-06-25T11:32:07.007000` |
| `tim` | `int` |  boleh NULL | — |

#### `qc_efficiencies`

- **Fungsi:** Efisiensi QC (170 baris).
- **Jumlah baris (16 Nov 2026):** 170
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `6031`, `6032`, `6033` |
| `line_number` | `int` | Nomor line. · boleh NULL | `13`, `18`, `20` |
| `buyer` | `nvarchar(255)` | Nama buyer/pembeli. · boleh NULL | `MUJI` |
| `period_id` | `bigint` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `8` |
| `efficiency` | `float(53)` | Nilai efisiensi (persen). · boleh NULL | `0.0`, `0.98`, `0.6` |
| `date` | `date` | Tanggal kejadian. | `2026-11-10`, `2026-11-11`, `2026-11-12` |
| `days` | `int` | Jumlah hari. · boleh NULL | `1` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-13T15:07:17.797000`, `2026-11-13T15:07:17.807000`, `2026-11-13T15:07:17.817000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-13T15:07:17.797000`, `2026-11-13T15:07:17.807000`, `2026-11-13T15:07:17.817000` |
| `third_party` | `nvarchar(255)` |  boleh NULL | — |
| `dept` | `nvarchar(255)` | Nama departemen (teks bebas pada beberapa tabel, bukan master). · boleh NULL | `qc`, `qa` |

#### `qc_violations`

- **Fungsi:** Pelanggaran QC per periode (persentase, alasan).
- **Jumlah baris (16 Nov 2026):** 2
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `2`, `3` |
| `period_id` | `bigint` | ID periode, join ke tabel periode terkait (payroll_periods/thr_periods). | `6`, `7` |
| `percentage` | `decimal(5,2)` | Persentase (0-100). · default `'0'` | `10.00` |
| `reason` | `nvarchar(max)` | Alasan. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `sewing_violations`

- **Fungsi:** Pelanggaran bagian sewing per tanggal.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `id_dept` | `bigint` | ID departemen, join ke `DEPT.ID_DEPT` (int). | — |
| `pelanggaran` | `nvarchar(255)` | Jenis pelanggaran. | — |
| `tanggal` | `date` | Tanggal kejadian/periode (date). | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

### D9. Ekspatriat, Tamu & Dokumen — 15 tabel

Ekspatriat, keluarga, izin/visa, tamu asing, OCR dokumen.

#### `chu_family`

- **Fungsi:** Data keluarga ekspatriat (paspor, visa, KITAS).
- **Jumlah baris (16 Nov 2026):** 4
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Data pribadi sensitif.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `gender` | `nvarchar(255)` | Jenis kelamin ('L'/'P'/'ALL'). · boleh NULL | `Male`, `Female` |
| `place` | `nvarchar(255)` | Tempat lahir/kejadian. · boleh NULL | `CHINA`, `Taiwan` |
| `birth_date` | `date` | Tanggal lahir. · boleh NULL | `1938-12-18`, `1965-08-15`, `1972-06-20` |
| `nationality` | `nvarchar(255)` | Kewarganegaraan. · boleh NULL | `Singapura`, `HON GKONG SAR` |
| `passport_number` | `nvarchar(255)` | Nomor paspor. Sensitif. · boleh NULL | `K2621222H`, `K4776447N`, `K5077461B` |
| `passport_expiry` | `date` | Tanggal kedaluwarsa paspor. · boleh NULL | `2028-05-25`, `2032-04-25`, `2034-02-23` |
| `visa_type` | `nvarchar(255)` | Jenis visa. · boleh NULL | `VISA D2`, `VISA E25B`, `Visa D2` |
| `visa_expiry` | `date` | Tanggal kedaluwarsa visa. · boleh NULL | `2027-01-14`, `2026-12-22` |
| `kitas_expiry` | `date` | Tanggal kedaluwarsa KITAS. · boleh NULL | `2028-07-20`, `2028-09-17`, `2028-10-13` |
| `rptka_expiry` | `date` | Tanggal kedaluwarsa RPTKA. · boleh NULL | `2028-07-31`, `2028-11-30` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-25T13:50:48.087000`, `2026-05-28T09:27:03.860000`, `2026-05-28T09:29:02.067000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-10-26T10:00:20.080000`, `2026-10-26T10:00:31.147000`, `2026-10-26T10:00:44.777000` |
| `npwp` | `varchar(255)` | Nomor NPWP. Sensitif. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |

#### `epos`

- **Fungsi:** Data ekspatriat: izin/EPO, biaya, tanggal berakhir.
- **Jumlah baris (16 Nov 2026):** 4
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `expat_name` | `nvarchar(255)` | Ekspatriat nama | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `gender` | `nvarchar(255)` | Jenis kelamin ('L'/'P'/'ALL'). · boleh NULL | `Female`, `Male` |
| `place` | `nvarchar(255)` | Tempat lahir/kejadian. · boleh NULL | `Ballia, UTTAR PRADESH`, `JOHOR`, `PARANAQUE RIZAL` |
| `date_of_birth` | `date` | Tanggal lahir. · boleh NULL | `1963-10-01`, `1965-09-23`, `1973-08-22` |
| `nationality` | `nvarchar(255)` | Kewarganegaraan. · boleh NULL | `Malaysia`, `FILIPINA`, `INDIA` |
| `position` | `nvarchar(255)` | Nama posisi/jabatan. · boleh NULL | `Compliance Manager`, `GA MANAGER`, `MECHANICAL ENGINEER` |
| `department` | `nvarchar(255)` | Nama departemen (teks). · boleh NULL | `PT CHUTEX` |
| `termination_date` | `date` | Termination tanggal · boleh NULL | `2026-01-28`, `2026-01-31`, `2026-02-05` |
| `must_leave_date` | `date` | Must cuti tanggal · boleh NULL | `2026-02-02`, `2026-02-05`, `2026-02-12` |
| `epo_cost` | `decimal(15,2)` |  · default `'0'` | `750000.00` |
| `rptka_cancellation_cost` | `decimal(15,2)` |  · default `'0'` | `1500000.00` |
| `remarks` | `nvarchar(255)` | Catatan tambahan. · boleh NULL | `EPO`, `TSP` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-28T13:43:13.903000`, `2026-05-28T14:04:36.537000`, `2026-05-28T14:21:37.140000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-05-28T13:43:13.903000`, `2026-05-28T14:04:49.330000`, `2026-05-28T14:21:37.140000` |

#### `expat_cost`

- **Fungsi:** Biaya ekspatriat per periode.
- **Jumlah baris (16 Nov 2026):** 436
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `100`, `101` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00009`, `C-00003`, `C-00005` |
| `amount` | `decimal(15,2)` | Nominal uang. · boleh NULL | `500000.00`, `260850.00`, `366350.00` |
| `transactions_date` | `date` | Transactions tanggal · boleh NULL | `2026-09-01`, `2026-06-23`, `2026-07-21` |
| `remark` | `nvarchar(max)` | Catatan tambahan. · boleh NULL | `Tagihan Internet Juli 2026`, `Air Periode Juli 2026`, `Air Periode Juni` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-29T09:13:21.270000`, `2026-05-29T09:13:51.070000`, `2026-05-29T09:14:22.753000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-05-29T09:13:21.270000`, `2026-05-29T09:13:51.070000`, `2026-05-29T09:14:53.623000` |
| `component` | `bigint` | Nama komponen (gaji/BPJS/biaya). · boleh NULL | `11`, `5`, `7` |

#### `expat_cost_components`

- **Fungsi:** Komponen biaya ekspatriat (16 baris).
- **Jumlah baris (16 Nov 2026):** 16
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `component` | `nvarchar(255)` | Nama komponen (gaji/BPJS/biaya). | `Air`, `Apartement`, `Deposite Apartement` |
| `component_type` | `nvarchar(255)` | Component jenis | `living`, `meal`, `leave` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `expat_documents`

- **Fungsi:** Dokumen ekspatriat (paspor, visa, KITAS).
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `document_type` | `nvarchar(50)` | Document jenis | — |
| `file_name` | `nvarchar(255)` | Path/nama berkas NAME di server. | — |
| `file_path` | `nvarchar(255)` | Path file di server. | — |
| `file_size` | `bigint` | Path/nama berkas SIZE di server. · boleh NULL | — |
| `notes` | `nvarchar(max)` | Catatan. · boleh NULL | — |
| `uploaded_by` | `bigint` | Uploaded oleh · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `expat_master`

- **Fungsi:** Master ekspatriat (20 kolom).
- **Jumlah baris (16 Nov 2026):** 18
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00001`, `C-00003`, `C-00005` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `position` | `nvarchar(255)` | Nama posisi/jabatan. · boleh NULL | `PRODUCTION MANAGER`, `QA ADVISOR`, `Cutting Manager` |
| `joining_date` | `date` | Joining tanggal · boleh NULL | `2026-03-01`, `2025-09-12`, `2025-10-30` |
| `end_date` | `date` | Tanggal berakhir berlakunya record. · boleh NULL | `2027-03-01`, `2026-09-12`, `2026-10-30` |
| `passport_number` | `nvarchar(255)` | Nomor paspor. Sensitif. · boleh NULL | `A70697692`, `A70772914`, `A72431406` |
| `passport_expiry` | `date` | Tanggal kedaluwarsa paspor. · boleh NULL | `2028-05-02`, `2028-06-25`, `2028-07-10` |
| `kitas_expiry` | `date` | Tanggal kedaluwarsa KITAS. · boleh NULL | `2026-10-30`, `2027-03-01`, `2026-09-12` |
| `rptka_expiry` | `date` | Tanggal kedaluwarsa RPTKA. · boleh NULL | `2027-03-31`, `2027-07-31`, `2026-10-17` |
| `merp_expiry` | `date` | Merp kedaluwarsa · boleh NULL | — |
| `house_address` | `nvarchar(max)` | House alamat · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `house_startdate` | `date` |  boleh NULL | `2025-10-25`, `2026-01-15`, `2026-08-31` |
| `lease_enddate` | `date` |  boleh NULL | `2026-10-25`, `2027-01-15`, `2026-05-31` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-25T13:57:03.177000`, `2026-05-28T10:13:44.160000`, `2026-05-28T10:20:05.760000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-26T13:54:15.103000`, `2026-10-21T09:41:34.820000`, `2026-10-21T09:56:20.590000` |
| `place` | `varchar(255)` | Tempat lahir/kejadian. · boleh NULL | `GUANGDONG`, `JIANGSU`, `Malaysia` |
| `nationality` | `varchar(255)` | Kewarganegaraan. · boleh NULL | `CHINA`, `Malaysia`, `FILIPINA` |
| `direct_report` | `varchar(255)` |  boleh NULL | `General Manager`, `HO SINGAPORE VP`, `Factory Manager` |
| `npwp` | `varchar(255)` | Nomor NPWP. Sensitif. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |

#### `expat_meal_menus`

- **Fungsi:** Menu makan ekspatriat.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `makanan` | `nvarchar(150)` | Nama makanan pada menu. | — |
| `kategori` | `nvarchar(20)` | Kategori (menu/ekspatriat). | — |
| `tanggal` | `date` | Tanggal kejadian/periode (date). | — |
| `harga` | `decimal(12,2)` |  · default `'0'` | — |
| `shared` | `bit` |  · default `'0'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `expat_meal_participants`

- **Fungsi:** Peserta makan ekspatriat.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `npk` | `nvarchar(20)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `nama_expat` | `nvarchar(100)` | Nama ekspatriat | — |
| `tanggal` | `date` | Tanggal kejadian/periode (date). | — |
| `kategori` | `nvarchar(20)` | Kategori (menu/ekspatriat). | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `expat_onleave`

- **Fungsi:** Ekspatriat yang sedang cuti.
- **Jumlah baris (16 Nov 2026):** 15
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `11`, `12` |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | `C-00005`, `C-00001`, `C-00006` |
| `onleave_start` | `date` | Onleave mulai · boleh NULL | `2026-03-20`, `2026-03-16`, `2026-04-23` |
| `onleave_end` | `date` | Onleave selesai · boleh NULL | `2026-03-27`, `2026-03-31`, `2026-05-06` |
| `leave_type` | `nvarchar(255)` | Cuti jenis · boleh NULL | `Cuti`, `paid leave` |
| `transactions_date` | `nvarchar(max)` | Transactions tanggal · boleh NULL | `["2026-03-16","2026-03-16","2026-03-2...`, `["2026-03-16","2026-03-27","2026-03-1...`, `["2026-03-20","2026-03-20","2026-03-2...` |
| `remark` | `nvarchar(max)` | Catatan tambahan. · boleh NULL | `Change Airport`, `Ticket Pesawat (Include PP)`, `Tiket Pesawat (Include PP)` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-28T14:39:58.743000`, `2026-05-28T14:44:34.293000`, `2026-05-28T15:19:08.663000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-05-28T14:45:07.090000`, `2026-05-28T15:19:57.993000`, `2026-05-28T17:42:12.677000` |
| `component` | `nvarchar(max)` | Nama komponen (gaji/BPJS/biaya). · boleh NULL | `["1","1","2","2","3","4"]`, `["1"]`, `["2","3","4"]` |
| `amount` | `nvarchar(max)` | Nominal uang. · boleh NULL | `["0"]`, `["0000"]`, `["1030102","1339033","1700000"]` |

#### `extracted_data`

- **Fungsi:** Hasil ekstraksi field dari PDF (data_key, data_value).
- **Jumlah baris (16 Nov 2026):** 40
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `pdf_document_id` → `pdf_documents.id`; `category_id` → `categories.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `pdf_document_id` | `bigint` | **FK** → `pdf_documents.id` · ID PDF, join ke `pdf_documents.id`. | `4`, `2` |
| `category_id` | `bigint` | **FK** → `categories.id` · ID kategori, join ke `categories.id`. · boleh NULL | — |
| `data_key` | `nvarchar(255)` | Nama field hasil ekstraksi. | `As Agent for the Carrier`, `Biaya Tambahan (Additional Cost)`, `Carrier` |
| `data_value` | `nvarchar(max)` | Nilai field hasil ekstraksi. · boleh NULL | `MUJI PHILIPPINES CORP. ATTN:MS.FIDES ...`, `P.T.NX LEMO INDONESIA LOGISTIK`, `0.00` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-21T16:01:32.353000`, `2026-08-21T16:01:32.360000`, `2026-08-21T16:01:32.367000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-21T16:01:32.353000`, `2026-08-21T16:01:32.360000`, `2026-08-21T16:01:32.367000` |

#### `extracted_table_rows`

- **Fungsi:** Baris tabel hasil ekstraksi (row_data).
- **Jumlah baris (16 Nov 2026):** 9
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `extracted_table_id` → `extracted_tables.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `extracted_table_id` | `bigint` | **FK** → `extracted_tables.id` · Hasil ekstraksi tabel ID | `1`, `3`, `2` |
| `row_index` | `int` | Baris indeks · default `'0'` | `0`, `1`, `2` |
| `row_data` | `nvarchar(max)` | Isi baris tabel (JSON). | `{"Container No":"FCIU9135645","Seal N...`, `{"Description":"M'S BRUSHED JERSEY CR...`, `{"Description":"M'S BRUSHED JERSEY HI...` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-21T16:01:32.397000`, `2026-08-21T16:01:32.387000`, `2026-08-21T16:01:32.393000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-21T16:01:32.397000`, `2026-08-21T16:01:32.387000`, `2026-08-21T16:01:32.393000` |

#### `extracted_tables`

- **Fungsi:** Tabel yang terdeteksi pada PDF (nama, headers, index).
- **Jumlah baris (16 Nov 2026):** 3
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `pdf_document_id` → `pdf_documents.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `pdf_document_id` | `bigint` | **FK** → `pdf_documents.id` · ID PDF, join ke `pdf_documents.id`. | `4`, `2` |
| `table_name` | `nvarchar(255)` | Nama tabel hasil ekstraksi. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `headers` | `nvarchar(max)` | Header kolom tabel (JSON). | `["Container No","Seal No","Type"]`, `["Description","Style","Quantity","In...`, `["No.","No Order","Item (Descriptions...` |
| `table_index` | `int` | Tabel indeks · default `'0'` | `0`, `1` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-21T16:01:32.383000`, `2026-08-31T12:55:44.957000`, `2026-08-31T12:55:44.967000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-21T16:01:32.383000`, `2026-08-31T12:55:44.957000`, `2026-08-31T12:55:44.967000` |

#### `foreign_guests`

- **Fungsi:** Data tamu asing (paspor, visa, hotel, flight).
- **Jumlah baris (16 Nov 2026):** 22
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Data pribadi sensitif.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `16`, `17` |
| `guest_name` | `nvarchar(255)` | Tamu nama | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `bank_account` | `nvarchar(255)` | Nomor rekening bank. Sensitif. · boleh NULL | — |
| `photo` | `nvarchar(255)` | Path/gambar foto. Data biometrik/pribadi. · boleh NULL | — |
| `passport` | `nvarchar(255)` | Paspor · boleh NULL | — |
| `visa_type` | `nvarchar(255)` | Jenis visa. · boleh NULL | `Visa C2`, `Visa D2`, `Visa C16` |
| `visa_application` | `nvarchar(255)` |  boleh NULL | `foreign_guest/7ADEQom88oTz5DBNbzJvXJr...`, `foreign_guest/9tg8ep1XqToUe6jAPCGDepV...`, `foreign_guest/JiMdIbGIhI5iUrildHFNnVA...` |
| `visa_status` | `nvarchar(255)` |  boleh NULL | `APPROVED` |
| `visa_invoice` | `bigint` |  boleh NULL | `3500000`, `8500000`, `4500000` |
| `rent_invoice` | `bigint` |  boleh NULL | `200000`, `1100000`, `150000` |
| `flight_detail` | `nvarchar(255)` | Detail penerbangan. · boleh NULL | `VJ855` |
| `flight_eta` | `time(7)` | Penerbangan eta · boleh NULL | — |
| `eta` | `date` |  boleh NULL | `2026-04-06`, `2026-03-30`, `2026-05-15` |
| `return` | `date` | Tanggal kembalinya (untuk tamu). · boleh NULL | `2026-05-24`, `2026-04-04`, `2026-05-30` |
| `hotel` | `nvarchar(255)` | Nama hotel. · boleh NULL | `BROTHER HOTEL SUPERIOR`, `APSARA 2BR #0922`, `HOTEL BROTHERS DELUXE KING WITH BF` |
| `hotel_file` | `nvarchar(255)` |  boleh NULL | `(disamarkan)` |
| `hotel_invoice` | `bigint` |  boleh NULL | `(disamarkan)`, `4000000`, `5775000` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `CONFIRMED` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-29T09:48:53.217000`, `2026-05-29T09:57:22.883000`, `2026-05-29T09:59:57.413000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-06-05T09:23:36.340000`, `2026-06-05T09:23:52.370000`, `2026-06-05T14:23:42.753000` |
| `gender` | `varchar(255)` | Jenis kelamin ('L'/'P'/'ALL'). · boleh NULL | `Male`, `Female` |
| `place` | `varchar(255)` | Tempat lahir/kejadian. · boleh NULL | `THANH NOA`, `JOHOR` |
| `date_of_birth` | `date` | Tanggal lahir. · boleh NULL | `1967-07-18`, `1977-10-19`, `1989-07-29` |
| `nationality` | `varchar(255)` | Kewarganegaraan. · boleh NULL | `Vietnam`, `Malaysia` |
| `passport_no` | `varchar(255)` | Nomor paspor. Sensitif. · boleh NULL | `A72108079`, `E04842604`, `E04899307` |
| `remark` | `varchar(255)` | Catatan tambahan. · boleh NULL | `Visa C2 (Single Entry)` |
| `issue_date` | `date` | Issue tanggal · boleh NULL | `2026-03-25`, `2026-03-17` |
| `must_used_date` | `date` | Must terpakai tanggal · boleh NULL | `0026-06-20`, `2026-06-14`, `2026-06-22` |
| `arrival_date` | `date` | Tanggal kedatangan. · boleh NULL | `2026-03-30`, `2026-05-04` |
| `visa_expiry` | `date` | Tanggal kedaluwarsa visa. · boleh NULL | `2026-05-28`, `2026-06-04` |
| `is_created` | `varchar(255)` | Penanda dibuat · boleh NULL | `1` |

#### `guest_masters`

- **Fungsi:** Master tamu (terhubung foreign_guest_id).
- **Jumlah baris (16 Nov 2026):** 4
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `10`, `11`, `12` |
| `foreign_guest_id` | `bigint` | Foreign tamu ID · boleh NULL | — |
| `gender` | `nvarchar(255)` | Jenis kelamin ('L'/'P'/'ALL'). · boleh NULL | `Male`, `Female` |
| `place` | `nvarchar(255)` | Tempat lahir/kejadian. · boleh NULL | `THANH NOA`, `CHINA`, `Johor` |
| `date_of_birth` | `date` | Tanggal lahir. · boleh NULL | `1967-12-07`, `1968-01-23`, `1977-10-19` |
| `nationality` | `nvarchar(255)` | Kewarganegaraan. · boleh NULL | `Vietnam`, `CHINA`, `Malaysia` |
| `passport_no` | `nvarchar(255)` | Nomor paspor. Sensitif. · boleh NULL | `A72108079`, `E04842604`, `E04899307` |
| `remark` | `nvarchar(max)` | Catatan tambahan. · boleh NULL | — |
| `issue_date` | `date` | Issue tanggal · boleh NULL | `2026-03-25`, `2026-03-17`, `2026-04-24` |
| `must_used_date` | `date` | Must terpakai tanggal · boleh NULL | `2026-06-22`, `2026-06-14`, `2026-07-21` |
| `arrival_date` | `date` | Tanggal kedatangan. · boleh NULL | `2026-04-06`, `2026-03-30` |
| `visa_expiry` | `date` | Tanggal kedaluwarsa visa. · boleh NULL | `2026-06-04`, `2026-05-28` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `Return` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-07T13:35:03.420000`, `2026-08-07T13:43:02.663000`, `2026-08-07T13:44:51.807000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-07T13:35:03.420000`, `2026-08-07T13:43:02.663000`, `2026-08-07T13:44:51.807000` |
| `name` | `varchar(255)` | Nama (orang/entitas/objek). · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |

#### `pdf_documents`

- **Fungsi:** Dokumen PDF yang diunggah untuk OCR (raw_text, status).
- **Jumlah baris (16 Nov 2026):** 4
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `category_id` → `categories.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2`, `3` |
| `original_filename` | `nvarchar(255)` | Nama file asli saat diunggah. | `(disamarkan)`, `(disamarkan)` |
| `file_path` | `nvarchar(255)` | Path file di server. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `raw_text` | `nvarchar(max)` | Teks mentah hasil ekstraksi PDF. · boleh NULL | `WAYBILL                              ...`, `PT CHUTEX INTERNATIONAL INDONESIA    ...` |
| `category_id` | `bigint` | **FK** → `categories.id` · ID kategori, join ke `categories.id`. · boleh NULL | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · default `'pending'` | `failed`, `processed` |
| `error_message` | `nvarchar(max)` |  boleh NULL | `Class "Spatie\PdfToText\Pdf" not found`, `Panggilan API Gemini gagal: {
  "erro...` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-21T16:00:24.137000`, `2026-08-21T16:01:18.687000`, `2026-08-28T10:54:37.487000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-21T16:00:24.160000`, `2026-08-21T16:01:32.407000`, `2026-08-28T10:54:38.007000` |

### D10. Akses, Keamanan & Perangkat — 2 tabel

Blokir IP/MAC, perangkat QR, log akses, tanda tangan.

#### `live_access_logs`

- **Fungsi:** Log akses aplikasi live.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `user_id` | `bigint` | ID user aplikasi, join ke `users.id`. · boleh NULL | — |
| `user_name` | `nvarchar(255)` | Pengguna nama · boleh NULL | — |
| `npk` | `nvarchar(50)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. · boleh NULL | — |
| `ip_address` | `nvarchar(45)` | Alamat IP. · boleh NULL | — |
| `method` | `nvarchar(10)` | HTTP method. | — |
| `path` | `nvarchar(1000)` | Path/URL yang diakses. | — |
| `route_name` | `nvarchar(255)` | Nama route Laravel. · boleh NULL | — |
| `status` | `smallint` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | — |
| `user_agent` | `nvarchar(500)` | User agent browser/perangkat. · boleh NULL | — |
| `duration_ms` | `int` |  boleh NULL | — |
| `accessed_at` | `datetime` | Accessed pada | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `signatures`

- **Fungsi:** Gambar tanda tangan user (base64/gambar).
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `user_id` | `nvarchar(255)` | ID user aplikasi, join ke `users.id`. | — |
| `signature_img` | `nvarchar(255)` | Gambar tanda tangan. Sensitif. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

### D11. Aplikasi, Menu, Role & Log Sistem — 19 tabel

User, role, permission, menu, pengaturan aplikasi, audit trail.

#### `activity_logs`

- **Fungsi:** Log aktivitas aplikasi (166.822 baris: aksi, model, url, IP).
- **Jumlah baris (16 Nov 2026):** 166.822
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `user_id` | `bigint` | ID user aplikasi, join ke `users.id`. · boleh NULL | `12`, `1`, `13` |
| `action` | `nvarchar(255)` | Aksi yang dilakukan (create/update/delete/login). | `request`, `error` |
| `model` | `nvarchar(255)` | Nama model/tabel yang terkena aksi. · boleh NULL | — |
| `description` | `nvarchar(max)` | Deskripsi. · boleh NULL | `GET home`, `GET test-reverb`, `Vite manifest not found at: /var/www/...` |
| `old_data` | `nvarchar(max)` | Snapshot data sebelum perubahan (JSON). · boleh NULL | — |
| `new_data` | `nvarchar(max)` | Snapshot data setelah perubahan (JSON). · boleh NULL | — |
| `method` | `nvarchar(255)` | HTTP method. · boleh NULL | `GET`, `POST` |
| `url` | `nvarchar(255)` | URL yang diakses. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `ip` | `nvarchar(255)` | Alamat IP. · boleh NULL | `(disamarkan)`, `(disamarkan)` |
| `user_agent` | `nvarchar(255)` | User agent browser/perangkat. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-05-13T16:32:37.303000`, `2026-05-13T12:30:26.570000`, `2026-05-13T12:30:26.853000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-05-13T16:32:37.303000`, `2026-05-13T12:30:26.570000`, `2026-05-13T12:30:26.853000` |

#### `approval_datas`

- **Fungsi:** Status persetujuan per NPK untuk suatu aturan (rules_id, status).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `rules_id` → `approval_rules.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `rules_id` | `bigint` | **FK** → `approval_rules.id` · Rules ID | — |
| `npk` | `nvarchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. | — |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `approval_depts`

- **Fungsi:** Daftar departemen/section yang terlibat dalam satu aturan approval.
- **Jumlah baris (16 Nov 2026):** 108
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `101` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `dept` | `nvarchar(max)` | Nama departemen (teks bebas pada beberapa tabel, bukan master). | `["1"]`, `["2032"]`, `["2033"]` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-09-08T14:59:48.563000`, `2026-09-08T14:45:10.937000`, `2026-09-08T14:50:42.993000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-09-08T14:59:48.563000`, `2026-09-08T14:45:10.937000`, `2026-09-08T14:50:42.993000` |
| `section` | `nvarchar(255)` | Section. Di `DEPT` selalu 'CHUTEX'; di `BIODATA` berisi angka → jangan dipakai sebagai filter. · boleh NULL | `109`, `49` |

#### `approval_rules`

- **Fungsi:** Aturan alur persetujuan (rules_id, level, approval_id).
- **Jumlah baris (16 Nov 2026):** 538
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `rules_id` → `approval_depts.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `rules_id` | `bigint` | **FK** → `approval_depts.id` · Rules ID | `1`, `10`, `11` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `approval_id` | `nvarchar(255)` | Approval ID | `C-00003`, `C-00011`, `C-00783` |
| `level` | `nvarchar(255)` | Level pada hirarki. | `1`, `2`, `3` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-09-08T15:12:15.970000`, `2026-09-08T15:12:15.907000`, `2026-09-08T14:46:13.227000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-09-08T15:12:15.970000`, `2026-09-08T15:12:15.907000`, `2026-09-08T14:46:13.227000` |

#### `audit_trails`

- **Fungsi:** Audit trail perubahan data (811.987 baris).
- **Jumlah baris (16 Nov 2026):** 811.987
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `user_id` | `bigint` | ID user aplikasi, join ke `users.id`. · boleh NULL | `10`, `12`, `13` |
| `user_name` | `nvarchar(255)` | Pengguna nama · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `event` | `nvarchar(20)` | Jenis event audit (created/updated/deleted). | `created`, `updated` |
| `auditable_type` | `nvarchar(255)` | Auditable jenis | `App\Models\ActivityLog`, `App\Models\Kunjungan`, `App\Models\HeatEfficiency` |
| `auditable_id` | `bigint` | Auditable ID · boleh NULL | `2671`, `2672`, `2673` |
| `old_values` | `nvarchar(max)` | Lama values · boleh NULL | `{"efficiency":12.24,"piece":"236.00",...`, `{"efficiency":18.88,"piece":"364.00",...`, `{"efficiency":2.54,"piece":"49.00","u...` |
| `new_values` | `nvarchar(max)` | Baru values · boleh NULL | `{"NPK":"C-00568","nama":null,"dept":n...`, `{"NPK":"C-00651","nama":null,"dept":n...`, `{"NPK":"C-00662","nama":null,"dept":n...` |
| `url` | `nvarchar(1000)` | URL yang diakses. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `ip_address` | `nvarchar(45)` | Alamat IP. · boleh NULL | `(disamarkan)` |
| `user_agent` | `nvarchar(500)` | User agent browser/perangkat. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-08-18T09:09:08.857000`, `2026-08-18T09:25:06.717000`, `2026-08-18T10:20:47.497000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-08-18T09:09:08.857000`, `2026-08-18T09:25:06.717000`, `2026-08-18T10:20:47.497000` |

#### `card_menu_settings`

- **Fungsi:** Pengaturan kartu menu di beranda.
- **Jumlah baris (16 Nov 2026):** 10
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `2` |
| `key` | `nvarchar(255)` | Kunci unik konfigurasi (kata kunci SQL: pakai `[key]`). | `canteen-transaction`, `career`, `face-attendance` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `route` | `nvarchar(255)` |  boleh NULL | `/canteen-transaction`, `/employee-payroll`, `/face-attendance/login` |
| `icon` | `nvarchar(255)` | Nama/kelas ikon. · boleh NULL | `fas fa-utensils`, `fa fa-bullhorn`, `fas fa-briefcase` |
| `color` | `nvarchar(255)` |  boleh NULL | `primary`, `success`, `warning` |
| `is_visible` | `bit` | Penanda tampil di UI (1/0). · default `'1'` | `True`, `False` |
| `order` | `int` | Urutan tampil (kata kunci SQL: pakai `[order]`). · default `'0'` | `1`, `10`, `2` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-11-02T13:34:32.507000`, `2026-11-02T13:34:32.513000`, `2026-11-02T13:34:32.517000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-11-02T13:34:32.507000`, `2026-11-02T13:34:32.513000`, `2026-11-02T13:34:32.503000` |

#### `hris_v2_menus`

- **Fungsi:** Menu HRIS v2 (key, route, icon, is_enabled).
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `key` | `nvarchar(60)` | Kunci unik konfigurasi (kata kunci SQL: pakai `[key]`). | — |
| `label` | `nvarchar(120)` | Label tampilan. | — |
| `description` | `nvarchar(255)` | Deskripsi. · boleh NULL | — |
| `route_name` | `nvarchar(150)` | Nama route Laravel. | — |
| `icon` | `nvarchar(60)` | Nama/kelas ikon. · default `'fas fa-circle'` | — |
| `color` | `nvarchar(30)` |  · default `'#1d3f86'` | — |
| `is_enabled` | `bit` | Penanda fitur aktif (1/0). · default `'1'` | — |
| `sort_order` | `int` | Urutan tampil. · default `'0'` | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `hris_v2_settings`

- **Fungsi:** Konfigurasi HRIS v2 dalam bentuk key-value.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `key` | `nvarchar(60)` | Kunci unik konfigurasi (kata kunci SQL: pakai `[key]`). | — |
| `value` | `nvarchar(max)` | Nilai konfigurasi/komponen. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `menus`

- **Fungsi:** Master menu aplikasi (parent_id, route_name, permission_id).
- **Jumlah baris (16 Nov 2026):** 129
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `permission_id` → `permissions.id`; `parent_id` → `menus.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `parent_id` | `bigint` | **FK** → `menus.id` · ID induk (hirarki menu/departemen). · boleh NULL | `1`, `49`, `61` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `route_name` | `nvarchar(255)` | Nama route Laravel. · boleh NULL | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `icon` | `nvarchar(255)` | Nama/kelas ikon. · boleh NULL | ``, `fas fa-users-cog`, `fas fa-globe` |
| `permission_id` | `bigint` | **FK** → `permissions.id` · ID permission, join ke `permissions.id`. · boleh NULL | `366`, `374`, `382` |
| `order` | `int` | Urutan tampil (kata kunci SQL: pakai `[order]`). · default `'0'` | `0`, `2`, `1` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `True` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-01T15:06:14.747000`, `2026-07-03T11:27:10.183000`, `2026-07-03T11:27:31.123000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-01T15:06:14.747000`, `2026-07-03T11:27:31.123000`, `2026-07-03T16:39:01.620000` |

#### `migrations`

- **Fungsi:** Riwayat migrasi Laravel.
- **Jumlah baris (16 Nov 2026):** 168
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `int` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `migration` | `nvarchar(255)` | Nama file migrasi. | `2025_01_01_000000_create_role_payroll...`, `2025_01_01_000001_create_permissions_...`, `2025_01_01_000002_create_role_permiss...` |
| `batch` | `int` | Nomor batch migrasi. | `1`, `10`, `100` |

#### `model_has_permissions`

- **Fungsi:** Relasi model <-> permission (polymorphic).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `permission_id`, `model_id`, `model_type`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `permission_id` | `bigint` | **PK** · ID permission, join ke `permissions.id`. | — |
| `model_type` | `nvarchar(255)` | **PK** · Model jenis | — |
| `model_id` | `bigint` | **PK** · Model ID | — |

#### `model_has_roles`

- **Fungsi:** Relasi model <-> role (polymorphic).
- **Jumlah baris (16 Nov 2026):** 36
- **Akses:** bisa dibaca
- **Kunci:** `role_id`, `model_id`, `model_type`
- **Foreign key:** `role_id` → `roles.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `role_id` | `bigint` | **PK** · **FK** → `roles.id` · ID role, join ke `roles.id`. | `3`, `19`, `20` |
| `model_type` | `nvarchar(255)` | **PK** · Model jenis | `App\Models\User` |
| `model_id` | `bigint` | **PK** · Model ID | `1`, `10`, `11` |

#### `password_reset_tokens`

- **Fungsi:** Token reset password (kosong).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `email`
- **Penting:** Jangan pernah ditampilkan.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `email` | `nvarchar(255)` | **PK** · Alamat email. Data pribadi. | — |
| `token` | `nvarchar(255)` | Token acak untuk approval/reset. RAHASIA: jangan ditampilkan. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |

#### `permissions`

- **Fungsi:** Master permission (200+ baris).
- **Jumlah baris (16 Nov 2026):** 733
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `route_name` | `nvarchar(255)` | Nama route Laravel. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `group` | `nvarchar(255)` |  boleh NULL | `Payroll`, `Management`, `Insentif` |
| `description` | `nvarchar(max)` | Deskripsi. · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `personal_access_tokens`

- **Fungsi:** Token API pengguna (kosong).
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Penting:** Rahasia: jangan ditampilkan.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `tokenable_type` | `nvarchar(255)` | Tokenable jenis | — |
| `tokenable_id` | `bigint` | Tokenable ID | — |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | — |
| `token` | `nvarchar(64)` | Token acak untuk approval/reset. RAHASIA: jangan ditampilkan. | — |
| `abilities` | `nvarchar(max)` |  boleh NULL | — |
| `last_used_at` | `datetime` | Last terpakai pada · boleh NULL | — |
| `expires_at` | `datetime` | Expires pada · boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `role_has_permissions`

- **Fungsi:** Relasi role <-> permission.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `permission_id`, `role_id`
- **Foreign key:** `role_id` → `roles.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `permission_id` | `bigint` | **PK** · ID permission, join ke `permissions.id`. | — |
| `role_id` | `bigint` | **PK** · **FK** → `roles.id` · ID role, join ke `roles.id`. | — |

#### `role_permission`

- **Fungsi:** Relasi role <-> permission (tabel lama).
- **Jumlah baris (16 Nov 2026):** 2.317
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `role_id` → `roles.id`; `permission_id` → `permissions.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `100` |
| `role_id` | `bigint` | **FK** → `roles.id` · ID role, join ke `roles.id`. | `1`, `3`, `6` |
| `permission_id` | `bigint` | **FK** → `permissions.id` · ID permission, join ke `permissions.id`. | `10`, `11`, `12` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `roles`

- **Fungsi:** Master role (23 baris).
- **Jumlah baris (16 Nov 2026):** 23
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `guard_name` | `nvarchar(255)` | Guard nama | `(disamarkan)` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-02-23T01:27:49.130000`, `2026-03-03T01:37:16.357000`, `2026-06-15T08:22:27.177000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-02-23T01:27:49.130000`, `2026-03-03T01:37:16.357000`, `2026-06-15T08:22:27.177000` |

#### `users`

- **Fungsi:** Akun pengguna aplikasi HRIS.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission). Mengandung password.

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | — |
| `email` | `nvarchar(255)` | Alamat email. Data pribadi. | — |
| `email_verified_at` | `datetime` | Email verified pada · boleh NULL | — |
| `password` | `nvarchar(max)` | Hash password. RAHASIA: jangan pernah dipilih/ditampilkan. | — |
| `remember_token` | `nvarchar(100)` |  boleh NULL | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |
| `npk` | `varchar(255)` | Nomor Pokok Karyawan, format `C-00001`. Kunci join ke tabel karyawan. · boleh NULL | — |
| `disabled` | `int` |  boleh NULL · default `0` | — |

### D12. Integrasi & Chatbot — 5 tabel

WhatsApp, chatbot, token akses, sesi.

#### `chatbot_messages`

- **Fungsi:** Pesan chatbot.
- **Jumlah baris (16 Nov 2026):** 30
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `chatbot_session_id` → `chatbot_sessions.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `chatbot_session_id` | `bigint` | **FK** → `chatbot_sessions.id` · Chatbot session ID | `(disamarkan)`, `(disamarkan)` |
| `role` | `nvarchar(255)` | Nama role. | `assistant`, `user` |
| `content` | `nvarchar(max)` | Isi pesan/teks. | `tampilkan kontrak dimas galang ramadhan`, `Maaf, terjadi kendala saat menghubung...`, `Query yang dibuat AI gagal dijalankan...` |
| `meta` | `nvarchar(max)` |  boleh NULL | `{"error":"SQLSTATE[HYT00]: [Microsoft...`, `{"sql":null}`, `{"error":"Provider http:\/\/192.168.1...` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-09-08T09:12:06.433000`, `2026-09-08T09:13:00`, `2026-09-08T09:13:06.480000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-09-08T09:12:06.433000`, `2026-09-08T09:13:00`, `2026-09-08T09:13:06.480000` |

#### `chatbot_sessions`

- **Fungsi:** Sesi chatbot.
- **Jumlah baris (16 Nov 2026):** —
- **Akses:** dibatasi — `SELECT *` ditolak; sebagian kolom mungkin masih bisa dibaca
- **Kunci:** `id`
- **Penting:** Akses dibatasi (permission).

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `user_id` | `bigint` | ID user aplikasi, join ke `users.id`. · boleh NULL | — |
| `title` | `nvarchar(255)` | Judul. · boleh NULL | — |
| `mode` | `nvarchar(255)` |  · default `'rag'` | — |
| `ai_provider` | `nvarchar(255)` | Penyedia model AI yang dipakai sesi chatbot. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

#### `whatsapp_devices`

- **Fungsi:** Perangkat WhatsApp gateway (2 baris).
- **Jumlah baris (16 Nov 2026):** 2
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `2` |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | `(disamarkan)`, `(disamarkan)` |
| `token` | `nvarchar(255)` | Token acak untuk approval/reset. RAHASIA: jangan ditampilkan. | `(disamarkan)`, `(disamarkan)` |
| `phone` | `nvarchar(255)` | Nomor telepon. Data pribadi. · boleh NULL | `(disamarkan)`, `(disamarkan)` |
| `is_active` | `bit` | Penanda aktif (1/0). · default `'1'` | `False` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-07T16:30:41.740000`, `2026-07-07T16:31:15.017000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-07T16:40:30.110000`, `2026-09-07T11:39:56.123000` |

#### `whatsapp_logs`

- **Fungsi:** Log pengiriman pesan WhatsApp.
- **Jumlah baris (16 Nov 2026):** 42
- **Akses:** bisa dibaca
- **Kunci:** `id`
- **Foreign key:** `device_id` → `whatsapp_devices.id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | `1`, `10`, `11` |
| `device_id` | `bigint` | **FK** → `whatsapp_devices.id` · Perangkat ID | `1`, `2` |
| `target` | `nvarchar(255)` | Nomor/nama tujuan pengiriman pesan. | `(disamarkan)`, `(disamarkan)`, `(disamarkan)` |
| `message` | `nvarchar(max)` | Isi pesan. | `[PEMANGGILAN CALON KARYAWAN PT CHUTEX...`, `[PANGGILAN PERTAMA]
Semangat pagi. 
...`, `[PANGGILAN KEDUA]
Semangat pagi. 

...` |
| `status` | `nvarchar(255)` | Status record. Nilai berbeda antar tabel: huruf besar (A/LBR/SD) atau huruf kecil (approved/pending). · boleh NULL | `success` |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | `2026-07-07T16:39:43.010000`, `2026-07-07T16:41:37.907000`, `2026-07-13T09:27:02.357000` |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | `2026-07-07T16:39:43.010000`, `2026-07-07T16:41:37.907000`, `2026-07-13T09:27:02.357000` |

#### `whatsapp_templates`

- **Fungsi:** Template pesan WhatsApp.
- **Jumlah baris (16 Nov 2026):** 0
- **Akses:** bisa dibaca
- **Kunci:** `id`

| Kolom | Tipe | Keterangan | Contoh nilai nyata |
|---|---|---|---|
| `id` | `bigint` | **PK** · IDENTITY · Primary key auto-increment (IDENTITY). | — |
| `name` | `nvarchar(255)` | Nama (orang/entitas/objek). | — |
| `message` | `nvarchar(max)` | Isi pesan. | — |
| `created_at` | `datetime` | Waktu record dibuat (datetime). · boleh NULL | — |
| `updated_at` | `datetime` | Waktu record terakhir diubah (datetime). · boleh NULL | — |

## 6. TABEL DENGAN AKSES DIBATASI (24)

Tabel-tabel ini **ada** di database (muncul di dump skema), tetapi `SELECT *` ditolak dengan
error permission (229/230). Ini **bukan** berarti tabel tidak ada dan **bukan** berarti datanya kosong.

Penting: penolakan bersifat **per kolom**. `PKWT` menolak `SELECT *`/`COUNT(*)` karena kolom `KTP`,
tetapi `NPK, NAMA, TMK, TKK, BAGIAN` berhasil dibaca. Jadi urutan yang benar: coba dengan daftar
kolom eksplisit dan tanpa kolom sensitif, baru simpulkan batas akses.

| Tabel | Fungsi | Sikap AI |
|---|---|---|
| `PKWT` | Kontrak kerja seluruh karyawan: TMK (masuk) dan TKK (keluar). | **Masih bisa dibaca** dengan kolom eksplisit (NPK, NAMA, TMK, TKK, BAGIAN); hindari KTP/`SELECT *`. |
| `PKWT_OUT` | Arsip PKWT karyawan keluar versi lama (63 baris). | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `att_log_audit` | Audit/perubahan data att_log. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `chatbot_sessions` | Sesi chatbot. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `compensation_details` | Rincian kompensasi per karyawan. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `employees_contract` | Siklus/dokumen kontrak karyawan. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `exchange_rates` | Kurs valuta asing untuk perhitungan biaya. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `expat_documents` | Dokumen ekspatriat (paspor, visa, KITAS). | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `hris_v2_menus` | Menu HRIS v2 (key, route, icon, is_enabled). | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `hris_v2_settings` | Konfigurasi HRIS v2 dalam bentuk key-value. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `live_access_logs` | Log akses aplikasi live. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `mon_rekon_lock_fabrics` | Lock rekonsiliasi kain. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `mon_rekon_locks` | Lock rekonsiliasi (52 kolom). | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `mon_stock_fisik` | Stok fisik (22 kolom). | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `org_chart_settings` | Pengaturan bagan organisasi per departemen (level, parent, label). | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `overtimes_payroll_audit` | Audit perubahan overtimes_payroll. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `payroll_masters` | Data gaji pokok/tunjangan/PPh21 per NPK + rekening bank. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `payroll_run_details` | Rincian gaji per karyawan per run payroll. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `payroll_run_details_audit` | Audit perubahan rincian payroll. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `pelamar_details` | Rincian/berkas pelamar (53 kolom). | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `salary_approve` | Approval penetapan gaji. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `signatures` | Gambar tanda tangan user (base64/gambar). | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `thr_run_details` | Rincian THR per karyawan. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |
| `users` | Akun pengguna aplikasi HRIS. | Sampaikan: data tersedia tetapi akses dibatasi; jangan mengarang angka. |

Bila pengguna membutuhkan data dari tabel di atas, jalur yang benar: minta DBA memberikan grant
`SELECT` pada kolom non-sensitif atau membuat view tanpa kolom gaji/rekening, lalu ulangi query.

### 6b. TABEL YANG DILARANG DIBACA (R25)

Empat tabel ini **tidak boleh dipakai** untuk laporan, verifikasi, maupun pembanding — terlepas dari
apakah akun read-only bisa membacanya. Larangan ini kebijakan data, bukan soal permission.

| Tabel | Isi | Ganti dengan |
|---|---|---|
| `att_log_audit` | salinan/perubahan `att_log` | `att_log` (Resep 3) |
| `AUDIT` | rekap absensi olahan lama (531.822 baris) | `att_log` + `employee_shifts` + `shifts` (Resep 3) |
| `overtimes_payroll_audit` | audit perubahan `overtimes_payroll` | `overtimes` / `overtimes_payroll` (Resep 4) |
| `payroll_run_details_audit` | audit perubahan rincian payroll | `payroll_run_details` (bila akses diizinkan) |

Kalau pengguna secara eksplisit meminta data dari tabel ini, jawab bahwa tabel audit dilarang dipakai dan
tawarkan angka dari tabel induk.

## 7. BATASAN TEKNIS SQL SERVER 2014

| Fitur | Tersedia? | Pengganti |
|---|---|---|
| `TRIM()` | tidak | `LTRIM(RTRIM(x))` |
| `STRING_SPLIT()` | tidak | pola `CHARINDEX` dengan CSV ber-koma |
| `CONCAT_WS()` | tidak | `+` dengan `COALESCE` |
| `OPENJSON()` | tidak | parsing di aplikasi |
| `TRY_CAST`, `CHARINDEX`, `LEN`, `CONVERT`, `DATEPART` | ya | — |

Batas lain: maksimum 2.100 parameter per statement. Untuk daftar nilai panjang, gunakan
`CHARINDEX` atas CSV (satu parameter per potongan), bukan `IN (...)` dengan ribuan nilai.

## 8. FORMAT JAWABAN & VERIFIKASI

Setiap jawaban berbasis data harus memuat:

1. **Sumber**: `tabel.kolom` yang dipakai (contoh: `PKWT.TKK`, `BIODATA.NPK`).
2. **Periode**: tanggal awal dan akhir yang difilter.
3. **Jumlah baris**: hasil yang dikembalikan, dan bila ada batas `TOP`, sebutkan batasnya.
4. **Filter penting**: nilai status/kode yang dipakai (mis. `TKK IS NOT NULL`).
5. **Status verifikasi**: 'terverifikasi' vs 'perlu konfirmasi' bila memakai arti kode yang belum pasti.

Bila hasil 0 baris, periksa berurutan sebelum menyimpulkan: format NPK (`C-00001`), huruf besar/kecil
status, rentang tanggal, dan nilai NULL. Baru setelah itu nyatakan tidak ada data.

Bila pengguna meminta data pribadi (KTP, rekening, HP, alamat), tampilkan tersamar dan jelaskan
bahwa penyamaran dilakukan (R19).

## 9. LOG VERIFIKASI

| Yang diperiksa | Metode | Hasil |
|---|---|---|
| Jumlah tabel & kolom dump | parsing DDL `CREATE TABLE` | 167 tabel, 1.920 kolom, 0 baris terlewat |
| Koneksi read-only | `SELECT @@VERSION, DB_NAME()` | SQL Server 2014 (12.0.6024), database CII |
| Tabel bisa dibaca | `SELECT TOP 200 *` per tabel | 143 bisa dibaca, 24 ditolak permission, 0 tidak ditemukan |
| Jumlah baris | `COUNT(*)` per tabel; tabel yang menolak dihitung lewat kolom eksplisit | tersimpan di `schema/row_counts.json` |
| Karyawan aktif vs keluar | `PKWT` + `BIODATA` + `BIODATA_KELUAR` | 3.639 aktif; 2.159 keluar (PKWT); 2.204 arsip keluar |
| `att_log.pin` = `BARCODE` | join PIN Okt 2026 ke `BIODATA.BARCODE` | 3.432 dari 3.432 cocok |
| Distribusi `inoutmode` | `GROUP BY` Okt 2026 | 1=80.017, 2=65.377, 3=262, 4=1.138, 6=1.945 |
| Kode `JUMLAH_JAM_LEMBUR` | `GROUP BY` non-numerik | NULL 643.055, MA 19.339, BR 14.064, CT 13.884, OUT 12.584, P1 4.204, SD 3.959 |
| Tanggal masa depan | `COUNT` `OVERTIME_DATE` > hari ini | 47.587 baris (s/d 31 Des 2026) |
| Bahasa kolom `DAY` | `GROUP BY` dua tabel | `overtimes` campur (ada 'Selasa' 9.080), `overtimes_payroll` Inggris |
| `DEPT.SECTION` | `GROUP BY` | hanya 'CHUTEX' (310 baris) |
| `BIODATA_KELUAR.STATUS` | `GROUP BY` | 'A' 2.201, NULL 3 |
| Permission per kolom | `COUNT(*)` vs kolom eksplisit pada `PKWT` | `COUNT(*)` ditolak (kolom KTP); `NPK,TMK,TKK` berhasil |
| Cakupan roster shift | `COUNT(DISTINCT npk)` `employee_shifts` per bulan | Nov 2026: 173 NPK; Sep 2026: 3.393; 13 Nov: 90 baris |
| Roster tanpa BIODATA | `LEFT JOIN` `employee_shifts` → `BIODATA` | 814 dari 13.383 baris (6%), semua bertanggal 2026 |
| `inoutmode` bukan arah | `GROUP BY` 13 Nov 2026 + periksa tap per karyawan | 1=3.605, 2=3.236, 3=1 tap; 5/5 karyawan contoh kedua tap mode 1 |
| Resep 3 dijalankan | `DECLARE @tgl` 13 & 15 Nov 2026 | 3.639 baris; 90 ROSTER / 3.547 DEFAULT / 2 LIBUR; 17,7 detik; shift malam `C-00756` keluar 14 Nov 07:00 |
| Tap tunggal | `GROUP BY` NPK `HAVING COUNT(*) = 1` | 50 karyawan pada 13 Nov; 14 TANPA TAP MASUK + 33 TANPA TAP KELUAR dilaporkan apa adanya |
| Resep 3 utuh vs dipotong | jalankan blok penuh, lalu jalankan hanya sampai CTE `jadwal` | utuh: 3.639 baris / 13 kolom; dipotong: error 102 (Incorrect syntax) - jangan menyalin sebagian CTE |
| Resep 3 tanpa `DECLARE` | tanggal literal `'2026-11-13'` di keempat tempat | 3.639 baris, hasil identik |
| Tabel audit tidak dipakai | pemeriksaan seluruh resep + aturan | 0 referensi ke `AUDIT`/`att_log_audit`/`*_audit` |

Dokumen ini dibuat dari dump skema 12 Nov 2026 dan diverifikasi ulang ke database pada 16 Nov 2026.
Bila struktur tabel berubah setelah tanggal tersebut, dokumen ini harus diperbarui — jangan
menganggapnya berlaku selamanya.
### 9.1 Verifikasi perubahan 17 Nov 2026 (R26–R30 + konteks percakapan)

| Yang diperiksa | Metode | Hasil |
|---|---|---|
| Jumlah aturan terbaca | `RagRules::sidik()` | 30 aturan (R1–R30), dokumen 4.700+ baris |
| R26–R30 masuk prompt | cetak `ringkasanPrompt('turnover ...')` | R26, R27, R28, R29, R30 ADA; prompt 23.994 karakter |
| Bagian 7–8 tidak terpotong | periksa ekor prompt | `BATASAN` dan `FORMAT JAWABAN` ADA (sebelum perbaikan urutan keduanya hilang) |
| Riwayat masuk prompt SQL | test `handleRag` dengan provider tiruan | prompt memuat `RIWAYAT PERCAKAPAN SESI INI` + `QUERY TERAKHIR YANG BERHASIL`; pesan provider = user, assistant, user |
| Pertanyaan lanjutan dijawab | provider sungguhan, 2 percobaan | dengan riwayat: menyebut LINE 66 dan menjelaskan pembagi 0; tanpa riwayat: hanya minta detail |
| Unit test chatbot | `php vendor/bin/phpunit --testsuite Unit` | 66 test lulus (9 di antaranya `ChatbotConversationContextTest`) |
| Resep 6 dijalankan ke DB | — | **BELUM** — DB CII tidak terjangkau dari VM pengembangan; wajib diuji di server aplikasi |
