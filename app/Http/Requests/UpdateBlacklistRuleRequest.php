<?php

namespace App\Http\Requests;

use App\Models\BlacklistRule;

class UpdateBlacklistRuleRequest extends StoreBlacklistRuleRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('blacklistRule');

        return $rule instanceof BlacklistRule && ($this->user()?->can('update', $rule) ?? false);
    }

    protected function ruleForUniqueValidation(): ?BlacklistRule
    {
        $rule = $this->route('blacklistRule');

        return $rule instanceof BlacklistRule ? $rule : null;
    }
}
