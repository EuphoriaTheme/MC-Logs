{{-- Blueprint admin view for the addon.
     `{name}`, `{author}`, and `{identifier}` are placeholders populated by Blueprint from `conf.yml`. --}}
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><strong>{name}</strong> by <strong>{author}</strong></h3>
            </div>
            <div class="box-body">
                Identifier: <code>{identifier}</code><br>
                Uninstall using: <code>blueprint -remove {identifier}</code><br>
                Get support via <a href="https://discord.gg/Cus2zP4pPH" target="_blank" rel="noopener noreferrer">Discord</a><br>
            </div>
        </div>
    </div>
</div>
