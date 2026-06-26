<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\mclogs;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;

class mclogsExtensionController extends Controller
{
    public function __construct(
        private BlueprintExtensionLibrary $blueprint,
        private SoftwareVersionService $version,
        private ViewFactory $view,
    ) {}

    public function index(): View
    {
        return $this->view->make('admin.extensions.mclogs.index', [
            'blueprint' => $this->blueprint,
            'version'   => $this->version,
            'root'      => '/admin/extensions/mclogs',
        ]);
    }

    public function post(Request $request)
    {
        if (!auth()->user()?->root_admin) {
            abort(403);
        }

        $validated   = $request->validate(['max_entries' => 'required|integer|min:1|max:10000']);
        $maxEntries  = (int) $validated['max_entries'];

        $existing = DB::table('mclogs_settings')->first();
        if ($existing) {
            DB::table('mclogs_settings')->update(['max_entries' => $maxEntries, 'updated_at' => now()]);
        } else {
            DB::table('mclogs_settings')->insert(['max_entries' => $maxEntries, 'created_at' => now(), 'updated_at' => now()]);
        }

        // Prune excess records across all servers immediately.
        $serverIds = DB::table('mclogs_uploads')->whereNull('deleted_at')->distinct()->pluck('server_id');
        foreach ($serverIds as $serverId) {
            $keepIds = DB::table('mclogs_uploads')
                ->where('server_id', $serverId)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->limit($maxEntries)
                ->pluck('id');

            if ($keepIds->isNotEmpty()) {
                DB::table('mclogs_uploads')
                    ->where('server_id', $serverId)
                    ->whereNull('deleted_at')
                    ->whereNotIn('id', $keepIds)
                    ->update(['deleted_at' => now()]);
            }
        }

        return redirect('/admin/extensions/mclogs')->with('success', 'MC Logs settings saved successfully.');
    }

    public function put(Request $request)
    {
        if (!auth()->user()?->root_admin) {
            abort(403);
        }

        DB::statement('TRUNCATE TABLE mclogs_uploads');

        return redirect('/admin/extensions/mclogs')->with('success', 'All MC Logs upload records have been permanently deleted from the database.');
    }
}
