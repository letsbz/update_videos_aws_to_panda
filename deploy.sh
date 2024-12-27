#!/bin/bash

PORT="65002"
LOCAL_DIR="/c/dev/Github/update_videos_aws_to_panda/files"
UPDATE_LABEL_PANDA="update_label_panda.php"
LIST_FILE="lista_panda.txt"

declare -A SERVERS
SERVERS=(
  ["u_SERVER@SER.VER.IP"]="u_SERVER"  # Servidor X
)

DOMAINS=("url.com.br")

failed_domains=()

process_directory() {
  local REMOTE_DIR=$1
  local USER=$2
  local SERVER=$3

  echo "Verificando a existência de ${UPDATE_LABEL_PANDA} e ${LIST_FILE} no servidor ${SERVER} no diretório ${REMOTE_DIR}..."
  ssh -p ${PORT} ${USER}@${SERVER} <<EOF
    if [ -f "${REMOTE_DIR}/${UPDATE_LABEL_PANDA}" ]; then
      echo "Arquivo ${UPDATE_LABEL_PANDA} encontrado. Excluindo..."
      rm -f "${REMOTE_DIR}/${UPDATE_LABEL_PANDA}"
    fi
    if [ -f "${REMOTE_DIR}/${LIST_FILE}" ]; then
      echo "Arquivo ${LIST_FILE} encontrado. Excluindo..."
      rm -f "${REMOTE_DIR}/${LIST_FILE}"
    fi
EOF

  echo "Fazendo upload de ${UPDATE_LABEL_PANDA} e ${LIST_FILE} para o diretório raiz ${REMOTE_DIR} no servidor ${SERVER}..."
  scp -P ${PORT} ${LOCAL_DIR}/${UPDATE_LABEL_PANDA} ${USER}@${SERVER}:${REMOTE_DIR}/
  scp -P ${PORT} ${LOCAL_DIR}/${LIST_FILE} ${USER}@${SERVER}:${REMOTE_DIR}/

  echo "Arquivos enviados para o diretório raiz ${REMOTE_DIR} no servidor."

  echo "Executando ${UPDATE_LABEL_PANDA} no próprio ambiente Moodle do servidor ${SERVER}..."
  ssh -p ${PORT} ${USER}@${SERVER} "php ${REMOTE_DIR}/${UPDATE_LABEL_PANDA}"
  echo "Script ${UPDATE_LABEL_PANDA} executado no ambiente Moodle no servidor ${SERVER}."
}

for DOMAIN in "${DOMAINS[@]}"; do
  echo "Iniciando processamento para o domínio ${DOMAIN}..."
  processed_successfully=false

  for SERVER_USER in "${!SERVERS[@]}"; do
    USER="${SERVERS[$SERVER_USER]}"
    SERVER="${SERVER_USER#*@}"
    REMOTE_BASE_DIR="/home/${USER}/domains"
    REMOTE_PUBLIC_HTML="${REMOTE_BASE_DIR}/${DOMAIN}/public_html"
    REMOTE_EAD="${REMOTE_PUBLIC_HTML}/ead"

    echo "Conectando ao servidor ${SERVER} com o usuário ${USER} para o domínio ${DOMAIN}..."

    if ssh -p ${PORT} ${USER}@${SERVER} "[ -d ${REMOTE_EAD} ]"; then
      echo "Pasta ead encontrada em ${REMOTE_EAD}."
      process_directory "${REMOTE_EAD}" "${USER}" "${SERVER}"
      processed_successfully=true
      break
    elif ssh -p ${PORT} ${USER}@${SERVER} "[ -d ${REMOTE_PUBLIC_HTML} ]"; then
      echo "Pasta ead não encontrada. Processando public_html em ${REMOTE_PUBLIC_HTML}..."
      process_directory "${REMOTE_PUBLIC_HTML}" "${USER}" "${SERVER}"
      processed_successfully=true
      break
    else
      echo "Domínio ${DOMAIN} não encontrado no servidor ${SERVER}. Pulando para o próximo servidor."
    fi
  done

  if [ "$processed_successfully" = false ]; then
    echo "Domínio ${DOMAIN} não foi processado com sucesso em nenhum servidor."
    failed_domains+=("${DOMAIN}")
  else
    echo "Processamento para o domínio ${DOMAIN} concluído com sucesso em um dos servidores."
  fi
done

echo "Todas as operações foram concluídas."

if [ ${#failed_domains[@]} -gt 0 ]; then
  echo "Domínios que não foram processados com sucesso:"
  for DOMAIN in "${failed_domains[@]}"; do
    echo "- ${DOMAIN}"
  done
else
  echo "Todos os domínios foram processados com sucesso em pelo menos um servidor."
fi