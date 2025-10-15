<?php
$token = "8086272992:AAGmcgdQmty3e6DQjzmYGKT5Fl68NBl_Mok";
$website = "https://api.telegram.org/bot".$token;

$update = json_decode(file_get_contents("php://input"), TRUE);
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text = trim($update["message"]["text"] ?? "");

// Função genérica de envio
function sendMessage($chat_id, $text, $reply_markup = null){
    global $website;
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    file_get_contents($website."/sendMessage?".http_build_query($data));
}

function getKeyboard($buttons){
    return [
        'keyboard' => $buttons,
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
}

// MENU PRINCIPAL
if ($text == "/start" || $text == "🔙 Voltar ao menu") {
    $keyboard = getKeyboard([
        ["📊 Consultas"],
    ]);
    sendMessage($chat_id, "👋 *Bem-vindo ao Sanchez Search!*\n\nEscolha uma das opções abaixo para começar:", $keyboard);
}

// SUBMENU CONSULTAS
elseif ($text == "📊 Consultas") {
    $keyboard = getKeyboard([
        ["📍 Consultar CEP", "🏢 Consultar CNPJ"],
        ["🔙 Voltar ao menu"]
    ]);
    sendMessage($chat_id, "🔎 *Escolha o tipo de consulta que deseja realizar:*", $keyboard);
}

// EXPLICA CONSULTA CEP
elseif ($text == "📍 Consultar CEP" || $text == "/cep") {
    $keyboard = getKeyboard([["🔙 Voltar ao menu"]]);
    $msg = "📦 *Consulta de CEP*\n\nDigite o CEP no formato `00000-000` para descobrir:\n- Logradouro\n- Bairro\n- Cidade\n- UF\n\n_Envie agora o CEP que deseja consultar._";
    sendMessage($chat_id, $msg, $keyboard);
}

// EXPLICA CONSULTA CNPJ
elseif ($text == "🏢 Consultar CNPJ" || $text == "/cnpj") {
    $keyboard = getKeyboard([["🔙 Voltar ao menu"]]);
    $msg = "🏢 *Consulta de CNPJ*\n\nEnvie o número do CNPJ com 14 dígitos (somente números).\nO sistema retornará:\n- Nome e Fantasia\n- Endereço\n- Telefone e Atividade principal";
    sendMessage($chat_id, $msg, $keyboard);
}

// CONSULTA CEP REAL
elseif (preg_match("/^\d{5}-?\d{3}$/", $text)) {
    $cep = preg_replace("/[^0-9]/", "", $text);
    $json = file_get_contents("https://viacep.com.br/ws/$cep/json/");
    $data = json_decode($json, true);

    if (isset($data['erro'])) {
        sendMessage($chat_id, "❌ *CEP inválido ou não encontrado.*\n\nTente novamente com outro número.");
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

// CONSULTA CNPJ REAL
elseif (preg_match("/^\d{14}$/", preg_replace("/[^0-9]/", "", $text))) {
    $cnpj = preg_replace("/[^0-9]/", "", $text);
    $json = @file_get_contents("https://www.receitaws.com.br/v1/cnpj/$cnpj");
    $data = json_decode($json, true);

    if (!isset($data['status']) || $data['status'] != "OK") {
        sendMessage($chat_id, "❌ *CNPJ inválido ou não encontrado.*\n\nVerifique se o número está correto.");
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

// QUALQUER OUTRO TEXTO
else {
    sendMessage($chat_id, "ℹ️ Não entendi...\n\nUse /start para voltar ao menu principal.");
}
?>