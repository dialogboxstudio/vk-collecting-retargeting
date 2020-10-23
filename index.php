<?php
require_once __DIR__.'/vendor/autoload.php';

use DialogBoxStudio\VkCollectingRetargeting\Retargeting;
use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\ErrorHandler;
use DigitalStar\vk_api\vk_api;
use DigitalStar\vk_api\VkApiException;

Dotenv::createImmutable(__DIR__, '/config/.env')->load();
$logger = new Logger('vk-collecting-retargeting: ');
$logger->pushHandler(new StreamHandler(__DIR__ . '/log/vk-collecting-retargeting.log'));
ErrorHandler::register($logger);

try {
    $vk = vk_api::create($_ENV['VK_ACCESS_TOKEN'], $_ENV['VK_VERSION_API'])->setConfirm($_ENV['VK_CONFIRM']);
} catch (VkApiException $e) {
    $logger->critical($e);
    exit();
}

$data = $vk->initVars($id, $message, $payload, $user_id, $type);

$client = new Google_Client([
    'credentials' => __DIR__.'/config/google.json',
    'scopes' => 'https://www.googleapis.com/auth/spreadsheets'
]);
$service = new Google_Service_Sheets($client);

$retargeting =  new Retargeting($user_id, $data, $vk);
$answer = $retargeting->getAnswer();

foreach ($answer['params'] as $item => $record) {
    $body = new Google_Service_Sheets_ValueRange( [ 'values' => $record] );
    $options = array( 'valueInputOption' => 'RAW' );
    $service->spreadsheets_values->update( $_ENV['GOOGLE_SPREAD_SHEET_ID'], 'Пользователи!A'.($answer['count']+1), $body, $options );
}

foreach ($answer['text'] as $item => $text) {
    try {
        $vk->sendMessage($user_id, $text);
    } catch (VkApiException $e) {
        $logger->critical($e);
        exit();
    }
}
