<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Restore traits that Laravel 11+ removed from the base Controller by
    // default. Several controllers in this app call $this->authorize()
    // (EnrollmentController, ClassSessionController, JournalController,
    // ClassroomController) which requires the AuthorizesRequests trait.
    // Role-based access control is already enforced by RoleMiddleware at
    // the route level; these authorize() calls are a secondary layer that
    // would always pass for authenticated admins (no policies registered,
    // so Gate returns true).
    use AuthorizesRequests;
    use ValidatesRequests;
}
