<?php
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);

// ==============================================
// 🔑 DADOS — PREENCHA AQUI!
// ==============================================
$token = '8662843036:AAG3ZQP5vTG47oMqgLqQ2bYVWLj4lH03fMI';
$admin_id = 7761133138;
$grupo_id = -100COLOCA_ID_DO_GRUPO_AQUI;

$api = "https://api.telegram.org/bot$token/";
$offset = 0;

// ==============================================
// 📋 LISTA DE SNI / CDN PARA TESTAR
// ==============================================
$lista_sni = [
    // VIVO
    ['nome' => 'cdn.vivo.com.br', 'host' => 'cdn.vivo.com.br', 'porta' => 443, 'operadora' => 'VIVO 🟢'],
    ['nome' => 'www.vivo.com.br', 'host' => 'www.vivo.com.br', 'porta' => 443, 'operadora' => 'VIVO 🟢'],
    ['nome' => 'app.vivo.com.br', 'host' => 'app.vivo.com.br', 'porta' => 443, 'operadora' => 'VIVO 🟢'],
    ['nome' => 'sso.vivo.com.br', 'host' => 'sso.vivo.com.br', 'porta' => 443, 'operadora' => 'VIVO 🟢'],
    ['nome' => 'login.vivo.com.br', 'host' => 'login.vivo.com.br', 'porta' => 443, 'operadora' => 'VIVO 🟢'],
    
    // CLARO
    ['nome' => 'www.claro.com.br', 'host' => 'www.claro.com.br', 'porta' => 443, 'operadora' => 'CLARO 🔴'],
    ['nome' => 'cdn.claro.com.br', 'host' => 'cdn.claro.com.br', 'porta' => 443, 'operadora' => 'CLARO 🔴'],
    ['nome' => 'smart.claro.com.br', 'host' => 'smart.claro.com.br', 'porta' => 443, 'operadora' => 'CLARO 🔴'],
    ['nome' => 'minhaconta.claro.com.br', 'host' => 'minhaconta.claro.com.br', 'porta' => 443, 'operadora' => 'CLARO 🔴'],
    
    // TIM
    ['nome' => 'www.tim.com.br', 'host' => 'www.tim.com.br', 'porta' => 443, 'operadora' => 'TIM 🔵'],
    ['nome' => 'cdn.tim.com.br', 'host' => 'cdn.tim.com.br', 'porta' => 443, 'operadora' => 'TIM 🔵'],
    ['nome' => 'meutim.tim.com.br', 'host' => 'meutim.tim.com.br', 'porta' => 443, 'operadora' => 'TIM 🔵'],
    
    // CDN / GLOBAIS
    ['nome' => 'cloudflare.com', 'host' => 'cloudflare.com', 'porta' => 443, 'operadora' => 'CDN ⚡'],
    ['nome' => 'cdnjs.cloudflare.com', 'host' => 'cdnjs.cloudflare.com', 'porta' => 443, 'operadora' => 'CDN ⚡'],
    ['nome' => 'cdn.telegram.org', 'host' => 'cdn.telegram.org', 'porta' => 443, 'operadora' => 'CDN ⚡'],
    ['nome' => 'static.telegram.org', 'host' => 'static.telegram.org', 'porta' => 443, 'operadora' => 'CDN ⚡'],
    ['nome' => 'github.com', 'host' => 'github.com', 'porta' => 443, 'operadora' => 'CDN ⚡'],
    ['nome' => 'raw.githubusercontent.com', 'host' => 'raw.githubusercontent.com', 'porta' => 443, 'operadora' => 'CDN ⚡'],
    ['nome' => 'wikipedia.org', 'host' => 'wikipedia.org', 'porta' => 443, 'operadora' => 'CDN ⚡'],
    ['nome' => 'youtu.be', 'host' => 'youtu.be', 'porta' => 443, 'operadora' => 'CDN ⚡'],
    ['nome' => 'googlevideo.com', 'host' => 'googlevideo.com', 'porta' => 443, 'operadora' => 'CDN ⚡'],
];

