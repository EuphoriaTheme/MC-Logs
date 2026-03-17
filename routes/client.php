<?php

Route::prefix('/api/client/servers')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/{uuid}/mclogs', 'Blueprint\Extensions\Mclogs\controllers\MclogsController@getUploads');

    Route::post('/{uuid}/mclogs', 'Blueprint\Extensions\Mclogs\controllers\MclogsController@storeUpload');

    Route::delete('/{uuid}/mclogs/{mclogs_id}', 'Blueprint\Extensions\Mclogs\controllers\MclogsController@deleteUpload');
    Route::delete('/{uuid}/mclogs', 'Blueprint\Extensions\Mclogs\controllers\MclogsController@clearAllUploads');
});
