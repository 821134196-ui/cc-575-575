<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 前台分配：从印刷批次（前台总库）分配给入口领用。
        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entrance_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->comment('分配数量');
            $table->string('allocated_by')->default('前台');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocations');
    }
};
