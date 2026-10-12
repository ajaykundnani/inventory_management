<?php

use Illuminate\Support\Facades\Route;

// Every non-API path is handled by the Vue router.
Route::view('/{any?}', 'welcome')->where('any', '^(?!api|sanctum|up).*$');
