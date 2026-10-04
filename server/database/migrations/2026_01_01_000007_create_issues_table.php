<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 入口发放：入口向观众实际发出的节目单数量。
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entrance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->comment('发放数量');
            $table->string('issued_by')->default('入口工作人员');
            $table->timestamp('issued_at');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
