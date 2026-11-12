# Rangkuman Project HR-CII (Chutex HRIS)

Dokumen ini dibuat dari hasil pembacaan langsung seluruh source code project
`/home/hermes/workspace/hr-cii` (bukan dari dokumentasi eksternal).

Tanggal: 2026-11-09
Branch aktif saat pemindaian: `payroll-cuti-cost`

---

## 1. Identitas Project

| Item | Nilai |
|---|---|
| Nama | HR-CII / Chutex HRIS |
| Owner | PT. Chutex International Indonesia |
| Framework | Laravel 10 (`laravel/framework ^10.0`), PHP ^8.1 |
| Asset build | Vite 4 (`vite.config.js`, `npm run build`) |
| Frontend | Blade + SB Admin 2 (Bootstrap 4) + jQuery + DataTables (Yajra server-side) + SweetAlert2 + Select2 + Leaflet |
| View engine | Blade, 317 file `.blade.php` |
| Auth | Custom `LoginController` (email/password + CAPTCHA + QR auth + face auth) |
| RBAC | `spatie/laravel-permission` + `CheckPermission` middleware custom (match by `route_name`) |
| Report | `maatwebsite/excel` (Excel), `barryvdh/laravel-dompdf` + `laravel-snappy` (PDF) |
| QR | `simplesoftwareio/simple-qrcode` (SVG/base64, tanpa ekstensi GD/Imagick) |
| AI | `AiChatService` multi-provider (OpenAI-compatible, Anthropic, Gemini) |
| Komunikasi | WhatsApp via Fonnte API (`FonnteService`) |
| Face recognition | `face-api.js` lokal (`public/vendor-face-api/`) + model `tinyFaceDetector`, `faceLandmark68TinyNet`, `faceRecognitionNet` |
| Realtime | Laravel Reverb, Laravel Echo, Pusher JS |
| Container | Dockerfile + docker-compose.yml (siap deploy) |

## 2. Skala Kode (angka nyata hasil penghitungan file)

| Komponen | Jumlah |
|---|---|
| Route di `routes/web.php` | **966** (898 baris deklarasi Route) |
| Controller | 136 |
| Model (Eloquent) | 152 |
| Migration | 169 |
| Seeder | 20 |
| Service | 30 |
| Export class | 112 |
| Import class | 39 |
| Queue Job | 14 |
| Artisan Command | 18 |
| Middleware | 12 |
| Blade view | 317 |
| Test file | 8 |
| Direktori view (modul) | ~110 |

> Catatan: `routes/web.php` berukuran 143 KB dan `app/Http/Controllers` memuat banyak file
> berawalan `* copy.php`, `*_BKP.php` yang merupakan arsipVersi lama yang belum dibersihkan.

## 3. Multi-Database (arsitektur data paling penting)

Project ini **bukan** satu database. Ada 4 koneksi aktif yang dipakai di kode:

| Connection | Driver | Isi | Pemakaian |
|---|---|---|---|
| `cii` | SQL Server | Master HRIS: `BIODATA`, `BIODATA_KELUAR`, `DEPT`, `AUDIT`, `att_log`, `PKWT`, `PKWT_OUT`, `PELAMAR` | **559 pemakaian** (dominan) |
| `smartit` | SQL Server | Modul produksi: PO, BOM, Work Order, Shipment, Subkon, Rekonsiliasi, Master barang/supplier/negara/prod-line | 11 pemakaian + 12 Artisan sync command |
| `sqlsrv` / `sqlsrv_readonly` | SQL Server | Default + koneksi read-only khusus untuk chatbot AI | 8 pemakaian |
| `fingerspot` | MySQL | Fingerprint attendance device (`fin_pro`) | 2 pemakaian |
| `canteen` | SQL Server | Transaksi kantin (`canteen`, `canteen_twos`) | dipakai `CanteenTransactionController` |

