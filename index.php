<?php
$token = "8086272992:AAGmcgdQmty3e6DQjzmYGKT5Fl68NBl_Mok";
$website = "https://api.telegram.org/bot" . $token;

// Recebe atualização do Telegram
$update = json_decode(file_get_contents("php://input"), TRUE);
$message = $update["message"] ?? null;
$callback = $update["callback_query"] ?? null;

$chat_id = $message["chat"]["id"] ?? $callback["message"]["chat"]["id"] ?? null;
$text = trim($message["text"] ?? "");
$data = $callback["data"] ?? null;
$message_id = $callback["message"]["message_id"] ?? null;

// === Funções ===
function sendMessage($chat_id, $text, $keyboard = null) {
    global $website;
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    file_get_contents($website . "/sendMessage?" . http_build_query($params));
}

function editMessage($chat_id, $message_id, $text, $keyboard = null) {
    global $website;
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    file_get_contents($website . "/editMessageText?" . http_build_query($params));
}

function typing($chat_id) {
    global $website;
    file_get_contents($website . "/sendChatAction?chat_id=$chat_id&action=typing");
}

// === /START ===
if ($text == "/start") {
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "📊 Consultas", 'callback_data' => 'menu_consultas']]
        ]
    ];
    sendMessage($chat_id, "👋 *Bem-vindo ao Sanchez Search!*\n\nEscolha uma opção abaixo:", $keyboard);
}

// === CALLBACKS ===
elseif ($data) {
    switch ($data) {
        case "menu_principal":
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "📊 Consultas", 'callback_data' => 'menu_consultas']]
                ]
            ];
            editMessage($chat_id, $message_id, "🏠 *Menu principal*\n\nEscolha uma das opções abaixo:", $keyboard);
            break;

        case "menu_consultas":
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "📍 Consultar CEP", 'callback_data' => 'exp_cep']],
                    [['text' => "🏢 Consultar CNPJ", 'callback_data' => 'exp_cnpj']],
                    [['text' => "🔙 Voltar", 'callback_data' => 'menu_principal']]
                ]
            ];
            editMessage($chat_id, $message_id, "🔎 *Consultas disponíveis:*\n\nEscolha o tipo de consulta:", $keyboard);
            break;

        case "exp_cep":
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "🔙 Voltar", 'callback_data' => 'menu_consultas']]
                ]
            ];
            editMessage($chat_id, $message_id, "📍 *Consulta de CEP*\n\nUse o comando:\n`/cep 00000-000`\n\nExemplo:\n`/cep 30140071`\n\nRetorna logradouro, bairro, cidade e UF.", $keyboard);
            break;

        case "exp_cnpj":
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "🔙 Voltar", 'callback_data' => 'menu_consultas']]
                ]
            ];
            editMessage($chat_id, $message_id, "🏢 *Consulta de CNPJ*\n\nUse o comando:\n`/cnpj 00000000000000`\n\nExemplo:\n`/cnpj 19131243000197`\n\nRetorna nome, fantasia, endereço, telefone e atividade principal.", $keyboard);
            break;
    }
    echo json_encode(['ok' => true]);
    exit;
}

// === /CEP ===
elseif (preg_match("/^\/cep\s+(\d{5}-?\d{3})$/", $text, $m)) {
    typing($chat_id);
    $cep = preg_replace("/[^0-9]/", "", $m[1]);
    $json = @file_get_contents("https://viacep.com.br/ws/$cep/json/");
    $data = json_decode($json, true);

    sleep(1);
    if (isset($data['erro'])) {
        sendMessage($chat_id, "❌ *CEP inválido ou não encontrado.*");
    } else {
        $msg = "✅ *Resultado da consulta de CEP:*\n\n".
               "📍 *CEP:* `{$data['cep']}`\n".
               "🏠 *Logradouro:* {$data['logradouro']}\n".
               "🏘️ *Bairro:* {$data['bairro']}\n".
               "🌆 *Cidade:* {$data['localidade']}\n".
               "🏴 *UF:* {$data['uf']}";
        sendMessage($chat_id, $msg);
    }
}

// === /CNPJ ===
elseif (preg_match("/^\/cnpj\s+(\d{14})$/", $text, $m)) {
    typing($chat_id);
    $cnpj = $m[1];
    $json = @file_get_contents("https://www.receitaws.com.br/v1/cnpj/$cnpj");
    $data = json_decode($json, true);

    sleep(1);
    if (!isset($data['status']) || $data['status'] != "OK") {
        sendMessage($chat_id, "❌ *CNPJ inválido ou não encontrado.*");
    } else {
        $msg = "✅ *Resultado da consulta de CNPJ:*\n\n".
               "🏢 *Nome:* {$data['nome']}\n".
               "💼 *Fantasia:* {$data['fantasia']}\n".
               "🧾 *CNPJ:* `{$data['cnpj']}`\n".
               "📍 *Endereço:* {$data['logradouro']}, {$data['numero']} - {$data['bairro']}\n".
               "🌆 *Cidade/UF:* {$data['municipio']}/{$data['uf']}\n".
               "📞 *Telefone:* {$data['telefone']}\n".
               "💻 *Atividade:* {$data['atividade_principal'][0]['text']}";
        sendMessage($chat_id, $msg);
    }
}

// === DEFAULT ===
else {
    sendMessage($chat_id, "⚙️ *Comando não reconhecido.*\n\nUse /start para abrir o menu.");
}
?>