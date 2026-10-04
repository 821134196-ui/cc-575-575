<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrance;
use App\Models\Production;
use Illuminate\Http\Request;

class ProductionController extends Controller
{
    public function index()
    {
        return Production::query()
            ->withCount('versions')
            ->with('versions')
            ->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:productions,code',
            'note' => 'nullable|string',
        ]);
        return Production::create($data);
    }

    public function show(Production $production)
    {
        return $production->load([
            'versions.printBatches.allocations.entrance',
            'performances.programVersion',
        ]);
    }

    public function entrances()
    {
        return Entrance::orderBy('id')->get();
    }

    public function storeEntrance(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:50|unique:entrances,code',
            'note' => 'nullable|string',
        ]);
        return Entrance::create($data);
    }
}
