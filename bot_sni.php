<?php
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);

// ==============================================
// 🔑 DADOS — PREENCHA!
// ==============================================
$token = '8662843036:AAG3ZQP5vTG47oMqgLqQ2bYVWLj4lH03fM';
$admin_id = 7761133138;
$grupo_id = -100SEU_ID_AQUI;

$api = "https://api.telegram.org/bot$token/";
$offset = 0;

// ==============================================
// 💾 BANCO DE DOMÍNIOS — alimentado automaticamente
// ==============================================
$arquivo_dominios = __DIR__ . '/dominios_conhecidos.json';
$arquivo_descobertos = __DIR__ . '/descobertos.json';

function carregarLista($arquivo, $padrao = []) {
    if (file_exists($arquivo)) return json_decode(file_get_contents($arquivo), true) ?: $padrao;
    return $padrao;
}
function salvarLista($arquivo, $dados) {
    file_put_contents($arquivo, json_encode($dados, JSON_PRETTY_PRINT));
}

// Lista base inicial — o bot vai expandir sozinho
$base_inicial = [
    ['nome' => 'cloudflare.com', 'fonte' => 'base', 'categoria' => 'CDN'],
    ['nome' => 'cdnjs.cloudflare.com', 'fonte' => 'base', 'categoria' => 'CDN'],
    ['nome' => 'cdn.telegram.org', 'fonte' => 'base', 'categoria' => 'CDN'],
    ['nome' => 'static.telegram.org', 'fonte' => 'base', 'categoria' => 'CDN'],
    ['nome' => 'jsdelivr.net', 'fonte' => 'base', 'categoria' => 'CDN'],
    ['nome' => 'akamaiedge.net', 'fonte' => 'base', 'categoria' => 'CDN'],
    ['nome' => 'fastly.com', 'fonte' => 'base', 'categoria' => 'CDN'],
    ['nome' => 'github.com', 'fonte' => 'base', 'categoria' => 'GLOBAL'],
    ['nome' => 'raw.githubusercontent.com', 'fonte' => 'base', 'categoria' => 'GLOBAL'],
    ['nome' => 'google.com', 'fonte' => 'base', 'categoria' => 'GLOBAL'],
    ['nome' => 'youtube.com', 'fonte' => 'base', 'categoria' => 'GLOBAL'],
    ['nome' => 'facebook.com', 'fonte' => 'base', 'categoria' => 'GLOBAL'],
    ['nome' => 'instagram.com', 'fonte' => 'base', 'categoria' => 'GLOBAL'],
    ['nome' => 'wikipedia.org', 'fonte' => 'base', 'categoria' => 'GLOBAL'],
    ['nome' => 'speedtest.net', 'fonte' => 'base', 'categoria' => 'GLOBAL'],
    ['nome' => 'vivo.com.br', 'fonte' => 'base', 'categoria' => 'BRASIL'],
    ['nome' => 'claro.com.br', 'fonte' => 'base', 'categoria' => 'BRASIL'],
    ['nome' => 'tim.com.br', 'fonte' => 'base', 'categoria' => 'BRASIL'],
];

