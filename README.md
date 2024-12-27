# Migração e Gerenciamento de Vídeos com AWS S3 e Panda Video

Este projeto tem como objetivo realizar a migração de vídeos armazenados no AWS S3 para a plataforma Panda Video, além de também migrar todos os vídeos de plataformas moodle do vimeo ou AWS em massa para o Panda Video.

## Estrutura do Projeto

- **`migrar_aws_to_panda.php`**: Script principal para migrar vídeos do AWS S3 para o Panda Video.
- **`listar_videos.php`**: Script para listar vídeos já existentes no Panda Video e salvar informações em um arquivo local --> **`files/lista_panda.txt`**.
- **`deploy.sh`**: Script bash para sincronização de arquivos e execução remota em servidores Moodle.
- **`update_label_panda.php`**: Script para atualizar registros no Moodle com base em uma lista de vídeos e seus UUIDs.
- **`credentials.php`**: Arquivo de configuração contendo as credenciais necessárias para acessar a AWS e a API do Panda Video.
- **`files/lista_panda.txt`**: Arquivo gerado pelo script `listar_videos.php` com informações dos vídeos existentes.

---

## Requisitos

1. **PHP**: Versão 7.4 ou superior.
2. **Composer**: Para gerenciar as dependências.
3. **Bibliotecas Necessárias**:
   - AWS SDK for PHP
   - Guzzle HTTP
   - Ramsey UUID
   - ReactPHP Event Loop
   - Ratchet Pawl

---

## Configuração do Projeto

1. **Instale as Dependências**
   Execute os comandos abaixo para instalar as bibliotecas necessárias:
   ```bash
   composer require aws/aws-sdk-php
   composer require guzzlehttp/guzzle
   composer require ramsey/uuid
   composer require react/event-loop
   composer require ratchet/pawl
   ```

2. **Configure o Arquivo `credentials.php`**
   Crie o arquivo `credentials.php` na raiz do projeto com o seguinte conteúdo:
   ```php
   <?php
   return [
       'aws' => [
           'region' => 'us-east-1',
           'key' => 'SUA_CHAVE_AWS',
           'secret' => 'SEU_SEGREDO_AWS',
           'bucket' => 'SEU_BUCKET_NAME',
           'prefix' => 'SUA/PASTA/ESPECIFICA/',
       ],
       'panda' => [
           'api_key' => 'SUA_API_KEY_DO_PANDA',
           'folder_id' => null,
       ],
   ];
   ```

---

## Scripts e Objetivos

### 1. `migrar_aws_to_panda.php`

**Objetivo**: Migrar vídeos armazenados no AWS S3 para o Panda Video.

**Execução**:
   ```bash
   php migrar_aws_to_panda.php
   ```

O script irá:
- Listar os arquivos `.mp4` no bucket configurado no `credentials.php`.
- Gerar URLs pré-assinadas para acessar os vídeos no S3.
- Fazer o upload dos vídeos para o Panda Video, acompanhando o progresso via WebSocket.

### 2. `listar_videos.php`

**Objetivo**: Listar todos os vídeos existentes na conta do Panda Video e salvar informações localmente.

**Execução**:
   ```bash
   php listar_videos.php | ou executar no navegador usando localhost
   ```

O script irá:
- Conectar-se à API do Panda Video utilizando a chave de API configurada no `credentials.php`.
- Salvar os títulos e IDs externos dos vídeos em `files/lista_panda.txt`.

### 3. `deploy.sh`

**Objetivo**: Sincronizar arquivos e executar remotamente scripts em servidores Moodle.
**Primeiramente**:
   ```bash
   chmd +x deploy.sh
   ```
**Execução**:
   ```bash
   ./deploy.sh
   ```

O script irá:
- Copiar arquivos especificados (como `update_label_panda.php` e `lista_panda.txt`) para os servidores configurados.
- Executar o script `update_label_panda.php` remotamente.
- Gerar um relatório final com os domínios processados e falhas, caso existam.

Para adicionar novos servidores ou domínios, edite as variáveis `SERVERS` e `DOMAINS` diretamente no script.

### 4. `update_label_panda.php`

**Objetivo**: Atualizar registros em tabelas do Moodle, substituindo códigos antigos por iframes gerados a partir dos UUIDs dos vídeos.

**Execução**:
   ```bash
   Será executado automaticamente pelo deploy.sh | ou deve-se acessar a URL/update_label_panda.php (retire o comando que permite a execução por cli no início do arquivo)
   ```

O script irá:
- Ler o arquivo `lista_panda.txt` e gerar uma lista de códigos simplificados e seus respectivos UUIDs.
- Atualizar as tabelas `label` e `course_sections` no Moodle, substituindo o conteúdo antigo pelo HTML do iframe do Panda Video ou atualizar um video do panda por um novo.
- Limpar o cache do Moodle após as atualizações.

---

## Estrutura do Arquivo Gerado

### `files/lista_panda.txt`

Formato:
```text
Título do Vídeo 1 = ID Externo 1
Título do Vídeo 2 = ID Externo 2
...
```

---

## Problemas Conhecidos

- Certifique-se de que o `credentials.php` está corretamente configurado e na mesma pasta dos scripts.
- Todas as dependências devem estar instaladas com o Composer para o funcionamento correto.
- Permissões adequadas no S3 e Panda Video para leitura, escrita e acesso via API são necessárias.
- Para o script `update_label_panda.php`, é necessário que o Moodle esteja corretamente configurado e que as tabelas `label` e `course_sections` sejam acessíveis.
- Caso queira atualizar um único vídeo, basta inserir no **`files/lista_panda.txt`** o código anterior do panda e o código novo, seguindo o Formato:
```text
codigo-antigo = codigo-novo
codigo-antigo = codigo-novo
...
```
E executar o deploy.sh


