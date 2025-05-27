<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('test', function (Request $request) {
    return $response = [
        'status' => 'success',
        'message' => 'API is working'
    ];
});

Route::post('register', [\App\Http\Controllers\V1\AuthController::class, 'register']);
Route::post('login', [\App\Http\Controllers\V1\AuthController::class, 'login']);


//test is token is working
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



Route::middleware(['auth:sanctum', 'throttle:custom'])->group(function () {
    Route::post('logout', [\App\Http\Controllers\V1\AuthController::class, 'logout']);
    Route::apiResource('projects', \App\Http\Controllers\V1\ProjectController::class);
    Route::apiResource('clients', \App\Http\Controllers\V1\ClientController::class);
    Route::apiResource('project-timelogs', \App\Http\Controllers\V1\ProjectTimeLogController::class);
    Route::post('project-timelogs/{project_id}/start', [\App\Http\Controllers\V1\ProjectTimeLogController::class, 'start']);
    Route::post('project-timelogs/{project_id}/stop', [\App\Http\Controllers\V1\ProjectTimeLogController::class, 'stop']);
    Route::get('report', [\App\Http\Controllers\V1\ProjectTimeLogController::class, 'report']);
    Route::get('export-report', [\App\Http\Controllers\V1\ProjectTimeLogController::class, 'reportExport']);
});