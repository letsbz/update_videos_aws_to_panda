<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');

global $DB;

function lerArquivoListas($arquivo) {
    $lista_codigos = [];
    $lista_uuids = [];
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($linhas as $linha) {
        list($codigo_completo, $uuid) = explode(' = ', $linha, 2);
        $codigo_simplificado = preg_replace('/_.*$/', '', trim($codigo_completo));
        $lista_codigos[] = $codigo_simplificado;
        $lista_uuids[] = trim($uuid);
    }
    echo "Lista lida com sucesso: " . count($lista_codigos) . " códigos encontrados.\n";
    return [$lista_codigos, $lista_uuids];
}

function gerarIframe($uuid) {
    return '<div style="position:relative;padding-top:56.25%;"><iframe id="panda-' . $uuid . 
           '" src="https://player-vz-cc0877c2-936.tv.pandavideo.com.br/embed/?v=' . $uuid . 
           '" style="border:none;position:absolute;top:0;left:0;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" ' . 
           'allowfullscreen=true width="100%" height="100%" fetchpriority="high"></iframe></div>';
}

function extrairCodigo($conteudo) {
    if (preg_match('/{{video:([\w\-\/]+)}}/', $conteudo, $matches)) {
        return preg_replace('/[-\/].*$/', '', $matches[1]);
    }
    if (preg_match('/player\.vimeo\.com\/video\/(\w+)/', $conteudo, $matches)) {
        return $matches[1];
    }
    if (preg_match('/<iframe.*?id="panda-([\w\-]+)".*?>.*?<\/iframe>/', $conteudo, $matches)) {
        return $matches[1];
    }
    return null;
}

function atualizar_registros($tabela, $campo_conteudo, $lista_codigos, $lista_uuids) {
    global $DB;

    echo "Iniciando atualização na tabela '{$tabela}'...\n";
    $registros = $DB->get_records($tabela, null, '', "id, {$campo_conteudo}");
    echo count($registros) . " registros encontrados na tabela '{$tabela}'.\n";

    foreach ($registros as $registro) {
        $conteudo_original = $registro->{$campo_conteudo};
        echo "Processando registro ID {$registro->id}...\n";

        $codigo = extrairCodigo($conteudo_original);

        if ($codigo) {
            echo "Código extraído: {$codigo}\n";
            if (in_array($codigo, $lista_codigos)) {
                $indice = array_search($codigo, $lista_codigos);
                $uuid = $lista_uuids[$indice];
                $conteudo_atualizado = gerarIframe($uuid);

                if ($conteudo_atualizado !== $conteudo_original) {
                    $DB->update_record($tabela, (object)[
                        'id' => $registro->id,
                        $campo_conteudo => $conteudo_atualizado
                    ]);
                    echo "Registro ID {$registro->id} atualizado com novo conteúdo.\n";
                } else {
                    echo "Nenhuma alteração necessária para registro ID {$registro->id}.\n";
                }
            } else {
                echo "Código '{$codigo}' não encontrado na lista.\n";
            }
        } else {
            echo "Nenhum código extraído do conteúdo do registro ID {$registro->id}.\n";
        }
    }
    echo "Atualização concluída na tabela '{$tabela}'.\n";
}

$arquivoLista = 'lista_panda.txt';
list($lista_codigos, $lista_uuids) = lerArquivoListas($arquivoLista);

try {
    atualizar_registros('label', 'intro', $lista_codigos, $lista_uuids);
    atualizar_registros('course_sections', 'summary', $lista_codigos, $lista_uuids);

    echo "Atualizações concluídas em ambas as tabelas.\n";

    purge_all_caches();
    echo "Cache limpo com sucesso.\n";

} catch (Exception $e) {
    echo "Erro ao atualizar registros: " . $e->getMessage();
}
