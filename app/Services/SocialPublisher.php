<?php

namespace App\Services;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SocialPublisher
{
    public function __construct(private readonly XOAuth1Signer $oauth1Signer) {}

    public function publish(SocialPost $post): string
    {
        $post->loadMissing('account');
        $account = $post->account;

        if (! $account->is_active || blank($account->access_token)) {
            throw new RuntimeException('Sosyal yayın hesabı aktif değil veya erişim anahtarı eksik.');
        }

        if ($account->publish_mode === 'local_sandbox') {
            return 'local-'.$account->platform.'-'.Str::uuid();
        }

        if ($account->platform !== 'x' || $account->publish_mode !== 'x_api_v2') {
            throw new RuntimeException('Bu sosyal ağ için canlı yayın adaptörü etkin değil.');
        }

        $request = Http::asJson()->acceptJson();

        if (filled($account->api_key) && filled($account->api_secret) && filled($account->access_token_secret)) {
            $oauth = [
                'oauth_consumer_key' => (string) $account->api_key,
                'oauth_nonce' => bin2hex(random_bytes(16)),
                'oauth_signature_method' => 'HMAC-SHA1',
                'oauth_timestamp' => (string) now()->timestamp,
                'oauth_token' => (string) $account->access_token,
                'oauth_version' => '1.0',
            ];
            $request = $request->withHeaders([
                'Authorization' => $this->oauth1Signer->authorizationHeader(
                    'POST',
                    'https://api.x.com/2/tweets',
                    $oauth,
                    (string) $account->api_secret,
                    (string) $account->access_token_secret,
                ),
            ]);
        } else {
            $request = $request->withToken((string) $account->access_token);
        }

        $response = $request
            ->connectTimeout(5)
            ->timeout(20)
            ->post('https://api.x.com/2/tweets', [
                'text' => $this->postText($post),
            ]);

        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException('X kimlik bilgileri geçersiz veya uygulamada tweet.write izni bulunmuyor. X Developer Console ayarlarını kontrol edin.');
        }

        if ($response->status() === 402) {
            throw new RuntimeException('X API kredisi yetersiz. Developer Console üzerinden kredi ve harcama limitini kontrol edin.');
        }

        $response->throw();
        $externalId = $response->json('data.id');

        if (! is_string($externalId) || $externalId === '') {
            throw new RuntimeException('X API geçerli bir gönderi kimliği döndürmedi.');
        }

        return $externalId;
    }

    private function postText(SocialPost $post): string
    {
        $link = trim((string) $post->link_url);
        $suffix = $link !== '' ? "\n\n".$link : '';
        $contentLimit = $link !== '' ? 255 : 280;
        $content = Str::of(strip_tags($post->content))->squish()->limit($contentLimit, '')->toString();

        return $content.$suffix;
    }
}
