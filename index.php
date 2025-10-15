<?php
$token = "8086272992:AAGmcgdQmty3e6DQjzmYGKT5Fl68NBl_Mok";
$website = "https://api.telegram.org/bot" . $token;

$update = json_decode(file_get_contents("php://input"), TRUE);
$message = $update["message"] ?? null;
$callback = $update["callback_query"] ?? null;

$chat_id = $message["chat"]["id"] ?? $callback["message"]["chat"]["id"] ?? null;
$text = trim($message["text"] ?? "");
$data = $callback["data"] ?? null;
$message_id = $callback["message"]["message_id"] ?? null;

// === FUNÇÕES ===
function sendMessage($chat_id, $text, $keyboard = null) {
    global $website;
    $params = ['chat_id'=>$chat_id, 'text'=>$text, 'parse_mode'=>'Markdown'];
    if($keyboard) $params['reply_markup'] = json_encode($keyboard);
    $res = file_get_contents($GLOBALS['website']."/sendMessage?".http_build_query($params));
    return json_decode($res,true);
}

function editMessage($chat_id, $message_id, $text, $keyboard = null) {
    global $website;
    $params = ['chat_id'=>$chat_id,'message_id'=>$message_id,'text'=>$text,'parse_mode'=>'Markdown'];
    if($keyboard) $params['reply_markup'] = json_encode($keyboard);
    file_get_contents($GLOBALS['website']."/editMessageText?".http_build_query($params));
}

function typing($chat_id) {
    global $website;
    file_get_contents($GLOBALS['website']."/sendChatAction?chat_id=$chat_id&action=typing");
}

function animateMessage($chat_id, $msg_id, $steps, $delay=1){
    foreach($steps as $s){
        editMessage($chat_id,$msg_id,$s);
        sleep($delay);
    }
}

