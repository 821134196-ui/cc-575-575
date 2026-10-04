<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 场次：演出信息确认后锁定本场允许发放的节目单版本（待印文件）。
        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_version_id')->nullable()->constrained()
                ->comment('本场允许发放的节目单版本');
            $table->string('name')->comment('场次名称，如 10月4日晚场');
            $table->timestamp('performed_at')->comment('演出时间');
            // scheduled=待确认 confirmed=已确认(文件锁定) closed=已散场盘点
            $table->enum('status', ['scheduled', 'confirmed', 'closed'])->default('scheduled');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmed_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
