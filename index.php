<?php
$token = "8086272992:AAGmcgdQmty3e6DQjzmYGKT5Fl68NBl_Mok";
$website = "https://api.telegram.org/bot".$token;

$update = json_decode(file_get_contents("php://input"), TRUE);
$chat_id = $update["message"]["chat"]["id"] ?? null;
$text = $update["message"]["text"] ?? null;

// Função para enviar mensagens
function sendMessage($chat_id, $text, $reply_markup = null){
    global $website;
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    file_get_contents($GLOBALS['website']."/sendMessage?".http_build_query($data));
}

// Função para criar teclado com botões
function getKeyboard($buttons){
    return [
        'keyboard' => $buttons,
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
}

// Teclado inicial
if($text == "/start"){
    $keyboard = getKeyboard([["📍 Consultar CEP"], ["🏢 Consultar CNPJ"]]);
    sendMessage($chat_id, "🔥 *Bem-vindo ao Bot de Consultas!* Escolha uma opção:", $keyboard);
}

// Consulta CEP
elseif(preg_match("/^\d{5}-?\d{3}$/", $text)){
    $cep = preg_replace("/[^0-9]/", "", $text);
    $json = file_get_contents("https://viacep.com.br/ws/$cep/json/");
    $data = json_decode($json, true);

    if(isset($data['erro'])){
        sendMessage($chat_id, "❌ CEP inválido ou não encontrado.");
    } else {
        $msg = "📍 *CEP:* ".$data['cep']."\n".
               "🏠 *Logradouro:* ".$data['logradouro']."\n".
               "🏘️ *Bairro:* ".$data['bairro']."\n".
               "🌆 *Cidade:* ".$data['localidade']."\n".
               "🏴 *UF:* ".$data['uf'];
        sendMessage($chat_id, $msg);
    }
}

// Consulta CNPJ
elseif(preg_match("/^\d{14}$/", preg_replace("/[^0-9]/", "", $text))){
    $cnpj = preg_replace("/[^0-9]/", "", $text);
    $json = file_get_contents("https://www.receitaws.com.br/v1/cnpj/$cnpj");
    $data = json_decode($json, true);

    if(isset($data['status']) && $data['status'] != "OK"){
        sendMessage($chat_id, "❌ CNPJ inválido ou não encontrado.");
    } else {
        $msg = "🏢 *Nome:* ".$data['nome']."\n".
               "📄 *CNPJ:* ".$data['cnpj']."\n".
               "🏢 *Fantasia:* ".$data['fantasia']."\n".
               "📍 *Logradouro:* ".$data['logradouro'].", ".$data['numero']."\n".
               "🏘️ *Bairro:* ".$data['bairro']."\n".
               "🌆 *Cidade/UF:* ".$data['municipio']."/".$data['uf']."\n".
               "📞 *Telefone:* ".$data['telefone']."\n".
               "💻 *Site:* ".$data['atividade_principal'][0]['text'];
        sendMessage($chat_id, $msg);
    }
}

// Mensagem caso não entenda
else{
    sendMessage($chat_id, "⚠️ Envie um CEP válido (00000-000) ou um CNPJ válido (14 números).");
}
?>