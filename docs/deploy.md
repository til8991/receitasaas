# Deploy em VM com Nginx

Topologia: o Nginx da VM termina o TLS e faz proxy para `127.0.0.1:8088`, onde fica o container `nginx` do stack. As calculadoras, o MySQL e o Redis não publicam porta nenhuma.

## Requisitos da VM
- Ubuntu 22.04/24.04, **4 vCPU / 8 GB RAM** recomendados (as duas calculadoras Java usam até 1 GB cada) e 30 GB de disco.
- Docker Engine 24+ com o plugin Compose v2.24+ (o Compose usa `additional_contexts` e `env_file.required`).
- Nginx e certbot (`sudo apt install nginx certbot python3-certbot-nginx`).
- DNS do domínio apontando para a VM. Portas 80/443 abertas; **não** abra a 8088.

## Primeira instalação

```bash
sudo mkdir -p /opt/razonete && sudo chown $USER /opt/razonete && cd /opt/razonete
git clone --recurse-submodules https://github.com/til8991/receitasaas.git .
cp .env.production.example .env
nano .env        # APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD (senhas fortes)

# APP_KEY
docker compose build php
echo "APP_KEY=$(docker compose run --rm --no-deps --entrypoint php php artisan key:generate --show)" >> .env
# (remova a linha "APP_KEY=" vazia que veio do exemplo)

# Calculadoras: o primeiro build baixa as dependências Maven e gera os bancos SQLite (alguns minutos)
docker compose build calc-regular calc-simples

scripts/deploy.sh
```

O `scripts/deploy.sh` faz, nesta ordem:
1. Atualiza o código e os submodules.
2. Reconstrói as imagens.
3. Sobe o MySQL, o Redis e as calculadoras.
4. Roda as migrations.
5. Sobe o php, o nginx, o horizon e o scheduler.
6. Roda o teste de fumaça das calculadoras.
7. Confere `/healthz` e `/up`.

### Nginx da VM + TLS

```bash
sudo cp deploy/nginx-host.conf /etc/nginx/sites-available/razonete
sudo sed -i 's/app.seudominio.com.br/SEU.DOMINIO/g' /etc/nginx/sites-available/razonete
sudo ln -s /etc/nginx/sites-available/razonete /etc/nginx/sites-enabled/
sudo certbot --nginx -d SEU.DOMINIO
sudo nginx -t && sudo systemctl reload nginx
```

## Atualizações

```bash
cd /opt/razonete
scripts/deploy.sh           # código da aplicação
scripts/deploy.sh --calc    # também atualiza e reconstrói as calculadoras oficiais
```

Quando as calculadoras mudam de versão, as simulações antigas continuam com a versão registrada na auditoria. Rode o lote de novo para recalcular.

## Operação

| Tarefa | Comando |
|---|---|
| Status das filas | `docker compose exec horizon php artisan horizon:status` |
| Jobs com falha | `docker compose exec php php artisan queue:failed` |
| Logs da API | `docker compose logs -f php horizon` |
| Log das chamadas às calculadoras | `docker compose exec php tail -f storage/logs/calculadora-$(date +%F).log` |
| Versões das calculadoras | `docker compose --profile smoke run --rm smoke` |
| Backup do banco | `docker compose exec mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction receitasass' \| gzip > backup-$(date +%F).sql.gz` |
| Backup dos arquivos (logos/PDFs) | `docker run --rm -v receitasass_storage:/s -v $PWD:/b alpine tar czf /b/storage-$(date +%F).tgz -C /s .` |

O painel do Horizon **não é exposto**: ele usa sessão web e os usuários do produto autenticam por token. Para vê-lo, use um túnel SSH para um ambiente `local`, ou só os comandos acima.

## Segurança
- As calculadoras não têm autenticação (`permitAll`). Por isso ficam na rede `calc` (`internal: true`), sem acesso à internet e sem porta publicada.
- Só o Nginx da VM é público. O container `nginx` escuta em `127.0.0.1`.
- `TRUSTED_PROXIES` limita quem pode enviar `X-Forwarded-*`.
- Os tokens Sanctum expiram em 7 dias (`SANCTUM_EXPIRATION`). O login tem limite de tentativas por e-mail+rede e por conta.
- Faça backups diários do volume `mysql` e do volume `storage`.