### Aturan penting yang sudah mengikat
- Master transaksi canteen **wajib** di DB `canteen`, **dilarang** menulis ke CII
  (`canteen_id=1` -> tabel `canteen`, `canteen_id=2` -> tabel `canteen_twos`).
- Transaksi payroll diberi blokir pada mode RTG chatbot:
  `CHATBOT_RTG_BLOCKED_TABLES=payroll_masters,payroll_run_details,payroll_run_details_audit`.
- Kolom sensitif juga diblokir (gaji, PPH21, NIK, No KK, HP, KTP, password, email).
- `att_log.pin` **tidak boleh di-CAST** (index mati) - join ke `BIODATA.BARCODE`.
- Relasi logis (tanpa FK constraint) didokumentasikan manual di `docs/DATABASE_SCHEMA.md`
  dan otomatis disisipkan ke prompt AI mode RTG.

## 4. Modul Fungsional (dari route + controller + view)

### 4.1 Inti HR
- **Biodata** - CRUD karyawan, gender, soft file, generate PDF, export all docs, soft-files.
- **Biodata Keluar** + **Employee Exit History** - karyawan keluar.
- **PKWT / PKWT Out** - data Werkekgrafisch / kepawai.
- **Employee Mutation** - mutasi karyawan antar unit/departemen.
- **Employees Contract** - kontrak kerja: stop, finish, undo, extend, split, update salary.
- **Dept / Parent Dept / Section / Line / Shift / Employee Shift** - master struktur & shift.
- **Employee Late**, **Late Compensation**, **Employee Violation**, **Sewing Violation**.

### 4.2 Presensi & Verifikasi
- **Attendance** - rekap dari tabel `AUDIT` (pagi/siang/malam) per bagian/subdivisi.
- **Attendance Finger** - dari device FingerSpot (MySQL).
- **Attendance Expat** - presensi ekspatriat.
- **Face Attendance** - absen pakai wajah: NPK -> validasi server -> liveness -> face match
  -> cek GPS dalam polygon -> cek time window -> catat. Status: `baru_in`, `hanya_out`, `belum`,
  `clock_in`, `expired`.
- **Face Attendance Admin** - master setting, lokasi kerja (geomap), rekap, dan
  **Face Attendance Log** (audit per transaksi scan, DataTables server-side).
- **Face Recognition / Self Register** -, karyawan daftar wajah mandiri (4 langkah wizard).
- **Break Master / Dept Breaktime** - jendela istirahat dari tabel `break_masters`.

### 4.3 Payroll & Kompensasi (terbesar)
- **Payroll**: master, periode, komponen, adjustment, proses (`PayrollProcessController`),
  approval multi-step (`HandlesSalaryApprovalSteps` trait), recap, role payroll.
- **THR**: periode, proses, approve, export.
- **Compensation** & **Compensation Approve**.
- **Insentif** per unit: Cutting, Heat, Line, Line Jumper, Pad, QC + threshold + role formula
  + approval insentif.
- **Efficiency**: Cutting/Heat/Line/Pad/QC efficiency.
- **Employee 6s Assignment** & assignment cutting/heat/line/pad/qc.
- Job payroll: `GeneratePayrollProcess`, `GeneratePayrollExport`, `GenerateThrProcess`,
  `GeneratePayrollCheck`, `GenerateCompensation` (queue, `QUEUE_CONNECTION=sync` saat ini).
- 112 export class + `Sheets/` (PayrollDetailSheet, PayrollMADetailSheet, PayrollSummarySheet,
  PayrollOutDetailSheet, ThrDetailSheet) + subdirektori `Sewing/`, `NonSewing/`, `Staff/`,
  `NonStaff/`, `Support/`.

### 4.4 Cuti, Izin, Kontrak Sosial
- **Pengajuan Cuti / Leave Request** (publik, login terpisah), **Leave Balance**,
  **Leave Approval**, **Leave Recap**, master Leave Type & Leave Reason.
- **Ijin Meninggalkan Pekerjaan**, **BPJS Exceptions**, **PKWT**.
- Leave balance digenerate otomatis (`GenerateEmployeeLeaveBalances`, `GenerateDailyLeave`).

