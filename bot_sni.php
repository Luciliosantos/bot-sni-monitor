<?php
error_reporting(0);
ini_set('display_errors', 0);
set_time_limit(0);

// ==============================================
// 🔑 DADOS — JÁ PREENCHIDOS
// ==============================================
$token = '8662843036:AAG3ZQP5vTG47oMqgLqQ2bYVWLj4lH03fM
I';
$admin_id = 7761133138;
$grupo_id = -1003820426660;

$api = "https://api.telegram.org/bot$token/";
$offset = 0;

// ==============================================
// 💾 BANCO DE DADOS — salva os que funcionam
// ==============================================
$arquivo_vivos = __DIR__ . '/vivos.json';
$arquivo_tentados = __DIR__ . '/tentados.json';

function carregar($arq, $padrao = []) {
    return file_exists($arq) ? json_decode(file_get_contents($arq), true) ?: $padrao : $padrao;
}
function salvar($arq, $dados) {
    file_put_contents($arq, json_encode($dados, JSON_PRETTY_PRINT));
}

// ==============================================
// 🌐 BASE PARA DESCOBRIR — raízes + prefixos
// ==============================================
$dominios_raiz = [
    'vivo.com.br', 'vivo.com', 'claro.com.br', 'claro.com',
    'tim.com.br', 'tim.com', 'cloudflare.com', 'github.com',
    'telegram.org', 'google.com', 'youtube.com', 'facebook.com',
    'instagram.com', 'akamaiedge.net', 'fastly.com', 'jsdelivr.net',
    'speedtest.net', 'wikipedia.org', 'opendns.com', 'github.io'
];

$prefixos = [
    'cdn', 'www', 'app', 'api', 'sso', 'login', 'auth', 'media',
    'static', 'img', 'video', 'cloud', 'edge', 'mail', 'news',
    'dev', 'staging', 'admin', 'portal', 'servicos', 'atendimento',
    'loja', 'pagamento', 'checkout', 'upload', 'download', 'data',
    'vpn', 'proxy', 'gateway', 'node', 'server', 'relay', 'bridge'
];

// ==============================================
// 🔍 GERAR NOVOS SUBDOMÍNIOS PARA TESTAR
// ==============================================
function gerarNovos($raizes, $prefixos, $ja_tentados, $limite = 15) {
    $novos = [];
    shuffle($raizes); shuffle($prefixos);
    
    foreach ($raizes as $raiz) {
        foreach ($prefixos as $p) {
            $completo = "$p.$raiz";
            if (!in_array($completo, $ja_tentados)) {
                $novos[] = $completo;
                if (count($novos) >= $limite) return $novos;
            }
        }
    }
    return $novos;
}

// ==============================================
// ✅ TESTAR — Status HTTP 200 + Portas
// ==============================================
function testarDominio($host, $timeout = 2) {
    $resultado = [
        'host' => $host,
        'porta80' => ['ok' => false, 'codigo' => 0, 'ms' => 0],
        'porta443' => ['ok' => false, 'codigo' => 0, 'ms' => 0],
        'encontrado_em' => date('Y-m-d')
    ];
    
    // Teste PORTA 80 — HTTP + status 200
    $i = microtime(true);
    $ch = curl_init("http://$host/");
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => true, // só cabeçalho = rápido
    ]);
    curl_exec($ch);
    $cod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ms = round((microtime(true) - $i) * 1000);
    $resultado['porta80']['codigo'] = $cod;
    $resultado['porta80']['ok'] = ($cod >= 200 && $cod < 400); // 200-399 = vivo
    $resultado['porta80']['ms'] = $ms;
    curl_close($ch);
    
    // Teste PORTA 443 — HTTPS + status 200
    $i = microtime(true);
    $ch = curl_init("https://$host/");
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER => false,
        CURLOPT_NOBODY => true,
    ]);
    curl_exec($ch);
    $cod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ms = round((microtime(true) - $i) * 1000);
    $resultado['porta443']['codigo'] = $cod;
    $resultado['porta443']['ok'] = ($cod >= 200 && $cod < 400);
    $resultado['porta443']['ms'] = $ms;
    curl_close($ch);
    
    return $resultado;
}

