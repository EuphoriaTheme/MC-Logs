{{-- Blueprint admin view for the MC Logs addon.
     `{name}`, `{author}`, and `{identifier}` are placeholders populated by Blueprint from `conf.yml`. --}}

@php
    $settings    = \Illuminate\Support\Facades\DB::table('mclogs_settings')->first();
    $maxEntries  = $settings?->max_entries ?? 50;
    $totalStored = \Illuminate\Support\Facades\DB::table('mclogs_uploads')->whereNull('deleted_at')->count();
@endphp

<div class="row">
    {{-- Info card --}}
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><strong>{name}</strong> by <strong>{author}</strong></h3>
            </div>
            <div class="box-body">
                Identifier: <code>{identifier}</code><br>
                Uninstall using: <code>blueprint -remove {identifier}</code><br>
                Get support via <a href="https://discord.gg/Cus2zP4pPH" target="_blank" rel="noopener noreferrer">Discord</a>
            </div>
        </div>
    </div>

    {{-- Settings card --}}
    <div class="col-xs-12 col-sm-8 col-md-6">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Storage Settings</h3>
            </div>

            <form method="POST" action="/admin/extensions/mclogs/settings">
                @csrf

                <div class="box-body">

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="form-group">
                        <label for="max_entries">Max Upload Entries per Server</label>
                        <input
                            type="number"
                            id="max_entries"
                            name="max_entries"
                            class="form-control"
                            value="{{ old('max_entries', $maxEntries) }}"
                            min="1"
                            max="10000"
                            required
                        >
                        <p>
                            Maximum number of MC Logs upload records stored per server.
                            When a new upload exceeds this limit the oldest record is automatically removed.
                            Saving a lower value here will also immediately prune any existing records that exceed it.
                            <br><br>
                            <strong>Currently stored:</strong> {{ number_format($totalStored) }} record(s) across all servers.
                        </p>
                    </div>

                </div>

                <div class="box-footer">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa fa-save"></i>&nbsp; Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
