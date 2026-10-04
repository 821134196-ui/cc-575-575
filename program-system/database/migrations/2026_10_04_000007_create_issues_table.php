<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 入口向观众发放，按批次记录数量
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('issued_by')->default('入口工作人员');
            $table->dateTime('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
