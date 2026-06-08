<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Models\Project;
use App\Models\Server;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'servers' => Server::withCount('projects')->latest()->get(),
            'recentRuns' => BackupRun::with(['server', 'project'])->latest()->limit(10)->get(),
        ]);
    }
}
