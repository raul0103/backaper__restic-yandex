<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use App\Models\Server;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'servers' => Server::withCount(['backupPaths', 'databases'])->latest()->get(),
            'recentRuns' => BackupRun::with(['server'])->latest()->limit(10)->get(),
        ]);
    }
}
