<?php

use App\Http\Controllers\FreshchatController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/freshchat');
});

Route::prefix('freshchat')->group(function () {
    Route::get('/', [FreshchatController::class, 'index']);
    Route::get('/tickets', [FreshchatController::class, 'tickets']);
    Route::get('/test-api', [FreshchatController::class, 'testApi']);
    Route::get('/save', [FreshchatController::class, 'save']);
    Route::get('/database', [FreshchatController::class, 'database']);
    Route::get('/export', [FreshchatController::class, 'export']);
    Route::get('/sync', [FreshchatController::class, 'sync']);
Route::get('/export-batch', [FreshchatController::class, 'exportBatch']);
Route::get('/export-all', [FreshchatController::class, 'exportAll']);
});