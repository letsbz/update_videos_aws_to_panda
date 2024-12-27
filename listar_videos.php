<?php

$credentials = require 'credentials.php';
if (!$credentials || !isset($credentials['panda']['api_key'])) {
    die("Erro: Credenciais inválidas ou não carregadas.");
}

$url = "https://api-v2.pandavideo.com.br/videos";
$apiKey = $credentials['panda']['api_key'];

$headers = array(
    "accept: application/json",
    "Authorization: $apiKey"
);

$filePath = "files/lista_panda.txt";
$file = fopen($filePath, "w");
if ($file === false) {
    die("Erro ao abrir o arquivo.");
}

$page = 1;
$limit = 50;
$total_videos = 0;

do {
    $paginatedUrl = $url . "?page=$page&limit=$limit";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers)
        ]
    ]);

    $response = file_get_contents($paginatedUrl, false, $context);

    if ($response === false) {
        die("Erro ao acessar a API.");
    }

    $data = json_decode($response, true);

    if (!isset($data['videos']) || empty($data['videos'])) {
        break;
    }

    foreach ($data['videos'] as $video) {
        if (isset($video['title']) && isset($video['video_external_id'])) {
            $line = $video['title'] . " = " . $video['video_external_id'] . PHP_EOL;
            fwrite($file, $line);
            $total_videos++;
        }
    }

    $page++;

} while (!empty($data['videos']));

fclose($file);

echo "Arquivo atualizado com sucesso. Total de vídeos: $total_videos\n";