### 4.5 Rekrutmen & Karier
- **Pelamar** + **Pelamar Details** (file KTP, KK, akta, pas foto di storage).
- **Recruitment / Recruitment Form / Recruitment Position**, **Portal Recruitment Status**,
  **Job Vacancy**, **RecruitmentAiController** (AI-assisted recruitment).
- **Event / Event Invitation / Speech** - acara & undangan.

### 4.6 Canteen & Food
- **Canteen Transaction** (publik): pilih kantin 1/2 -> NPK -> scan wajah -> invoice/bukti QRIS.
- **Canteen Setting** (liveness terpisah, random challenge, require geomap, allowed work location).
- **Canteen Break Window** (adapter ke `break_masters`), **Canteen Report**.
- **Food Menu / Food Order** (scan QR).

### 4.7 Kuis, Polling & Survei ( fitur AI-corporate)
- **Quiz** multi-mode: `quiz` | `polling` | `word_cloud`.
  - Join peserta lewat QR code (SVG base64), monitoring live via polling 3 detik.
  - Live view menampilkan word cloud + daftar jawaban terbaru.
- **Satisfaction Survey** - anonim total (tanpa NPK/nama), 35 soal Likert/Open, section B-G,
  mobile-first step per section, halaman `/thanks`, admin CRUD + hasil + export Excel.

### 4.8 Monitoring Produksi & Rekonsiliasi
- **Monitoring Dashboard**, **Monitoring Rekonsiliasi** (Mon_Rekonsiliasi), **Rekon Lock**,
  **Mon Stage Data**, **Grade Excel**, **Notes Excel**, **Summary**.
- 12 Artisan command sinkronisasi dari SmartIT: PO, BOM, Work Order, Shipment, Subkon,
  Rekonsiliasi, Stock Fisik, Master Barang/Supplier/Negara/Prod Line, Exchange Rate.

### 4.9 Ekspatriat
- **Expat Master, Expat Cost (component), Expat Meal (menu + participant), Expat Onleave,
  Expat Dashboard**, **Exchange Rate** (Frankfurter + BI).

### 4.10 Chatbot AI (SsST? HR)
- `ChatbotController` + `AiChatService` (1.050 baris).
- 3 mode: `global` (obrolan), `rag` (Q&A dokumen), `rtg` (Read-To-Generate -> AI menulis SQL
  read-only lalu Mengekspor hasilnya ke Excel/PDF/Image/HTML).
- Introspeksi schema otomatis (`INFORMATION_SCHEMA` + `sys.foreign_keys`) digabung dengan
  `docs/DATABASE_SCHEMA.md` (maks 6.000 karakter) ke system prompt.
- Multi-provider: OpenAI-compatible, Anthropic, Gemini. Timeout 300 detik,
  `CHATBOT_RTG_MAX_ROWS=200`, output token 65.536.
- Deteksi format export otomatis dari pesan user (Excel/PDF/Image/HTML).

### 4.11 Infrastruktur & Admin
- **RBAC**: Role, Permission, Menu (sidebar dinamis per role), Role Payroll.
- **Audit Trail** + **Audit Recap** + **Activity Log** (middleware `ActivityLogMiddleware`).
- **User Management**, **User Signature** (e-signature), **Notifications Contract**.
- **WhatsApp**: device, template, kirim, log (Fonnte).
- **PDF Extraction** (`PdfAiExtractionService` - AI baca PDF), **Dokter/Antrian** (klinik),
  **Health Test**, **Foreign Guest / Guest Master**, **Doorprize**, **Kunjungan**,
  **Overtime**, **QR Device / QR Authorized Device / QrScanLog**, **Monitoring**,
  **Speech/Event**, **Outsource**, **Foreign Guest**, **Epo**.

### 4.12 Portal Karyawan (HRIS Public) - yang dilihat karyawan
`resources/views/hris.blade.php` menampilkan 10 menu card (bisa show/hide dari DB
`card_menu_settings` lewat admin):

