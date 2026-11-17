<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recycle Bin Entity Types
    |--------------------------------------------------------------------------
    |
    | Maps an entity_type stored in recycle_bin_items to its Eloquent model.
    | Each registered model must use SoftDeletes. Modules are added here as
    | they are implemented; an unknown type is never purged.
    |
    */

    'types' => [
        // 'purchase_request' => App\Models\PurchaseRequest::class,
    ],

];
