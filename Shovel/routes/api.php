<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationHandoffController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\ConversationResolutionController;
use App\Http\Controllers\Api\QueueItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.apikey'])->group(function () {
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::post('/conversations/{conversation}/messages', [ConversationMessageController::class, 'store']);
    Route::post('/conversations/{conversation}/handoff', [ConversationHandoffController::class, 'store']);
    Route::post('/conversations/{conversation}/resolve', [ConversationResolutionController::class, 'store']);

    Route::get('/queues/{queue}/items', [QueueItemController::class, 'index']);
    Route::post('/queues/{queue}/items/{queueItem}/claim', [QueueItemController::class, 'claim']);

    Route::post('/assignments/{assignment}/accept', [AssignmentController::class, 'accept']);
    Route::post('/assignments/{assignment}/release', [AssignmentController::class, 'release']);
    Route::post('/assignments/{assignment}/resolve', [AssignmentController::class, 'resolve']);
});

// Routes requiring authenticated user (human agent)
Route::middleware(['auth'])->group(function () {
    Route::post('/conversations/{conversation}/human-messages', [ConversationMessageController::class, 'storeHumanMessage']);
});
