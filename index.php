<?php
$token = "8086272992:AAGmcgdQmty3e6DQjzmYGKT5Fl68NBl_Mok";
$website = "https://api.telegram.org/bot".$token;

$update = json_decode(file_get_contents("php://input"), TRUE);
$message = $update["message"] ?? null;
$callback = $update["callback_query"] ?? null;

$chat_id = $message["chat"]["id"] ?? $callback["message"]["chat"]["id"] ?? null;
$text = trim($message["text"] ?? "");
$data = $callback["data"] ?? null;
$message_id = $callback["message"]["message_id"] ?? null;

// Enviar mensagem normal
function sendMessage($chat_id, $text, $keyboard = null){
    global $website;
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if($keyboard) $params['reply_markup'] = json_encode($keyboard);
    file_get_contents($website."/sendMessage?".http_build_query($params));
}

// Editar mensagem existente
function editMessage($chat_id, $message_id, $text, $keyboard = null){
    global $website;
    $params = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if($keyboard) $params['reply_markup'] = json_encode($keyboard);
    file_get_contents($website."/editMessageText?".http_build_query($params));
}

// Enviar mensagem de "digitando"
function typing($chat_id){
    global $website;
    file_get_contents($website."/sendChatAction?chat_id=$chat_id&action=typing");
}

// -----------------------------
// /START
// -----------------------------
if($text == "/start"){
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "📊 Consultas", 'callback_data' => 'menu_consultas']]
        ]
    ];
    sendMessage($chat_id, "👋 *Bem-vindo ao Sanchez Search!*\n\nSelecione uma opção abaixo:", $keyboard);
}

// -----------------------------
// CALLBACKS
// -----------------------------
elseif($data){

    // Menu principal
    if($data == "menu_principal"){
        $keyboard = [
            'inline_keyboard' => [
                [['text' => "📊 Consultas", 'callback_data' => 'menu_consultas']]
            ]
        ];
        editMessage($chat_id, $message_id, "🏠 *Menu principal*\n\nEscolha uma das opções abaixo:", $keyboard);
    }

    // Menu de consultas
    elseif($data == "menu_consultas"){
        $keyboard = [
            'inline_keyboard' => [
                [['text' => "📍 Consultar CEP", 'callback_data' => 'exp_cep']],
                [['text' => "🏢 Consultar CNPJ", 'callback_data' => 'exp_cnpj']],
                [['text' => "🔙 Voltar", 'callback_data' => 'menu_principal']]
            ]
        ];
        editMessage($chat_id, $message_id, "🔎 *Consultas disponíveis:*\n\nEscolha o tipo de consulta que deseja realizar:", $keyboard);
    }

    // Explicação CEP
    elseif($data == "exp_cep"){
        $keyboard = [
            'inline_keyboard' => [
                [['text' => "🔙 Voltar", 'callback_data' => 'menu_consultas']]
            ]
        ];
        $msg = "📍 *Consulta de CEP*\n\nEnvie um CEP no formato `00000-000`.\nO sistema mostrará:\n- Logradouro\n- Bairro\n- Cidade\n- UF";
        editMessage($chat_id, $message_id, $msg, $keyboard);
    }

    // Explicação CNPJ
    elseif($data == "exp_cnpj"){
        $keyboard = [
            'inline_keyboard' => [
                [['text' => "🔙 Voltar", 'callback_data' => 'menu_consultas']]
            ]
        ];
        $msg = "🏢 *Consulta de CNPJ*\n\nEnvie o número do CNPJ (somente números, 14 dígitos).\nRetorna:\n- Nome\n- Fantasia\n- Endereço\n- Telefone\n- Atividade principal";
        editMessage($chat_id, $message_id, $msg, $keyboard);
    }

    // Confirmar callback
    file_get_contents($website."/answerCallbackQuery?callback_query_id=".$callback["id"]);
}

// -----------------------------
// CONSULTA CEP
// -----------------------------
elseif(preg_match("/^\d{5}-?\d{3}$/", $text)){
    typing($chat_id);
    $cep = preg_replace("/[^0-9]/", "", $text);
    $json = @file_get_contents("https://viacep.com.br/ws/$cep/json/");
    $data = json_decode($json, true);

    sleep(1);
    if(isset($data['erro'])){
        sendMessage($chat_id, "❌ *CEP inválido ou não encontrado.*");
    } else {
        $msg = "✅ *Resultado da consulta:*\n\n".
               "📍 *CEP:* `{$data['cep']}`\n".
               "🏠 *Logradouro:* {$data['logradouro']}\n".
               "🏘️ *Bairro:* {$data['bairro']}\n".
               "🌆 *Cidade:* {$data['localidade']}\n".
               "🏴 *UF:* {$data['uf']}";
        sendMessage($chat_id, $msg);
    }
}

// -----------------------------
// CONSULTA CNPJ
// -----------------------------
elseif(preg_match("/^\d{14}$/", preg_replace("/[^0-9]/", "", $text))){
    typing($chat_id);
    $cnpj = preg_replace("/[^0-9]/", "", $text);
    $json = @file_get_contents("https://www.receitaws.com.br/v1/cnpj/$cnpj");
    $data = json_decode($json, true);

    sleep(1);
    if(!isset($data['status']) || $data['status'] != "OK"){
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

// -----------------------------
// MENSAGEM PADRÃO
// -----------------------------
else{
    sendMessage($chat_id, "ℹ️ Envie /start para abrir o menu principal.");
}
?>