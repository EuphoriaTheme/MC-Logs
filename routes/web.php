<?php

Route::prefix('/admin/extensions/mclogs')->middleware(['web', 'auth'])->group(function () {
    Route::post('/settings', 'Blueprint\Extensions\Mclogs\controllers\MclogsController@updateSettings');
});
