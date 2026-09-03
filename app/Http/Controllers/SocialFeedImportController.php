<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportSocialFeedRequest;
use App\Models\SocialFeedSource;
use App\Services\SocialFeedImporter;
use Illuminate\Http\RedirectResponse;

class SocialFeedImportController extends Controller
{
    public function __invoke(ImportSocialFeedRequest $request, SocialFeedSource $socialFeedSource, SocialFeedImporter $importer): RedirectResponse
    {
        /** @var array<int, mixed> $items */
        $items = json_decode($request->validated('payload'), true, 512, JSON_THROW_ON_ERROR);
        $run = $importer->import($socialFeedSource, $items, $request->user()?->id);

        return back()->with('success', "Akış alımı tamamlandı: {$run->imported_count} yeni, {$run->skipped_count} atlandı, {$run->failed_count} hatalı.");
    }
}
