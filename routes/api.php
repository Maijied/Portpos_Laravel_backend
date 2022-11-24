<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderManagementController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('signup',[AuthController::class,'signup']);
Route::post('login', [AuthController::class,'login']);
Route::group(['middleware' => 'api'], function ($router) {
    Route::post('logout', [AuthController::class,'logout']);
    Route::get('get-order', [OrderManagementController::class,'getOrder']);
    Route::post('create-order', [OrderManagementController::class,'createOrder']);
    Route::post('update-order-status', [OrderManagementController::class,'updateOrderStatus']);
    Route::post('refund-request', [OrderManagementController::class,'refundRequest']);
    Route::get('get-refund-request-data', [OrderManagementController::class,'getRefundRequestData']);

});