| Card | Route | Keterangan |
|---|---|---|
| Login | `/login` | Masuk sistem |
| Payslip | `/employee-payroll` | Slip gaji |
| Career | `/lowongan` | Lowongan |
| Recruitment Announcement | `/portal-recruitment-status` | Pengumuman rekrutmen |
| Food Orders | `/food-orders/scan` | Pesan makanan (default hidden) |
| Leave Request | `/pengajuan-cuti/login` | Pengajuan cuti |
| Face Attendance | `/face-attendance/login` | Absen wajah |
| Face Self Registration | `/self-register-face` | Daftar wajah |
| Canteen Transaction | `/canteen-transaction` | Transaksi kantin |
| Satisfaction Survey | `/satisfaction-survey` | Kuesioner kepuasan |

Copy di halaman hero (dipakai juga sebagai copy poster):
- "Welcome to CHUTEX HRIS"
- "Effortless Attendance with Face Recognition"
- "Your attendance is recorded in seconds"
- "Secure, Fast, and Intelligent HR Data"
- Badge: "AI Integrated"
- Sub: "Human Resource Information System - secure, fast, and intelligent."

## 5. Identitas Visual / Brand (untuk materi promosi)

Ambil dari `resources/views/hris.blade.php` dan `resources/views/layout/header.blade.php`:

- **Judul aplikasi**: `Chutex HRIS` (file header.blade.php)
- **Perusahaan**: `PT. Chutex International Indonesia`
- **Logo**: `public/img/chutex.svg` (path SVG putih, CorelDRAW) - abstrak geometric navy.
  Varian: `chutex_blue.svg`, `chutex_logo.png`, `Chutex.png`, `iconchutex.ico`.
- **Gradient brand (halaman HRIS)**:
  `linear-gradient(135deg, #1d3f86 0%, #274b8f 50%, #3a6fa8 100%)`
  (`--blue-900`, `--blue-700`, `--blue-500`)
- **Orb dekoratif**: `#ffffff` (op .16), `#5b9bd5`, `#8fb8e8` (blur 70px, op .28, float 16s)
- **Grid overlay**: `rgba(255,255,255,.03)` 46px, radial mask 78%
- **Warna aksen modul**: primary `#22d3ee`, success `#34d399`, warning `#fbbf24`,
  danger `#fb7185`, info `#38bdf8`, secondary `#94a3b8`
- **Font/framework**: SB Admin 2 (Bootstrap 4) - To be continued by designer
- **Maskot**: `public/img/chutex-mascot.png` = logo corporate navy (bukan maskot karakter).
  Aset maskot berada di `workspace/near-ai-chutex-robot-owl/` (deskripsi di 5.1).

### 5.1 Deskripsi Maskot (dari lampiran, sudah dianalisis)

Aset lampiran di `workspace/near-ai-chutex-robot-owl/`:
`near-ai-chutex-logo-transparent.png`, `near-ai-chutex-logo.jpg`,
`robot-owl-3d-2.jpg`, `robot-owl-transparent-source.jpg`.

> Catatan penamaan: aset di folder tersebut bernama "robot-owl", namun untuk materi
> promosi deskripsi maskot sengaja ditulis **tanpa nama spesies** (deskripsi geometris
> saja) supaya prompt tidak menghasilkan karakter yang salah. Lihat
> `docs/PROMPT_PROMOSI_POSTER_VIDEO.md` bagian 0.

Deskripsi geometris (hasil analisis visual lampiran):

- **Siluet**: karakter 3D kecil bergaya chibi, proporsi telur/pir, badan pendek dan
  membulat. Kepala besar menyatu dengan torso, tidak ada leher.
- **Kepala**: bulat dan kubah di bagian atas. Dua formantuk/horns panjang dan runcing
  menjulang ke atas dan ke luar dari puncak kepala, ujungnya sedikit melengkung ke dalam.