// ==============================================
// 🔍 FUNÇÃO: TESTAR CONEXÃO TLS/SNI
// ==============================================
function testarHost($host, $porta = 443, $timeout = 4) {
    $inicio = microtime(true);
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'disable_compression' => true,
        ]
    ]);
    
    $fp = @stream_socket_client(
        "ssl://$host:$porta",
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $ctx
    );
    
    $fim = microtime(true);
    $tempo = round(($fim - $inicio) * 1000);
    
    if ($fp) { fclose($fp); return ['ok' => true, 'ms' => $tempo]; }
    return ['ok' => false, 'ms' => 0];
}

// ==============================================
// 📤 FUNÇÃO: ENVIAR MENSAGEM
// ==============================================
function enviar($chat_id, $texto, $api) {
    $ch = curl_init($api . 'sendMessage');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['chat_id' => $chat_id, 'text' => $texto, 'parse_mode' => 'HTML']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// ==============================================
// ✅ VERIFICAR TUDO E MONTAR LISTA
// ==============================================
function verificarTudo($lista, $api, $chat_id = null) {
    $saida = "🔍 <b>SNI/CDN ATIVOS — " . date('d/m/Y H:i') . "</b>\n\n";
    $grupos = [];
    
    foreach ($lista as $item) {
        $res = testarHost($item['host']);
        if (!isset($grupos[$item['operadora']])) $grupos[$item['operadora']] = [];
        if ($res['ok']) {
            $grupos[$item['operadora']][] = "✅ <code>{$item['nome']}</code> ({$res['ms']}ms)";
        } else {
            $grupos[$item['operadora']][] = "❌ <s>{$item['nome']}</s>";
        }
    }
    
    foreach ($grupos as $op => $itens) {
        $saida .= "<b>$op</b>\n" . implode("\n", $itens) . "\n\n";
    }
    
    $saida .= "💡 <b>Como usar:</b> Copia o domínio → coloca no SNI/Host do seu VPN (porta 443)";
    
    if ($chat_id) enviar($chat_id, $saida, $api);
    return $saida;
}

// ==============================================
// 🤖 LOOP PRINCIPAL
// ==============================================
echo "🤖 BOT SNI MONITOR INICIADO!\n";
$ultimo_envio = 0;
$intervalo = 1800; // 30 minutos em segundos

while (true) {
    // Comandos do Telegram
    $resp = @file_get_contents($api . "getUpdates?offset=$offset&timeout=5");
    if ($resp) {
        $dados = json_decode($resp, true);
        foreach ($dados['result'] ?? [] as $upd) {
            $offset = $upd['update_id'] + 1;
            $msg = $upd['message']['text'] ?? '';
            $cid = $upd['message']['chat']['id'] ?? 0;
            
            if ($msg === '/start') {
                enviar($cid, "👋 <b>Monitor de SNI/CDN</b>!\n\nComandos:\n/check — Verificar agora\n/ajuda — Como usar\n\n⏰ Verificação automática a cada 30 min no grupo!", $api);
            }
            elseif ($msg === '/check' && $cid == $admin_id) {
                enviar($cid, "🔍 Verificando... aguarde!", $api);
                verificarTudo($lista_sni, $api, $cid);
            }
            elseif ($msg === '/ajuda') {
                enviar($cid, "📖 <b>INSTRUÇÕES:</b>\n\n1️⃣ Copia o domínio que está ✅ ativo\n2️⃣ Coloca no campo <b>SNI/Host</b> do VPN\n3️⃣ Porta: <b>443</b>\n4️⃣ Salva e conecta!\n\n⚠️ Nem todo domínio funciona para todos — testa vários!", $api);
            }
        }
    }
    
    // Envio automático no grupo
    if ($grupo_id < 0 && time() - $ultimo_envio >= $intervalo) {
        echo "[" . date('H:i') . "] Enviando lista no grupo...\n";
        verificarTudo($lista_sni, $api, $grupo_id);
        $ultimo_envio = time();
    }
    
    sleep(3);
}

