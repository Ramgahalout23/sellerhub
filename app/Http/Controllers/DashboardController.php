<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $service
    ) {}

    public function index()
    {
        $summary = $this->service->summary();
        return view('dashboard', compact('summary'));
    }
}
