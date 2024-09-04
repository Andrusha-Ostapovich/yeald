<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TrelloController extends Controller
{
    public function handle(Request $request)
    {
        Log::info($request->all());
        $webhookData = $request->all();

        $fullName = Arr::get($webhookData, 'action.memberCreator.fullName', '');
        $cardName = Arr::get($webhookData, 'action.data.card.name', '');
        $listBefore = Arr::get($webhookData, 'action.data.listBefore.name', '');
        $listAfter = Arr::get($webhookData, 'action.data.listAfter.name', '');

        Http::post('https://api.telegram.org/bot' . config('services.telegram.token') . '/sendMessage', [
            'chat_id' => '-1002153585701',
            'text' => "Користувач: {$fullName}
        Перемістив таск: {$cardName}
        Зі списку: {$listBefore}
        До списку: {$listAfter}."
        ]);
    }
}