- **Mata**: dua lingkaran raksasa, lebar di bagian depan kepala, pupil hitam solid dengan
  iris cyan biru bercahaya (glow). Ekspresi melebar, alert, ramah.
- **Wajah**: kerucut kecil berwarna tan-gold seperti paruh, menonjol ke bawah di antara
  dan sedikit di bawah kedua mata.
- **Badan**: kepala & torso atas biru tua, torso bawah navy sangat gelap / hampir hitam.
- **Samping**: dua bentuk paddle memanjang berwarna gelap menjuntai di sisi tubuh,
  melengkung ke dalam di ujung bawah.
- **Dada**: motif garis circuit biru bercahaya (interlocking pattern) samar.
- **Kaki**: dua kaki pendek, ruas atas gelap berengsel, ruas bawah metalik gold-bronze,
  ujung kaki emas meregang dengan tiga cakar runcing.
- **Warna**: biru tua, midnight navy, near-black, cyan terang (mata + dada), tan-gold
  (paruh), gold-bronze metalik (kaki & cakar).
- **Gaya render**: 3D premium, STUDIO, soft contact shadow, proporsi toy + nuansa
  robotik-mekanis. Varian lain menampilkan siluet berlapis bertekstur dan mata almond
  kecil merah-pink pada render profil.

## 6. Catatan Teknis & Risiko yang Tersisa di Project

1. **Arsip file basi**: banyak `* copy.php`, `*_BKP.php` di controller/service/job.
2. **Route monster**: 966 route dalam 1 file `routes/web.php` (143 KB) - perlu dipecah per modul.
3. **Test coverage tipis**: hanya 8 test file untuk 136 controller. Test yang ada:
   `FaceAttendanceLogTest`, `FaceAttendanceCrossPersonBugTest`, `EmployeeTkkActiveStatusTest`,
   `SatisfactionSurveySecurityTest`, `CanteenValidateNpkContractTest`,
   `CanteenGeomapSettingContractTest`, `ExampleTest` x2.
4. **`QUEUE_CONNECTION=sync`** di `.env` produksi - job payroll/THR jalan blocking.
5. **`.env` berisi kredensial** (DB SQL Server `sa`, MySQL `root`, Fonnte master key).
   Sudah di-mask saat pemindaian, tapi file ini ada di dalam tree project.
6. **HTML debug sisa** di root project: `_fa2.html`, `_fa3.html`, `_fa_dbg.html`,
   `_mw_base.html`, `_mw_new.html`, `_probe_x.html`, `_ovf_self_register.html` (~750 KB).
7. **Dokumentasiwajah**: `README.md` masih default Laravel (tidak menjelaskan HRIS).
8. **Data master dari SmartIT** -> selalu sinkron via command, bukan realtime.

## 7. Peta Modul -> Prompt Promosi

| Nama produk untuk promosi | Modul | Satu kalimat nilai |
|---|---|---|
| Face Attendance | Absen wajah | Absen cukupwajah + GPS, selesai dalam hitungan detik |
| Face Self Registration | Daftar wajah mandiri | Daftarkan wajah sendiri, tanpa antre |
| Payslip | Slip gaji | Slip gaji selalu transparan |
| Leave Request | Pengajuan cuti | Cuti diajukan dalam hitungan detik |
| Canteen Transaction | Kantin | Bayar kantin tanpa antre |
| Satisfaction Survey | Kuesioner | Suara karyawan menentukan perbaikan |
| AI Chatbot (RTG) | Chatbot AI | Tanya data HR, langsung jadi laporan Excel |
| Rekonsiliasi | Monitoring | Data produksi selalu sinkron |

## 8. Prasyarat Deploy (dari `DEPLOY_v12.md` + skill)

