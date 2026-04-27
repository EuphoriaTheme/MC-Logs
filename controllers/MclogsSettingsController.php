<?php

namespace Blueprint\Extensions\Mclogs\controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Admin controller for MC Logs global settings.
 *
 * Manages the single-row `mclogs_settings` table that stores panel-wide
 * configuration values such as the maximum number of upload records retained
 * per server.
 */
class MclogsSettingsController extends Controller
{
    protected string $settingsTable = 'mclogs_settings';
    protected string $uploadsTable  = 'mclogs_uploads';

    /**
     * Persist updated settings and optionally prune excess records.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        // Pterodactyl does not register an 'admin' middleware alias — guard manually.
        if (!auth()->user()?->root_admin) {
            abort(403);
        }

        $validated = $request->validate([
            'max_entries' => 'required|integer|min:1|max:10000',
        ]);

        $maxEntries = (int) $validated['max_entries'];

        // Upsert the single settings row.
        $existing = DB::table($this->settingsTable)->first();

        if ($existing) {
            DB::table($this->settingsTable)->update([
                'max_entries' => $maxEntries,
                'updated_at'  => now(),
            ]);
        } else {
            DB::table($this->settingsTable)->insert([
                'max_entries' => $maxEntries,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // Prune all servers: keep only the newest $maxEntries non-deleted rows per server.
        $serverIds = DB::table($this->uploadsTable)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('server_id');

        foreach ($serverIds as $serverId) {
            $keepIds = DB::table($this->uploadsTable)
                ->where('server_id', $serverId)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->limit($maxEntries)
                ->pluck('id');

            if ($keepIds->isNotEmpty()) {
                DB::table($this->uploadsTable)
                    ->where('server_id', $serverId)
                    ->whereNull('deleted_at')
                    ->whereNotIn('id', $keepIds)
                    ->update(['deleted_at' => now()]);
            }
        }

        return redirect()
            ->back()
            ->with('success', 'MC Logs settings saved successfully.');
    }
}
