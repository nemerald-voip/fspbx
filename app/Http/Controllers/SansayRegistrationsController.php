<?php

namespace App\Http\Controllers;

use App\Services\SansayApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SansayRegistrationsController extends Controller
{
    public function __construct(public SansayApiService $sansayApiService) {}

    public function index(Request $request)
    {
        return Inertia::render('SansayRegistrations', [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page($request),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'data_route' => route('sansay.registrations.data'),
                'delete' => route('sansay.registrations.delete'),
                'select_all' => route('sansay.registrations.select.all'),
            ],
            'permissions' => ['device_destroy' => userCheckPermission('device_delete')],
        ]);
    }

    public function getData(Request $request)
    {
        $data = $this->builder($request);
        $perPage = fspbx_pagination_per_page($request);
        $lastPage = max(1, (int) ceil($data->count() / $perPage));
        $page = min(max(1, $request->integer('page', 1)), $lastPage);

        return fspbx_paginate_collection($data, $perPage, $page);
    }

    public function builder(Request $request)
    {
        $server = $request->input('filter.server');
        if (empty($server)) return collect();

        $params = ['server' => $server];
        if (! $request->boolean('filter.showGlobal')) {
            $params['userDomain'] = session('domain_name');
        }
        $data = $this->sansayApiService->fetchStats($params);

        if (isset($params['userDomain'])) {
            $data = $data->where('userDomain', $params['userDomain']);
        }
        $search = (string) $request->input('filter.search', '');
        if ($search !== '') {
            $data = $data->filter(function ($item) use ($search) {
                foreach (['userDomain', 'states', 'userIp', 'username'] as $field) {
                    if (stripos((string) ($item[$field] ?? ''), $search) !== false) return true;
                }
                return false;
            });
        }

        $sort = (string) $request->input('sort', 'username');
        $field = ltrim($sort, '-');
        if (! in_array($field, ['username', 'userDomain', 'states', 'userIp', 'userPort', 'protocol', 'createTime', 'expiration', 'agent'], true)) {
            $field = 'username';
        }

        return (str_starts_with($sort, '-') ? $data->sortByDesc($field) : $data->sortBy($field))->values();
    }

    public function destroy(Request $request)
    {
        try {
            $this->sansayApiService->deleteStats($request->input('filter.server'), $request->input('statsData'));

            return response()->json([
                'messages' => ['success' => [__('Request to delete was successfully sent')]],
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }

    public function selectAll(Request $request)
    {
        return response()->json([
            'messages' => ['success' => [__('All items selected')]],
            'items' => $this->builder($request)->pluck('id'),
        ]);
    }
}
