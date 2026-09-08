<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Dashboard per peran — pemetaannya ada di App\Enums\Role::homeRoute().
        $home = $request->user()->roleEnum()?->homeRoute();

        return $home ? redirect()->route($home) : abort(403);
    }
}
