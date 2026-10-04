<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 散场盘点：按场次 × 库位 × 版本记录实际余量
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->string('location')->comment('warehouse / entrance:{id}');
            $table->unsignedInteger('system_qty')->comment('系统账面数量');
            $table->unsignedInteger('actual_qty')->comment('散场实际清点数量');
            $table->string('counted_by')->default('运营');
            $table->dateTime('counted_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['performance_id', 'program_version_id', 'location']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_counts');
    }
};
