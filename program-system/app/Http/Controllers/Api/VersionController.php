<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramVersion;
use App\Services\StockService;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    public function __construct(private readonly StockService $stock)
    {
    }

    /** 编辑修改草稿 / 或尝试修改锁定版本（锁定后修改必须被拒绝） */
    public function update(Request $request, ProgramVersion $version)
    {
        $data = $request->validate([
            'file_name' => ['sometimes', 'string', 'max:150'],
            'cast_list' => ['sometimes', 'string'],
        ]);

        // 领域规则：锁定后修改直接抛 VersionLockedException（全局渲染为 422）
        $version->guardEditable();

        $version->update($data);

        return response()->json(['ok' => true, 'version' => $version]);
    }

    public function lock(ProgramVersion $version)
    {
        $v = $this->stock->lockVersion($version, request('by', '编辑'));

        return response()->json(['ok' => true, 'version' => $v]);
    }

    /** 演员名单临时变化 / 增加场次：新建版本，旧版节目单不得改名顶替 */
    public function revision(Request $request, ProgramVersion $version)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
            'cast_list' => ['nullable', 'string'],
            'file_name' => ['nullable', 'string', 'max:150'],
        ]);

        $new = $this->stock->createRevision(
            $version,
            $data['reason'],
            array_filter(['cast_list' => $data['cast_list'] ?? null, 'file_name' => $data['file_name'] ?? null],
                fn ($x) => $x !== null),
        );

        return response()->json(['ok' => true, 'version' => $new], 201);
    }
}
