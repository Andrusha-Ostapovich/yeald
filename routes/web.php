<?php

use App\Http\Controllers\SettingController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TrelloController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('set/telegram/webhook',[SettingController::class, 'setTelegramWebhook']);
Route::get('set/trello/webhook',[SettingController::class, 'setTrelloWebhook']);
Route::any('webhooks/telegram', [TelegramController::class, 'handle']);
Route::any('webhooks/trello', [TrelloController::class, 'handle']);
