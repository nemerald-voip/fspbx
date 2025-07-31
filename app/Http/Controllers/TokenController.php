<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Data\TokenData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Requests\StoreApiTokenRequest;

class TokenController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $userId = $request->input('uuid');

        $user = User::where('user_uuid', $userId)->firstOrFail();

        // Use QueryBuilder on tokens relationship
        $query = QueryBuilder::for($user->tokens()->getQuery())
            ->select([
                'id',
                'name',
                'created_at',
                'last_used_at',
                'expires_at',
            ])
            ->allowedFilters(['name', 'id'])
            ->allowedSorts(['created_at', 'last_used_at', 'name'])
            ->defaultSort('-created_at');

        // For pagination, use paginate(); otherwise, get()
        $tokens = $query->paginate($request->input('per_page', 15));

        $tokens->getCollection()->transform(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at
                    ? Carbon::parse($token->created_at)->format('M d, Y H:i')
                    : null,
                'last_used_at' => $token->last_used_at
                    ? Carbon::parse($token->last_used_at)->diffForHumans()
                    : null,
                'expires_at' => $token->expires_at
                    ? Carbon::parse($token->expires_at)->format('M d, Y H:i')
                    : null,
            ];
        });

        return response()->json($tokens);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {

        $fields = $request->validate([
            'user_email' => 'required|string|email|max:255|',
            'password' => 'required'
        ]);

        $user = User::where('user_email', $request->user_email)->first();

        if (!$user || !password_verify($request->password, $user->password)) {
            return $this->sendError('Athentication failed.', [], 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->sendResponse(
            [
                'name' => $user->username,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
            'Token generated successfully.'
        );
    }

    public function store(StoreApiTokenRequest $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validated();

            $user = User::where('user_uuid', $validated['user_uuid'])->firstOrFail();

            // Create the token
            $token = $user->createToken($validated['name']);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['API token created successfully.']],
                'token' => $token->plainTextToken, // Show once!
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('API token create error: ' . $e->getMessage()
                . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while creating the API token.']]
            ], 500);
        }
    }


    /**
     * Bulk-delete selected API tokens.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        // 1) Permission check
        if (! userCheckPermission('api_key_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            $uuids = $request->input('items', []);

            // 2) Fetch the tokens (using Sanctum's model)
            $tokens = \Laravel\Sanctum\PersonalAccessToken::whereIn('id', $uuids)->get();

            foreach ($tokens as $token) {
                // 3) Delete the token
                $token->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected API Key revoked successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'Tokens bulkDelete error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['An error occurred while revoking API Key.']]
            ], 500);
        }
    }
}