// ==============================================
// 🌐 PASSO 1: BUSCAR NOVOS DOMÍNIOS DA REDE
// ==============================================
function buscarNovosDominios() {
    $novos = [];
    
    // Fontes públicas atualizadas
    $fontes = [
        'https://cdn.jsdelivr.net/npm/publicsuffixlist@latest/list/public_suffix_list.dat',
        'https://raw.githubusercontent.com/danielmiessler/SecLists/master/Discovery/DNS/cdn-names.txt',
        'https://raw.githubusercontent.com/danielmiessler/SecLists/master/Discovery/DNS/top-10000-domains.txt',
    ];
    
    foreach ($fontes as $fonte) {
        $dados = @file_get_contents($fonte, false, ['timeout' => 8]);
        if (!$dados) continue;
        
        preg_match_all('/([a-zA-Z0-9][a-zA-Z0-9\-\.]*\.(com|net|org|br|cloud|io|co))/i', $dados, $m);
        foreach (array_unique($m[1] ?? []) as $dom) {
            if (strlen($dom) > 5 && !in_array($dom, ['example.com', 'test.com'])) {
                $novos[] = strtolower(trim($dom));
            }
        }
        usleep(200000);
    }
    
    // Gera variações inteligentes
    $base_var = ['cdn', 'www', 'app', 'api', 'sso', 'login', 'static', 'media', 'img', 'video', 'cloud', 'edge'];
    $extras = [];
    foreach ($novos as $d) {
        $partes = explode('.', $d);
        if (count($partes) >= 2) {
            $raiz = implode('.', array_slice($partes, -2));
            foreach ($base_var as $pref) {
                $extras[] = "$pref.$raiz";
            }
        }
    }
    $novos = array_unique(array_merge($novos, $extras));
    
    return array_slice($novos, 0, 150); // Limita pra não travar
}

// ==============================================
// 🔍 PASSO 2: TESTAR CONEXÃO
// ==============================================
function testarHost($host, $porta, $timeout = 2.5) {
    $inicio = microtime(true);
    
    if ($porta == 443) {
        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true]
        ]);
        $fp = @stream_socket_client("ssl://$host:$porta", $e, $es, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    } else {
        $fp = @fsockopen($host, $porta, $e, $es, $timeout);
    }
    
    $ms = round((microtime(true) - $inicio) * 1000);
    if ($fp) { fclose($fp); return ['ok' => true, 'ms' => $ms]; }
    return ['ok' => false, 'ms' => 0];
}

// ==============================================
// ✅ PASSO 3: VERIFICAR E CLASSIFICAR
// ==============================================
function verificarETriar($lista) {
    $ativos = [];
    $novos_descobertos = [];
    
    foreach ($lista as $item) {
        $host = is_array($item) ? $item['nome'] : $item;
        $cat = is_array($item) ? ($item['categoria'] ?? 'DESCOBERTO') : 'DESCOBERTO';
        $fonte = is_array($item) ? ($item['fonte'] ?? 'escaneio') : 'escaneio';
        
        $p80 = testarHost($host, 80);
        $p443 = testarHost($host, 443);
        
        if ($p80['ok'] || $p443['ok']) {
            $ativos[] = [
                'nome' => $host,
                'categoria' => $cat,
                'fonte' => $fonte,
                'porta80' => $p80,
                'porta443' => $p443,
                'descoberto_em' => date('Y-m-d')
            ];
            if ($fonte === 'escaneio') $novos_descobertos[] = $host;
        }
        usleep(100000);
    }
    
    return [$ativos, $novos_descobertos];
}

