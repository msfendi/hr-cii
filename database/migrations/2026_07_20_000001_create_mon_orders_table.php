<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table sumber untuk sheet "ORDER" (diupload manual via Excel oleh user).
 * Nama kolom mengikuti header asli sheet ORDER, di-snake_case-kan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mon_orders', function (Blueprint $table) {
            $table->id();

            $table->string('uraian')->index(); // kode CPO, kunci join ke BOM & PO
            $table->string('ocf_no')->nullable();
            $table->string('buyer_po')->nullable();
            $table->string('buyer')->nullable()->index();
            $table->string('brand')->nullable();
            $table->string('style')->nullable()->index();
            $table->string('item')->nullable();
            $table->decimal('qty_ord', 18, 2)->default(0); // QTY ORD (PCS)
            $table->string('destination')->nullable();
            $table->string('artwork')->nullable();
            $table->string('sewing_process')->nullable();
            $table->date('production_delivery')->nullable();
            $table->date('buyer_delivery')->nullable();
            $table->date('prod_start')->nullable();
            $table->date('prod_end')->nullable();
            $table->string('shipment_mode')->nullable();
            $table->string('material_fab')->nullable();
            $table->string('fabric')->nullable();
            $table->string('sample')->nullable();
            $table->string('thread')->nullable();
            $table->string('pad_htl')->nullable();
            $table->string('main_label')->nullable();
            $table->string('care_label')->nullable();
            $table->string('button_snap')->nullable();
            $table->string('tape')->nullable();
            $table->string('hangtag')->nullable();
            $table->string('price_ticket')->nullable();
            $table->string('size_strip')->nullable();
            $table->string('polybag')->nullable();
            $table->string('sticker')->nullable();
            $table->string('hanger')->nullable();
            $table->string('sizer')->nullable();
            $table->string('carton_box')->nullable();
            $table->string('vessel_book')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('column17')->nullable();
            $table->string('fob')->nullable();
            $table->decimal('price', 18, 4)->nullable();
            $table->string('column18')->nullable();
            $table->string('column19')->nullable();
            $table->string('cmt')->nullable();
            $table->string('price20')->nullable();
            $table->string('column21')->nullable();
            $table->string('sample2')->nullable(); // "SAMPLE " (kolom kedua, header ganda di excel)
            $table->decimal('smv', 18, 4)->nullable();
            $table->decimal('planned_qty', 18, 2)->nullable();
            $table->date('sewing_start_date')->nullable();
            $table->text('remarks')->nullable();

            $table->string('import_batch')->nullable()->index(); // penanda batch upload excel
            $table->timestamps();

            $table->index(['uraian', 'buyer', 'style']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mon_orders');
    }
};
