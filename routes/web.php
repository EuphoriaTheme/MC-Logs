<?php

Route::prefix('/admin/extensions/mclogs')
    ->middleware(['web', 'auth', 'admin'])
    ->group(function () {
        Route::post('/settings', 'Blueprint\Extensions\Mclogs\admin\MclogsSettingsController@update')
            ->name('admin.extensions.mclogs.settings.update');
    });
