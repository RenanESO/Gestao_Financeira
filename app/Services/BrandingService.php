<?php

namespace App\Services;

use App\Models\User;
use App\Support\Branding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class BrandingService
{
    public function update(array $data, ?UploadedFile $icon = null, bool $removeIcon = false): void
    {
        abort_unless(Auth::check(), 403);
        $data['name'] = is_string($data['name'] ?? null) ? trim($data['name']) : ($data['name'] ?? null);
        $rules = [
            'name' => ['required', 'string', 'max:80'],
            'show_name' => ['required', 'boolean'],
            'colors' => ['required', 'array:'.implode(',', array_keys(Branding::COLORS))],
            'icon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
        ];
        foreach (array_keys(Branding::COLORS) as $key) {
            $rules["colors.{$key}"] = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        $validated = Validator::make([...$data, 'icon' => $icon], $rules)->validate();
        unset($validated['icon']);
        $validated['colors'] = array_filter($validated['colors']);
        $newPath = null;
        $oldPath = null;
        try {
            if ($icon) {
                $newPath = $icon->store('branding/'.Auth::id(), 'local');
                if (! $newPath) {
                    throw new \RuntimeException('Não foi possível salvar o ícone.');
                }
            }
            DB::transaction(function () use ($validated, $newPath, $removeIcon, &$oldPath) {
                $user = User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                $oldPath = $user->branding['icon_path'] ?? null;
                $user->forceFill(['branding' => [
                    ...$validated, 'icon_path' => $newPath ?? ($removeIcon ? null : $oldPath),
                ]])->save();
            }, 3);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldPath && ($newPath || $removeIcon)) {
            Storage::disk('local')->delete($oldPath);
        }
        Auth::user()->refresh();
    }
}
