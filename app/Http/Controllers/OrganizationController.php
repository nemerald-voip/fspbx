<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    /**
     * Search Organizations for the Dropdown
     * Returns: [{ value: 'uuid', label: 'Name' }]
     */
    public function index(Request $request)
    {
        $domainUuid = session('domain_uuid');
        $query = $request->input('query'); // VueForm sends search term as 'query'

        $orgs = Organization::where('domain_uuid', $domainUuid)
            ->when($query, function ($q, $query) {
                $q->where('name', 'ilike', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function ($org) {
                return [
                    'value' => $org->organization_uuid,
                    'label' => $org->name
                ];
            });

        return response()->json($orgs);
    }

    /**
     * Create a new Organization via the Modal
     */
    public function store(Request $request)
    {

        $data = $request->validate([
            'name'           => 'required|string',
            'website'        => 'nullable|string',
            'email'          => 'nullable|email',
            'notes'          => 'nullable|string',
            'address_street' => 'nullable|string',
            'address_city'   => 'nullable|string',
            'address_state'  => 'nullable|string',
            'address_zip'    => 'nullable|string',
        ]);

        $domainUuid = session('domain_uuid');


        DB::beginTransaction();
        try {
            // 1. Create Organization
            $org = Organization::create([
                'organization_uuid' => (string) Str::uuid(),
                'domain_uuid' => $domainUuid,
                'name'        => $data['name'],
                'website'     => $data['website'],
                'notes'       => $data['notes'],
            ]);

            // 2. Add Email (if provided)
            if (!empty($data['email'])) {
                $org->emails()->create([
                    'email_address' => $data['email'],
                    'label'         => 'work', // Default label
                ]);
            }

            // 3. Add Address (if provided)
            if (!empty($data['address_street']) || !empty($data['address_city'])) {
                $org->addresses()->create([
                    'domain_uuid' => $domainUuid,
                    'label'       => 'main',
                    'street'      => $data['address_street'],
                    'city'        => $data['address_city'],
                    'region'      => $data['address_state'],
                    'postal_code' => $data['address_zip'],
                    'country_code' => 'US', // Default
                ]);
            }

            DB::commit();

            // Return format for VueForm Select
            return response()->json([
                'value' => $org->organization_uuid,
                'label' => $org->name
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            logger('OrganizationController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create organization']]
            ], 500);
        }
    }
}
