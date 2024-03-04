<?php
$botToken = "6754177877:AAGX4XKGq4zt4kD6gFUhzNt8lGWQk0tU0PA";
$webhookUrl = "https://landing-ens.ru/chat/index.php";


// Устанавливаем вебхук
$response = file_get_contents("https://api.telegram.org/bot{$botToken}/setWebhook?url={$webhookUrl}");
echo $response;

// Ваш остальной код
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if ($update && isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $message_text = $update['message']['text'];

    switch ($message_text) {
        case '/start':
            sendWelcomeMessage($chat_id);
            break;
        case 'Все заявки':
            sendApplicationsMenu($chat_id);
            break;
        case 'Последние заявки за ближайший час':
            sendRecentApplications($chat_id);
            break;
        default:
            saveApplication($chat_id, $message_text);
            startInterview($chat_id);
            break;
    }
}

// Обработка нажатий на кнопки
if ($update && isset($update['callback_query']['data'])) {
    $chat_id = $update['callback_query']['message']['chat']['id'];
    $callback_data = $update['callback_query']['data'];

    // Обработка нажатий на кнопки
    if (strpos($callback_data, 'application_') === 0) {
        $groupKey = substr($callback_data, 12);
        $applicationContent = getApplicationContent($groupKey);
        sendApplicationDetails($chat_id, $groupKey, $applicationContent);
    }
}