1. Extract ZIP ke root `hr-cii`.
2. `php artisan view:clear` (+ `route:clear` bila route berubah).
3. `php artisan migrate` bila ada migration baru (SQL Server, idempotent wajib).
4. `php artisan db:seed --class=<Seeder>` bila ada seeder menu/permission baru.
5. `php artisan queue:restart` bila ada job.
6. Hard refresh browser (Ctrl+Shift+R) - compiled view & asset sering basi.
7. Server HTTP untuk unduhan ZIP + verifikasi SHA256.

---

## 9. ERD (Entity Relationship Diagram)

ERD inti HR-CII (koneksi `cii` = SQL Server master, koneksi default = MySQL untuk tabel proses seperti payroll/leave/face-attendance). Relasi yang ditulis adalah relasi logis (join by kolom), karena sebagian besar tidak terdaftar sebagai FK constraint di database.

```mermaid
erDiagram
    DEPT ||--o{ BIODATA : "ID_DEPT"
    PARENT_DEPT ||--o{ DEPT : "id_parent_dept"
    BIODATA ||--|| PKWT : "NPK"
    BIODATA ||--o{ AUDIT : "NPK"
    BIODATA ||--o{ att_log : "BARCODE = att_log.pin"
    BIODATA ||--o{ face_attendance_logs : "NPK"
    BIODATA ||--o{ leave_requests : "NPK"
    BIODATA ||--o{ leave_balances : "NPK"
    LEAVE_TYPES ||--o{ leave_requests : "leave_type_id"
    LEAVE_TYPES ||--o{ leave_balances : "leave_type_id"
    APPROVAL_DEPT ||--o{ APPROVAL_RULES : "rules_id"
    APPROVAL_RULES ||--o{ leave_requests : "approval_id + approval_level"
    leave_requests ||--o{ leave_recap : "token"
    users ||--o{ chatbot_sessions : "user_id"
    chatbot_sessions ||--o{ chatbot_messages : "session_id"
    PAYROLL_PERIODS ||--o{ PAYROLL_RUNS : "period_id"
    PAYROLL_RUNS ||--o{ PAYROLL_RUN_DETAILS : "run_id"
    PAYROLL_RUNS ||--o{ PAYROLL_APPROVE : "payroll_run_id"
    PAYROLL_RUNS ||--o{ PAYROLL_EXPORTS : "run_id"
    PKWT ||--o{ payroll_run_details : "NPK"

    DEPT {
        string ID_DEPT PK
        string DEPARTEMENT
        string SECTION
        int id_parent_dept FK
    }
    PARENT_DEPT {
        int id PK
        string name
    }
    BIODATA {
        string NPK PK
        string NAMA_KARYAWAN
        string BARCODE
        string ID_DEPT FK
    }
    PKWT {
        string NPK PK_FK
        string NAMA
        date TGLLAHIR
        date TMK
        date TKK "null = aktif, terisi = resign"
        string file_pas_foto
    }
    att_log {
        string pin FK "= BIODATA.BARCODE"
        datetime CHECKTIME
        int INOUTMODE
    }
    face_attendance_logs {
        bigint id PK
        string npk
        string action "clock_in/clock_out"
        datetime scan_date
        string status
        string ip_address
    }
    leave_requests {
        bigint id PK
        string NPK
        int leave_type_id FK
        date start_date
        date end_date
        int approval_id FK
        int approval_level
        string status
        string token
    }
    leave_balances {
        bigint id PK
        string NPK
        int leave_type_id FK
        int balance
    }
    payroll_periods {
        bigint id PK
        string name
        date start_date
        date end_date
        bool is_closed
    }
    payroll_runs {
        bigint id PK
        bigint period_id FK
        string status
        int employee_count
    }
    payroll_run_details {
        bigint id PK
        bigint run_id FK
        string NPK
        decimal take_home_pay
    }
    payroll_approve {
        bigint id PK
        bigint payroll_run_id FK
        json approval "daftar approver per level"
        json progress
        string status
    }
    users {
        bigint id PK
        string email
    }
    chatbot_sessions {
        bigint id PK
        bigint user_id FK
        string mode "global/rag"
    }
    chatbot_messages {
        bigint id PK
        bigint session_id FK
        string role "user/assistant"
        text content
        json meta "rows, sql, export url"
    }
```

