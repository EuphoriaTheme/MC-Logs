<?php

Route::prefix('/admin/extensions/mclogs')
    ->middleware(['web', 'auth', 'admin'])
    ->group(function () {
        Route::post('/settings', 'Blueprint\Extensions\Mclogs\controllers\MclogsSettingsController@update')
            ->name('admin.extensions.mclogs.settings.update');
    });
