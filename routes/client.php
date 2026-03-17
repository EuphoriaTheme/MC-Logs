<?php

Route::prefix('/api/client/servers')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/{uuid}/mclogs', 'Blueprint\Extensions\Mclogs\client\MclogsController@getUploads');

    Route::post('/{uuid}/mclogs', 'Blueprint\Extensions\Mclogs\client\MclogsController@storeUpload');

    Route::delete('/{uuid}/mclogs/{mclogs_id}', 'Blueprint\Extensions\Mclogs\client\MclogsController@deleteUpload');
    Route::delete('/{uuid}/mclogs', 'Blueprint\Extensions\Mclogs\client\MclogsController@clearAllUploads');
});
