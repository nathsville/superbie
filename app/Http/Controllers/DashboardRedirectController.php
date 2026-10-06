<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirects authenticated users to their role-specific dashboard.
 */
class DashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            'masyarakat' => redirect()->route('citizen.dashboard'),
            'operator'   => redirect()->route('operator.dashboard'),
            'admin'      => redirect()->route('admin.dashboard'),
            'super_admin' => redirect()->route('super-admin.dashboard'),
            default       => redirect()->route('login'),
        };
    }
}
