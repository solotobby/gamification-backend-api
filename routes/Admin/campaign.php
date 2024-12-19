
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CampaignController;

Route::middleware([
    'auth:api',
    'isAdmin'
])->prefix(
    'admin'
)->group(function () {
    Route::post('/campaign/decision', [CampaignController::class, 'campaignDecision']);
});
