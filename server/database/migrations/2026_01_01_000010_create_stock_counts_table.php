<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 散场盘点：按场次+入口+版本记录实际结余，是补印依据。
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entrance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('remaining_qty')->comment('散场实际结余数量');
            $table->string('counted_by')->default('运营');
            $table->text('note')->nullable();
            $table->timestamp('counted_at');
            $table->timestamps();

            $table->unique(['performance_id', 'entrance_id', 'program_version_id'], 'stock_counts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_counts');
    }
};
