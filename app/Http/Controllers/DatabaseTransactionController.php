<?php

namespace App\Http\Controllers;

use App\Models\DatabaseTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DatabaseTransactionController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('database_transaction_view')) {
            return redirect('/');
        }

        return Inertia::render('DatabaseTransactions', [
            'routes' => [
                'current_page' => route('database-transactions.index'),
                'data_route' => route('database-transactions.data'),
                'show' => route('database-transactions.show', ['database_transaction' => '__TRANSACTION__']),
                'undo' => route('database-transactions.undo', ['database_transaction' => '__TRANSACTION__']),
            ],
            'permissions' => [
                'view' => userCheckPermission('database_transaction_view'),
                'update' => userCheckPermission('database_transaction_edit'),
            ],
            'users' => $this->userOptions(),
            'timezone' => get_local_time_zone(session('domain_uuid')),
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('database_transaction_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return $this->scopedTransactions($request)
            ->select([
                't.database_transaction_uuid',
                't.domain_uuid',
                'd.domain_name',
                'u.username',
                't.user_uuid',
                't.app_name',
                't.app_uuid',
                't.transaction_code',
                't.transaction_address',
                't.transaction_type',
                't.transaction_date',
            ])
            ->allowedSorts([
                'domain_name',
                'username',
                'app_name',
                'transaction_code',
                'transaction_address',
                'transaction_type',
                'transaction_date',
            ])
            ->defaultSort('-transaction_date')
            ->paginate($this->perPage)
            ->appends($request->query());
    }

    public function show(DatabaseTransaction $database_transaction): JsonResponse
    {
        if (! userCheckPermission('database_transaction_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $transaction = $this->findScopedTransaction($database_transaction->database_transaction_uuid);

        if (! $transaction) {
            return response()->json([
                'messages' => ['error' => ['Transaction not found.']],
            ], 404);
        }

        return response()->json([
            'item' => $this->serializeTransaction($transaction),
        ]);
    }

    public function undo(DatabaseTransaction $database_transaction): JsonResponse
    {
        if (! userCheckPermission('database_transaction_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $transaction = $this->findScopedTransaction($database_transaction->database_transaction_uuid);

        if (! $transaction) {
            return response()->json([
                'messages' => ['error' => ['Transaction not found.']],
            ], 404);
        }

        $type = $this->transactionType($transaction);
        if (! in_array($type, ['delete', 'update'], true)) {
            return response()->json([
                'messages' => ['error' => ['Only delete and update transactions can be undone.']],
            ], 422);
        }

        $payload = $this->decodeJson($transaction->transaction_old);
        if (! is_array($payload)) {
            return response()->json([
                'messages' => ['error' => ['The original transaction payload is not valid JSON.']],
            ], 422);
        }

        try {
            $this->restoreTransaction($transaction, $payload);
        } catch (\Throwable $e) {
            logger('DatabaseTransactionController@undo error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to undo the transaction.']],
            ], 500);
        }

        return response()->json([
            'messages' => ['success' => ['Transaction undone successfully.']],
        ]);
    }

    private function scopedTransactions(Request $request): QueryBuilder
    {
        return QueryBuilder::for(
            DatabaseTransaction::query()
                ->from('v_database_transactions as t')
                ->leftJoin('v_domains as d', 'd.domain_uuid', '=', 't.domain_uuid')
                ->leftJoin('v_users as u', 'u.user_uuid', '=', 't.user_uuid')
                ->where('t.domain_uuid', session('domain_uuid'))
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('t.app_name', 'ilike', "%{$needle}%")
                            ->orWhere('t.transaction_code', 'ilike', "%{$needle}%")
                            ->orWhere('t.transaction_address', 'ilike', "%{$needle}%")
                            ->orWhere('t.transaction_type', 'ilike', "%{$needle}%")
                            ->orWhereRaw('CAST(t.transaction_date AS TEXT) ILIKE ?', ["%{$needle}%"])
                            ->orWhere('t.transaction_old', 'ilike', "%{$needle}%")
                            ->orWhere('t.transaction_new', 'ilike', "%{$needle}%")
                            ->orWhere('u.username', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('user_uuid', function ($query, $value) {
                    $userUuid = trim((string) $value);

                    if ($userUuid !== '') {
                        $query->where('t.user_uuid', $userUuid);
                    }
                }),
            ]);
    }

    private function findScopedTransaction(string $uuid): ?DatabaseTransaction
    {
        return DatabaseTransaction::query()
            ->with([
                'domain:domain_uuid,domain_name,domain_description',
                'user:user_uuid,username,user_email',
            ])
            ->where('domain_uuid', session('domain_uuid'))
            ->whereKey($uuid)
            ->first();
    }

    private function serializeTransaction(DatabaseTransaction $transaction): array
    {
        $type = $this->transactionType($transaction);

        return [
            'database_transaction_uuid' => $transaction->database_transaction_uuid,
            'domain_name' => $transaction->domain?->domain_name,
            'domain_description' => $transaction->domain?->domain_description,
            'username' => $transaction->user?->username,
            'user_email' => $transaction->user?->user_email,
            'user_uuid' => $transaction->user_uuid,
            'app_name' => $transaction->app_name,
            'app_uuid' => $transaction->app_uuid,
            'transaction_code' => $transaction->transaction_code,
            'transaction_address' => $transaction->transaction_address,
            'transaction_type' => $type,
            'transaction_date' => $transaction->transaction_date,
            'can_undo' => in_array($type, ['delete', 'update'], true),
            'diff' => $this->buildDiff($transaction, $type),
            'raw' => [
                'old' => $transaction->transaction_old,
                'new' => $transaction->transaction_new,
                'result' => $transaction->transaction_result,
            ],
        ];
    }

    private function transactionType(DatabaseTransaction $transaction): string
    {
        if (! empty($transaction->transaction_type)) {
            return (string) $transaction->transaction_type;
        }

        $old = trim((string) $transaction->transaction_old);

        return $old === '' || $old === 'null' ? 'add' : 'update';
    }

    private function buildDiff(DatabaseTransaction $transaction, string $type): array
    {
        $before = $this->decodeJson($transaction->transaction_old);
        $after = $this->decodeJson($transaction->transaction_new);

        if ($type === 'add') {
            return $this->groupLeafValues($after, 'new');
        }

        if ($type === 'delete') {
            return $this->groupLeafValues($before, 'old');
        }

        return $this->groupChanges($before, $after);
    }

    private function groupLeafValues(mixed $payload, string $side): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $sections = [];

        foreach ($payload as $table => $rows) {
            $items = [];

            foreach ($this->leafValues($rows) as $path => $value) {
                $items[] = [
                    'name' => $path,
                    'old' => $side === 'old' ? $this->stringValue($value) : null,
                    'new' => $side === 'new' ? $this->stringValue($value) : null,
                    'changed' => true,
                ];
            }

            if (! empty($items)) {
                $sections[] = [
                    'title' => (string) $table,
                    'rows' => $items,
                ];
            }
        }

        return $sections;
    }

    private function groupChanges(mixed $before, mixed $after): array
    {
        if (! is_array($before) && ! is_array($after)) {
            return [];
        }

        $before = is_array($before) ? $before : [];
        $after = is_array($after) ? $after : [];
        $sections = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
            $rows = $this->compareLeaves($before[$table] ?? [], $after[$table] ?? []);

            if (! empty($rows)) {
                $sections[] = [
                    'title' => (string) $table,
                    'rows' => $rows,
                ];
            }
        }

        return $sections;
    }

    private function compareLeaves(mixed $before, mixed $after, string $prefix = ''): array
    {
        if (! is_array($before) && ! is_array($after)) {
            $old = $this->stringValue($before);
            $new = $this->stringValue($after);

            return [[
                'name' => $prefix,
                'old' => $old,
                'new' => $new,
                'changed' => $old !== $new,
            ]];
        }

        $before = is_array($before) ? $before : [];
        $after = is_array($after) ? $after : [];
        $rows = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            array_push($rows, ...$this->compareLeaves($before[$key] ?? null, $after[$key] ?? null, $path));
        }

        return $rows;
    }

    private function leafValues(mixed $value, string $prefix = ''): array
    {
        if (! is_array($value)) {
            return [$prefix => $value];
        }

        $rows = [];

        foreach ($value as $key => $child) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $rows += $this->leafValues($child, $path);
        }

        return $rows;
    }

    private function decodeJson(?string $value): mixed
    {
        if ($value === null || trim($value) === '' || trim($value) === 'null') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    private function restoreTransaction(DatabaseTransaction $transaction, array $payload): void
    {
        if (! class_exists('database')) {
            require_once base_path('public/resources/require.php');
        }

        $database = new \database();
        $database->app_name = $transaction->app_name;
        $database->app_uuid = $transaction->app_uuid;
        $database->save($payload);
    }

    private function userOptions(): array
    {
        return DB::table('v_users')
            ->select(['user_uuid', 'username'])
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('username')
            ->get()
            ->map(fn ($user) => [
                'value' => $user->user_uuid,
                'label' => $user->username,
            ])
            ->all();
    }
}
