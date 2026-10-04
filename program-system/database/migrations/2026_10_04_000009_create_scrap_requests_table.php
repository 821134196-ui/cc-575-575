<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 错版报废审批：pending -> approved 后才真正扣减库存；rejected 不动库存
        Schema::create('scrap_requests', function (Blueprint $table) {
            $table->id();
            $table->string('scrap_no')->unique();
            $table->foreignId('program_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_batch_id')->nullable()->constrained()->nullOnDelete();
            // warehouse 总仓报废 / entrance:{id} 入口报废
            $table->string('location');
            $table->unsignedInteger('quantity');
            $table->string('reason')->comment('如：错版（与锁定版本不符）');
            $table->string('status')->default('pending');
            $table->string('requested_by')->default('运营');
            $table->string('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_requests');
    }
};
