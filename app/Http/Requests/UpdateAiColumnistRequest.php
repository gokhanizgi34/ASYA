<?php

namespace App\Http\Requests;

use App\Models\AiColumnist;

class UpdateAiColumnistRequest extends StoreAiColumnistRequest
{
    public function authorize(): bool
    {
        $c = $this->route('aiColumnist');

        return $c instanceof AiColumnist && ($this->user()?->can('update', $c) ?? false);
    }

    protected function columnist(): ?AiColumnist
    {
        $c = $this->route('aiColumnist');

        return $c instanceof AiColumnist ? $c : null;
    }
}
