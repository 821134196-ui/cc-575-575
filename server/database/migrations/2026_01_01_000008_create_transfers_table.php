<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 入口调拨：一条记录同时保存调出入口与调入入口。
        // 调出与调入数量相等，不增加总库存。
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_entrance_id')->constrained('entrances')->cascadeOnDelete();
            $table->foreignId('to_entrance_id')->constrained('entrances')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->comment('调拨数量');
            $table->string('transferred_by')->default('前台');
            $table->timestamp('transferred_at');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
