<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UserStatusController extends Controller
{
    public function update(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'is_active' => $request->validated('is_active'),
        ]);

        $message = $user->is_active ? 'Kullanıcı aktif edildi.' : 'Kullanıcı pasif edildi.';

        return redirect()->route('users.index')->with('success', $message);
    }
}
