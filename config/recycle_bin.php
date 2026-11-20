<?php

use App\Models\Budget;
use App\Models\Contract;
use App\Models\Lhstp;
use App\Models\PaymentEvidence;
use App\Models\PrDocument;
use App\Models\PurchaseRequest;

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
        'purchase_request' => PurchaseRequest::class,
        'pr_document' => PrDocument::class,
        'payment_evidence' => PaymentEvidence::class,
        'budget' => Budget::class,
        'contract' => Contract::class,
        'lhstp' => Lhstp::class,
    ],

];
