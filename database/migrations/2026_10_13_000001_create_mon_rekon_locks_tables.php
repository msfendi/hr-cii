<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel khusus "Lock Data OCF" (dashboard Monitoring Rekonsiliasi).
 *
 *  - mon_rekon_locks        : 1 baris = 1 snapshot (lock) per OCF per tanggal lock.
 *                             Menyimpan identitas (buyer/style/OCF), qty tiap stage
 *                             pipeline (Total Contract, Cutting, Sewing, QC, Packing,
 *                             CT-PAT [dest Warehouse], Sabkon, Warehouse Sabkon,
 *                             Shipment), hasil turunan (loss, short ship, sisa fabric),
 *                             jenis lock (partial / shortship), dan Grade A/B/C yang
 *                             diisi lewat import Excel di halaman Summary Rekonsiliasi.
 *  - mon_rekon_lock_fabrics : snapshot "Fabric Achievement -> Rekap per Fabric",
 *                             1 baris per fabric (barang_code) per lock.
 *
 * `lock_date` adalah PERIODE yang dipakai Summary Rekonsiliasi untuk memfilter data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_rekon_locks', function (Blueprint $table) {
            $table->id();

            // ===== Periode & jenis lock =====
            $table->date('lock_date')->index();
            $table->string('lock_type', 20);                 // partial | shortship

            // ===== Identitas =====
            $table->string('ocf_no', 100);
            $table->string('buyer', 150)->nullable();
            $table->string('style', 255)->nullable();
            $table->string('sub_ref', 150)->nullable();
            $table->string('uraian', 150)->nullable();       // CPO (kalau dipilih di filter)
            $table->string('negara', 100)->nullable();
            $table->text('filters_json')->nullable();        // filter dashboard persis saat lock

            // ===== Qty tiap stage (PCS) =====
            $table->decimal('qty_contract', 18, 2)->default(0);
            $table->decimal('qty_cutting', 18, 2)->default(0);
            $table->decimal('qty_sewing', 18, 2)->default(0);
            $table->decimal('qty_qc', 18, 2)->default(0);
            $table->decimal('qty_packing', 18, 2)->default(0);
            $table->decimal('qty_ct_pat', 18, 2)->default(0);          // stage Warehouse setelah Packing
            $table->decimal('qty_dest_sewing', 18, 2)->default(0);     // basis loss Sewing
            $table->decimal('qty_dest_packing', 18, 2)->default(0);    // basis loss Packing
            $table->decimal('qty_sabkon', 18, 2)->default(0);
            $table->decimal('qty_sabkon_warehouse', 18, 2)->default(0);
            $table->decimal('qty_shipment', 18, 2)->default(0);

            // ===== Hasil turunan pipeline =====
            $table->decimal('loss_cutting', 18, 2)->default(0);
            $table->decimal('loss_sewing', 18, 2)->default(0);
            $table->decimal('loss_packing', 18, 2)->default(0);
            $table->decimal('total_loss', 18, 2)->default(0);          // loss sewing + packing
            $table->decimal('total_loss_pct', 9, 2)->default(0);
            $table->decimal('shipment_loss', 18, 2)->default(0);       // shipment - contract
            $table->decimal('balance_garment_stock', 18, 2)->default(0);

            // ===== Metrik untuk Summary Rekonsiliasi =====
            $table->decimal('short_ship_pcs', 18, 2)->default(0);      // max(0, contract - shipment)
            $table->decimal('sisa_fabric_kg', 18, 2)->default(0);      // SUM stock fabric
            $table->decimal('sisa_fabric_value', 18, 2)->default(0);   // estimasi nilai sisa fabric (Rp)
            $table->decimal('cm_per_pcs', 18, 4)->default(0);          // SUM(nilai_barang)/SUM(jumlah_doc) BC 3.0

            // ===== Grade (diisi lewat import Excel) =====
            $table->decimal('grade_a', 18, 2)->nullable();
            $table->decimal('grade_b', 18, 2)->nullable();
            $table->decimal('grade_c', 18, 2)->nullable();
            $table->timestamp('grade_imported_at')->nullable();
            $table->string('grade_imported_by', 100)->nullable();

            // ===== Audit =====
            $table->string('locked_by', 100)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            // Cegah double-klik: OCF yang sama, tanggal yang sama, jenis lock yang sama.
            $table->unique(['ocf_no', 'lock_date', 'lock_type'], 'uq_mon_rekon_locks_ocf_date_type');
            $table->index(['lock_date', 'ocf_no'], 'ix_mon_rekon_locks_date_ocf');
        });

        Schema::create('mon_rekon_lock_fabrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lock_id');
            $table->string('barang_code', 100)->nullable();
            $table->string('barang_name', 255)->nullable();

            // Qty (KGM)
            $table->decimal('need_qty', 18, 2)->default(0);
            $table->decimal('order_qty', 18, 2)->default(0);
            $table->decimal('received_qty', 18, 2)->default(0);
            $table->decimal('out_req_qty', 18, 2)->default(0);
            $table->decimal('out_doc_qty', 18, 2)->default(0);
            $table->decimal('stock_qty', 18, 2)->default(0);
            $table->decimal('harga_total', 18, 2)->default(0);

            // Persentase (%)
            $table->decimal('order_pct', 9, 2)->default(0);
            $table->decimal('received_pct', 9, 2)->default(0);
            $table->decimal('out_req_pct', 9, 2)->default(0);
            $table->decimal('out_doc_pct', 9, 2)->default(0);
            $table->decimal('stock_pct', 9, 2)->default(0);

            $table->timestamps();

            $table->index('lock_id', 'ix_mon_rekon_lock_fabrics_lock');
            $table->foreign('lock_id', 'fk_mon_rekon_lock_fabrics_lock')
                ->references('id')->on('mon_rekon_locks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_rekon_lock_fabrics');
        Schema::dropIfExists('mon_rekon_locks');
    }
};
