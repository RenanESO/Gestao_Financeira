<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserPreferencesService
{
    public function update(array $data): User
    {
        $data = Validator::make($data, [
            'theme' => ['required', Rule::in(['dark', 'light'])],
            'notification_days_before' => ['required', 'integer', 'min:0', 'max:365'],
        ])->validate();

        return DB::transaction(function () use ($data) {
            $user = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            $user->update($data);

            return $user->refresh();
        }, 3);
    }
}
