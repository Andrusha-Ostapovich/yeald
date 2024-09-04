<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class TelegramController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Telegram request: ', $request->all());

        $data = $request->all();

        if (Arr::has($data, 'message')) {

            $message = Arr::get($data, 'message');
            $chatId = Arr::get($message, 'chat.id');
            $firstName = Arr::get($message, 'from.first_name');
            $text = Arr::get($message, 'text', '');
            $chatType = Arr::get($message, 'chat.type');

            // Відповідати на команди в групах і приватних чатах
            if ($chatType === 'group' || $chatType === 'supergroup' || $chatType === 'private') {

                User::updateOrCreate(
                    ['telegram_id' => $chatId],
                );

                if ($text === '/start') {
                    $responseText = 'Привіт, ' . $firstName;
                } elseif (preg_match('/^\/connect\s+(.+)$/', $text, $matches)) {
                    $trelloUserName = $matches[1];
                    $responseText = $this->connectToTrello($trelloUserName, $data);
                } elseif (preg_match('/^\/connect/', $text)) {
                    $responseText = "Команда /connect не містить username параметру Trello";
                }

                if ($responseText) {
                    $this->sendMessage($chatId, $responseText);
                }
            }
        }
        if (Arr::has($data, 'callback_query')) {
            $callbackQuery = Arr::get($data, 'callback_query');

            $chatId = Arr::get($callbackQuery, 'message.chat.id');
            $dataClick = Arr::get($callbackQuery, 'data');

            $url = "https://api.trello.com/1/boards/" . config('services.trello.board') . "/lists";
            // Виконуємо GET-запит
            $response = Http::accept('application/json')
                ->get($url, $this->getConfig());


            // Перевіряємо, яка кнопка була натиснута
            if ($dataClick === 'button_clicked') {
                $url = "https://api.trello.com/1/lists/" . $response->json()[0]['id'] . "/cards";

                // Виконуємо GET-запит
                $response = Http::accept('application/json')
                    ->get($url, $this->getConfig());

                $tasks = $response->json();
                $message = "В процесі такі таски учасників:\n";

                // Отримуємо всіх користувачів з наявним telegram_id
                $telegramUsers = User::whereNotNull('telegram_id')->get();

                foreach ($tasks as $card) {
                    if (empty($card['idMembers'])) {
                        continue; // Пропускаємо картки без учасників
                    }

                    foreach ($card['idMembers'] as $memberId) {
                        // Знаходимо користувача серед тих, що мають trello_id
                        $user = $telegramUsers->firstWhere('trello_id', $memberId);

                        if ($user) {
                            $message .= "{$user->name}\nТаск: {$card['name']}\n";
                        } else {
                            $message .= "Не підключений до тг користувач :\n" . User::where('trello_id', $memberId)->first()->name . "\nТаски: {$card['name']}\n";
                        }
                    }
                }

                Log::info($user);
                $this->sendMessage($user->group_id ?? Arr::get($data, 'callback_query.from.id'), $message);
            }
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function connectToTrello($trelloUserName, $data)
    {
        $this->firstOrCreateUsers();
        // Перевірка, чи існує користувач з вказаною username
        $user = User::where('trello_username', $trelloUserName)->first();

        if ($user) {
            $user->update(['telegram_id' => Arr::get($data, 'message.from.id')]);
            return "Ви успішно підключились до Trello.";
        }
        return "Користувача з таким username не знайдено.";
    }

    private function sendMessage($chatId, $text)
    {
        Http::post('https://api.telegram.org/bot' . config('services.telegram.token') . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Переглянути звіт', 'callback_data' => 'button_clicked']
                ]
            ]
        ];

        Http::post("https://api.telegram.org/bot" . config('services.telegram.token') . "/sendMessage", [
            'chat_id' => $chatId,
            'text' => 'Кнопка перегляду звіту:',
            'reply_markup' => json_encode($keyboard)
        ]);
    }
    private function firstOrCreateUsers()
    {
        $url = 'https://api.trello.com/1/members/me/organizations';

        // Виконуємо GET запит
        $response = Http::get($url, $this->getConfig());
        $orId = $response->json()[0]['id'];

        $url = "https://api.trello.com/1/organizations/{$orId}/members";

        $response = Http::get($url, $this->getConfig());

        foreach ($response->json() as $user) {
            $users = User::firstOrCreate(
                ['trello_id' => $user['id']], // Унікальний ключ для знаходження чи створення
                [
                    'trello_username' => $user['username'] ?? null,
                    'name' => $user['fullName'] ?? null,
                    'trello_id' => $user['id'],
                ]
            );
        }
        return $users;
    }
    private function getConfig(): array
    {
        return [
            'key' => config('services.trello.key'),
            'token' => config('services.trello.token'),
        ];
    }
}
