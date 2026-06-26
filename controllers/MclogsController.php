<?php

namespace Pterodactyl\BlueprintFramework\Extensions\mclogs;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Controller for managing MC Logs uploads.
 * Uses query builder directly without Eloquent models.
 * Each upload is stored with a UUID primary key.
 */
class MclogsController extends Controller
{
    protected string $uploadsTable = 'mclogs_uploads';

    public function getUploads(Request $request, string $uuid): JsonResponse
    {
        $server = Server::query()->where('uuid', $uuid)->orWhere('uuidShort', $uuid)->firstOrFail();
        $this->authorize('view', $server);

        $uploads = DB::table($this->uploadsTable)
            ->where('server_id', $server->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($upload) => [
                'id'         => $upload->mclogs_id,
                'url'        => $upload->mclogs_url,
                'uploadedAt' => $upload->created_at,
                'fileName'   => $upload->log_file_name,
            ])
            ->toArray();

        return response()->json(['data' => $uploads]);
    }

    public function storeUpload(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'mclogs_id'     => 'required|string',
            'mclogs_url'    => 'required|url',
            'log_file_name' => 'nullable|string|max:255',
        ]);

        $server = Server::query()->where('uuid', $uuid)->orWhere('uuidShort', $uuid)->firstOrFail();
        $this->authorize('view', $server);

        $existing = DB::table($this->uploadsTable)
            ->where('server_id', $server->id)
            ->where('mclogs_id', $request->mclogs_id)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return response()->json(['data' => [
                'id'         => $existing->mclogs_id,
                'url'        => $existing->mclogs_url,
                'uploadedAt' => $existing->created_at,
                'fileName'   => $existing->log_file_name,
            ]]);
        }

        $recordId = Str::uuid()->toString();
        $now      = now();

        DB::table($this->uploadsTable)->insert([
            'id'            => $recordId,
            'server_id'     => $server->id,
            'user_id'       => auth()->id(),
            'mclogs_id'     => $request->mclogs_id,
            'mclogs_url'    => $request->mclogs_url,
            'log_file_name' => $request->log_file_name,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Enforce the admin-configured max_entries limit for this server.
        $maxEntries = (int) (DB::table('mclogs_settings')->value('max_entries') ?? 50);
        $keepIds = DB::table($this->uploadsTable)
            ->where('server_id', $server->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->limit($maxEntries)
            ->pluck('id');

        if ($keepIds->isNotEmpty()) {
            DB::table($this->uploadsTable)
                ->where('server_id', $server->id)
                ->whereNull('deleted_at')
                ->whereNotIn('id', $keepIds)
                ->update(['deleted_at' => $now]);
        }

        return response()->json(['data' => [
            'id'         => $request->mclogs_id,
            'url'        => $request->mclogs_url,
            'uploadedAt' => $now->toIso8601String(),
            'fileName'   => $request->log_file_name,
        ]], 201);
    }

    public function deleteUpload(Request $request, string $uuid, string $mclogs_id): JsonResponse
    {
        $server = Server::query()->where('uuid', $uuid)->orWhere('uuidShort', $uuid)->firstOrFail();
        $this->authorize('view', $server);

        $upload = DB::table($this->uploadsTable)
            ->where('server_id', $server->id)
            ->where('mclogs_id', $mclogs_id)
            ->whereNull('deleted_at')
            ->first();

        if (!$upload) {
            return response()->json(['message' => 'Upload not found'], 404);
        }

        DB::table($this->uploadsTable)
            ->where('id', $upload->id)
            ->update(['deleted_at' => now()]);

        return response()->json(null, 204);
    }

    public function clearAllUploads(Request $request, string $uuid): JsonResponse
    {
        $server = Server::query()->where('uuid', $uuid)->orWhere('uuidShort', $uuid)->firstOrFail();
        $this->authorize('view', $server);

        DB::table($this->uploadsTable)
            ->where('server_id', $server->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        return response()->json(null, 204);
    }
}
