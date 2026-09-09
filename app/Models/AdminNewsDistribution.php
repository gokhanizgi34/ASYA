<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['created_by', 'title', 'summary', 'body', 'image_disk', 'image_path', 'image_original_name', 'image_mime_type', 'selection_mode', 'province', 'recipient_agency_count', 'publication_count'])]
class AdminNewsDistribution extends Model
{
    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AdminNewsDistributionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AdminNewsDistributionItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recipient_agency_count' => 'integer',
            'publication_count' => 'integer',
        ];
    }
}
