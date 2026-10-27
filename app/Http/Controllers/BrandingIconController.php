<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class BrandingIconController extends Controller
{
    public function __invoke()
    {
        $path = auth()->user()->branding['icon_path'] ?? null;
        abort_unless(is_string($path) && preg_match('#^branding/'.auth()->id().'/[a-zA-Z0-9]+\.(png|jpg|jpeg|webp)$#', $path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
