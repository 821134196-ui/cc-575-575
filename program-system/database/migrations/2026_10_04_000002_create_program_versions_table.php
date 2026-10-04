<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained()->cascadeOnDelete();
            // 派生来源：演员名单变化或增加场次时，从上一版本新建
            $table->foreignId('source_version_id')->nullable()
                ->references('id')->on('program_versions')->nullOnDelete();
            $table->unsignedSmallInteger('version_no')->default(1);
            $table->string('file_name')->comment('待印文件名称');
            $table->string('file_hash')->nullable()->comment('文件内容哈希，锁定时固化');
            $table->text('cast_list')->comment('演员名单');
            $table->string('change_reason')->nullable()->comment('新建版本原因，如：演员名单临时变化');
            // draft 编辑中 / locked 已锁定待印 / superseded 已被新版本替代
            $table->string('status')->default('draft');
            $table->dateTime('locked_at')->nullable();
            $table->string('locked_by')->nullable();
            $table->timestamps();

            $table->unique(['performance_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_versions');
    }
};
