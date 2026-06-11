<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('inicio');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('panel', 'dashboard')->name('panel');
});

require __DIR__.'/settings.php';
require __DIR__.'/mail.php';
