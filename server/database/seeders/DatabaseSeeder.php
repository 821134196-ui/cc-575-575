<?php

namespace Database\Seeders;

use App\Models\Entrance;
use App\Models\Performance;
use App\Models\Production;
use App\Services\ProgramService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(ProgramService $svc): void
    {
        $prod = Production::firstOrCreate(
            ['code' => 'CY-2026'],
            ['name' => '话剧《长夜》', 'note' => '秋季巡演']
        );

        $east = Entrance::firstOrCreate(['code' => 'EAST'], ['name' => '东门']);
        $west = Entrance::firstOrCreate(['code' => 'WEST'], ['name' => '西门']);
        $vip = Entrance::firstOrCreate(['code' => 'VIP'], ['name' => 'VIP通道']);

        // v1：初版，已锁定、已交货、已分配（今晚场确认使用，看板立即可见）
        if (! $prod->versions()->where('version_no', 1)->exists()) {
            $v1 = $svc->createVersion($prod->id, ['label' => '首演初版', 'change_reason' => '建组首演']);
            $svc->updateFile($v1, [
                'file_name' => 'changye-v1.pdf',
                'file_path' => 'program-files/changye-v1.pdf',
                'file_hash' => hash('sha256', 'changye v1 contents'),
                'file_size' => 102400,
            ]);
            $svc->lockVersion($v1, '编辑小李');

            $b = $svc->orderPrint($v1->fresh(), 300);
            $b = $svc->deliverBatch($b);
            $svc->allocate($b->fresh(), $east, 150);
            $svc->allocate($b->fresh(), $west, 120);
            // 总库余 30
        }

        // v2：换角修订版（草稿，待印文件待编辑确认）
        if (! $prod->versions()->where('version_no', 2)->exists()) {
            $svc->createVersion($prod->id, [
                'label' => '换角修订版',
                'change_reason' => '主演临时更换，演员名单变化',
            ]);
        }

        // 今晚场次：已确认使用 v1（待发放入口看板可见）
        $tonight = Performance::firstOrCreate(
            ['production_id' => $prod->id, 'name' => '10月4日晚场'],
            ['performed_at' => '2026-10-04 19:30', 'status' => 'scheduled']
        );
        if ($tonight->status === 'scheduled' && $v1 = $prod->versions()->where('version_no', 1)->first()) {
            $svc->confirmPerformance($tonight, $v1->id, '编辑小李');
        }
        // 加演场次：待确认（演员换角后应选 v2）
        Performance::firstOrCreate(
            ['production_id' => $prod->id, 'name' => '10月5日加演场'],
            ['performed_at' => '2026-10-05 14:00', 'status' => 'scheduled']
        );
    }
}
