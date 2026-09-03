<?php

namespace App\Http\Controllers;

use App\Models\VisualAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VisualAssetFileController extends Controller
{
    public function __invoke(VisualAsset $visualAsset): StreamedResponse
    {
        Gate::authorize('view', $visualAsset);
        abort_if(blank($visualAsset->storage_path), 404);

        $disk = Storage::disk($visualAsset->storage_disk);
        abort_unless($disk->exists($visualAsset->storage_path), 404);

        return $disk->response($visualAsset->storage_path);
    }
}
