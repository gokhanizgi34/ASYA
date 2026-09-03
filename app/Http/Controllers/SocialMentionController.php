<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSocialMentionRequest;
use App\Http\Requests\UpdateSocialMentionStatusRequest;
use App\Models\SocialListeningWatch;
use App\Models\SocialMention;
use App\Services\SocialMentionAnalyzer;
use App\SocialMentionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SocialMentionController extends Controller
{
    public function store(StoreSocialMentionRequest $request, SocialMentionAnalyzer $analyzer): RedirectResponse
    {
        $watch = SocialListeningWatch::findOrFail($request->integer('social_listening_watch_id'));
        $analysis = $analyzer->analyze(
            $watch,
            $request->validated('content'),
            $request->integer('engagement_count'),
        );

        if ($analysis['matchedKeywords'] === []) {
            throw ValidationException::withMessages([
                'content' => 'İçerik anahtar kelimelerle eşleşmiyor veya hariç tutulan bir terim içeriyor.',
            ]);
        }

        SocialMention::create([
            'agency_id' => $watch->agency_id,
            'social_listening_watch_id' => $watch->id,
            'created_by' => $request->user()?->id,
            ...$request->validated(),
            'sentiment' => $analysis['sentiment'],
            'sentiment_score' => $analysis['sentimentScore'],
            'urgency_score' => $analysis['urgencyScore'],
            'matched_keywords' => $analysis['matchedKeywords'],
            'status' => SocialMentionStatus::New,
        ]);

        return back()->with('success', 'Sosyal medya bahsi analiz edilerek kaydedildi.');
    }

    public function update(UpdateSocialMentionStatusRequest $request, SocialMention $socialMention): RedirectResponse
    {
        $socialMention->update($request->validated());

        return back()->with('success', 'Bahis inceleme durumu güncellendi.');
    }
}
