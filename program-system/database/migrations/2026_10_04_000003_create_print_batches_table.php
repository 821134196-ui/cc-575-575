<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no')->unique()->comment('印刷批次号');
            $table->unsignedInteger('quantity_ordered')->comment('下单印量');
            $table->unsignedInteger('quantity_delivered')->default(0)->comment('印厂实际交货数量');
            // ordered 已下单 / delivered 已交货
            $table->string('status')->default('ordered');
            $table->string('printer_receipt_no')->nullable()->comment('印厂回执号（模拟接口返回）');
            $table->dateTime('delivered_at')->nullable();
            $table->string('ordered_by')->default('编辑部');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_batches');
    }
};
