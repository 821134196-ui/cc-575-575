<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 错版报废：必须审批。仅 approved 状态才扣减库存；
        // pending/rejected 不影响库存。
        Schema::create('scraps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_batch_id')->nullable()->constrained()->nullOnDelete();
            // 报废品所在位置：null=前台总库；有值=该入口库存
            $table->foreignId('entrance_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->comment('报废数量');
            $table->string('reason')->comment('报废原因，如 错版');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('requested_by')->default('运营');
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraps');
    }
};
