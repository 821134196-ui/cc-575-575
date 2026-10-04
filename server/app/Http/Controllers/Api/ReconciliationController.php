<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;

class ReconciliationController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index()
    {
        return response()->json($this->inventory->reconciliation());
    }
}
