<?php

namespace App\Http\Requests;

use App\Models\SocialPublishingAccount;

class UpdateSocialPublishingAccountRequest extends StoreSocialPublishingAccountRequest
{
    public function authorize(): bool
    {
        $account = $this->route('socialPublishingAccount');

        return $account instanceof SocialPublishingAccount && ($this->user()?->can('update', $account) ?? false);
    }

    protected function accountForUniqueRule(): ?SocialPublishingAccount
    {
        $account = $this->route('socialPublishingAccount');

        return $account instanceof SocialPublishingAccount ? $account : null;
    }
}
