<?php

namespace Database\Seeders;

use App\Models\Entrance;
use App\Models\Performance;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Entrance::query()->delete();
        Performance::query()->delete();

        foreach ([
            ['code' => 'A', 'name' => '正门入口'],
            ['code' => 'B', 'name' => '贵宾入口'],
            ['code' => 'C', 'name' => '侧门入口'],
        ] as $e) {
            Entrance::create($e);
        }

        $p1 = Performance::create([
            'title' => '歌剧《图兰朵》',
            'performed_at' => '2026-10-05 19:30:00',
            'venue' => '主剧场',
            'status' => 'draft',
            'notes' => '国庆档首场',
        ]);
        $p1->versions()->create([
            'version_no' => 1,
            'file_name' => 'turandot-program-v1.pdf',
            'cast_list' => "卡拉夫：张明\n图兰朵：李华\n柳儿：王芳",
            'status' => 'draft',
        ]);

        $p2 = Performance::create([
            'title' => '芭蕾舞剧《天鹅湖》',
            'performed_at' => '2026-10-06 14:00:00',
            'venue' => '主剧场',
            'status' => 'draft',
        ]);
        $p2->versions()->create([
            'version_no' => 1,
            'file_name' => 'swanlake-program-v1.pdf',
            'cast_list' => "奥杰塔：陈静\n齐格弗里德：刘洋",
            'status' => 'draft',
        ]);
    }
}