// ==============================================
// 📤 ENVIAR NO TELEGRAM
// ==============================================
function msg($cid, $texto, $api) {
    $ch = curl_init($api.'sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['chat_id' => $cid, 'text' => $texto, 'parse_mode' => 'HTML'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);
    curl_exec($ch); curl_close($ch);
    usleep(350000);
}

function montarLista($vivos, $novos_encontrados, $cid, $api) {
    if (empty($vivos)) {
        msg($cid, "🔍 Nenhum domínio ativo ainda. Continuando busca...", $api);
        return;
    }
    
    $texto = "🌐 <b>SUBDOMÍNIOS ATIVOS — STATUS 200 OK</b>\n";
    $texto .= "📊 Total: " . count($vivos) . " | 🆕 Recém-descobertos: " . count($novos_encontrados) . "\n\n";
    
    foreach ($vivos as $d) {
        $texto .= "<code>{$d['host']}</code>";
        if (in_array($d['host'], $novos_encontrados)) $texto .= " 🆕";
        $texto .= "\n";
        $texto .= $d['porta80']['ok'] ? "  ✅ 80 ({$d['porta80']['codigo']}) → {$d['porta80']['ms']}ms\n" : "  ❌ 80\n";
        $texto .= $d['porta443']['ok'] ? "  ✅ 443 ({$d['porta443']['codigo']}) → {$d['porta443']['ms']}ms\n" : "  ❌ 443\n";
        $texto .= "\n";
    }
    
    $texto .= "💡 Copia o domínio → usa no SNI/Host do VPN\n";
    msg($cid, $texto, $api);
}

// ==============================================
// 🤖 LOOP PRINCIPAL
// ==============================================
echo "🤖 BOT DESCOBRIDOR DE SUBDOMÍNIOS INICIADO!\n";
echo "📋 15 novos por vez | A cada 15 min | Status 200 OK\n";

$ultimo_escaneio = 0;
$intervalo = 900; // 15 minutos

// Carrega histórico
$vivos = carregar($arquivo_vivos, []);
$tentados = carregar($arquivo_tentados, []);

while (true) {
    // Comandos do Telegram
    $resp = @file_get_contents($api."getUpdates?offset=$offset&timeout=5");
    if ($resp) {
        $dados = json_decode($resp, true);
        foreach ($dados['result']??[] as $upd) {
            $offset = $upd['update_id'] + 1;
            if (!isset($upd['message'])) continue;
            
            $txt = trim($upd['message']['text'] ?? '');
            $cid = $upd['message']['chat']['id'];
            
            if ($txt === '/start') {
                msg($cid, "🌐 <b>DESCOBRIDOR DE SUBDOMÍNIOS SNI</b>\n\nComandos:\n/scan — Descobrir e testar 15 novos\n/lista — Ver os que já estão vivos\n/status — Estatísticas\n\n🔄 O bot busca sozinho, testa status 200 e guarda os vivos!\n📋 15 por vez = leve e não trava ✅", $api);
            }
            elseif ($txt === '/scan' && ($cid == $admin_id || $grupo_id < 0)) {
                msg($cid, "🔍 Buscando 15 novos subdomínios... aguarde!", $api);
                
                $para_testar = gerarNovos($dominios_raiz, $prefixos, $tentados, 15);
                $novos_vivos = [];
                $achou_agora = [];
                
                foreach ($para_testar as $host) {
                    $tentados[] = $host; // marca como testado
                    $res = testarDominio($host);
                    
                    if ($res['porta80']['ok'] || $res['porta443']['ok']) {
                        $novos_vivos[] = $res;
                        $achou_agora[] = $host;
                    }
                    usleep(150000); // pausa = não sobrecarrega
                }
                
                // Mescla e salva
                $vivos = array_merge($novos_vivos, $vivos);
                salvar($arquivo_vivos, $vivos);
                salvar($arquivo_tentados, array_unique($tentados));
                
                montarLista($vivos, $achou_agora, $cid, $api);
            }
            elseif ($txt === '/lista') {
                montarLista($vivos, [], $cid, $api);
            }
            elseif ($txt === '/status') {
                $prox = $ultimo_escaneio ? max(0, $intervalo - (time() - $ultimo_escaneio)) : 0;
                msg($cid, "📊 <b>ESTATÍSTICAS</b>\n\n✅ Vivos: " . count($vivos) . "\n🔍 Já testados: " . count($tentados) . "\n⏰ Próximo escaneio automático: " . ($prox ? ceil($prox/60)." min" : "agora") . "\n\nA cada 15 min busca 15 novos!", $api);
            }
        }
    }
    
    // Escaneio automático no grupo
    if ($grupo_id < 0 && time() - $ultimo_escaneio >= $intervalo) {
        echo "[".date('H:i')."] Escaneio automático...\n";
        
        $para_testar = gerarNovos($dominios_raiz, $prefixos, $tentados, 15);
        $novos_vivos = []; $achou_agora = [];
        
        foreach ($para_testar as $host) {
            $tentados[] = $host;
            $res = testarDominio($host);
            if ($res['porta80']['ok'] || $res['porta443']['ok']) {
                $novos_vivos[] = $res;
                $achou_agora[] = $host;
            }
            usleep(150000);
        }
        
        $vivos = array_merge($novos_vivos, $vivos);
        salvar($arquivo_vivos, $vivos);
        salvar($arquivo_tentados, array_unique($tentados));
        
        montarLista($vivos, $achou_agora, $grupo_id, $api);
        $ultimo_escaneio = time();
        echo "[".date('H:i')."] Concluído — " . count($novos_vivos) . " novos vivos\n";
    }
    
    sleep(3);
}
