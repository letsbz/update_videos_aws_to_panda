<?php
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use GuzzleHttp\Client;
use Ramsey\Uuid\Uuid;
use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;

$credentials = require 'credentials.php';

$awsConfig = [
    'region' => $credentials['aws']['region'],
    'version' => 'latest',
    'credentials' => [
        'key' => $credentials['aws']['key'],
        'secret' => $credentials['aws']['secret'],
    ],
];

define('PANDA_API_KEY', $credentials['panda']['api_key']);
define('PANDA_FOLDER_ID', $credentials['panda']['folder_id']);

$bucket = $credentials['aws']['bucket'];
$prefix = $credentials['aws']['prefix'];

$s3 = new S3Client($awsConfig);
$httpClient = new Client();

function generatePresignedUrl($s3, $bucket, $key)
{
    try {
        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $key,
        ]);

        $request = $s3->createPresignedRequest($cmd, '+1 hour');
        return (string) $request->getUri();
    } catch (Exception $e) {
        echo "Erro ao gerar URL pré-assinada para {$key}: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function getFileSize($s3, $bucket, $key)
{
    try {
        $result = $s3->headObject([
            'Bucket' => $bucket,
            'Key' => $key,
        ]);
        return $result['ContentLength'];
    } catch (Exception $e) {
        echo "Erro ao obter tamanho do arquivo para {$key}: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function trackProgress($websocketUrl, $filename)
{
    echo "Acompanhando progresso para o vídeo: {$filename}\n";
    $loop = \React\EventLoop\Factory::create();
    $connector = new Connector($loop);

    $connector($websocketUrl)->then(
        function (WebSocket $conn) use ($filename, $loop) {
            $conn->on('message', function ($msg) use ($conn, $filename, $loop) {
                $data = json_decode($msg, true);
                if ($data['action'] === 'progress') {
                    echo "Progresso do vídeo {$filename}: " . floor($data['payload']['progress']) . "%\n";
                } elseif ($data['action'] === 'success') {
                    echo "Upload do vídeo {$filename} concluído com sucesso!\n";
                    $conn->close();
                    $loop->stop();
                } elseif ($data['action'] === 'error') {
                    echo "Erro durante o upload do vídeo {$filename}.\n";
                    $conn->close();
                    $loop->stop();
                }
            });
        },
        function ($e) use ($loop) {
            echo "Erro na conexão com o WebSocket: {$e->getMessage()}\n";
            $loop->stop();
        }
    );

    $loop->run();
}

function uploadToPanda($url, $key, $fileSize)
{
    $videoId = Uuid::uuid4()->toString();
    $filename = basename($key);

    $data = [
        'folder_id' => PANDA_FOLDER_ID,
        'video_id' => $videoId,
        'title' => $filename,
        'description' => "Upload automático do vídeo {$filename}",
        'url' => $url,
        'size' => $fileSize,
    ];

    try {
        echo "Iniciando upload do vídeo: {$filename}\n";

        $response = $GLOBALS['httpClient']->post('https://import.pandavideo.com:9443/videos', [
            'headers' => [
                'Authorization' => PANDA_API_KEY,
                'Content-Type' => 'application/json',
            ],
            'json' => $data,
        ]);

        $responseBody = json_decode($response->getBody()->getContents(), true);
        $websocketUrl = $responseBody['websocket_url'] ?? null;

        if ($websocketUrl) {
            trackProgress($websocketUrl, $filename);
        } else {
            echo "Upload iniciado sem progresso retornado. Verifique no painel do Panda Video.\n";
        }
    } catch (Exception $e) {
        echo "Erro ao fazer upload do vídeo {$filename}: " . $e->getMessage() . "\n";
    }
}

try {
    $objects = $s3->listObjects(["Bucket" => $bucket, 'Prefix' => $prefix]);

    if (!empty($objects['Contents'])) {
        $totalVideos = count(array_filter($objects['Contents'], function ($object) {
            return str_ends_with($object['Key'], '.mp4');
        }));

        $processedVideos = 0;

        foreach ($objects['Contents'] as $object) {
            $key = $object['Key'];
            if (str_ends_with($key, '.mp4')) {
                $url = generatePresignedUrl($s3, $bucket, $key);
                $fileSize = getFileSize($s3, $bucket, $key);
                echo "Processando vídeo: {$key} ({$processedVideos}/{$totalVideos})\n";

                uploadToPanda($url, $key, $fileSize);
                $processedVideos++;
                echo "Vídeos restantes: " . ($totalVideos - $processedVideos) . "\n";
            }
        }
    } else {
        echo "Nenhum arquivo encontrado na pasta especificada.\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
