<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('演出名称');
            $table->dateTime('performed_at')->comment('演出时间');
            $table->string('venue')->default('主剧场')->comment('剧场');
            // draft 草稿 / confirmed 演出信息已确认 / settled 已散场盘点结算
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
