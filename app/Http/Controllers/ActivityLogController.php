<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('ActivityLog', [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page($request),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => ['data_route' => route('activities.data')],
            'permissions' => ['view_global' => userCheckPermission('device_all')],
        ]);
    }

    public function getData(Request $request)
    {
        $showGlobal = $request->boolean('filter.showGlobal');
        abort_if($showGlobal && ! userCheckPermission('device_all'), 403);

        $query = Activity::query()->select(
            'id', 'log_name', 'description', 'properties', 'causer_type', 'causer_id',
            'subject_type', 'subject_id', 'created_at', 'domain_uuid'
        )->with(['causer' => function (MorphTo $morphTo) {
            $morphTo->morphWith([User::class => ['user_adv_fields']]);
        }]);

        if ($showGlobal) {
            $domainUuids = collect(session('domains', []))->pluck('domain_uuid')->all();
            $query->with('domain:domain_uuid,domain_name,domain_description')
                ->where(function ($query) use ($domainUuids) {
                    $query->whereIn('domain_uuid', $domainUuids)->orWhereNull('domain_uuid');
                });
        } else {
            $query->where('domain_uuid', session('domain_uuid'));
        }

        $search = $request->input('filter.search');
        if ($search !== null && $search !== '') {
            $query->where(function ($query) use ($search) {
                $value = '%'.$search.'%';
                $query->where('log_name', 'ilike', $value)
                    ->orWhere('description', 'ilike', $value)
                    ->orWhereRaw('CAST(properties AS TEXT) ILIKE ?', [$value])
                    ->orWhereHasMorph('causer', [User::class], function ($query) use ($value) {
                        $query->where('username', 'ilike', $value)->orWhere('user_email', 'ilike', $value);
                    });
            });
        }

        $sort = (string) $request->input('sort', '-created_at');
        $field = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        if (! in_array($field, ['created_at', 'log_name', 'description'], true)) {
            $field = 'created_at';
            $direction = 'desc';
        }

        return $query->orderBy($field, $direction)->orderBy('id')->paginate(fspbx_pagination_per_page($request));
    }
}
