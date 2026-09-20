<?php

use App\Http\Controllers\WebsiteTrackingController;
use Illuminate\Support\Facades\Route;

// No session, authentication cookies or CSRF middleware on the public telemetry route.
// It is origin-restricted and never creates customers, conversions or commissions.
Route::match(['POST', 'OPTIONS'], '/tracking/connections/{connectionId}/visit', [WebsiteTrackingController::class, 'visit'])
    ->whereUuid('connectionId')->middleware('throttle:120,1')->name('tracking.visit');
