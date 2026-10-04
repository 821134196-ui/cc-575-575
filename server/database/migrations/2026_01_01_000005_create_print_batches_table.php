<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 印刷批次：印厂交货按批次记录数量，回执来自本地模拟印厂接口。
        Schema::create('print_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no')->comment('批次号');
            $table->unsignedInteger('ordered_qty')->comment('订货数量');
            $table->unsignedInteger('delivered_qty')->default(0)->comment('印厂实际交货数量');
            $table->enum('status', ['ordered', 'delivered'])->default('ordered');
            $table->string('printer_name')->default('市第一印厂');
            $table->string('receipt_no')->nullable()->comment('印厂回执单号');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['program_version_id', 'batch_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_batches');
    }
};
