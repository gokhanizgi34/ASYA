<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_news_distribution_id', 'agency_id', 'publishing_target_id', 'article_id', 'publication_id', 'failure_message'])]
class AdminNewsDistributionItem extends Model
{
    /** @return BelongsTo<AdminNewsDistribution, $this> */
    public function distribution(): BelongsTo
    {
        return $this->belongsTo(AdminNewsDistribution::class, 'admin_news_distribution_id');
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /** @return BelongsTo<PublishingTarget, $this> */
    public function publishingTarget(): BelongsTo
    {
        return $this->belongsTo(PublishingTarget::class)->withTrashed();
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    /** @return BelongsTo<Publication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }
}
