<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 库存流水账：所有引起库存变化的业务动作在同一事务内落流水。
        // entrance_id 为 null 表示前台总库；quantity 为带符号整数。
        // 交货(+)、发放(-)、报废(-) 改变总库存；
        // 分配(总库-/入口+)、调拨(出-/入+) 总库存净额恒为 0。
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entrance_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity')->comment('带符号数量变化');
            $table->enum('kind', [
                'deliver',       // 印厂交货入总库
                'allocate_out',  // 总库分配出
                'allocate_in',   // 入口领入
                'issue',         // 入口发放给观众
                'transfer_out',  // 调拨调出
                'transfer_in',   // 调拨调入
                'scrap',         // 报废（审批通过后）
            ]);
            $table->string('ref_type')->nullable()->comment('多态来源，如 PrintBatch');
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['program_version_id', 'entrance_id']);
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
