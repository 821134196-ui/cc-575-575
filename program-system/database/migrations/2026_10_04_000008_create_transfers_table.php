<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 入口之间调拨：一条单据，同时生成调出/调入两条流水，不增加总库存
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_entrance_id')->constrained('entrances')->cascadeOnDelete();
            $table->foreignId('to_entrance_id')->constrained('entrances')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('operator')->default('前台');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
