-- Skema referensi SQLite hasil konversi dari dump skema SQL Server (database CII).
-- Dibuat oleh: php artisan cii:schema-to-sqlite
-- Tujuan: referensi lokal / harness uji. Bukan pengganti database produksi.

CREATE TABLE "activity_logs" (
  "id" INTEGER NOT NULL,
  "user_id" INTEGER,
  "action" TEXT NOT NULL,
  "model" TEXT,
  "description" TEXT,
  "old_data" TEXT,
  "new_data" TEXT,
  "method" TEXT,
  "url" TEXT,
  "ip" TEXT,
  "user_agent" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "applicant_contacts" (
  "id" INTEGER NOT NULL,
  "phone" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "position" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "approval_datas" (
  "id" INTEGER NOT NULL,
  "rules_id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "status" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "approval_datas_rules_id_foreign" FOREIGN KEY ("rules_id") REFERENCES "approval_rules" ("id")
);

CREATE TABLE "approval_depts" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "dept" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "section" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "approval_rules" (
  "id" INTEGER NOT NULL,
  "rules_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "approval_id" TEXT NOT NULL,
  "level" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "approval_rules_rules_id_foreign" FOREIGN KEY ("rules_id") REFERENCES "approval_depts" ("id")
);

CREATE TABLE "att_log" (
  "sn" TEXT NOT NULL,
  "scan_date" TEXT NOT NULL,
  "pin" TEXT NOT NULL,
  "verifymode" INTEGER NOT NULL,
  "inoutmode" INTEGER NOT NULL,
  "reserved" INTEGER NOT NULL,
  "work_code" INTEGER NOT NULL,
  "att_id" TEXT NOT NULL,
  PRIMARY KEY ("sn", "scan_date", "pin")
);

CREATE TABLE "att_log_audit" (
  "sn" TEXT NOT NULL,
  "scan_date" TEXT NOT NULL,
  "pin" TEXT NOT NULL,
  "verifymode" INTEGER NOT NULL,
  "inoutmode" INTEGER NOT NULL,
  "reserved" INTEGER NOT NULL,
  "work_code" INTEGER NOT NULL,
  "att_id" TEXT NOT NULL,
  PRIMARY KEY ("sn", "scan_date", "pin")
);

CREATE TABLE "attendance_work_locations" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "description" TEXT,
  "polygon_geojson" TEXT NOT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "AUDIT" (
  "NPK" TEXT NOT NULL,
  "TANGGAL" TEXT NOT NULL,
  "SUBDIVISI" TEXT,
  "JAM_PAGI" TEXT DEFAULT '-',
  "JAM_SIANG" TEXT DEFAULT '-',
  "JAM_MALAM" TEXT DEFAULT '-',
  "STATUS" TEXT DEFAULT 'MSK',
  "VOID" TEXT,
  "updated_at" TEXT,
  "created_at" TEXT,
  "NAMA_KARYAWAN" TEXT NOT NULL,
  "KODE_BAGIAN" TEXT,
  "DEPT_GROUP" TEXT
);

CREATE TABLE "audit_trails" (
  "id" INTEGER NOT NULL,
  "user_id" INTEGER,
  "user_name" TEXT,
  "event" TEXT NOT NULL,
  "auditable_type" TEXT NOT NULL,
  "auditable_id" INTEGER,
  "old_values" TEXT,
  "new_values" TEXT,
  "url" TEXT,
  "ip_address" TEXT,
  "user_agent" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "BIODATA" (
  "NPK" TEXT NOT NULL,
  "NAMA_KARYAWAN" TEXT,
  "BAG" TEXT,
  "ID_DEPT" INTEGER NOT NULL,
  "JENIS_KEL" TEXT,
  "BARCODE" TEXT,
  "SECTION" TEXT,
  "STATUS" TEXT,
  "IS_STAFF" TEXT,
  "IS_EXPAT" TEXT DEFAULT 0,
  "IS_EXCLUDE" TEXT,
  PRIMARY KEY ("NPK")
);

CREATE TABLE "BIODATA_KELUAR" (
  "NPK" TEXT,
  "NAMA_KARYAWAN" TEXT,
  "BAG" TEXT,
  "ID_DEPT" INTEGER,
  "JENIS_KEL" TEXT,
  "BARCODE" INTEGER,
  "SECTION" TEXT,
  "STATUS" TEXT,
  "IS_STAFF" TEXT,
  "IS_EXPAT" TEXT,
  "IS_EXCLUDE" TEXT
);

CREATE TABLE "BIODATA_OS" (
  "NPK" TEXT NOT NULL,
  "NAMA_KARYAWAN" TEXT,
  "BAG" TEXT,
  "ID_DEPT" INTEGER NOT NULL,
  "JENIS_KEL" TEXT,
  "BARCODE" TEXT,
  "SECTION" TEXT,
  "STATUS" TEXT,
  PRIMARY KEY ("NPK")
);

CREATE TABLE "BLOCKED" (
  "IP_ADDRESS" TEXT,
  "MAC_ADDRESS" TEXT,
  "BLOCKED" TEXT
);

CREATE TABLE "BLOCKING" (
  "ID" INTEGER NOT NULL,
  "IP_ADDRESS" TEXT NOT NULL,
  "MAC_ADDRESS" TEXT NOT NULL,
  "LOG_DATE" TEXT NOT NULL,
  "ACTIVITY" TEXT NOT NULL,
  PRIMARY KEY ("ID")
);

CREATE TABLE "bpjs_exceptions" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "component" TEXT NOT NULL,
  "percentage" NUMERIC NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "break_masters" (
  "id" INTEGER NOT NULL,
  "sesi" TEXT NOT NULL,
  "time_start" TEXT NOT NULL,
  "time_end" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "canteen_break_windows" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "time_start" TEXT NOT NULL,
  "time_end" TEXT NOT NULL,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "canteen_settings" (
  "id" INTEGER NOT NULL,
  "is_enabled" INTEGER NOT NULL DEFAULT 1,
  "require_geomap" INTEGER NOT NULL DEFAULT 0,
  "allowed_work_location_ids" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "canteens" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "location" TEXT,
  "pic_name" TEXT,
  "pic_phone" TEXT,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  "canteen_id" INTEGER,
  "contact_person" TEXT,
  "contact_phone" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "card_menu_settings" (
  "id" INTEGER NOT NULL,
  "key" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "route" TEXT,
  "icon" TEXT,
  "color" TEXT,
  "is_visible" INTEGER NOT NULL DEFAULT 1,
  "order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "categories" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "slug" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "chatbot_messages" (
  "id" INTEGER NOT NULL,
  "chatbot_session_id" INTEGER NOT NULL,
  "role" TEXT NOT NULL,
  "content" TEXT NOT NULL,
  "meta" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "chatbot_messages_chatbot_session_id_foreign" FOREIGN KEY ("chatbot_session_id") REFERENCES "chatbot_sessions" ("id") ON DELETE CASCADE
);

CREATE TABLE "chatbot_sessions" (
  "id" INTEGER NOT NULL,
  "user_id" INTEGER,
  "title" TEXT,
  "mode" TEXT NOT NULL DEFAULT 'rag',
  "ai_provider" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "chu_family" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "gender" TEXT,
  "place" TEXT,
  "birth_date" TEXT,
  "nationality" TEXT,
  "passport_number" TEXT,
  "passport_expiry" TEXT,
  "visa_type" TEXT,
  "visa_expiry" TEXT,
  "kitas_expiry" TEXT,
  "rptka_expiry" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "npwp" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "compensation_approve" (
  "id" INTEGER NOT NULL,
  "run_id" INTEGER NOT NULL,
  "approval" TEXT NOT NULL,
  "progress" TEXT,
  "approved_at" TEXT,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "compensation_details" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "id_dept" TEXT,
  "contract_id" INTEGER NOT NULL,
  "cutoff_date" TEXT NOT NULL,
  "amount" NUMERIC NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL,
  "is_active" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "month_duration" TEXT,
  "day_duration" TEXT,
  "end_date" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "compensations" (
  "id" INTEGER NOT NULL,
  "cutoff_date" TEXT NOT NULL,
  "total_employee" INTEGER,
  "total_amount" NUMERIC DEFAULT 0,
  "file_pdf" TEXT,
  "file_csv" TEXT,
  "progress" INTEGER,
  "status" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "is_closed" INTEGER,
  "file_excel" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "cutting_efficiencies" (
  "id" INTEGER NOT NULL,
  "period_id" TEXT NOT NULL,
  "npk" TEXT,
  "efficiency" NUMERIC NOT NULL,
  "date" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "DEPT" (
  "ID_DEPT" INTEGER NOT NULL,
  "DEPARTEMENT" TEXT,
  "IS_SEWING" TEXT,
  "SECTION" TEXT,
  "id_parent_dept" TEXT,
  PRIMARY KEY ("ID_DEPT")
);

CREATE TABLE "dept_breaktimes" (
  "id" INTEGER NOT NULL,
  "id_dept" TEXT NOT NULL,
  "id_break" INTEGER NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "dept_breaktimes_id_break_foreign" FOREIGN KEY ("id_break") REFERENCES "break_masters" ("id") ON DELETE CASCADE
);

CREATE TABLE "dept_insentif_role" (
  "id" INTEGER NOT NULL,
  "id_dept" INTEGER NOT NULL,
  "role" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "doorprize_scans" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "scanned_by" INTEGER,
  "scanned_at" TEXT NOT NULL DEFAULT getdate,
  "is_winner" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  "event_id" INTEGER NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "doorprize_scans_scanned_by_foreign" FOREIGN KEY ("scanned_by") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "doorprize_winners" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "name" TEXT,
  "department" TEXT,
  "photo" TEXT,
  "batch_label" TEXT,
  "drawn_by" INTEGER,
  "won_at" TEXT NOT NULL DEFAULT getdate,
  "created_at" TEXT,
  "updated_at" TEXT,
  "is_void" INTEGER NOT NULL DEFAULT 0,
  "void_reason" TEXT,
  "voided_at" TEXT,
  "voided_by" INTEGER,
  "event_id" INTEGER NOT NULL,
  PRIMARY KEY ("id"),
  CONSTRAINT "doorprize_winners_drawn_by_foreign" FOREIGN KEY ("drawn_by") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "employee_6s_assignments" (
  "id" INTEGER NOT NULL,
  "period_id" INTEGER NOT NULL,
  "section_id" INTEGER NOT NULL,
  "inspector" TEXT NOT NULL,
  "inspection_date" TEXT NOT NULL,
  "total_score" NUMERIC NOT NULL DEFAULT 0,
  "percentage" NUMERIC NOT NULL DEFAULT 0,
  "file_path" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "npk" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "employee_6s_assignments_period_id_foreign" FOREIGN KEY ("period_id") REFERENCES "payroll_periods" ("id") ON DELETE CASCADE
);

CREATE TABLE "employee_cutting_assignments" (
  "id" INTEGER NOT NULL,
  "period_id" TEXT NOT NULL,
  "npk" TEXT NOT NULL,
  "role" TEXT NOT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "employee_faces" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "name" TEXT,
  "face_img" TEXT NOT NULL,
  "descriptor" TEXT,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "registered_by" INTEGER,
  "last_verified_at" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "can_login" INTEGER NOT NULL DEFAULT 0,
  "is_attendance_enabled" INTEGER NOT NULL DEFAULT 1,
  "location_scope" TEXT NOT NULL DEFAULT 'all',
  "allowed_location_ids" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "employee_lates" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "date" TEXT NOT NULL,
  "arrival_time" TEXT NOT NULL,
  "reason" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "employee_line_assignments" (
  "id" INTEGER NOT NULL,
  "period_id" TEXT NOT NULL,
  "npk" TEXT NOT NULL,
  "line_number" INTEGER,
  "role" TEXT NOT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "section_id" TEXT,
  "work_hours" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "employee_mutations" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "from_dept" TEXT NOT NULL,
  "to_dept" TEXT NOT NULL,
  "date" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "employee_pad_assignments" (
  "id" INTEGER NOT NULL,
  "period_id" TEXT NOT NULL,
  "npk" TEXT NOT NULL,
  "dept" TEXT NOT NULL,
  "role" TEXT NOT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "employee_qc_assignments" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "period_id" INTEGER NOT NULL,
  "line_number" INTEGER,
  "role" TEXT,
  "start_date" TEXT,
  "end_date" TEXT,
  "work_hours" NUMERIC,
  "name" TEXT,
  "section_id" INTEGER,
  "buyer" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "third_party" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "employee_shifts" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "shift_id" INTEGER NOT NULL,
  "shift_date" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "employee_shifts_shift_id_foreign" FOREIGN KEY ("shift_id") REFERENCES "shifts" ("id") ON DELETE CASCADE
);

CREATE TABLE "employee_violations" (
  "id" INTEGER NOT NULL,
  "period_id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "percentage" NUMERIC NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "employees_contract" (
  "id" TEXT NOT NULL,
  "npk" TEXT NOT NULL,
  "contract_ke" TEXT NOT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT NOT NULL,
  "month_duration" TEXT NOT NULL,
  "status_contract" TEXT NOT NULL,
  "salary" NUMERIC NOT NULL DEFAULT 0,
  "allowance" NUMERIC NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  "type" TEXT,
  "daily_salary" NUMERIC,
  "pph21" NUMERIC,
  "day_duration" TEXT,
  "file_contract" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "epos" (
  "id" INTEGER NOT NULL,
  "expat_name" TEXT NOT NULL,
  "gender" TEXT,
  "place" TEXT,
  "date_of_birth" TEXT,
  "nationality" TEXT,
  "position" TEXT,
  "department" TEXT,
  "termination_date" TEXT,
  "must_leave_date" TEXT,
  "epo_cost" NUMERIC NOT NULL DEFAULT 0,
  "rptka_cancellation_cost" NUMERIC NOT NULL DEFAULT 0,
  "remarks" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "evaluation_employee" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "jobscope_id" TEXT NOT NULL,
  "score" INTEGER NOT NULL DEFAULT 0,
  "evaluation_date" TEXT,
  "employee_question" TEXT,
  "employee_answer" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "evaluation_jobscope" (
  "id" INTEGER NOT NULL,
  "job_code" TEXT,
  "job_name" TEXT,
  "description" TEXT,
  "qr_code" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "evaluation_questionnaire" (
  "id" INTEGER NOT NULL,
  "jobscope_id" TEXT NOT NULL,
  "question" TEXT NOT NULL,
  "optiona" TEXT NOT NULL,
  "optionb" TEXT NOT NULL,
  "optionc" TEXT NOT NULL,
  "optiond" TEXT NOT NULL,
  "correct_answer" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "event_invitations" (
  "id" INTEGER NOT NULL,
  "event_id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "nama" TEXT,
  "departemen" TEXT,
  "status" TEXT,
  "ucapan" TEXT,
  "ip_address" TEXT,
  "responded_at" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "event_invitations_event_id_foreign" FOREIGN KEY ("event_id") REFERENCES "events" ("id") ON DELETE CASCADE
);

CREATE TABLE "events" (
  "id" INTEGER NOT NULL,
  "nama_event" TEXT NOT NULL,
  "tanggal_event" TEXT NOT NULL,
  "waktu_event" TEXT NOT NULL,
  "lokasi_event" TEXT NOT NULL,
  "dress_code" TEXT,
  "detail_event" TEXT,
  "view_folder" TEXT NOT NULL,
  "jumlah_hadir" INTEGER NOT NULL DEFAULT 0,
  "jumlah_tidak_hadir" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "exchange_rates" (
  "id" INTEGER NOT NULL,
  "rate_date" TEXT NOT NULL,
  "currency_from" TEXT NOT NULL DEFAULT 'USD',
  "currency_to" TEXT NOT NULL DEFAULT 'IDR',
  "kurs_jual" NUMERIC,
  "kurs_beli" NUMERIC,
  "kurs_tengah" NUMERIC,
  "source" TEXT NOT NULL DEFAULT 'bi.go.id',
  "raw_response" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "expat_cost" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "amount" NUMERIC,
  "transactions_date" TEXT,
  "remark" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "component" INTEGER,
  PRIMARY KEY ("id")
);

CREATE TABLE "expat_cost_components" (
  "id" INTEGER NOT NULL,
  "component" TEXT NOT NULL,
  "component_type" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "expat_documents" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "document_type" TEXT NOT NULL,
  "file_name" TEXT NOT NULL,
  "file_path" TEXT NOT NULL,
  "file_size" INTEGER,
  "notes" TEXT,
  "uploaded_by" INTEGER,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "expat_master" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "name" TEXT,
  "position" TEXT,
  "joining_date" TEXT,
  "end_date" TEXT,
  "passport_number" TEXT,
  "passport_expiry" TEXT,
  "kitas_expiry" TEXT,
  "rptka_expiry" TEXT,
  "merp_expiry" TEXT,
  "house_address" TEXT,
  "house_startdate" TEXT,
  "lease_enddate" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "place" TEXT,
  "nationality" TEXT,
  "direct_report" TEXT,
  "npwp" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "expat_meal_menus" (
  "id" INTEGER NOT NULL,
  "makanan" TEXT NOT NULL,
  "kategori" TEXT NOT NULL,
  "tanggal" TEXT NOT NULL,
  "harga" NUMERIC NOT NULL DEFAULT 0,
  "shared" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "expat_meal_participants" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "nama_expat" TEXT NOT NULL,
  "tanggal" TEXT NOT NULL,
  "kategori" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "expat_onleave" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "onleave_start" TEXT,
  "onleave_end" TEXT,
  "leave_type" TEXT,
  "transactions_date" TEXT,
  "remark" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "component" TEXT,
  "amount" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "extracted_data" (
  "id" INTEGER NOT NULL,
  "pdf_document_id" INTEGER NOT NULL,
  "category_id" INTEGER,
  "data_key" TEXT NOT NULL,
  "data_value" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "extracted_data_pdf_document_id_foreign" FOREIGN KEY ("pdf_document_id") REFERENCES "pdf_documents" ("id") ON DELETE CASCADE,
  CONSTRAINT "extracted_data_category_id_foreign" FOREIGN KEY ("category_id") REFERENCES "categories" ("id") ON DELETE SET NULL
);

CREATE TABLE "extracted_table_rows" (
  "id" INTEGER NOT NULL,
  "extracted_table_id" INTEGER NOT NULL,
  "row_index" INTEGER NOT NULL DEFAULT 0,
  "row_data" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "extracted_table_rows_extracted_table_id_foreign" FOREIGN KEY ("extracted_table_id") REFERENCES "extracted_tables" ("id") ON DELETE CASCADE
);

CREATE TABLE "extracted_tables" (
  "id" INTEGER NOT NULL,
  "pdf_document_id" INTEGER NOT NULL,
  "table_name" TEXT,
  "headers" TEXT NOT NULL,
  "table_index" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "extracted_tables_pdf_document_id_foreign" FOREIGN KEY ("pdf_document_id") REFERENCES "pdf_documents" ("id") ON DELETE CASCADE
);

CREATE TABLE "face_attendance_logs" (
  "id" INTEGER NOT NULL,
  "att_id" TEXT,
  "npk" TEXT NOT NULL,
  "pin" TEXT NOT NULL,
  "action" TEXT NOT NULL,
  "scan_date" TEXT NOT NULL,
  "latitude" NUMERIC,
  "longitude" NUMERIC,
  "accuracy" REAL,
  "inside_polygon" INTEGER NOT NULL DEFAULT 0,
  "work_location_id" INTEGER,
  "face_match" INTEGER NOT NULL DEFAULT 0,
  "confidence" NUMERIC,
  "liveness_passed" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL,
  "rejection_reason" TEXT,
  "ip_address" TEXT,
  "user_agent" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "face_image" TEXT,
  "is_suspicious" INTEGER,
  "suspicious_reason" TEXT,
  "nonce_verified" INTEGER,
  PRIMARY KEY ("id")
);

CREATE TABLE "face_attendance_settings" (
  "id" INTEGER NOT NULL,
  "clock_in_before_minutes" INTEGER NOT NULL DEFAULT 15,
  "clock_out_after_minutes" INTEGER NOT NULL DEFAULT 15,
  "is_saturday_enabled" INTEGER NOT NULL DEFAULT 0,
  "is_sunday_enabled" INTEGER NOT NULL DEFAULT 0,
  "is_holiday_enabled" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  "clock_in_allowance_minutes" INTEGER NOT NULL DEFAULT 0,
  "clock_out_allowance_minutes" INTEGER NOT NULL,
  "is_npk_required_enabled" INTEGER NOT NULL DEFAULT 1,
  PRIMARY KEY ("id")
);

CREATE TABLE "face_recognition_settings" (
  "id" INTEGER NOT NULL,
  "is_enabled" INTEGER NOT NULL DEFAULT 1,
  "match_threshold" NUMERIC NOT NULL DEFAULT 0.5,
  "created_at" TEXT,
  "updated_at" TEXT,
  "is_qr_enabled" INTEGER NOT NULL DEFAULT 1,
  "is_qr_login_enabled" INTEGER NOT NULL DEFAULT 1,
  "is_qr_payroll_enabled" INTEGER NOT NULL DEFAULT 1,
  "is_qr_thr_enabled" INTEGER NOT NULL DEFAULT 1,
  "is_qr_cuti_enabled" INTEGER NOT NULL DEFAULT 1,
  "is_liveness_enabled" INTEGER NOT NULL DEFAULT 1,
  "liveness_presensi_enabled" INTEGER NOT NULL DEFAULT 1,
  "liveness_login_enabled" INTEGER NOT NULL DEFAULT 1,
  "liveness_slip_enabled" INTEGER NOT NULL DEFAULT 1,
  "liveness_cuti_enabled" INTEGER NOT NULL DEFAULT 1,
  "liveness_registrasi_enabled" INTEGER NOT NULL DEFAULT 1,
  "is_npk_required_enabled" INTEGER NOT NULL DEFAULT 1,
  "is_random_challenge_enabled" INTEGER NOT NULL DEFAULT 0,
  "random_challenge_presensi_enabled" INTEGER NOT NULL DEFAULT 1,
  "random_challenge_login_enabled" INTEGER NOT NULL DEFAULT 1,
  "random_challenge_slip_enabled" INTEGER NOT NULL DEFAULT 1,
  "random_challenge_cuti_enabled" INTEGER NOT NULL DEFAULT 1,
  "random_challenge_registrasi_enabled" INTEGER NOT NULL DEFAULT 1,
  "liveness_canteen_enabled" INTEGER NOT NULL,
  "random_challenge_canteen_enabled" INTEGER NOT NULL,
  "canteen_verification_mode" TEXT NOT NULL DEFAULT 'liveness',
  "hris_v2_verification_mode" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "food_menus" (
  "id" INTEGER NOT NULL,
  "canteen_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "description" TEXT,
  "photo" TEXT,
  "price" NUMERIC NOT NULL DEFAULT 0,
  "available_start" TEXT,
  "available_end" TEXT,
  "quota" INTEGER,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  "available_dates" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "food_menus_canteen_id_foreign" FOREIGN KEY ("canteen_id") REFERENCES "canteens" ("id") ON DELETE CASCADE
);

CREATE TABLE "food_orders" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "food_menu_id" INTEGER NOT NULL,
  "canteen_id" INTEGER NOT NULL,
  "order_date" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "notes" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "foreign_guests" (
  "id" INTEGER NOT NULL,
  "guest_name" TEXT NOT NULL,
  "bank_account" TEXT,
  "photo" TEXT,
  "passport" TEXT,
  "visa_type" TEXT,
  "visa_application" TEXT,
  "visa_status" TEXT,
  "visa_invoice" INTEGER,
  "rent_invoice" INTEGER,
  "flight_detail" TEXT,
  "flight_eta" TEXT,
  "eta" TEXT,
  "return" TEXT,
  "hotel" TEXT,
  "hotel_file" TEXT,
  "hotel_invoice" INTEGER,
  "status" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "gender" TEXT,
  "place" TEXT,
  "date_of_birth" TEXT,
  "nationality" TEXT,
  "passport_no" TEXT,
  "remark" TEXT,
  "issue_date" TEXT,
  "must_used_date" TEXT,
  "arrival_date" TEXT,
  "visa_expiry" TEXT,
  "is_created" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "guest_masters" (
  "id" INTEGER NOT NULL,
  "foreign_guest_id" INTEGER,
  "gender" TEXT,
  "place" TEXT,
  "date_of_birth" TEXT,
  "nationality" TEXT,
  "passport_no" TEXT,
  "remark" TEXT,
  "issue_date" TEXT,
  "must_used_date" TEXT,
  "arrival_date" TEXT,
  "visa_expiry" TEXT,
  "status" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "name" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "health_tests" (
  "id" INTEGER NOT NULL,
  "nik" TEXT NOT NULL,
  "cacat" INTEGER NOT NULL DEFAULT 0,
  "buta_warna" INTEGER NOT NULL DEFAULT 0,
  "visus_mata_od" TEXT,
  "tinggi" INTEGER,
  "berat" INTEGER,
  "abdoment" TEXT,
  "gigi" TEXT,
  "cor_pulmo" TEXT,
  "tht" TEXT,
  "extreme" TEXT,
  "tekanan_darah" TEXT,
  "respirasi" INTEGER,
  "denyut" INTEGER,
  "suhu" NUMERIC,
  "paru" INTEGER NOT NULL DEFAULT 0,
  "hepatitis" INTEGER NOT NULL DEFAULT 0,
  "jantung" INTEGER NOT NULL DEFAULT 0,
  "thypoid" INTEGER NOT NULL DEFAULT 0,
  "alergi" INTEGER NOT NULL DEFAULT 0,
  "ashma" INTEGER NOT NULL DEFAULT 0,
  "lain" TEXT,
  "kesimpulan" INTEGER NOT NULL DEFAULT 1,
  "remark" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "visus_mata_os" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "heat_efficiencies" (
  "id" INTEGER NOT NULL,
  "period_id" TEXT NOT NULL,
  "npk" TEXT NOT NULL,
  "role" TEXT NOT NULL,
  "efficiency" NUMERIC,
  "piece" NUMERIC,
  "date" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "tim" INTEGER,
  "group" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "holidays" (
  "id" INTEGER NOT NULL,
  "holiday_date" TEXT NOT NULL,
  "name" TEXT NOT NULL,
  "is_national" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "hris_v2_menus" (
  "id" INTEGER NOT NULL,
  "key" TEXT NOT NULL,
  "label" TEXT NOT NULL,
  "description" TEXT,
  "route_name" TEXT NOT NULL,
  "icon" TEXT NOT NULL DEFAULT 'fas fa-circle',
  "color" TEXT NOT NULL DEFAULT '#1d3f86',
  "is_enabled" INTEGER NOT NULL DEFAULT 1,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "hris_v2_settings" (
  "id" INTEGER NOT NULL,
  "key" TEXT NOT NULL,
  "value" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "ijin_meninggalkan_pekerjaans" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "tanggal" TEXT NOT NULL,
  "jam_keluar" TEXT NOT NULL,
  "rencana_kembali" TEXT,
  "jam_kembali" TEXT,
  "reason" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "id_break" INTEGER,
  "is_deduction" INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY ("id")
);

CREATE TABLE "insentif_approvals" (
  "id" INTEGER NOT NULL,
  "period_id" INTEGER NOT NULL,
  "payroll_component" TEXT NOT NULL,
  "approval" TEXT,
  "progress" TEXT,
  "approved_at" TEXT,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "insentif_approvals_period_id_foreign" FOREIGN KEY ("period_id") REFERENCES "payroll_periods" ("id") ON DELETE CASCADE
);

CREATE TABLE "insentif_role_formulas" (
  "id" INTEGER NOT NULL,
  "role" TEXT NOT NULL,
  "dept" TEXT NOT NULL,
  "formula" TEXT NOT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "insentif_thresholds" (
  "id" INTEGER NOT NULL,
  "insentif_type" TEXT NOT NULL,
  "days" INTEGER NOT NULL,
  "minimum" NUMERIC NOT NULL,
  "type" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "job_vacancies" (
  "id" INTEGER NOT NULL,
  "position" TEXT NOT NULL,
  "department_id" TEXT NOT NULL,
  "total_needed" INTEGER NOT NULL DEFAULT 1,
  "employment_type" TEXT NOT NULL DEFAULT 'full_time',
  "job_description" TEXT,
  "criteria" TEXT,
  "required_documents" TEXT,
  "open_date" TEXT NOT NULL,
  "close_date" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "created_by" INTEGER,
  "created_at" TEXT,
  "updated_at" TEXT,
  "deleted_at" TEXT,
  "recruitment_position_id" INTEGER,
  PRIMARY KEY ("id"),
  CONSTRAINT "job_vacancies_created_by_foreign" FOREIGN KEY ("created_by") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "kunjungans" (
  "id" INTEGER NOT NULL,
  "NPK" TEXT,
  "tanggal_kunjungan" TEXT NOT NULL,
  "jam_masuk" TEXT,
  "jam_selesai" TEXT,
  "keluhan" TEXT NOT NULL,
  "diagnosa" TEXT,
  "catatan_dokter" TEXT,
  "tindakan" TEXT,
  "status" TEXT NOT NULL DEFAULT 'menunggu',
  "dokter_id" INTEGER,
  "no_antrian" INTEGER NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "nama" TEXT,
  "dept" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "kunjungans_dokter_id_foreign" FOREIGN KEY ("dokter_id") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "late_compensations" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "date" TEXT NOT NULL,
  "reason" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "leave_balances" (
  "id" INTEGER NOT NULL,
  "NPK" TEXT NOT NULL,
  "leave_type_id" INTEGER NOT NULL,
  "year" INTEGER NOT NULL,
  "remained_days" INTEGER NOT NULL,
  "used_days" INTEGER NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "negative_leave" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "leave_balances_leave_type_id_foreign" FOREIGN KEY ("leave_type_id") REFERENCES "leave_types" ("id")
);

CREATE TABLE "leave_reasons" (
  "id" INTEGER NOT NULL,
  "leave_type_id" INTEGER NOT NULL,
  "reason" TEXT NOT NULL,
  "created_at" TEXT NOT NULL DEFAULT getdate,
  "updated_at" TEXT NOT NULL DEFAULT getdate,
  PRIMARY KEY ("id")
);

CREATE TABLE "leave_requests" (
  "id" INTEGER NOT NULL,
  "NPK" TEXT NOT NULL,
  "leave_type_id" INTEGER NOT NULL,
  "reason" TEXT NOT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT NOT NULL,
  "total_days" INTEGER NOT NULL,
  "approval_id" TEXT NOT NULL,
  "approval_level" INTEGER NOT NULL,
  "approval_progress" INTEGER NOT NULL,
  "approval_date" TEXT,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "token" TEXT NOT NULL,
  "void" TEXT NOT NULL DEFAULT 'false',
  "created_at" TEXT,
  "updated_at" TEXT,
  "comment" TEXT,
  "attach_files" TEXT,
  "custom_dates" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "leave_requests_leave_type_id_foreign" FOREIGN KEY ("leave_type_id") REFERENCES "leave_types" ("id")
);

CREATE TABLE "leave_types" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "code" TEXT NOT NULL,
  "default_days" TEXT NOT NULL,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  "gender_type" TEXT,
  "count_calendar_days" INTEGER NOT NULL DEFAULT 0,
  "max_requests" INTEGER,
  PRIMARY KEY ("id")
);

CREATE TABLE "line_efficiencies" (
  "id" INTEGER NOT NULL,
  "period_id" TEXT NOT NULL,
  "line_number" INTEGER NOT NULL,
  "efficiency" NUMERIC NOT NULL,
  "date" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "days" INTEGER,
  PRIMARY KEY ("id")
);

CREATE TABLE "line_jumper_operations" (
  "id" INTEGER NOT NULL,
  "period_id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "operation" TEXT NOT NULL,
  "operation_key" TEXT NOT NULL,
  "machine" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "live_access_logs" (
  "id" INTEGER NOT NULL,
  "user_id" INTEGER,
  "user_name" TEXT,
  "npk" TEXT,
  "ip_address" TEXT,
  "method" TEXT NOT NULL,
  "path" TEXT NOT NULL,
  "route_name" TEXT,
  "status" INTEGER,
  "user_agent" TEXT,
  "duration_ms" INTEGER,
  "accessed_at" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "menus" (
  "id" INTEGER NOT NULL,
  "parent_id" INTEGER,
  "name" TEXT NOT NULL,
  "route_name" TEXT,
  "icon" TEXT,
  "permission_id" INTEGER,
  "order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "menus_permission_id_foreign" FOREIGN KEY ("permission_id") REFERENCES "permissions" ("id") ON DELETE SET NULL,
  CONSTRAINT "menus_parent_id_foreign" FOREIGN KEY ("parent_id") REFERENCES "menus" ("id")
);

CREATE TABLE "migrations" (
  "id" INTEGER NOT NULL,
  "migration" TEXT NOT NULL,
  "batch" INTEGER NOT NULL,
  PRIMARY KEY ("id")
);

CREATE TABLE "model_has_permissions" (
  "permission_id" INTEGER NOT NULL,
  "model_type" TEXT NOT NULL,
  "model_id" INTEGER NOT NULL,
  PRIMARY KEY ("permission_id", "model_id", "model_type")
);

CREATE TABLE "model_has_roles" (
  "role_id" INTEGER NOT NULL,
  "model_type" TEXT NOT NULL,
  "model_id" INTEGER NOT NULL,
  PRIMARY KEY ("role_id", "model_id", "model_type"),
  CONSTRAINT "model_has_roles_role_id_foreign" FOREIGN KEY ("role_id") REFERENCES "roles" ("id") ON DELETE CASCADE
);

CREATE TABLE "mon_boms" (
  "id" INTEGER NOT NULL,
  "bom_id" TEXT,
  "code_prod" TEXT,
  "tgl_prod" TEXT,
  "barang_code" TEXT,
  "barang_name" TEXT,
  "satuan_code" TEXT,
  "uraian" TEXT,
  "spesifikasi" TEXT,
  "cons" NUMERIC DEFAULT 0,
  "scrap_percent" NUMERIC DEFAULT 0,
  "departemen" TEXT,
  "komponen" TEXT,
  "barang_jadi" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_ms_barangs" (
  "id" INTEGER NOT NULL,
  "barang_code" TEXT NOT NULL,
  "barang_name" TEXT,
  "satuan_code" TEXT,
  "barang_category" TEXT,
  "header_code" TEXT,
  "hs_code" TEXT,
  "kode_sub_ap" TEXT,
  "kode_sub_ar" TEXT,
  "barang_status" TEXT,
  "create_by" TEXT,
  "create_date" TEXT,
  "modify_by" TEXT,
  "modify_date" TEXT,
  "satuan_hs" TEXT,
  "konversi" NUMERIC,
  "kode" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_ms_negaras" (
  "id" INTEGER NOT NULL,
  "negara_code" TEXT NOT NULL,
  "negara_name" TEXT,
  "create_by" TEXT,
  "create_date" TEXT,
  "modify_by" TEXT,
  "modify_date" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_ms_suppliers" (
  "id" INTEGER NOT NULL,
  "supplier_code" TEXT NOT NULL,
  "supplier_name" TEXT,
  "npwp" TEXT,
  "phone" TEXT,
  "pic" TEXT,
  "email" TEXT,
  "rekening" TEXT,
  "category" TEXT,
  "kode_sub_ap" TEXT,
  "kode_sub_ar" TEXT,
  "negara_id" TEXT,
  "supplier_status" TEXT,
  "create_by" TEXT,
  "create_date" TEXT,
  "modify_by" TEXT,
  "modify_date" TEXT,
  "ppb" INTEGER DEFAULT 0,
  "tpb" TEXT,
  "nib" TEXT,
  "tgl_tpb" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_orders" (
  "id" INTEGER NOT NULL,
  "uraian" TEXT NOT NULL,
  "ocf_no" TEXT,
  "buyer_po" TEXT,
  "buyer" TEXT,
  "brand" TEXT,
  "style" TEXT,
  "item" TEXT,
  "qty_ord" NUMERIC DEFAULT 0,
  "destination" TEXT,
  "artwork" TEXT,
  "sewing_process" TEXT,
  "production_delivery" TEXT,
  "buyer_delivery" TEXT,
  "prod_start" TEXT,
  "prod_end" TEXT,
  "shipment_mode" TEXT,
  "material_fab" TEXT,
  "fabric" TEXT,
  "sample" TEXT,
  "thread" TEXT,
  "pad_htl" TEXT,
  "main_label" TEXT,
  "care_label" TEXT,
  "button_snap" TEXT,
  "tape" TEXT,
  "hangtag" TEXT,
  "price_ticket" TEXT,
  "size_strip" TEXT,
  "polybag" TEXT,
  "sticker" TEXT,
  "hanger" TEXT,
  "sizer" TEXT,
  "carton_box" TEXT,
  "vessel_book" TEXT,
  "payment_terms" TEXT,
  "column17" TEXT,
  "fob" TEXT,
  "price" NUMERIC,
  "column18" TEXT,
  "column19" TEXT,
  "cmt" TEXT,
  "price20" TEXT,
  "column21" TEXT,
  "sample2" TEXT,
  "smv" NUMERIC,
  "planned_qty" NUMERIC,
  "sewing_start_date" TEXT,
  "remarks" TEXT,
  "import_batch" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "column1" TEXT,
  "column2" TEXT,
  "column3" TEXT,
  "catatan" TEXT,
  "sub_ref" TEXT,
  "season" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_prod_lines" (
  "id" INTEGER NOT NULL,
  "line_id" TEXT NOT NULL,
  "code_prod" TEXT,
  "department_id" TEXT,
  "tgl_produksi" TEXT,
  "barang_code" TEXT,
  "barang_name" TEXT,
  "barang_category" TEXT,
  "jumlah" NUMERIC,
  "destination" TEXT,
  "no_surat_jalan" TEXT,
  "create_by" TEXT,
  "create_date" TEXT,
  "digit" INTEGER NOT NULL DEFAULT 2,
  "total_nilai" NUMERIC,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_prod_qc" (
  "id" INTEGER NOT NULL,
  "code_prod" TEXT NOT NULL,
  "department_id" TEXT NOT NULL,
  "jumlah" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  "cpo" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_purchase_orders" (
  "id" INTEGER NOT NULL,
  "klaim_fsc" TEXT,
  "dt_po_id" TEXT,
  "po_id" TEXT,
  "jenis_po" TEXT,
  "no_po" TEXT,
  "tgl_po" TEXT,
  "supplier_name" TEXT,
  "barang_code" TEXT,
  "barang_name" TEXT,
  "satuan_order" TEXT,
  "uraian" TEXT,
  "spesifikasi" TEXT,
  "jumlah_order" NUMERIC DEFAULT 0,
  "harga_satuan" NUMERIC DEFAULT 0,
  "harga_total" NUMERIC DEFAULT 0,
  "harga_fob" NUMERIC DEFAULT 0,
  "total_fob" NUMERIC DEFAULT 0,
  "ppn" TEXT,
  "pph" TEXT,
  "discount" NUMERIC DEFAULT 0,
  "biaya" NUMERIC DEFAULT 0,
  "valas" TEXT,
  "note" TEXT,
  "create_by" TEXT,
  "create_date" TEXT,
  "ncv" TEXT,
  "jumlah_doc" NUMERIC DEFAULT 0,
  "total_in" NUMERIC DEFAULT 0,
  "out_req" NUMERIC DEFAULT 0,
  "total_req" NUMERIC DEFAULT 0,
  "out_prod" NUMERIC DEFAULT 0,
  "total_prod" NUMERIC DEFAULT 0,
  "out_doc" NUMERIC DEFAULT 0,
  "total_doc" NUMERIC DEFAULT 0,
  "digit" INTEGER DEFAULT 2,
  "total_order" NUMERIC DEFAULT 0,
  "total_harga" NUMERIC DEFAULT 0,
  "sisa" NUMERIC DEFAULT 0,
  "total_sisa" NUMERIC DEFAULT 0,
  "total_gudang" NUMERIC DEFAULT 0,
  "total_wip" NUMERIC DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  "tgl_pengiriman" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_rekon_lock_fabrics" (
  "id" INTEGER NOT NULL,
  "lock_id" INTEGER NOT NULL,
  "barang_code" TEXT,
  "barang_name" TEXT,
  "need_qty" NUMERIC NOT NULL DEFAULT 0,
  "order_qty" NUMERIC NOT NULL DEFAULT 0,
  "received_qty" NUMERIC NOT NULL DEFAULT 0,
  "out_req_qty" NUMERIC NOT NULL DEFAULT 0,
  "out_doc_qty" NUMERIC NOT NULL DEFAULT 0,
  "stock_qty" NUMERIC NOT NULL DEFAULT 0,
  "harga_total" NUMERIC NOT NULL DEFAULT 0,
  "order_pct" NUMERIC NOT NULL DEFAULT 0,
  "received_pct" NUMERIC NOT NULL DEFAULT 0,
  "out_req_pct" NUMERIC NOT NULL DEFAULT 0,
  "out_doc_pct" NUMERIC NOT NULL DEFAULT 0,
  "stock_pct" NUMERIC NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "fk_mon_rekon_lock_fabrics_lock" FOREIGN KEY ("lock_id") REFERENCES "mon_rekon_locks" ("id") ON DELETE CASCADE
);

CREATE TABLE "mon_rekon_locks" (
  "id" INTEGER NOT NULL,
  "lock_date" TEXT NOT NULL,
  "lock_type" TEXT NOT NULL,
  "ocf_no" TEXT NOT NULL,
  "buyer" TEXT,
  "style" TEXT,
  "sub_ref" TEXT,
  "uraian" TEXT,
  "negara" TEXT,
  "filters_json" TEXT,
  "qty_contract" NUMERIC NOT NULL DEFAULT 0,
  "qty_cutting" NUMERIC NOT NULL DEFAULT 0,
  "qty_sewing" NUMERIC NOT NULL DEFAULT 0,
  "qty_qc" NUMERIC NOT NULL DEFAULT 0,
  "qty_packing" NUMERIC NOT NULL DEFAULT 0,
  "qty_ct_pat" NUMERIC NOT NULL DEFAULT 0,
  "qty_dest_sewing" NUMERIC NOT NULL DEFAULT 0,
  "qty_dest_packing" NUMERIC NOT NULL DEFAULT 0,
  "qty_sabkon" NUMERIC NOT NULL DEFAULT 0,
  "qty_sabkon_warehouse" NUMERIC NOT NULL DEFAULT 0,
  "qty_shipment" NUMERIC NOT NULL DEFAULT 0,
  "loss_cutting" NUMERIC NOT NULL DEFAULT 0,
  "loss_sewing" NUMERIC NOT NULL DEFAULT 0,
  "loss_packing" NUMERIC NOT NULL DEFAULT 0,
  "total_loss" NUMERIC NOT NULL DEFAULT 0,
  "total_loss_pct" NUMERIC NOT NULL DEFAULT 0,
  "shipment_loss" NUMERIC NOT NULL DEFAULT 0,
  "balance_garment_stock" NUMERIC NOT NULL DEFAULT 0,
  "short_ship_pcs" NUMERIC NOT NULL DEFAULT 0,
  "sisa_fabric_kg" NUMERIC NOT NULL DEFAULT 0,
  "sisa_fabric_value" NUMERIC NOT NULL DEFAULT 0,
  "cm_per_pcs" NUMERIC NOT NULL DEFAULT 0,
  "grade_a" NUMERIC,
  "grade_b" NUMERIC,
  "grade_c" NUMERIC,
  "grade_imported_at" TEXT,
  "grade_imported_by" TEXT,
  "locked_by" TEXT,
  "locked_at" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "note_prioritas" INTEGER,
  "note_status" TEXT,
  "note_masalah" TEXT,
  "note_tindakan" TEXT,
  "note_pic" TEXT,
  "note_target" TEXT,
  "note_estimasi" NUMERIC,
  "note_imported_at" TEXT,
  "note_imported_by" TEXT,
  "lock_mode" TEXT NOT NULL DEFAULT 'ocf',
  "cpo_no" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_rekonsiliasis" (
  "id" INTEGER NOT NULL,
  "dt_po_id" TEXT NOT NULL,
  "valas" TEXT,
  "no_po" TEXT,
  "jenis_po" TEXT,
  "tgl_po" TEXT,
  "termin" TEXT,
  "tgl_pengiriman" TEXT,
  "supplier_name" TEXT,
  "barang_code" TEXT,
  "barang_name" TEXT,
  "satuan_order" TEXT,
  "satuan_code" TEXT,
  "klaim_fsc" TEXT,
  "uraian" TEXT,
  "spesifikasi" TEXT,
  "ncv" TEXT,
  "jumlah_order" NUMERIC NOT NULL DEFAULT 0,
  "jumlah_doc" NUMERIC NOT NULL DEFAULT 0,
  "out_req" NUMERIC NOT NULL DEFAULT 0,
  "out_prod" NUMERIC NOT NULL DEFAULT 0,
  "sisa" NUMERIC NOT NULL DEFAULT 0,
  "saldo_wip" NUMERIC NOT NULL DEFAULT 0,
  "digit" INTEGER NOT NULL DEFAULT 2,
  "out_doc" NUMERIC NOT NULL DEFAULT 0,
  "create_by" TEXT,
  "create_date" TEXT,
  "harga_total" NUMERIC NOT NULL DEFAULT 0,
  "saldo_gudang" NUMERIC NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_shipments" (
  "id" INTEGER NOT NULL,
  "row_key" TEXT NOT NULL,
  "hs_code" TEXT,
  "doc_id" TEXT,
  "jenis_doc" TEXT,
  "no_aju" TEXT,
  "no_doc" TEXT,
  "tgl_doc" TEXT,
  "no_bukti" TEXT,
  "tgl_bukti" TEXT,
  "jenis_ps" TEXT,
  "no_ps" TEXT,
  "no_invoice" TEXT,
  "file_number" TEXT,
  "supplier_name" TEXT,
  "barang_code" TEXT,
  "barang_name" TEXT,
  "barang_category" TEXT,
  "satuan_doc" TEXT,
  "jumlah_doc" NUMERIC NOT NULL DEFAULT 0,
  "valas" TEXT,
  "nilai_barang" NUMERIC NOT NULL DEFAULT 0,
  "nilai_fob" NUMERIC NOT NULL DEFAULT 0,
  "jumlah_aktual" NUMERIC NOT NULL DEFAULT 0,
  "keterangan" TEXT,
  "ps_id" TEXT,
  "spesifikasi" TEXT,
  "uraian" TEXT,
  "berat" NUMERIC,
  "jumlah_barang" NUMERIC NOT NULL DEFAULT 0,
  "satuan_order" TEXT,
  "satuan_code" TEXT,
  "dt_so_id" TEXT,
  "acc_bukti" TEXT,
  "acc_tgl" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_stage_remarks" (
  "id" INTEGER NOT NULL,
  "ocf_no" TEXT NOT NULL,
  "department_id" TEXT NOT NULL,
  "remark" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "cpo" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_stock_fisik" (
  "id" INTEGER NOT NULL,
  "tipe" TEXT NOT NULL,
  "dt_doc_id" TEXT,
  "barang_code" TEXT,
  "barang_name" TEXT,
  "satuan_code" TEXT,
  "barang_category" TEXT,
  "spesifikasi" TEXT,
  "jenis_doc" TEXT,
  "keterangan" TEXT,
  "jenis_doc1" TEXT,
  "no_doc" TEXT,
  "tgl_doc" TEXT,
  "saldo" NUMERIC NOT NULL DEFAULT 0,
  "harga_satuan" NUMERIC,
  "harga_satuan_fob" NUMERIC,
  "uraian" TEXT,
  "nilai_harga" NUMERIC,
  "nilai_harga_fob" NUMERIC,
  "digit" INTEGER NOT NULL DEFAULT 2,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_subkons" (
  "id" INTEGER NOT NULL,
  "id_order" TEXT NOT NULL,
  "no_order" TEXT,
  "tgl_order" TEXT,
  "jenis" TEXT NOT NULL,
  "supplier_code" TEXT,
  "supplier_name" TEXT,
  "qty_material_order" NUMERIC NOT NULL DEFAULT 0,
  "qty_result_order" NUMERIC NOT NULL DEFAULT 0,
  "qty_material_aktual" NUMERIC NOT NULL DEFAULT 0,
  "qty_result_aktual" NUMERIC NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "mon_work_orders" (
  "id" INTEGER NOT NULL,
  "wo_id" TEXT NOT NULL,
  "prod_id" TEXT,
  "code_prod" TEXT,
  "product_code" TEXT,
  "jumlah_prod" NUMERIC NOT NULL DEFAULT 0,
  "tgl_prod" TEXT,
  "tgl_doc" TEXT,
  "target_hari" NUMERIC,
  "note" TEXT,
  "warehouse_id" TEXT,
  "status" TEXT,
  "plan_create_by" TEXT,
  "plan_create_date" TEXT,
  "plan_modify_by" TEXT,
  "plan_modify_date" TEXT,
  "flag_type" TEXT,
  "product_name" TEXT,
  "bom_prod_id" TEXT,
  "barang_code" TEXT,
  "uraian" TEXT,
  "spesifikasi" TEXT,
  "departemen" TEXT,
  "cons" NUMERIC NOT NULL DEFAULT 0,
  "scrap_percent" NUMERIC NOT NULL DEFAULT 0,
  "bom_create_by" TEXT,
  "bom_create_date" TEXT,
  "bom_modify_by" TEXT,
  "bom_modify_date" TEXT,
  "auto_create" TEXT,
  "komponen" TEXT,
  "bom_jumlah_prod" NUMERIC NOT NULL DEFAULT 0,
  "barang_name" TEXT,
  "satuan_code" TEXT,
  "request" NUMERIC NOT NULL DEFAULT 0,
  "total" NUMERIC NOT NULL DEFAULT 0,
  "actual_cons" NUMERIC NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "notifications_contracts" (
  "id" INTEGER NOT NULL,
  "contract_id" TEXT NOT NULL,
  "npk" TEXT NOT NULL,
  "employee_name" TEXT NOT NULL,
  "contract_end_date" TEXT NOT NULL,
  "days_remaining" INTEGER NOT NULL,
  "type" TEXT NOT NULL DEFAULT 'contract_expiring',
  "status" TEXT NOT NULL DEFAULT 'unread',
  "notified_at" TEXT NOT NULL DEFAULT getdate,
  "read_at" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "notifications_contracts_contract_id_foreign" FOREIGN KEY ("contract_id") REFERENCES "employees_contract" ("id") ON DELETE CASCADE
);

CREATE TABLE "org_chart_settings" (
  "id" INTEGER NOT NULL,
  "id_dept" TEXT,
  "level" TEXT NOT NULL,
  "parent_id" INTEGER,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  "label" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "OUTSOURCE" (
  "NPK" TEXT NOT NULL,
  "NAMA" TEXT,
  "JK" TEXT,
  "TGLLAHIR" TEXT,
  "PDDK" TEXT,
  "AGAMA" TEXT,
  "TMK" TEXT,
  "USIA" TEXT,
  "TKK" TEXT,
  "BAGIAN" TEXT,
  "ALAMAT" TEXT,
  "KABUPATEN" TEXT,
  "KTP" TEXT,
  "NO_KK" TEXT DEFAULT '-',
  "IBU" TEXT,
  "HP" TEXT,
  "STATUS" TEXT,
  "TANGGUNGAN" TEXT,
  "KETERANGAN" TEXT,
  "TUTUPBUKU" TEXT,
  "TMPTLAHIR" TEXT,
  "NOREK" TEXT,
  "JURUSAN" TEXT,
  "FASKES" TEXT,
  PRIMARY KEY ("NPK")
);

CREATE TABLE "overtimes" (
  "id" INTEGER NOT NULL,
  "NPK" TEXT NOT NULL,
  "NAMA_KARYAWAN" TEXT NOT NULL,
  "BAGIAN" TEXT NOT NULL,
  "OVERTIME_DATE" TEXT NOT NULL,
  "JUMLAH_JAM_LEMBUR" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "DAY" TEXT,
  "DEPT_GROUP" TEXT,
  "is_request" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "overtimes_payroll" (
  "id" INTEGER NOT NULL,
  "NPK" TEXT NOT NULL,
  "NAMA_KARYAWAN" TEXT NOT NULL,
  "BAGIAN" TEXT NOT NULL,
  "OVERTIME_DATE" TEXT NOT NULL,
  "JUMLAH_JAM_LEMBUR" TEXT,
  "DAY" TEXT NOT NULL,
  "DEPT_GROUP" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "overtimes_payroll_audit" (
  "id" INTEGER NOT NULL,
  "NPK" TEXT NOT NULL,
  "NAMA_KARYAWAN" TEXT NOT NULL,
  "BAGIAN" TEXT NOT NULL,
  "OVERTIME_DATE" TEXT NOT NULL,
  "JUMLAH_JAM_LEMBUR" TEXT,
  "DAY" TEXT NOT NULL,
  "DEPT_GROUP" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "pad_efficiencies" (
  "id" INTEGER NOT NULL,
  "period_id" TEXT NOT NULL,
  "npk" TEXT NOT NULL,
  "role" TEXT NOT NULL,
  "efficiency" NUMERIC,
  "piece" NUMERIC,
  "date" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "tim" INTEGER,
  PRIMARY KEY ("id")
);

CREATE TABLE "parent_dept" (
  "id" INTEGER NOT NULL,
  "parent_dept_name" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "password_reset_tokens" (
  "email" TEXT NOT NULL,
  "token" TEXT NOT NULL,
  "created_at" TEXT,
  PRIMARY KEY ("email")
);

CREATE TABLE "payroll_adjusments" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "period_id" INTEGER NOT NULL,
  "adjusment" NUMERIC NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  "keterangan" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "payroll_adjusments_period_id_foreign" FOREIGN KEY ("period_id") REFERENCES "payroll_periods" ("id") ON DELETE CASCADE
);

CREATE TABLE "payroll_approve" (
  "id" INTEGER NOT NULL,
  "payroll_run_id" INTEGER NOT NULL,
  "approval" TEXT NOT NULL,
  "progress" TEXT,
  "approved_at" TEXT,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "payroll_components" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "code" TEXT NOT NULL,
  "type" TEXT NOT NULL,
  "calculation_method" TEXT NOT NULL,
  "value" NUMERIC,
  "formula" TEXT,
  "description" TEXT,
  "category" TEXT,
  "priority" INTEGER NOT NULL DEFAULT 0,
  "is_taxable" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  "orders" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "payroll_exports" (
  "id" INTEGER NOT NULL,
  "run_id" INTEGER NOT NULL,
  "progress" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'processing',
  "file_excel" TEXT,
  "file_pdf" TEXT,
  "file_bank_active" TEXT,
  "file_bank_resign" TEXT,
  "file_peng" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "file_bank_mangkir" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "payroll_masters" (
  "id" INTEGER NOT NULL,
  "npk" TEXT NOT NULL,
  "bank_name" TEXT,
  "bank_account" TEXT,
  "salary" NUMERIC DEFAULT 0,
  "allowance" NUMERIC DEFAULT 0,
  "pph21" NUMERIC DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  "type" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "payroll_periods" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "start_date" TEXT NOT NULL,
  "end_date" TEXT NOT NULL,
  "is_closed" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "payroll_run_details" (
  "id" INTEGER NOT NULL,
  "run_id" INTEGER NOT NULL,
  "employee_npk" TEXT NOT NULL,
  "employee_name" TEXT NOT NULL,
  "components" TEXT NOT NULL,
  "total_salary" NUMERIC NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "employee_dept" TEXT,
  "employee_staff" TEXT,
  "employee_expat" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "payroll_run_details_run_id_foreign" FOREIGN KEY ("run_id") REFERENCES "payroll_runs" ("id")
);

CREATE TABLE "payroll_run_details_audit" (
  "id" INTEGER NOT NULL,
  "run_id" INTEGER NOT NULL,
  "employee_npk" TEXT NOT NULL,
  "employee_name" TEXT NOT NULL,
  "components" TEXT NOT NULL,
  "total_salary" NUMERIC NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "employee_dept" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "FK__payroll_r__run_i__1B29035F" FOREIGN KEY ("run_id") REFERENCES "payroll_runs" ("id")
);

CREATE TABLE "payroll_runs" (
  "id" INTEGER NOT NULL,
  "period_id" INTEGER NOT NULL,
  "processed_at" TEXT NOT NULL,
  "total_payroll" NUMERIC NOT NULL DEFAULT 0,
  "employee_count" INTEGER NOT NULL DEFAULT 0,
  "progress" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "payroll_runs_period_id_foreign" FOREIGN KEY ("period_id") REFERENCES "payroll_periods" ("id")
);

CREATE TABLE "payroll_settings" (
  "id" INTEGER NOT NULL,
  "component" TEXT NOT NULL,
  "approval" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "pdf_documents" (
  "id" INTEGER NOT NULL,
  "original_filename" TEXT NOT NULL,
  "file_path" TEXT NOT NULL,
  "raw_text" TEXT,
  "category_id" INTEGER,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "error_message" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "pdf_documents_category_id_foreign" FOREIGN KEY ("category_id") REFERENCES "categories" ("id") ON DELETE SET NULL
);

CREATE TABLE "PELAMAR" (
  "ID" INTEGER NOT NULL,
  "NPK" TEXT,
  "NAMA" TEXT NOT NULL,
  "JENIS_KELAMIN" TEXT NOT NULL,
  "TMPT_LAHIR" TEXT,
  "TGL_LAHIR" TEXT,
  "TMK" TEXT,
  "UMUR" TEXT NOT NULL,
  "ALAMAT_LENGKAP" TEXT NOT NULL,
  "KABUPATEN" TEXT NOT NULL,
  "ALAMAT_DOMISILI" TEXT NOT NULL,
  "PENDIDIKAN" TEXT NOT NULL,
  "NAMA_SEKOLAH" TEXT NOT NULL,
  "KABUPATEN_SEKOLAH" TEXT NOT NULL,
  "JURUSAN" TEXT NOT NULL,
  "TINGGI_BADAN" INTEGER NOT NULL,
  "BERAT_BADAN" INTEGER NOT NULL,
  "HP" TEXT NOT NULL,
  "AGAMA" TEXT NOT NULL,
  "NIK" TEXT NOT NULL,
  "NO_KK" TEXT DEFAULT '-',
  "IBU" TEXT NOT NULL,
  "STATUS" TEXT NOT NULL,
  "TANGGUNGAN" INTEGER NOT NULL,
  "IS_KONTRAK" TEXT NOT NULL DEFAULT 'FALSE',
  "IS_STAFF" TEXT,
  PRIMARY KEY ("ID")
);

CREATE TABLE "pelamar_details" (
  "id" INTEGER NOT NULL,
  "id_pelamar" TEXT,
  "nomor_sim" TEXT,
  "warga_negara" TEXT,
  "ikut_kb" INTEGER,
  "bakat_hobby" TEXT,
  "mode_transportasi" TEXT,
  "jabatan" TEXT,
  "department" TEXT,
  "bpjs_tk" TEXT,
  "bpjs_kes" TEXT,
  "alamat_skrg" TEXT,
  "kabupaten_kota_skrg" TEXT,
  "status_domisili" TEXT,
  "nama_ktk_darurat" TEXT,
  "hubungan" TEXT,
  "no_telp_darurat" TEXT,
  "motivasi" TEXT,
  "kegiatan_ekstra" TEXT,
  "pengalaman_kerja" TEXT,
  "data_ayah" TEXT,
  "data_ibu" TEXT,
  "saudara_kandung" TEXT,
  "data_anak" TEXT,
  "riwayat_pendidikan" TEXT,
  "file_surat_lamaran" TEXT,
  "file_cv" TEXT,
  "file_ktp" TEXT,
  "file_kk" TEXT,
  "file_ijasah" TEXT,
  "file_akta_kelahiran" TEXT,
  "file_skck" TEXT,
  "file_surat_sehat" TEXT,
  "file_pas_foto" TEXT,
  "status_apply" TEXT,
  "is_test" TEXT,
  "tgl_test" TEXT,
  "is_kesehatan" TEXT,
  "tgl_kesehatan" TEXT,
  "is_interview" TEXT,
  "tgl_interview" TEXT,
  "tgl_diterima" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "result_test" TEXT,
  "comment_test" TEXT,
  "result_kesehatan" TEXT,
  "comment_kesehatan" TEXT,
  "result_interview" TEXT,
  "comment_interview" TEXT,
  "result_user" TEXT,
  "comment_user" TEXT,
  "file_test" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "permissions" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "route_name" TEXT NOT NULL,
  "group" TEXT,
  "description" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "personal_access_tokens" (
  "id" INTEGER NOT NULL,
  "tokenable_type" TEXT NOT NULL,
  "tokenable_id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "token" TEXT NOT NULL,
  "abilities" TEXT,
  "last_used_at" TEXT,
  "expires_at" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "PKWT" (
  "NPK" TEXT NOT NULL,
  "NAMA" TEXT,
  "JK" TEXT,
  "TGLLAHIR" TEXT,
  "PDDK" TEXT,
  "AGAMA" TEXT,
  "TMK" TEXT,
  "USIA" TEXT,
  "TKK" TEXT,
  "BAGIAN" TEXT,
  "ALAMAT" TEXT,
  "KABUPATEN" TEXT,
  "KTP" TEXT,
  "NO_KK" TEXT DEFAULT '-',
  "IBU" TEXT,
  "HP" TEXT,
  "STATUS" TEXT,
  "TANGGUNGAN" TEXT,
  "KETERANGAN" TEXT,
  "TUTUPBUKU" TEXT,
  "TMPTLAHIR" TEXT,
  "NOREK" TEXT,
  "JURUSAN" TEXT,
  "FASKES" TEXT,
  "leave_reasons" TEXT,
  "file_surat_lamaran" TEXT,
  "file_cv" TEXT,
  "file_ktp" TEXT,
  "file_kk" TEXT,
  "file_ijazah" TEXT,
  "file_akta_kelahiran" TEXT,
  "file_skck" TEXT,
  "file_surat_sehat" TEXT,
  "file_pas_foto" TEXT,
  "is_agreement_signed" TEXT DEFAULT '0',
  "file_penalty_letter" TEXT,
  PRIMARY KEY ("NPK")
);

CREATE TABLE "PKWT_OUT" (
  "NPK" TEXT NOT NULL,
  "NAMA" TEXT,
  "JK" TEXT,
  "TGLLAHIR" TEXT,
  "PDDK" TEXT,
  "AGAMA" TEXT,
  "TMK" TEXT,
  "USIA" TEXT,
  "TKK" TEXT,
  "BAGIAN" TEXT,
  "ALAMAT" TEXT,
  "KABUPATEN" TEXT,
  "KTP" TEXT,
  "NO_KK" TEXT DEFAULT '-',
  "IBU" TEXT,
  "HP" TEXT,
  "STATUS" TEXT,
  "TANGGUNGAN" TEXT,
  "KETERANGAN" TEXT,
  "TUTUPBUKU" TEXT,
  "TMPTLAHIR" TEXT,
  "NOREK" TEXT,
  "JURUSAN" TEXT,
  "FASKES" TEXT
);

CREATE TABLE "qc_efficiencies" (
  "id" INTEGER NOT NULL,
  "line_number" INTEGER,
  "buyer" TEXT,
  "period_id" INTEGER NOT NULL,
  "efficiency" REAL,
  "date" TEXT NOT NULL,
  "days" INTEGER,
  "created_at" TEXT,
  "updated_at" TEXT,
  "third_party" TEXT,
  "dept" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "qc_violations" (
  "id" INTEGER NOT NULL,
  "period_id" INTEGER NOT NULL,
  "percentage" NUMERIC NOT NULL DEFAULT 0,
  "reason" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "qr_authorized_devices" (
  "id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "device_uuid" TEXT NOT NULL,
  "device_name" TEXT NOT NULL,
  "device_type" TEXT NOT NULL DEFAULT 'unknown',
  "platform" TEXT,
  "browser" TEXT,
  "assigned_by" INTEGER,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "last_used_at" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "qr_authorized_devices_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE TABLE "qr_scan_logs" (
  "id" INTEGER NOT NULL,
  "user_id" INTEGER,
  "npk_scanned" TEXT NOT NULL,
  "device_uuid" TEXT,
  "device_name" TEXT,
  "device_type" TEXT NOT NULL DEFAULT 'unknown',
  "platform" TEXT,
  "browser" TEXT,
  "ip_address" TEXT NOT NULL,
  "user_agent" TEXT,
  "status" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "qr_scan_logs_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE SET NULL
);

CREATE TABLE "quiz_options" (
  "id" INTEGER NOT NULL,
  "question_id" INTEGER NOT NULL,
  "option_text" TEXT NOT NULL,
  "is_correct" INTEGER NOT NULL DEFAULT 0,
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "quiz_options_question_id_foreign" FOREIGN KEY ("question_id") REFERENCES "quiz_questions" ("id") ON DELETE CASCADE
);

CREATE TABLE "quiz_questions" (
  "id" INTEGER NOT NULL,
  "quiz_id" INTEGER NOT NULL,
  "question_text" TEXT NOT NULL,
  "type" TEXT NOT NULL DEFAULT 'single_choice',
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "quiz_questions_quiz_id_foreign" FOREIGN KEY ("quiz_id") REFERENCES "quizzes" ("id") ON DELETE CASCADE
);

CREATE TABLE "quiz_responses" (
  "id" INTEGER NOT NULL,
  "session_id" INTEGER NOT NULL,
  "question_id" INTEGER,
  "option_id" INTEGER,
  "answered_at" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "answer_text" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "quiz_responses_session_id_foreign" FOREIGN KEY ("session_id") REFERENCES "quiz_sessions" ("id") ON DELETE CASCADE,
  CONSTRAINT "quiz_responses_question_id_foreign" FOREIGN KEY ("question_id") REFERENCES "quiz_questions" ("id"),
  CONSTRAINT "quiz_responses_option_id_foreign" FOREIGN KEY ("option_id") REFERENCES "quiz_options" ("id")
);

CREATE TABLE "quiz_sessions" (
  "id" INTEGER NOT NULL,
  "quiz_id" INTEGER NOT NULL,
  "participant_name" TEXT NOT NULL,
  "participant_ip" TEXT,
  "joined_at" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "quiz_sessions_quiz_id_foreign" FOREIGN KEY ("quiz_id") REFERENCES "quizzes" ("id") ON DELETE CASCADE
);

CREATE TABLE "quizzes" (
  "id" INTEGER NOT NULL,
  "title" TEXT NOT NULL,
  "description" TEXT,
  "qr_code" TEXT NOT NULL,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "started_at" TEXT,
  "closed_at" TEXT,
  "created_by" INTEGER,
  "created_at" TEXT,
  "updated_at" TEXT,
  "mode" TEXT NOT NULL DEFAULT 'quiz',
  PRIMARY KEY ("id")
);

CREATE TABLE "recruitment_positions" (
  "id" INTEGER NOT NULL,
  "position" TEXT NOT NULL,
  "dept" TEXT NOT NULL,
  "is_aktif" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  "is_staff" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "REKAP" (
  "ID" INTEGER NOT NULL,
  "PKWT" INTEGER NOT NULL,
  "MAGANG" INTEGER NOT NULL,
  "BULAN" INTEGER NOT NULL,
  "TAHUN" TEXT NOT NULL,
  PRIMARY KEY ("ID")
);

CREATE TABLE "resep_obats" (
  "id" INTEGER NOT NULL,
  "kunjungan_id" INTEGER NOT NULL,
  "keterangan_obat" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "qty" INTEGER,
  PRIMARY KEY ("id"),
  CONSTRAINT "resep_obats_kunjungan_id_foreign" FOREIGN KEY ("kunjungan_id") REFERENCES "kunjungans" ("id") ON DELETE CASCADE
);

CREATE TABLE "role_has_permissions" (
  "permission_id" INTEGER NOT NULL,
  "role_id" INTEGER NOT NULL,
  PRIMARY KEY ("permission_id", "role_id"),
  CONSTRAINT "role_has_permissions_role_id_foreign" FOREIGN KEY ("role_id") REFERENCES "roles" ("id") ON DELETE CASCADE
);

CREATE TABLE "role_payrolls" (
  "id" INTEGER NOT NULL,
  "user_id" INTEGER NOT NULL,
  "payroll_role" TEXT NOT NULL,
  "created_by" INTEGER,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "role_payrolls_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);

CREATE TABLE "role_permission" (
  "id" INTEGER NOT NULL,
  "role_id" INTEGER NOT NULL,
  "permission_id" INTEGER NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "role_permission_role_id_foreign" FOREIGN KEY ("role_id") REFERENCES "roles" ("id") ON DELETE CASCADE,
  CONSTRAINT "role_permission_permission_id_foreign" FOREIGN KEY ("permission_id") REFERENCES "permissions" ("id") ON DELETE CASCADE
);

CREATE TABLE "roles" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "guard_name" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "salary_approve" (
  "id" INTEGER NOT NULL,
  "id_pelamar" INTEGER NOT NULL,
  "expected_salary" NUMERIC NOT NULL,
  "approved_salary" NUMERIC,
  "progress" TEXT NOT NULL,
  "approved_at" TEXT,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "requested_by" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "satisfaction_questions" (
  "id" INTEGER NOT NULL,
  "section" TEXT,
  "question_text" TEXT NOT NULL,
  "question_type" TEXT NOT NULL DEFAULT 'likert',
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "satisfaction_responses" (
  "id" INTEGER NOT NULL,
  "question_id" INTEGER NOT NULL,
  "score" INTEGER,
  "answer_text" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "satisfaction_responses_question_id_foreign" FOREIGN KEY ("question_id") REFERENCES "satisfaction_questions" ("id") ON DELETE CASCADE
);

CREATE TABLE "sections" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "line_start" INTEGER NOT NULL,
  "line_end" INTEGER NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  "blank_line" INTEGER,
  PRIMARY KEY ("id")
);

CREATE TABLE "SETLIBUR" (
  "ID" INTEGER NOT NULL,
  "TANGGAL" TEXT,
  "KETERANGAN" TEXT,
  PRIMARY KEY ("ID")
);

CREATE TABLE "sewing_violations" (
  "id" INTEGER NOT NULL,
  "id_dept" INTEGER NOT NULL,
  "pelanggaran" TEXT NOT NULL,
  "tanggal" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "shifts" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "work_start" TEXT NOT NULL,
  "work_end" TEXT NOT NULL,
  "gender" TEXT NOT NULL DEFAULT 'ALL',
  "created_at" TEXT,
  "updated_at" TEXT,
  "start_date" TEXT,
  "end_date" TEXT,
  "is_holiday" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "signatures" (
  "id" INTEGER NOT NULL,
  "user_id" TEXT NOT NULL,
  "signature_img" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "thr_approve" (
  "id" INTEGER NOT NULL,
  "thr_run_id" INTEGER NOT NULL,
  "approval" TEXT NOT NULL,
  "progress" TEXT,
  "approved_at" TEXT,
  "status" TEXT NOT NULL DEFAULT 'pending',
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "thr_exports" (
  "id" INTEGER NOT NULL,
  "run_id" INTEGER NOT NULL,
  "progress" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'waiting',
  "file_excel" TEXT,
  "file_pdf" TEXT,
  "file_bank" TEXT,
  "file_peng" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "thr_periods" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "cutoff_date" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "thr_run_details" (
  "id" INTEGER NOT NULL,
  "run_id" INTEGER NOT NULL,
  "employee_npk" TEXT NOT NULL,
  "employee_name" TEXT NOT NULL,
  "components" TEXT,
  "total_salary" NUMERIC NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "thr_runs" (
  "id" INTEGER NOT NULL,
  "period_id" INTEGER NOT NULL,
  "processed_at" TEXT,
  "total_thr" NUMERIC NOT NULL DEFAULT 0,
  "employee_count" INTEGER NOT NULL DEFAULT 0,
  "progress" INTEGER NOT NULL DEFAULT 0,
  "status" TEXT NOT NULL DEFAULT 'draft',
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "users" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "email" TEXT NOT NULL,
  "email_verified_at" TEXT,
  "password" TEXT NOT NULL,
  "remember_token" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  "npk" TEXT,
  "disabled" INTEGER DEFAULT 0,
  PRIMARY KEY ("id")
);

CREATE TABLE "whatsapp_devices" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "token" TEXT NOT NULL,
  "phone" TEXT,
  "is_active" INTEGER NOT NULL DEFAULT 1,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

CREATE TABLE "whatsapp_logs" (
  "id" INTEGER NOT NULL,
  "device_id" INTEGER NOT NULL,
  "target" TEXT NOT NULL,
  "message" TEXT NOT NULL,
  "status" TEXT,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id"),
  CONSTRAINT "whatsapp_logs_device_id_foreign" FOREIGN KEY ("device_id") REFERENCES "whatsapp_devices" ("id")
);

CREATE TABLE "whatsapp_templates" (
  "id" INTEGER NOT NULL,
  "name" TEXT NOT NULL,
  "message" TEXT NOT NULL,
  "created_at" TEXT,
  "updated_at" TEXT,
  PRIMARY KEY ("id")
);

