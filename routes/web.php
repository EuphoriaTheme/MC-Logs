<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::prefix('/admin/extensions/mclogs')
    ->middleware(['web', 'auth'])
    ->group(function () {
        Route::post('/settings', function (Request $request) {
            // Guard: only root admins may change settings.
            if (!auth()->user()?->root_admin) {
                abort(403);
            }

            $validated = $request->validate([
                'max_entries' => 'required|integer|min:1|max:10000',
            ]);

            $maxEntries    = (int) $validated['max_entries'];
            $settingsTable = 'mclogs_settings';
            $uploadsTable  = 'mclogs_uploads';

            // Upsert the single settings row.
            $existing = DB::table($settingsTable)->first();

            if ($existing) {
                DB::table($settingsTable)->update([
                    'max_entries' => $maxEntries,
                    'updated_at'  => now(),
                ]);
            } else {
                DB::table($settingsTable)->insert([
                    'max_entries' => $maxEntries,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            // Prune excess records for every server immediately.
            $serverIds = DB::table($uploadsTable)
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('server_id');

            foreach ($serverIds as $serverId) {
                $keepIds = DB::table($uploadsTable)
                    ->where('server_id', $serverId)
                    ->whereNull('deleted_at')
                    ->orderBy('created_at', 'desc')
                    ->limit($maxEntries)
                    ->pluck('id');

                if ($keepIds->isNotEmpty()) {
                    DB::table($uploadsTable)
                        ->where('server_id', $serverId)
                        ->whereNull('deleted_at')
                        ->whereNotIn('id', $keepIds)
                        ->update(['deleted_at' => now()]);
                }
            }

            return redirect()->back()->with('success', 'MC Logs settings saved successfully.');
        })->name('admin.extensions.mclogs.settings.update');
    });
