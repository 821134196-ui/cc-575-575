<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 统一库存流水：任何数量变化都在此留痕，按版本 + 库位记录
        // location: warehouse 前台总仓 / entrance:{id} 各入口 / audience 已发放到观众 / scrapped 已报废
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_batch_id')->nullable()->constrained()->nullOnDelete();
            // print_in 交货入库 / allocate 前台分配到入口 / issue 入口发放
            // / transfer_out 调出 / transfer_in 调入 / scrap 报废 / count_adjust 盘点调整
            $table->string('type');
            $table->string('location_from')->nullable();
            $table->string('location_to')->nullable();
            $table->integer('quantity')->comment('带符号数量：调入为正，调出/发出为负');
            $table->string('ref_type')->nullable()->comment('关联业务类型');
            $table->unsignedBigInteger('ref_id')->nullable()->comment('关联业务记录ID');
            $table->string('operator')->default('系统');
            $table->timestamps();

            $table->index(['program_version_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