Catatan penting ERD:
- Kunci utama karyawan adalah **NPK** (bukan `employee_id`/`nik`). Semua nama di `BIODATA.NAMA_KARYAWAN` tersimpan UPPER CASE.
- `PKWT.TKK` adalah penanda status: `NULL` = karyawan aktif, terisi tanggal = sudah resign.
- `att_log.pin` menyimpan **BARCODE** karyawan (bukan NPK) — join ke `BIODATA.BARCODE`.
- Tabel master (`DEPT`, `BIODATA`, `PKWT`, `att_log`, `AUDIT`) ada di koneksi `cii` (SQL Server); tabel proses (payroll, leave, face attendance, chatbot) di koneksi default (MySQL).

## 10. Alur Approval (Cuti, Payroll, Insentif)

### 10.1 Approval Pengajuan Cuti (`leave_requests`)
1. Karyawan membuat pengajuan lewat menu Leave Request (route `leave-recap.*`, `leave-balances.*` untuk admin).
2. Sistem membaca aturan approval dari tabel `approval_rules` (kolom `rules_id` -> `approval_dept`, `approval_id`, `level`) sesuai dept karyawan (`BIODATA.ID_DEPT`).
3. `leave_requests.approval_id` diisi approver pertama, `approval_level` = 1, `approval_progress` mencatat level yang sedang berjalan, `status` = `pending`.
4. Approver approve/reject per level; sistem menaikkan `approval_level` sampai level terakhir -> `status` = `approved` (atau `rejected` bila ada yang menolak), `approval_date` diisi, `token` dipakai untuk halaman rekap publik (`leave-recap.detail/{token}`).
5. Cuti dengan `custom_dates` (tanggal bebas) dihitung dari daftar tanggal itu; selain itu dihitung dari rentang `start_date..end_date` (`LeaveRequest::leaveDates()`).
6. Saldo cuti (`leave_balances`) dikurangi sesuai `total_days` setelah approved; admin bisa generate saldo tahunan lewat `leave-balances.generate-yearly`.

### 10.2 Approval Payroll (`payroll_approve`)
1. Admin membuat periode di `payroll-periods` (`name`, `start_date`, `end_date`, `is_closed`).
2. Jalankan proses payroll: `payroll-process.generatev2` -> `payroll-process.checkv2/{period_id}` -> `payroll-process.processv2` (route `payroll-process.*`). Hasilnya tersimpan di `payroll_runs` + `payroll_run_details` per NPK.
3. Status approval tersimpan di `payroll_approve` (kolom `approval` dan `progress` berupa JSON array per level approver). Route cek status: `payroll-process.approval/{period}`.
4. Setelah semua level approve, status run menjadi final dan slip gaji bisa diakses karyawan: `employee-payroll.view-slip-html` (`/employee-payroll/view/{run_id}/{npk}`) atau `payroll-slip` (`/payroll-slip/{run_id}/{npk}`).
5. PPh21 bisa diupdate massal lewat `payroll-process.update-pph21` / `payroll-process.update-pph-by-contract/{run_id}`.

### 10.3 Approval Insentif & THR
- Insentif: tabel `insentif_approvals` (model `InsentifApproval`), alur sama (multi-level, status pending/approved/rejected).
- THR: `thr-process.*` dengan slip `thr-slip` (`/thr-slip/{run_id}/{npk}`) dan approval `thr-process.approval/{period}`.

## 11. Runbook Payroll Bulanan

