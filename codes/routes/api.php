<?php

use App\Http\Controllers\Api\WebhookIncidentController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/incidents', WebhookIncidentController::class)
    ->name('api.webhooks.incidents');
