<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel hasil sync query "get_subkon" dari smartit -- gabungan 2 sisi
     * transaksi subkon (Kirim Subkon dari prd_po_hd & Terima Subkon dari
     * prd_so_hd), dibedakan lewat kolom `jenis`.
     */
    public function up(): void
    {
        Schema::create('mon_subkons', function (Blueprint $table) {
            $table->id();

            // po_id (Kirim Subkon) atau so_id (Terima Subkon) dari smartit.
            $table->string('id_order');
            $table->string('no_order')->nullable();
            $table->date('tgl_order')->nullable();

            // 'Kirim Subkon' atau 'Terima Subkon'.
            $table->string('jenis');

            $table->string('supplier_code')->nullable();
            $table->string('supplier_name')->nullable();

            $table->decimal('qty_material_order', 18, 4)->default(0);
            $table->decimal('qty_result_order', 18, 4)->default(0);
            $table->decimal('qty_material_aktual', 18, 4)->default(0);
            $table->decimal('qty_result_aktual', 18, 4)->default(0);

            $table->timestamps();

            $table->index('jenis');
            $table->index('supplier_code');
            $table->index('tgl_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_subkons');
    }
};
