<?php 

require_once('/var/www/php_include/notifications.php');
set_error_handler('debug');

//Check if the request comes from telegram
$apikey = $_GET['apikey'];
if (hash('sha512',$apikey) == 'fd734360f59206824c1195471397e4ae634799fd33a5abf7da1043e4f6402726c43ef3e7de0cefc06219e2befc2a355fc57cd41463e506316c565f70b3f34f68') {
	define('API_KEY',$apikey);
} else {
	exit('We\'re done here Mr. '.$_SERVER['REMOTE_ADDR'].' '.$_SERVER['HTTP_X_FORWARDED_FOR']);
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (isset($update['message']['reply_to_message']['text']))
	$update['message']['text'] = $update['message']['text'].' '.$update['message']['reply_to_message']['text'];
if (isset($update['message']['text'])) {
	if ($update['message']['text'][0] == '/') {
		$str = str_replace('@hashmebot','', explode(" ",substr($update['message']['text'], 1)), $count)[0];
		$command = ($str[strlen($str) - 2] == 'v' ? substr_replace($str, ',', strlen($str) - 2, 1) : $str);
		$offset = strlen($command) + 2 + ($count == 0 ? 0 : 10);
		switch ($command) {
			case "start":
				sendMessage($update['message']['chat']['id'], "I am your fellow hash companion\nUse me inline, in private- or groupchats, I don't care...\nYou may also reply to a message with a hashing command and I will hash it for you :D\nHappy hashing and btw we don't store your texts in a rainbow table\n\nSource code: https://github.com/wjclub/telegram-bot-hashme");
				break;
			default:
				if (in_array($command, hash_algos())) {
					sendMessage($update['message']['chat']['id'], "<code>".hash($command, substr($update['message']['text'], $offset))."</code>");
				} else
					sendMessage($update['message']['chat']['id'], "Command $command not recognized...\nTry /help?");
				break;
		}
	}
} else if (isset($update['inline_query'])) {
	answerInlineQuery($update['inline_query']['id'], $update['inline_query']['query']);
}

function sendMessage($chat_id,$reply) {
	$reply_content = [
	'method' => "sendMessage",
	'chat_id' => $chat_id,
	'parse_mode' => 'HTML',
	'text' => $reply,
	];
	$reply_json = json_encode($reply_content);
//async request
	$url = 'https://api.telegram.org/bot'.API_KEY.'/';
	$cmd = "curl -s -X POST -H 'Content-Type:application/json'";
	$cmd.= " -d " . escapeshellarg($reply_json) . " '" . $url . "'";
	exec($cmd, $output, $exit);
}

function answerInlineQuery($query_id, $text) {
	$results = array();
	foreach (hash_algos() as $algo) {
		$hash = hash($algo, $text);
		$input_message_content = [
			'parse_mode' => 'HTML',
			'message_text' => $hash,
			'disable_web_page_preview' => true,
		];
		$results[] = [
			'type' => 'article',
			'id' => $query_id.$algo,
			'title' => $algo,
			'description' => $hash,
			'input_message_content' => $input_message_content,
		]; 
	}
	$reply_json = json_encode([
	'method' => 'answerInlineQuery',
	'inline_query_id' => $query_id,
	'results' => $results,
	'is_personal' => false,
	]);
	$url = 'https://api.telegram.org/bot'.API_KEY.'/';
	$cmd = "curl -s -X POST -H 'Content-Type:application/json'";
	$cmd.= " -d " . escapeshellarg($reply_json) . " '" . $url . "'";
	exec($cmd, $output, $exit);
}

?>
