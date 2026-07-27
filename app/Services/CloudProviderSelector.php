<?php

namespace App\Services;

class CloudProviderSelector
{
    public function getCloudProvider($vendor)
    {
        switch (strtolower($vendor)) {
            case 'polycom':
                return new \App\Services\PolycomCloudProvider();
            case 'yealink':
                return new \App\Services\YealinkRpsCloudProvider();
            default:
                return null;
        }
    }
}
