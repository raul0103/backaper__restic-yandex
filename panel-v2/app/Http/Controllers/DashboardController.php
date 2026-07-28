<?php

namespace App\Http\Controllers;

use App\Models\Server;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'servers' => Server::query()->latest()->get(),
        ]);
    }
}
