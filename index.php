<?php

/*
Copyright 2019 FiveYellowMice

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

require_once 'vendor/autoload.php';
require 'config.php';

function send_api_request($method, $params) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/'.$method);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json; charset=utf-8' ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
  if (array_key_exists('http_proxy', $_SERVER)) {
    curl_setopt($ch, CURLOPT_PROXY, $_SERVER['http_proxy']);
  }
  $inline_query = json_decode(curl_exec($ch), true);
  curl_close($ch);

  if (!$inline_query) {
    return false;
  }

  if (!array_key_exists('result', $inline_query)) {
    if (array_key_exists('description', $inline_query)) {
      trigger_error('Telegram API error: '.$inline_query['description']);
    }
    return false;
  }

  $result = $inline_query['result'];
  return $result;
}


if (!in_array($_SERVER['REQUEST_METHOD'], ['POST'])) {
  http_response_code(405);
  echo "Unaccepted request method.\n";
  die();
}

if (@$_GET['token'] !== WEBHOOK_TOKEN) {
  http_response_code(403);
  echo "Invalid token.\n";
  die();
}

$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
  http_response_code(400);
  echo "Improper JSON received.\n";
  die();
}

http_response_code(204);

$message = $update['message'] ?? die();
$chat_id = $message['chat']['id'] ?? die();
if (!is_numeric($chat_id)) die();
$text = $message['text'] ?? '';

preg_match('/^\/(\w+)(?:'.TELEGRAM_USERNAME.')?(?: (.*)|$)/', $text, $matches);
$command = $matches[1] ?? null;
$command_args = $matches[2] ?? '';
unset($matches);

// Check if chat is in tracking opt-out list
$opt_out_list_file = fopen(__DIR__.'/data/tracking_opt_out.json', 'c+');
flock($opt_out_list_file, LOCK_SH);
$opt_out_list = json_decode(fgets($opt_out_list_file), true);
$tracking_disabled = $opt_out_list[$chat_id] ?? false;
flock($opt_out_list_file, LOCK_UN);
fclose($opt_out_list_file);

$tracker = new PiwikTracker(3, "https://matomo.fiveyellowmice.com/");
$tracker->disableSendImageResponse();
$tracker->setTokenAuth(MATOMO_TOKEN);
$tracker->setUrl('/');
$tracker->setIp('0.0.0.0');
$tracker->setUserId($chat_id);
$tracker->setBrowserLanguage($message['user']['language_code'] ?? null);

if ($command == 'start') {
  if ($command_args == 'u') { // User is from Twitter
    $tracker->setAttributionInfo(json_encode(['Twitter', '', 0, '']));
  }
  $button_don = null;
  $button_katsu = null;
  switch ($command_args) {
    case 'どん かつ':
      $button_don = 'どん';
      $button_katsu = 'かつ';
      break;
    case 'ドン カツ':
      $button_don = 'ドン';
      $button_katsu = 'カツ';
      break;
    case 'don katsu':
      $button_don = 'don';
      $button_katsu = 'katsu';
      break;
    case '咚 咔';
      $button_don = '咚';
      $button_katsu = '咔';
      break;
    case '🔴 🔵';
      $button_don = '🔴';
      $button_katsu = '🔵';
      break;
  }
  if (is_null($button_don) || is_null($button_katsu)) {
    send_api_request('sendMessage', [
      'chat_id' => $chat_id,
      'text' => "ボタンスタイルを選ぶドン：\nChoose a button style:",
      'reply_markup' => [
        'keyboard' => [
          [['text' => '/start どん かつ'], ['text' => '/start ドン カツ']],
          [['text' => '/start don katsu'], ['text' => '/start 咚 咔']],
          [['text' => '/end'], ['text' => '/start 🔴 🔵']],
        ],
        'resize_keyboard' => true,
      ],
    ]);
    if (!$tracking_disabled) {
      $tracker->doTrackEvent('Bot', 'Start');
    }
  } else {
    send_api_request('sendMessage', [
      'chat_id' => $chat_id,
      'text' => "さあ、始まるドン！",
      'reply_markup' => [
        'keyboard' => [
          [
            ['text' => $button_katsu],
            ['text' => $button_don],
            ['text' => $button_don],
            ['text' => $button_katsu],
          ],
          [
            ['text' => '/end'],
            ['text' => '???'],
            ['text' => '(・▽・)'],
            ['text' => '!!!'],
          ],
        ],
      ],
    ]);
    if (!$tracking_disabled) {
      $tracker->doTrackEvent('Game', 'Start', $command_args);
    }
  }
} elseif ($command == 'end') {
  send_api_request('sendMessage', [
    'chat_id' => $chat_id,
    'text' => "上手に演奏できたドン！",
    'reply_markup' => [
      'remove_keyboard' => true,
    ],
  ]);
  if (!$tracking_disabled) {
    $tracker->doTrackEvent('Game', 'End');
  }
} elseif ($command == 'help') {
  send_api_request('sendMessage', [
    'chat_id' => $chat_id,
    'text' =>
      "太鼓の達人練習ボット vundefined (<a href=\"https://gist.github.com/FiveYellowMice/eee06bfe61ddcdd8576692a46bfe23db\">code</a>)\n".
      "Send /start and /end to start and end the game. Send 'どん', 'かつ', 'か', 'ドン', 'カツ', 'カ', 'don', 'katsu', 'ka', '咚', '咔', '🔴', '🔵' to play.\n".
      "Usage of this bot will be tracked, you can send /tracking_opt_out and /tracking_opt_in to disable and enable tracking.\n".
      "Inspired by <a href=\"https://t.me/yingyoushadiao/3149\">this screenshot</a>. Made with 🥁 by @FiveYellowMice.",
    'parse_mode' => 'HTML',
    'disable_web_page_preview' => true,
  ]);
  if (!$tracking_disabled) {
    $tracker->doTrackEvent('Bot', 'Print Help');
  }
} elseif ($command == 'tracking_opt_out' || $command == 'tracking_opt_in') {
  $opt_out_list_file = fopen(__DIR__.'/data/tracking_opt_out.json', 'c+');
  flock($opt_out_list_file, LOCK_EX);
  $opt_out_list = json_decode(fgets($opt_out_list_file), true);
  $opt_out_list[$chat_id] = $command == 'tracking_opt_out';
  fseek($opt_out_list_file, 0);
  ftruncate($opt_out_list_file, 0);
  fwrite($opt_out_list_file, json_encode($opt_out_list));
  flock($opt_out_list_file, LOCK_UN);
  fclose($opt_out_list_file);
  send_api_request('sendMessage', [
    'chat_id' => $chat_id,
    'text' => 'Tracking has been '.($command == 'tracking_opt_out' ? 'disabled' : 'enabled').'.'
  ]);
} elseif (in_array(strtolower($text), ['どん', 'かつ', 'か', 'ドン', 'カツ', 'カ', 'don', 'katsu', 'ka', '咚', '咔', '🔴', '🔵'])) {
  $combo = apcu_inc('taiko_practice_bot.user_states.'.$chat_id.'.combo', 1, $inc_success, 120);
  if (rand(0, 2) === 0) {
    $hit_result = "可";
  } else {
    $hit_result = "良";
  }
  if ($combo > 0 && $combo % 50 == 0) {
    $hit_result = $hit_result.'  <b>'.$combo." 連打！</b>";
  }
  send_api_request('sendMessage', [
    'chat_id' => $chat_id,
    'text' => $hit_result,
    'parse_mode' => 'HTML',
  ]);
  if (!$tracking_disabled) {
    $tracker->doTrackEvent('Game', 'Hit', strtolower($text));
  }
} elseif (strpos($text, '干') === 0 || strpos($text, '幹') === 0) {
  send_api_request('sendMessage', [
    'chat_id' => $chat_id,
    'text' => "幹你娘",
  ]);
  if (!$tracking_disabled) {
    $tracker->doTrackEvent('Game', 'Miss', str_replace("\n", ' ', $text));
  }
} else {
  apcu_delete('taiko_practice_bot.user_states.'.$chat_id.'.combo');
  send_api_request('sendMessage', [
    'chat_id' => $chat_id,
    'text' => "不可",
  ]);
  if (!$tracking_disabled) {
    $tracker->doTrackEvent('Game', 'Miss', str_replace("\n", ' ', $text));
  }
}
