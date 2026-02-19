<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserApprovalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Approvals', [
            'users' => User::query()
                ->where('role', User::ROLE_ADMIN)
                ->orderByDesc('last_seen_at')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'approved' => ['required', 'boolean'],
            'role' => ['nullable', Rule::in([User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])],
        ]);

        if (!empty($data['role'])) {
            $user->role = $data['role'];
        }

        $user->approved_at = $data['approved'] ? now() : null;
        $user->save();

        return back()->with('success', 'User approval updated.');
    }
}