function sendTxtFile($chat_id, $filename, $content){
    global $website;
    file_put_contents($filename, $content);
    $data = [
        'chat_id' => $chat_id,
        'document' => new CURLFile($filename)
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $GLOBALS['website']."/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
    unlink($filename); // remove arquivo após envio
}

// === /START ===
if($text=="/start"){
    $keyboard=[
        'inline_keyboard'=>[
            [['text'=>"📊 Consultas",'callback_data'=>'menu_consultas']],
            [['text'=>"💰 Planos",'callback_data'=>'menu_planos']]
        ]
    ];
    sendMessage($chat_id,"👋 *Bem-vindo ao Sanchez Search!*\n🔍 *A busca inteligente que não dorme.*\n\nEscolha uma opção abaixo:",$keyboard);
}

// === CALLBACKS ===
elseif($data){
    switch($data){
        case "menu_principal":
            $keyboard=[['inline_keyboard'=>[[['text'=>"📊 Consultas",'callback_data'=>'menu_consultas']],
                                            [['text'=>"💰 Planos",'callback_data'=>'menu_planos']]]];
            editMessage($chat_id,$message_id,"🏠 *Menu principal*\n🔹 *Sanchez Search*", $keyboard);
            break;
        case "menu_consultas":
            $keyboard=[['inline_keyboard'=>[[['text'=>"📍 Consultar CEP",'callback_data'=>'exp_cep']],
                                            [['text'=>"🏢 Consultar CNPJ",'callback_data'=>'exp_cnpj']],
                                            [['text'=>"🔙 Voltar",'callback_data'=>'menu_principal']]]];
            editMessage($chat_id,$message_id,"🔎 *Consultas disponíveis:*\nEscolha o tipo de consulta:",$keyboard);
            break;
        case "menu_planos":
            $keyboard=[['inline_keyboard'=>[[['text'=>"🔙 Voltar",'callback_data'=>'menu_principal']]]]];
            $planos="💰 *Nossos Planos*\n\n".
                    "🟢 *Plano Curioso* — R\$7,90/mês\n➡️ 100 consultas.\n\n".
                    "🔵 *Plano Veloz* — R\$19,90/mês\n➡️ 500 consultas + prioridade.\n\n".
                    "👑 *Plano Supremo* — R\$39,90/mês\n➡️ Consultas ilimitadas + suporte VIP.\n\n".
                    "_Escolha o plano ideal e consulte sem limites!_\n\n🔹 _powered by Sanchez Search_";
            editMessage($chat_id,$message_id,$planos,$keyboard);
            break;
        case "exp_cep":
            $keyboard=[['inline_keyboard'=>[[['text'=>"🔙 Voltar",'callback_data'=>'menu_consultas']]]]];
            editMessage($chat_id,$message_id,"📍 *Consulta de CEP*\nUse o comando:\n`/cep 00000-000`\n\nExemplo:\n`/cep 30140071`\n\nRetorna logradouro, bairro, cidade e UF.\n\n🔹 _powered by Sanchez Search_",$keyboard);
            break;
        case "exp_cnpj":
            $keyboard=[['inline_keyboard'=>[[['text'=>"🔙 Voltar",'callback_data'=>'menu_consultas']]]]];
            editMessage($chat_id,$message_id,"🏢 *Consulta de CNPJ*\nUse o comando:\n`/cnpj 00000000000000`\n\nExemplo:\n`/cnpj 19131243000197`\n\nRetorna nome, fantasia, endereço, telefone e atividade principal.\n\n🔹 _powered by Sanchez Search_",$keyboard);
            break;
    }
    echo json_encode(['ok'=>true]);
    exit;
}

// === /CEP com txt ===
elseif(preg_match("/^\/cep\s+(\d{5}-?\d{3})$/",$text,$m)){
    $cep=preg_replace("/[^0-9]/","",$m[1]);
    $msg=sendMessage($chat_id,"*💭 Consultando...*");
    $msg_id=$msg['result']['message_id'];

    animateMessage($chat_id,$msg_id,["💭 Consultando...","📂 Lendo base de dados...","✅ Resultado pronto!"],1);

    $json=@file_get_contents("https://viacep.com.br/ws/$cep/json/");
    $data=json_decode($json,true);

    if(isset($data['erro'])){
        editMessage($chat_id,$msg_id,"❌ *CEP inválido ou não encontrado.*");
    }else{
        $txt="✅ Resultado da consulta de CEP:\n\n".
             "CEP: {$data['cep']}\n".
             "Logradouro: {$data['logradouro']}\n".
             "Bairro: {$data['bairro']}\n".
             "Cidade: {$data['localidade']}\n".
             "UF: {$data['uf']}\n\n🔹 _powered by Sanchez Search_";

        $filename="cep_{$cep}.txt";
        sendTxtFile($chat_id,$filename,$txt);
        editMessage($chat_id,$msg_id,"✅ *Consulta finalizada!* Arquivo enviado em anexo 📄");
    }
}

// === /CNPJ com txt ===
elseif(preg_match("/^\/cnpj\s+(\d{14})$/",$text,$m)){
    $cnpj=$m[1];
    $msg=sendMessage($chat_id,"*💭 Consultando...*");
    $msg_id=$msg['result']['message_id'];

    animateMessage($chat_id,$msg_id,["💭 Consultando...","📂 Lendo base da Receita Federal...","✅ Resultado pronto!"],1);

    $json=@file_get_contents("https://www.receitaws.com.br/v1/cnpj/$cnpj");
    $data=json_decode($json,true);

    if(!isset($data['status']) || $data['status']!="OK"){
        editMessage($chat_id,$msg_id,"❌ *CNPJ inválido ou não encontrado.*");
    }else{
        $txt="✅ Resultado da consulta de CNPJ:\n\n".
             "Nome: {$data['nome']}\n".
             "Fantasia: {$data['fantasia']}\n".
             "CNPJ: {$data['cnpj']}\n".
             "Endereço: {$data['logradouro']}, {$data['numero']} - {$data['bairro']}\n".
             "Cidade/UF: {$data['municipio']}/{$data['uf']}\n".
             "Telefone: {$data['telefone']}\n".
             "Atividade: {$data['atividade_principal'][0]['text']}\n\n🔹 _powered by Sanchez Search_";

        $filename="cnpj_{$cnpj}.txt";
        sendTxtFile($chat_id,$filename,$txt);
        editMessage($chat_id,$msg_id,"✅ *Consulta finalizada!* Arquivo enviado em anexo 📄");
    }
}

// === DEFAULT ===
else{
    sendMessage($chat_id,"⚙️ *Comando não reconhecido.*\n\nUse /start para abrir o menu.");
}
?>