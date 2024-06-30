<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\DTO\RingotelOrganizationDTO;
use Illuminate\Support\Facades\Http;

class RingotelApiService
{
    // protected $apiUrl;

    public function __construct()
    {
        // $this->apiUrl = config('services.third_party_api.url');
    }

    public function getOrganizations()
    {
        $data = array(
            'method' => 'getOrganizations',
        );

        $response = Http::ringotel()
            ->timeout(5)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to retrieve organizations");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return collect($response['result'])->map(function ($item) {
            return RingotelOrganizationDTO::fromArray($item);
        });
    }

    public function getUsersByOrgId($orgId)
    {
        $data = [
            'method' => 'getUsers',
            'orgid' => $orgId,
        ];

        $response = Http::ringotel()
            ->timeout(5)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) use ($orgId) {
                throw new \Exception("Unable to retrieve users for organization ID: $orgId");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return $response['result'];
    }

    public function matchLocalDomains($organizations)
    {

        $orgs = DB::table('v_domain_settings')
        -> join('v_domains', 'v_domains.domain_uuid', '=', 'v_domain_settings.domain_uuid')
        -> where('domain_setting_category', 'app shell')
        -> where ('domain_setting_subcategory', 'org_id')
        -> get([
            'v_domain_settings.domain_uuid',
            'domain_setting_value AS org_id',
            'domain_name',
            'domain_description',
        ]);

        $orgArray = $organizations->map(function ($organization) use ($orgs) {
            foreach ($orgs as $org) {
                if ($organization->id == $org->org_id) {
                    $organization->domain_uuid = $org->domain_uuid;
                    $organization->domain_name = $org->domain_name;
                    $organization->domain_description = $org->domain_description;
                }
            }
            return $organization;
        });

        return $orgArray;
    }

}
