<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $scoreSheets = Auth::user()
            ->scoreSheets()
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard', compact('scoreSheets'));
    }
}
