
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CampaignController;

Route::middleware([
    'auth:api',
    'isAdmin'
])->prefix(
    'admin/campaign'
)->group(function () {
    Route::get('/', [CampaignController::class, 'getCampaignsByAdmin']);

    Route::post('/decision', [CampaignController::class, 'campaignDecision']);
});
