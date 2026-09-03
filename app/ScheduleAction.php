<?php

namespace App;

enum ScheduleAction: string
{
    case PublishWordPress = 'publish_wordpress';
    case ActivateCampaign = 'activate_campaign';
    case CompleteCampaign = 'complete_campaign';

    public function label(): string
    {
        return match ($this) {
            self::PublishWordPress => 'WordPress Yayını', self::ActivateCampaign => 'Kampanyayı Başlat', self::CompleteCampaign => 'Kampanyayı Tamamla'
        };
    }
}
