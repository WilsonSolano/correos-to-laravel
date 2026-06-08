<?php

use App\Http\Controllers\OAuthController;
use App\Livewire\MailSearch;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mail Extractor Routes
|--------------------------------------------------------------------------
|
| These routes are public for development purposes.
| In production you would wrap them in an auth middleware.
|
*/

// ── Main search page (Livewire) ──────────────────────────────────────────
Route::get('/mail', MailSearch::class)->name('mail.search');

// ── OAuth flow ───────────────────────────────────────────────────────────
Route::get('/oauth/{provider}/redirect', [OAuthController::class, 'redirect'])
    ->name('oauth.redirect');

Route::get('/oauth/{provider}/callback', [OAuthController::class, 'callback'])
    ->name('oauth.callback');

Route::get('/oauth/{provider}/disconnect', [OAuthController::class, 'disconnect'])
    ->name('oauth.disconnect');
