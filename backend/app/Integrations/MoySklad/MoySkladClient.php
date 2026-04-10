<?php

namespace App\Integrations\MoySklad;

class MoySkladClient
{
    public function isConfigured(): bool
    {
        return filled(config('integrations.moysklad.base_url'))
            && filled(config('integrations.moysklad.token'));
    }
}
