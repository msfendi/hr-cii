<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->index();
            $table->string('npk', 20);   
            $table->string('employee_name');    
            $table->date('contract_end_date');            
            $table->integer('days_remaining');            
            $table->enum('type', ['contract_expiring', 'contract_expired'])->default('contract_expiring');
            $table->enum('status', ['unread', 'read', 'archived'])->default('unread')->index();
            $table->timestamp('notified_at')->useCurrent();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->foreign('contract_id')->references('id')->on('employees_contract')->onDelete('cascade');
            $table->index(['status', 'created_at']);
            $table->index(['contract_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_contracts');
    }
};
