<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Http;


class SettingController extends Controller
{
    public function setTelegramWebhook()
    {
        $url = 'https://api.telegram.org/bot' . config('services.telegram.token') . '/setWebhook';

        Http::post($url, [
            'url' => env('APP_URL') . '/webhooks/telegram',
        ]);
        $commands = [
            [
                'command' => 'connect',
                'description' => 'Введіть username id для приєднання акаунту до Trello'
            ],
            [
                'command' => 'start',
                'description' => 'Привітання'
            ],
        ];

        // Запит до Telegram API для встановлення команд
        Http::post("https://api.telegram.org/bot" . config('services.telegram.token') . "/setMyCommands", [
            'commands' => json_encode($commands),
        ]);
    }
    public function setTrelloWebhook()
    {
        $url = 'https://api.trello.com/1/members/me';
        $response = Http::get($url, $this->getConfig());


        $url = 'https://api.trello.com/1/webhooks/';
        $params = [
            'callbackURL' => env('APP_URL') . '/webhooks/trello', // URL для прослуховування вебхуку
            'idModel' => $response->json()['id'],
        ] + $this->getConfig();

        $response = Http::post($url, $params);

        dd($response->json());
    }
    private function getConfig(): array
    {
        return [
            'key' => config('services.trello.key'),
            'token' => config('services.trello.token'),
        ];
    }
}
