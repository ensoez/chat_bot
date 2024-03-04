<?php
$botToken = "7145128260:AAFIDnPRMYu6cbOEEX7GQElR8jLf-OysaMc";
$webhookUrl = "https://landing-ens.ru/chat/index2.php";


$content = file_get_contents("php://input");
$update = json_decode($content, true);

if ($update && isset($update['message']['text'])) {
    $chat_id = $update['message']['chat']['id'];
    $message_text = $update['message']['text'];

    if ($message_text == '/start') {
        sendWelcomeMessage($chat_id);
    } elseif ($message_text == '/interview') {
        startInterview($chat_id);
    } else {
        // Сохраняем ответ пользователя в файл admin.txt
        file_put_contents("admin.txt", "$chat_id: $message_text\n", FILE_APPEND);

        // Запускаем следующий вопрос после того, как ответ записан
        startNextQuestion($chat_id);
    }
}

function sendTelegramMessage($apiUrl, $postData) {
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode != 200) {
        error_log("Failed to send message: HTTP code $httpCode");
        return false;
    }

    return $response;
}

function sendWelcomeMessage($chat_id) {
    global $botToken;

    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $welcomeMessage = "Привет! Я бот HR компании. Мы ищем курьеров в Москве. Если вы готовы к собеседованию, нажмите или введите /interview";

    $postData = [
        'chat_id' => $chat_id,
        'text' => $welcomeMessage
    ];

    sendTelegramMessage($apiUrl, $postData);
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
        "Как Вас зовут?",
        "Какое у Вас гражданство?",
        "У вас есть велосипед?",
        "Введите свой номер телфона",
        "Введите свой номер телфона",

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
                if ($question_position < count($questions) -1 ) {
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


// Установка вебхука
$apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url={$webhookUrl}";
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
]);

$response = curl_exec($ch);
curl_close($ch);
echo $response;
?>  
