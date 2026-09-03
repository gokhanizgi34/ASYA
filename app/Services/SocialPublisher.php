<?php

namespace App\Services;

use App\Models\SocialPost;
use Illuminate\Support\Str;
use RuntimeException;

class SocialPublisher
{
    public function publish(SocialPost $post): string
    {
        $post->loadMissing('account');
        $account = $post->account;

        if (! $account->is_active || blank($account->access_token)) {
            throw new RuntimeException('Sosyal yayın hesabı aktif değil veya erişim anahtarı eksik.');
        }

        if ($account->publish_mode !== 'local_sandbox') {
            throw new RuntimeException('Canlı sosyal ağ adaptörü henüz etkinleştirilmedi.');
        }

        return 'local-'.$account->platform.'-'.Str::uuid();
    }
}
