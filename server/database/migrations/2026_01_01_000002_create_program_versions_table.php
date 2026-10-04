<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 节目单版本：演员名单临时变化或增加场次时新建版本，
        // 锁定后待印文件不可修改；已发出的旧版本不能改名顶替。
        Schema::create('program_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_no')->comment('版本序号，同一剧目递增');
            $table->string('label')->comment('版本标签，如 初版/加演修订版');
            $table->string('change_reason')->nullable()->comment('新建版本原因，如 演员名单临时变化');
            $table->string('file_name')->nullable()->comment('待印文件名称');
            $table->string('file_path')->nullable()->comment('待印文件存储路径');
            $table->string('file_hash')->nullable()->comment('文件内容哈希，锁定后校验');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('status', ['draft', 'locked'])->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->string('locked_by')->nullable();
            $table->timestamps();

            $table->unique(['production_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_versions');
    }
};
