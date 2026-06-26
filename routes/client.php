<?php

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/{uuid}/mclogs', 'Pterodactyl\BlueprintFramework\Extensions\mclogs\MclogsController@getUploads');
    Route::post('/{uuid}/mclogs', 'Pterodactyl\BlueprintFramework\Extensions\mclogs\MclogsController@storeUpload');
    Route::delete('/{uuid}/mclogs/{mclogs_id}', 'Pterodactyl\BlueprintFramework\Extensions\mclogs\MclogsController@deleteUpload');
    Route::delete('/{uuid}/mclogs', 'Pterodactyl\BlueprintFramework\Extensions\mclogs\MclogsController@clearAllUploads');
});
