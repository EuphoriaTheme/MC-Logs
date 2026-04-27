<?php

namespace Blueprint\Extensions\Mclogs\client;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Controller for managing MC Logs uploads.
 * Uses query builder directly without Eloquent models.
 * Each upload is stored with a UUID primary key.
 */
class MclogsController extends Controller
{
    protected string $table = 'mclogs_uploads';

    /**
     * Get all log uploads for a specific server.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function getUploads(Request $request, string $uuid): JsonResponse
    {
        $server = Server::query()->where('uuid', $uuid)->orWhere('uuidShort', $uuid)->firstOrFail();

        // Check if user has permission to view this server
        $this->authorize('view', $server);

        $uploads = DB::table($this->table)
            ->where('server_id', $server->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($upload) {
                return [
                    'id' => $upload->mclogs_id,
                    'url' => $upload->mclogs_url,
                    'uploadedAt' => $upload->created_at,
                    'fileName' => $upload->log_file_name,
                ];
            })
            ->toArray();

        return response()->json([
            'data' => $uploads,
        ]);
    }

    /**
     * Store a new log upload record with a unique UUID.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function storeUpload(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'mclogs_id' => 'required|string',
            'mclogs_url' => 'required|url',
            'log_file_name' => 'nullable|string|max:255',
        ]);

        $server = Server::query()->where('uuid', $uuid)->orWhere('uuidShort', $uuid)->firstOrFail();

        // Check if user has permission to access this server
        $this->authorize('view', $server);

        // Check if this mclogs_id already exists (prevent duplicates)
        $existing = DB::table($this->table)
            ->where('server_id', $server->id)
            ->where('mclogs_id', $request->mclogs_id)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return response()->json([
                'data' => [
                    'id' => $existing->mclogs_id,
                    'url' => $existing->mclogs_url,
                    'uploadedAt' => $existing->created_at,
                    'fileName' => $existing->log_file_name,
                ],
            ]);
        }

        // Generate a UUID for this record
        $recordId = Str::uuid()->toString();
        $now = now();

        // Insert the record directly using query builder
        DB::table($this->table)->insert([
            'id' => $recordId,
            'server_id' => $server->id,
            'user_id' => auth()->id(),
            'mclogs_id' => $request->mclogs_id,
            'mclogs_url' => $request->mclogs_url,
            'log_file_name' => $request->log_file_name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Enforce the admin-configured max_entries limit for this server.
        // Soft-delete any records that exceed the cap, keeping the newest ones.
        $settings   = DB::table('mclogs_settings')->first();
        $maxEntries = $settings ? (int) $settings->max_entries : 50;

        $keepIds = DB::table($this->table)
            ->where('server_id', $server->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit($maxEntries)
            ->pluck('id');

        if ($keepIds->isNotEmpty()) {
            DB::table($this->table)
                ->where('server_id', $server->id)
                ->whereNull('deleted_at')
                ->whereNotIn('id', $keepIds)
                ->update(['deleted_at' => $now]);
        }

        return response()->json([
            'data' => [
                'id' => $request->mclogs_id,
                'url' => $request->mclogs_url,
                'uploadedAt' => $now->toIso8601String(),
                'fileName' => $request->log_file_name,
            ],
        ], 201);
    }

    /**
     * Delete a specific log upload record by mclogs_id.
     *
     * @param Request $request
     * @param string $uuid
     * @param string $mclogs_id
     * @return JsonResponse
     */
    public function deleteUpload(Request $request, string $uuid, string $mclogs_id): JsonResponse
    {
        $server = Server::query()->where('uuid', $uuid)->orWhere('uuidShort', $uuid)->firstOrFail();

        // Check if user has permission to access this server
        $this->authorize('view', $server);

        $upload = DB::table($this->table)
            ->where('server_id', $server->id)
            ->where('mclogs_id', $mclogs_id)
            ->whereNull('deleted_at')
            ->first();

        if (!$upload) {
            return response()->json(['message' => 'Upload not found'], 404);
        }

        // Soft delete by setting deleted_at
        DB::table($this->table)
            ->where('id', $upload->id)
            ->update(['deleted_at' => now()]);

        return response()->json(null, 204);
    }

    /**
     * Delete all log uploads for a server.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function clearAllUploads(Request $request, string $uuid): JsonResponse
    {
        $server = Server::query()->where('uuid', $uuid)->orWhere('uuidShort', $uuid)->firstOrFail();

        // Check if user has permission to access this server
        $this->authorize('view', $server);

        DB::table($this->table)
            ->where('server_id', $server->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        return response()->json(null, 204);
    }
}