// ==============================================
// 📤 ENVIAR NO TELEGRAM
// ==============================================
function enviarMsg($chat_id, $texto, $api) {
    $ch = curl_init($api . 'sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['chat_id' => $chat_id, 'text' => $texto, 'parse_mode' => 'HTML'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    curl_exec($ch);
    curl_close($ch);
    usleep(350000);
}

function montarEEnviar($lista, $chat_id, $api, $qtd_novos = 0) {
    $grupos = [];
    foreach ($lista as $item) {
        $cat = $item['categoria'];
        if (!isset($grupos[$cat])) $grupos[$cat] = [];
        $linha = "<code>{$item['nome']}</code>\n";
        $linha .= $item['porta80']['ok'] ? "  ✅ 80 → {$item['porta80']['ms']}ms\n" : "  ❌ 80\n";
        $linha .= $item['porta443']['ok'] ? "  ✅ 443 → {$item['porta443']['ms']}ms\n" : "  ❌ 443\n";
        if ($item['fonte'] === 'escaneio') $linha .= "  🆕 DESCOBERTO AGORA\n";
        $grupos[$cat][] = $linha;
    }
    
    $cab = "🌐 <b>ESCANEIO GLOBAL — " . date('d/m/Y H:i') . "</b>\n";
    $cab .= "📊 Total ativos: " . count($lista);
    $cab .= ($qtd_novos > 0) ? " | 🆕 Recém-descobertos: $qtd_novos" : "";
    $cab .= "\n\n";
    
    $primeira = true;
    foreach ($grupos as $cat => $itens) {
        $msg = ($primeira ? $cab : '') . "<b>📂 $cat</b>\n" . implode('', $itens) . "\n";
        if ($primeira) $msg .= "💡 Copia o domínio → usa no SNI/Host do VPN\n";
        enviarMsg($chat_id, $msg, $api);
        $primeira = false;
    }
}

// ==============================================
// 🤖 LOOP PRINCIPAL
// ==============================================
echo "🤖 BOT ESCANEAR GLOBAL INICIADO!\n";
$ultimo_escaneio = 0;
$intervalo = 2700; // 45 min = escaneio completo

// Carrega base
$dominios = carregarLista($arquivo_dominios, $base_inicial);

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
                enviarMsg($cid, "🌐 <b>ESCANEADOR GLOBAL DE SNI</b>\n\nComandos:\n/scan — Escanear e descobrir NOVOS domínios\n/check — Verificar lista atual\n/status — Estatísticas\n\n🔄 O bot escaneia a rede sozinho e aprende novos hosts!", $api);
            }
            elseif ($msg === '/scan' && ($cid == $admin_id || $grupo_id < 0)) {
                enviarMsg($cid, "🔍 Escaneando rede global... aguarde!\nIsso pode levar ~1 minuto", $api);
                
                $novos_brutos = buscarNovosDominios();
                $todos_juntos = array_merge($dominios, $novos_brutos);
                list($ativos, $descobertos) = verificarETriar($todos_juntos);
                
                // Atualiza banco
                foreach ($descobertos as $dn) {
                    $dominios[] = ['nome' => $dn, 'fonte' => 'escaneio', 'categoria' => 'DESCOBERTO'];
                }
                salvarLista($arquivo_dominios, $dominios);
                
                montarEEnviar($ativos, $cid, $api, count($descobertos));
            }
            elseif ($msg === '/check' && ($cid == $admin_id || $grupo_id < 0)) {
                enviarMsg($cid, "✅ Verificando " . count($dominios) . " domínios conhecidos...", $api);
                list($ativos,) = verificarETriar($dominios);
                montarEEnviar($ativos, $cid, $api);
            }
            elseif ($msg === '/status') {
                enviarMsg($cid, "📊 <b>ESTATÍSTICAS</b>\n\nDomínios conhecidos: " . count($dominios) . "\nÚltimo escaneio: " . date('d/m/Y H:i', $ultimo_escaneio ?: time()) . "\nPróximo escaneio automático: a cada 45 min\n\n🟢 O bot aprende sozinho!", $api);
            }
        }
    }
    
    // Escaneio automático no grupo
    if ($grupo_id < 0 && time() - $ultimo_escaneio >= $intervalo) {
        echo "[" . date('H:i') . "] Escaneio automático iniciado...\n";
        $novos_brutos = buscarNovosDominios();
        $todos_juntos = array_merge($dominios, $novos_brutos);
        list($ativos, $descobertos) = verificarETriar($todos_juntos);
        
        foreach ($descobertos as $dn) {
            $dominios[] = ['nome' => $dn, 'fonte' => 'escaneio', 'categoria' => 'DESCOBERTO'];
        }
        salvarLista($arquivo_dominios, $dominios);
        
        montarEEnviar($ativos, $grupo_id, $api, count($descobertos));
        $ultimo_escaneio = time();
        echo "[" . date('H:i') . "] Escaneio concluído — " . count($ativos) . " ativos, " . count($descobertos) . " novos\n";
    }
    
    sleep(2);
}