| Langkah | Aksi | Route/Command | Verifikasi |
|---|---|---|---|
| 1 | Buat/cek periode bulan berjalan | `payroll-periods.index/create` | Periode muncul di index, `is_closed=false` |
| 2 | Sinkron data master (att_log, BIODATA, PKWT) dari SmartIT | `php artisan` command sync bawaan | Cek `data-freshness/{period_id}` |
| 3 | Generate payroll v2 | `GET /payroll-process/generatev2` | Muncul run baru di `payroll-process.index` |
| 4 | Cek hasil generate (cek duplikat NPK, komponen kosong) | `GET /payroll-process/checkv2/{period_id}` | Tidak ada error komponen |
| 5 | Proses payroll | `POST /payroll-process/processv2` | `payroll_runs.status` berubah, `employee_count` terisi |
| 6 | Update PPh21 bila ada koreksi | `POST /payroll-process/update-pph21` | Cek di detail run |
| 7 | Approval multi-level | `GET /payroll-process/approval/{period}` lalu approve per level | `payroll_approve.status` = approved |
| 8 | Export rekap payroll | `GET /payroll-process/export-rekap/{run_id}` | File Excel/PDF terunduh |
| 9 | Slip gaji karyawan | `GET /employee-payroll/view/{run_id}/{npk}` | Slip tampil, bisa juga via chatbot |
| 10 | Tutup periode | set `payroll_periods.is_closed=true` lewat `payroll-periods.update` | Periode tidak bisa di-generate ulang |

Catatan operasional:
- `QUEUE_CONNECTION=sync` di produksi membuat job payroll berjalan blocking — jalankan di luar jam sibuk atau ganti ke database/redis queue.
- Selalu cek `php artisan queue:restart` setelah deploy yang mengubah job.

## 12. SOP Troubleshoot

| # | Gejala | Langkah diagnosis | Solusi |
|---|---|---|---|
| 1 | Error SQL Server: kolom `liveness_canteen_enabled` / `is_random_challenge_enabled` / `random_challenge_canteen_enabled` tidak ada di `canteen_settings` | Kolom sudah di-drop dari skema fisik tapi masih dikirim dari form settings | Pakai patch V44 (`CanteenSetting::sanitizeAttributes()` unset kolom deprecated + migration drop idempotent). Verifikasi: `php artisan test --filter=V44CanteenSetting` |
| 2 | Chatbot AI mengembalikan bubble kosong | Model reasoning menghabiskan token, `content` kosong | Sudah ada guard di `AiChatService::callOpenAiCompatible()` (fallback teks + `resolveProviderMessage` multi-level). Cek log Laravel untuk provider error |
| 3 | Sidebar child (Face Attendance) terbuka tapi tidak bisa di-hide | Handler collapse SB Admin 2 tidak terpasang/rusak | Pastikan `sb-admin-2.js` termuat di `footerscript` dan handler `data-toggle="collapse"` jalan; lihat TASK 5 |
| 4 | Log Face Attendance: filter cari tidak jalan, card count 0, kolom IP tampil | Salah konfigurasi DataTables server-side & card | Lihat TASK 4: kirim parameter search via `columns[i][search][value]`, hitung card dari query aggregate, hapus kolom `ip_address` |
| 5 | File V44 tidak jalan di server produksi | File masih untracked di git | Commit + push branch aktif, lalu deploy; server menjalankan kode lama sampai file ter-track |
| 6 | `php artisan migrate` gagal di SQL Server | Constraint/FK sudah ada (drop by name error) | Migration harus idempotent: cek `sys.columns`/`sys.indexes` sebelum ALTER; lihat skill `sqlserver-migration-constraints` |
| 7 | Halaman 404 setelah deploy route baru | Route cache basi | `php artisan route:clear && php artisan view:clear` |
| 8 | Asset (CSS/JS) tidak ter-update | Vite build belum dijalankan / browser cache | `npm run build`, lalu hard refresh Ctrl+Shift+R |
| 9 | Queue job tidak jalan setelah deploy | Worker masih pakai kode lama | `php artisan queue:restart` (atau `sudo systemctl restart` bila pakai systemd) |
| 10 | WA bot tidak membalas | Gateway allowlist / contacts.yaml | Cek nomor pengirim sudah terdaftar di `~/.hermes/contacts.yaml` (verifikasi dengan `od -c`) dan gateway aktif |