function sendWelcomeMessage($chat_id) {
    global $botToken;

    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $welcomeMessage = "Привет! Я бот HR компании. Что желаете просмотреть?";

    $keyboard = [
        'keyboard' => [
            [['text' => 'Все заявки']],
            [['text' => 'Последние заявки за ближайший час']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
    $encodedKeyboard = json_encode($keyboard);

    $postData = [
        'chat_id' => $chat_id,
        'text' => $welcomeMessage,
        'reply_markup' => $encodedKeyboard
    ];

    sendRequest($apiUrl, $postData);
}

function sendApplicationsMenu($chat_id) {
    global $botToken;

    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $applicationsMenuMessage = "Выберите заявку для просмотра:";

    // Прочитать содержимое файла admin.txt
    $lines = file("admin.txt");

    // Создаем массив для хранения группированных заявок
    $groupedApplications = [];

    // Группируем заявки по первым 8 символам
    foreach ($lines as $line) {
        $key = substr($line, 0, 8);
        $groupedApplications[$key][] = $line;
    }

    // Формируем кнопки на основе группировки
    $keyboard = ['inline_keyboard' => []];
    $counter = 1;
    foreach ($groupedApplications as $groupKey => $groupedApplication) {
        $buttonText = "Заявка номер $counter";
        $callbackData = "application_$groupKey"; // Можно изменить формат, если нужно
        $keyboard['inline_keyboard'][] = [['text' => $buttonText, 'callback_data' => $callbackData]];
        $counter++;
    }

    $encodedKeyboard = json_encode($keyboard);

    $postData = [
        'chat_id' => $chat_id,
        'text' => $applicationsMenuMessage,
        'reply_markup' => $encodedKeyboard
    ];

    sendRequest($apiUrl, $postData);
}


function sendRecentApplications($chat_id) {
    global $botToken;

    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $recentApplicationsMessage = "Эта функциия на данный момент в разработке";

    $postData = [
        'chat_id' => $chat_id,
        'text' => $recentApplicationsMessage
    ];

    sendRequest($apiUrl, $postData);
}

function saveApplication($chat_id, $message_text) {
    // Сохраняем ответ пользователя в файл admin.txt
    file_put_contents("admin.txt", "$chat_id: $message_text\n", FILE_APPEND);
}

function startInterview($chat_id) {
    global $botToken;

    // Устанавливаем стартовую позицию вопросов
    file_put_contents("question_position.txt", "0");

    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

    // Запускаем первый вопрос
    startNextQuestion($chat_id);
}

function startNextQuestion($chat_id) {
    global $botToken;

    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

    // Читаем текущую позицию вопроса
    $question_position = (int) file_get_contents("question_position.txt");

    $questions = array(
        "Расскажите о вашем опыте работы курьером?",
        "Как вы оцениваете свои коммуникативные навыки?",
        "Как вы справляетесь с задачами в условиях строгого временного ограничения?",
        "Что вы ожидаете от работы в нашей компании?"
    );

    if ($question_position < count($questions)) {
        // Если это первый вопрос, отправляем его сразу
        if ($question_position === 0) {
            $postData = [
                'chat_id' => $chat_id,
                'text' => $questions[$question_position]
            ];
            sendTelegramMessage($apiUrl, $postData);

            // Увеличиваем позицию вопроса
            $question_position++;
            file_put_contents("question_position.txt", $question_position);
        } else {
            // Проверяем, был ли получен ответ на предыдущий вопрос
            $answer_received = checkAnswerReceived();

            if ($answer_received) {
                // Отправляем следующий вопрос и увеличиваем позицию
                if ($question_position < count($questions) - 1) {
                    $postData = [
                        'chat_id' => $chat_id,
                        'text' => $questions[$question_position]
                    ];

                    sendTelegramMessage($apiUrl, $postData);

                    // Увеличиваем позицию вопроса
                    $question_position++;

                    // Записываем новую позицию в файл
                    file_put_contents("question_position.txt", $question_position);
                } else {
                    // Это последний вопрос, отправляем благодарственное сообщение
                    $thanksMessage = "Спасибо за ответы на вопросы. Мы свяжемся с вами в ближайшее время.";
                    $postData = [
                        'chat_id' => $chat_id,
                        'text' => $thanksMessage
                    ];

                    sendTelegramMessage($apiUrl, $postData);

                    // Сохраняем все заявки в admin2.txt
                    $applicationsContent = file_get_contents("admin.txt");
                    file_put_contents("admin2.txt", $applicationsContent, FILE_APPEND);
                    // Очищаем файл admin.txt после сохранения в admin2.txt
                    file_put_contents("admin.txt", "");
                }
            }
        }
    }
}


function checkAnswerReceived() {
    // Проверяем наличие файла admin.txt
    if (file_exists("admin.txt")) {
        // Если файл не пустой, значит, ответ получен
        $content = trim(file_get_contents("admin.txt"));
        return !empty($content);
    } else {
        return false;
    }
}

function getApplicationContent($groupKey) {
    // Прочитать содержимое файла admin.txt
    $lines = file("admin.txt");
    $applications = [];

    // Переменная для отслеживания номера строки
    $lineNumber = 1;

    // Перебрать все строки и найти те, что соответствуют groupKey
    foreach ($lines as $line) {
        if (strpos($line, $groupKey) === 0) {
            // Заменяем цифры до ":" на слово "привет" в первой строке
            if ($lineNumber == 1) {
                $line = preg_replace('/^\d+:/u', 'Имя:', $line);
            }
            // Заменяем цифры до ":" на слово "пока" во второй строке
            elseif ($lineNumber == 2) {
                $line = preg_replace('/^\d+:/u', 'Гражданство:', $line);
            }
            // Заменяем цифры до ":" на слово "возраст" в третьей строке
            elseif ($lineNumber == 3) {
                $line = preg_replace('/^\d+:/u', 'Наличие велосипеда:', $line);
            }
            // Заменяем цифры до ":" на слово "вторник" в четвертой строке
            elseif ($lineNumber == 4) {
                $line = preg_replace('/^\d+:/u', 'Номер телефона:', $line);
            }
            $applications[] = $line;
            $lineNumber++;
        }
    }

    // Объединить данные в строку
    $applicationContent = implode("\n", $applications);

    return $applicationContent;
}

function sendApplicationDetails($chat_id, $groupKey, $applicationContent) {
    global $botToken;

    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $applicationDetails = "Детали заявки с префиксом $groupKey:\n\n$applicationContent";

    $postData = [
        'chat_id' => $chat_id,
        'text' => $applicationDetails
    ];

    sendRequest($apiUrl, $postData);
}

function sendRequest($url, $data) {
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    echo $response;
}
?>
