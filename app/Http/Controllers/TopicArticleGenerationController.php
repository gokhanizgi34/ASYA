<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateTopicArticleRequest;
use App\Models\User;
use App\Services\AiVisionNewsExtractor;
use App\Services\GeneratedContentPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Throwable;

class TopicArticleGenerationController extends Controller
{
    public function __invoke(
        GenerateTopicArticleRequest $request,
        AiVisionNewsExtractor $generator,
        GeneratedContentPublicationService $publisher,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $agencyId = (int) $request->validated('agency_id');
        $topic = (string) $request->validated('topic');
        $image = $request->file('image');

        try {
            $content = $generator->generateTopicArticle($agencyId, $topic, $image?->getRealPath());
            $article = $publisher->send($agencyId, $user, [
                'title' => $content['title'],
                'summary' => $content['summary'],
                'body' => $content['body'],
                'keywords' => $content['keywords'],
                'hashtags' => $content['hashtags'],
                'category' => $content['category'],
                'source_type' => 'topic_ai',
                'source_id' => hash('sha256', Str::lower($topic).'|'.today()->toDateString()),
                'slug' => Str::slug($content['title']).'-'.today()->format('Ymd'),
                'destination' => 'publish',
                'uploaded_image' => $image,
            ]);
        } catch (Throwable $exception) {
            return back()->withErrors(['topic_generation' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('publications.index')->with('success', $article->title.' üretildi ve Yayın Merkezi’ne gönderildi.');
    }
}
