# Database Schema Documentation - HRIS Chutex

Dokumen ini melengkapi introspeksi otomatis (`INFORMATION_SCHEMA` + `sys.foreign_keys`)
yang sudah dilakukan `AiChatService` untuk mode chatbot **RTG (Read To Generate)**.

Introspeksi otomatis hanya bisa menemukan relasi yang **benar-benar didaftarkan
sebagai FOREIGN KEY constraint** di database. Banyak tabel di sistem ini
sebenarnya berelasi secara **logis** (biasa dipakai untuk JOIN di query manual/report),
tapi tidak pernah didaftarkan sebagai FK constraint di level database — sehingga
tidak pernah muncul di hasil introspeksi otomatis, dan AI jadi menebak-nebak nama
kolom JOIN atau salah pasang tabel.

File ini dibaca otomatis oleh chatbot (lihat `AiChatService::getSchemaDocumentation()`)
dan disisipkan ke prompt AI di mode RTG, **bersamaan** dengan relasi FK hasil
introspeksi — supaya AI bisa memakai keduanya untuk menyusun JOIN yang benar.

> **Cara update:** edit file ini langsung (tidak perlu ubah kode/deploy ulang).
> Perubahan akan terbaca otomatis setelah cache skema kedaluwarsa
> (`chatbot.schema_cache_minutes`, default 30 menit), atau langsung setelah
> `php artisan cache:clear`.

---

## Relasi Logis (Tanpa FK Constraint):

<!--
    Format satu baris per relasi:
    TABEL_A.KOLOM = TABEL_B.KOLOM (cardinality: penjelasan singkat, opsional)

    - Tulis nama tabel & kolom PERSIS sama dengan nama aslinya di database
      (huruf besar/kecil ikut penulisan asli).
    - Cardinality (one-to-many, many-to-many, dst) opsional tapi membantu AI
      memilih JOIN/agregasi yang benar (mis. kapan perlu GROUP BY atau DISTINCT
      supaya tidak dobel baris).
    - Contoh di bawah ini masih PLACEHOLDER — ganti dengan nama tabel/kolom
      yang sebenarnya dipakai di database HRIS Chutex.
-->

#Aturan Pencarian Data :

- NPK adalah Nomor Karyawan
- Semua nama karyawan di UPPER, contoh jika saya menulis Dimas Galang Ramadhan akan menjadi DIMAS GALANG RAMADHAN
- Lakukan pencarian di table BIODATA.NAMA_KARYAWAN, ambil kolom NPK dan cari di table lain menggunakan NPK tersebut.
- Tanggal Lahir diambil dari kolom TGLLAHIR
- Table yang tidak diperbolehkan diakses ada di env(CHATBOT_RTG_BLOCKED_TABLES)
- Kolom yang tidak diperbolehkan diakses ada di env(CHATBOT_RTG_BLOCKED_COLUMNS)

#Relasi Logis Antar Table :

- PKWT.NPK = BIODATA.NPK (one-to-one: 1 karyawan bisa punya 1 PKWT)
- PKWT.NPK = BIODATA_KELUAR.NPK (one-to-one: 1 karyawan keluar bisa punya banyak PKWT)
- AUDIT.NPK = BIODATA.NPK (one-to-many: 1 karyawan bisa punya banyak baris audit)
- att_log.pin = BIODATA.BARCODE (one-to-many: 1 barcode karyawan bisa punya banyak baris att_log)
- bpjs_exceptions.npk = BIODATA.NPK atau BIODATA_KELUAR.NPK (one-to-many: 1 karyawan bisa punya banyak baris bpjs_exceptions)
- DEPT.ID_DEPT = BIODATA.ID_DEPT atau BIODATA_KELUAR.ID_DEPT (one-to-one: 1 karyawan bisa punya 1 DEPT)
- employee_shifts.npk = BIODATA.NPK atau BIODATA_KELUAR.NPK (one-to-many: 1 karyawan bisa punya banyak employee_shifts)
- employees_contract.npk = BIODATA.NPK atau BIODATA_KELUAR.NPK (one-to-many: 1 karyawan bisa punya banyak employees_contract)
- ijin_meninggalkan_pekerjaans.npk = BIODATA.NPK atau BIODATA_KELUAR.NPK (one-to-many: 1 karyawan bisa punya banyak ijin_meninggalkan_pekerjaans)
- kunjungans.NPK = BIODATA.NPK atau BIODATA_KELUAR.NPK (one-to-many: 1 karyawan bisa punya banyak kunjungans)
- late_compensations.npk = BIODATA.NPK atau BIODATA_KELUAR.NPK (one-to-many: 1 karyawan bisa punya banyak late_compensations)
- leave_balances.npk = BIODATA.NPK atau BIODATA_KELUAR.NPK (one-to-many: 1 karyawan bisa punya banyak leave_balances)
- leave_balances.leave_type_id = leave_types.id(one-to-many: 1 leave id bisa punya banyak leave_balances)

#Penjelasan Nama Kolom Table :

- PKWT.TGLLAHIR adalah kolom untuk tanggal lahir karyawan

<!--
    Tambahkan relasi lain di bawah ini dengan format yang sama. Kelompokkan
    per modul kalau daftarnya sudah panjang, misalnya:

    ### Payroll
    - ...

    ### Recruitment
    - ...

    Judul sub-bagian ("### ...") boleh ditambah bebas — seluruh isi section
    "## Relasi Logis (Tanpa FK Constraint):" ini ikut dikirim ke AI apa adanya.
-->

---

## Catatan Tambahan (opsional)

Bagian ini bebas dipakai untuk catatan lain yang membantu AI menyusun query
yang lebih akurat, misalnya:

- Konvensi penamaan kolom kunci (mis. semua tabel karyawan pakai `NPK`, bukan
  `employee_id` atau `nik`).
- Tabel yang isinya "snapshot"/histori (butuh filter tanggal/periode tertentu
  supaya tidak salah ambil data lama).
- Kolom yang nilainya encoded/lookup (mis. `status_kerja` berupa kode angka,
  bukan teks) beserta artinya.

Seluruh isi file ini (bukan cuma bagian "Relasi Logis") dikirim ke AI sebagai
konteks tambahan, dengan batas panjang `chatbot.schema_docs_max_chars`
(default 6000 karakter) — kalau dokumentasi ini berkembang jadi sangat panjang,
pertimbangkan untuk memangkas isinya ke yang paling relevan untuk query, atau
naikkan batasnya lewat `.env` (`CHATBOT_SCHEMA_DOCS_MAX_CHARS`).
