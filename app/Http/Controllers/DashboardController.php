<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return match($request->user()->role) {
            'admin'   => redirect()->route('admin.dashboard'),
            'cfo'     => redirect()->route('finance.index'),
            'tutor'   => redirect()->route('tutor.dashboard'),
            'student' => redirect()->route('student.dashboard'),
            default   => abort(403),
        };
    }
}
