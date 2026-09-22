# Documento de implantação
<!-- lang-nav -->

Languages: [中文](DEPLOYMENT.md) · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · [Français](DEPLOYMENT.fr.md) · [Español](DEPLOYMENT.es.md) · **Português** · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Requisitos de ambiente

| Componente | Versão mínima | Configuração recomendada |
|------|---------|---------|
| SO | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache habilitado) |
| Extensões PHP | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | todas |
| MySQL | 8.0+ | 8.0+ com replicação mestre-escravo |
| Redis | 6.0+ | 7.x modo sentinel |
| Elasticsearch | 7.x+ | 8.x nó único |
| Nginx | 1.20+ | proxy reverso + gzip + SSL |
| Composer | 2.x | última versão estável |
| Flutter SDK | 3.x+ | última versão estável (necessário apenas para construir o frontend) |

---

## 2. Assistente de instalação com um clique (recomendado para novas implantações)

```bash
# 1. Clonar o projeto
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Iniciar o assistente de instalação
php -S 0.0.0.0:8888 -t install/

# 3. Abrir no navegador http://<IP do servidor>:8888
#    Seguir o assistente: verificação do ambiente → configuração do banco → conta de administrador → instalação automática

# 4. Instalar dependências
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. Iniciar os serviços (portas padrão admin 8789 / service 8792, alteráveis via APP_PORT no respectivo .env)
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Limpeza de segurança
rm -rf install/

# 7. Acessar o painel administrativo: http://<IP do servidor>:8789 (porta padrão)
```

Operações executadas pelo assistente de instalação:
- Verificação do ambiente PHP (versão, extensões, permissões de diretório)
- Execução do SQL consolidado (`install/install.sql`), criando 78 tabelas e importando os dados de seed
- Criação da conta de superadministrador (criptografia bcrypt, vinculada ao role super_admin)
- Geração automática das chaves JWT/Encryption/Hashids
- Gravação em `admin/.env` e `service/.env`
- Geração de `install/install.lock` para evitar instalação duplicada

---

## 3. Implantação com Docker Compose

### 3.1 Início com um clique

```bash
# 1. Clonar o projeto
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Usar o assistente de instalação com um clique para configurar o ambiente (ou configurar manualmente o arquivo .env)
#    Parâmetros do Docker, como portas, ficam no .env da raiz do projeto (modelo .env.example): cp .env.example .env
php -S 0.0.0.0:8888 -t install/
# Manual: cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Construir e iniciar todos os serviços
docker-compose up -d

# 4. Ver o status
docker-compose ps

# 5. Ver os logs
docker-compose logs -f
```

### 3.2 Lista de serviços

| Serviço | Nome do container | Porta | Observação |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | proxy reverso + arquivos estáticos |
| admin | game-platform-admin | 8789 | API do painel administrativo |
| service | game-platform-service | 8792 | API de negócio C-side |
| leaderboard-ws | game-platform-ws | 8790, 8791 | rankings WebSocket/chat |
| mysql | game-platform-mysql | 3306 | banco principal |
| redis | game-platform-redis | 6379 | cache/rate limit |
| elasticsearch | game-platform-es | 9200 | busca full-text |

> **Configuração de portas**: As portas da tabela são os valores padrão e todas podem ser alteradas no `.env` da raiz do projeto (modelo `.env.example`; edite após `cp .env.example .env`):
> `NGINX_HTTP_PORT`, `NGINX_HTTPS_PORT`, `ADMIN_PORT`, `SERVICE_PORT`, `LEADERBOARD_WS_PORT`, `CHAT_WS_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `ES_PORT`.
> As portas upstream do `nginx.conf.template` são renderizadas automaticamente pelo envsubst da imagem oficial; não é necessário alterar manualmente a configuração do Nginx.
> Em implantações Docker, os endereços públicos (`APP_URL` / `SITE_URL`) seguem automaticamente `ADMIN_PORT` / `SERVICE_PORT` por padrão (formato `http://localhost:porta`); para domínio personalizado ou HTTPS, defina `APP_URL` / `SITE_URL` no `.env` raiz (sobrescreve as chaves de mesmo nome em `admin/.env` e `service/.env`). Em implantações bare-metal (manuais), atualize os endereços manualmente ao alterar as portas.

### 3.3 Inicialização do banco de dados

```bash
# Os arquivos de migração são executados automaticamente no primeiro start do MySQL
# Ou executar manualmente:
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform < install/install.sql
```

### 3.4 Persistência de dados

Os volumes de dados são criados automaticamente, sem gerenciamento manual:

| Volume | Caminho | Conteúdo |
|----|------|------|
| mysql_data | /var/lib/mysql | arquivos do banco |
| redis_data | /data | persistência do Redis |
| es_data | /usr/share/elasticsearch/data | índices do ES |

Backup:
```bash
# Backup do MySQL
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game-platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Restauração
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game-platform
```

---

## 4. Implantação manual

### 4.1 Configuração do ambiente PHP

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Habilitar OPcache (obrigatório em produção)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 Instalar dependências

```bash
cd /opt/game-platform

# Painel administrativo
cd admin
cp .env.example .env
# Editar .env: conexão com o banco, JWT_SECRET, HASHIDS_SALT etc.
composer install --no-dev --optimize-autoloader

# Negócio C-side
cd ../service
cp .env.example .env
# Editar .env (atenção: SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 Configurar o .env

**Configurações-chave de admin/.env:**
```ini
APP_ENV=production
APP_DEBUG=false
APP_PORT=8789  # porta de escuta HTTP do webman (deve ser igual ao APP_URL)
APP_URL=http://localhost:8789  # endereço de acesso externo (baseUrl da documentação da API, etc.)

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game-platform
DB_USERNAME=game-platform
DB_PASSWORD=<senha forte>

JWT_SECRET=<string aleatória de 64 caracteres>
JWT_TTL=7200

HASHIDS_SALT=<salt aleatório>
HASHIDS_MIN_LENGTH=16

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=1

ENCRYPTION_KEY=<chave aleatória de 32 bytes>
ENCRYPTABLE_KEY=<chave AES aleatória>

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=<senha do Redis>

SCOUT_HOSTS=127.0.0.1:9200
```

**Configurações-chave de service/.env:**
```ini
# Mesmas configurações de banco, Redis e ES que o admin
APP_PORT=8792  # porta de escuta HTTP do webman
LEADERBOARD_WS_PORT=8790  # WebSocket de rankings (deve coincidir com o endereço de conexão do frontend)
CHAT_WS_PORT=8791  # WebSocket de chat
SNOWFLAKE_WORKER_ID=2  # deve ser diferente do admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<obtido no Google Cloud Console>
OAUTH_GOOGLE_CLIENT_SECRET=<segredo>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Webhook de pagamento
STRIPE_SECRET_KEY=<chave secreta Stripe>
STRIPE_WEBHOOK_SECRET=<obtido no Stripe Dashboard>
PAYPAL_WEBHOOK_ID=<obtido no PayPal Developer>
PAYPAL_VERIFY_URL=<endereço de verificação do webhook PayPal>
PAYPAL_CLIENT_ID=<PayPal Client ID>
PAYPAL_CLIENT_SECRET=<PayPal Client Secret>
PAYPAL_MODE=sandbox  # sandbox / live
NOWPAYMENTS_API_KEY=<chave API NOWPayments>
NOWPAYMENTS_IPN_SECRET=<chave de assinatura IPN>
NOWPAYMENTS_API_URL=https://api.nowpayments.io  # endereço padrão
COINBASE_COMMERCE_API_KEY=<chave API Coinbase Commerce>
COINBASE_COMMERCE_WEBHOOK_SECRET=<chave de webhook Coinbase Commerce>
SKRILL_API_URL=https://pay.skrill.com
SKRILL_API_KEY=<chave API Skrill>
SKRILL_MERCHANT_ID=<ID de comerciante Skrill>
SKRILL_SECRET_WORD=<chave de verificação de assinatura md5sig>
NETELLER_API_URL=https://api.neteller.com
NETELLER_CLIENT_ID=<Neteller Client ID>
NETELLER_CLIENT_SECRET=<Neteller Client Secret>
NETELLER_SECRET=<chave de verificação de assinatura do callback>
PAYSAFECARD_API_URL=https://api.paysafecard.com
PAYSAFECARD_API_KEY=<chave API Paysafecard>
PAYSAFECARD_SECRET=<chave de verificação X-Signature HMAC-SHA256>
PAYTM_MID=<ID de comerciante Paytm>
PAYTM_KEY=<chave Paytm>
PAYTM_API_URL=https://securegw.paytm.in
PAYTM_WEBSITE=DEFAULT  # produção DEFAULT / staging WEBSTAGING
MERCADOPAGO_CLIENT_ID=<Mercado Pago Client ID>
MERCADOPAGO_CLIENT_SECRET=<Mercado Pago Client Secret>
MERCADOPAGO_WEBHOOK_SECRET=<chave de verificação X-Signature>
MERCADOPAGO_API_URL=https://api.mercadopago.com
ASTROPAY_LOGIN=<nome de login AstroPay>
ASTROPAY_API_KEY=<chave API AstroPay>
ASTROPAY_SECRET=<chave de verificação de assinatura MD5>
ASTROPAY_API_URL=https://api.astropaycard.com
PAYPAY_CLIENT_ID=<PayPay Client ID>
PAYPAY_CLIENT_SECRET=<PayPay Client Secret>
PAYPAY_SIGNING_KEY=<chave de verificação PayPay-Signature>
PAYPAY_API_URL=https://api.paypay.ne.jp
KAKAOPAY_ADMIN_KEY=<KakaoPay Admin Key>
KAKAOPAY_CID=<CID de comerciante KakaoPay>
KAKAOPAY_APPROVAL_URL=<URL de redirecionamento após pagamento>
KAKAOPAY_API_URL=https://kapi.kakao.com
PAYMONGO_API_KEY=<chave API PayMongo>
PAYMONGO_WEBHOOK_SECRET=<chave de verificação Paymongo-Signature>
PAYMONGO_API_URL=https://api.paymongo.com/v1
# M-Pesa / Paystack / Toss
MPESA_CONSUMER_KEY=<M-Pesa Consumer Key>
MPESA_CONSUMER_SECRET=<M-Pesa Consumer Secret>
MPESA_PASSKEY=<M-Pesa STK Push Passkey>
MPESA_SHORTCODE=<código curto M-Pesa>
MPESA_API_URL=https://api.safaricom.co.ke
PAYSTACK_SECRET_KEY=<Paystack Secret Key>
PAYSTACK_API_URL=https://api.paystack.co
TOSS_SECRET_KEY=<Toss Secret Key>
TOSS_API_URL=https://api.tosspayments.com
SITE_URL=https://your-domain.com  # URL do site para callbacks/redirecionamentos de pagamento
```

### 4.4 Iniciar os serviços

```bash
# Painel administrativo (porta padrão 8789, alterável via APP_PORT em admin/.env)
cd /opt/game-platform/admin
php start.php start -d

# Negócio C-side (porta padrão 8792, alterável via APP_PORT em service/.env)
cd /opt/game-platform/service
php start.php start -d

# Verificação
curl http://localhost:8789/health
curl http://localhost:8792/health
```

### 4.5 Gerenciamento de processos (Systemd)

Crie `/etc/systemd/system/game-platform-admin.service`:

```ini
[Unit]
Description=Game Platform Admin
After=network.target mysql.service redis.service

[Service]
Type=forking
User=www-data
Group=www-data
WorkingDirectory=/opt/game-platform/admin
ExecStart=/usr/bin/php start.php start -d
ExecStop=/usr/bin/php start.php stop
ExecReload=/usr/bin/php start.php reload
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Crie também `game-platform-service.service` (altere WorkingDirectory para `/opt/game-platform/service`).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Proxy reverso Nginx

### 5.1 Arquivo de configuração

Crie `/etc/nginx/sites-available/game-platform`:

```nginx
# Portas com valores padrão (admin 8789 / service 8792 / ws 8790); se o .env foi alterado, ajuste-as também
server {
    listen 80;
    server_name your-domain.com;

    # nginx 自身发出的 301（如目录补斜杠 /admin-panel → /admin-panel/）改用相对
    # Location，客户端按当前 host:port 解析；默认绝对跳转会退回 listen 端口，
    # 非 80 端口部署（如 8080）时会跳错端口。
    absolute_redirect off;

    # API do painel administrativo
    location /admin/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # API C-side
    location /api/ {
        proxy_pass http://127.0.0.1:8792;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Rankings WebSocket (porta padrão 8790, igual ao LEADERBOARD_WS_PORT em service/.env)
    location /ws/ {
        proxy_pass http://127.0.0.1:8790;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Health check
    location /health {
        proxy_pass http://127.0.0.1:8789;
    }

    # Prometheus 指标
    location /metrics {
        proxy_pass http://127.0.0.1:8789;
    }

    # ================================================================
    # 静态前端。两套前端定位不同：
    #   apps/*         = C 端玩家端（调 /api/ → service）
    #   admin/apps/*   = 管理台（调 /admin/ → admin）
    # 各产物需先构建；React/Angular 必须带子路径前缀构建，否则资源 404：
    #   apps/react            npm run build                （已含 --base=/app-react/）
    #   apps/angular          npm run build                （已含 --base-href=/app-angular/）
    #   admin/apps/react      npm run build                （已含 --base=/admin-react/）
    #   admin/apps/angular    npm run build                （已含 --base-href=/admin-angular/）
    #   admin/apps/flutter    flutter build web --base-href=/admin-flutter/
    #   apps/flutter/platform flutter build web            （挂在根路径）
    # try_files 末项是【内部重定向】，目标 index.html 不存在时会重新匹配同一 location
    # 形成重定向环，nginx 报 500 而非 404。规避方式按 location 类型二选一：
    #   root  型 → 末项追加 =404，把它降级为文件存在性判断；
    #   alias 型 → 追加 =404 会让兜底不再经 alias 解析，已构建的 SPA 深链接也会 404，
    #              所以保留原样，另加 location = 精确匹配兜底 URI（精确匹配优先，
    #              不会再回到前缀 location，环不成立）。
    # alias 的结尾斜杠必须与 location 的结尾斜杠一致（location /x 配 alias .../x，
    # location /x/ 配 alias .../x/）。错配时 /x../<路径> 会越级解析到上级目录，可读
    # 取 docroot 之外的任意文件，且 nginx -t 完全查不出来。
    # ================================================================

    # C 端主入口 — Flutter Web
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html =404;
    }

    # C 端 React / Angular Web（URL 前缀与产物目录名不同，用 alias 直接指向产物）
    location /app-react/ {
        alias /opt/game-platform/apps/react/dist/;
        try_files $uri $uri/ /app-react/index.html;
    }
    location = /app-react/index.html {
        alias /opt/game-platform/apps/react/dist/index.html;
    }

    location /app-angular/ {
        alias /opt/game-platform/apps/angular/dist/game-client-angular/browser/;
        try_files $uri $uri/ /app-angular/index.html;
    }
    location = /app-angular/index.html {
        alias /opt/game-platform/apps/angular/dist/game-client-angular/browser/index.html;
    }

    # 管理台 — 通用投放位：把任一控制台产物拷进 admin/public 即可
    # 注意：location 不以 / 结尾时 alias 也【不能】以 / 结尾，否则 /admin-panel../.env
    # 会解析到上级目录（admin/.env）造成任意文件读取；nginx -t 查不出这类错配。
    location /admin-panel {
        alias /opt/game-platform/admin/public;
        try_files $uri $uri/ /admin-panel/index.html;
    }
    location = /admin-panel/index.html {
        alias /opt/game-platform/admin/public/index.html;
    }

    # 管理台 React / Angular / Flutter
    location /admin-react/ {
        alias /opt/game-platform/admin/apps/react/dist/;
        try_files $uri $uri/ /admin-react/index.html;
    }
    location = /admin-react/index.html {
        alias /opt/game-platform/admin/apps/react/dist/index.html;
    }

    location /admin-angular/ {
        alias /opt/game-platform/admin/apps/angular/dist/game-admin-angular/browser/;
        try_files $uri $uri/ /admin-angular/index.html;
    }
    location = /admin-angular/index.html {
        alias /opt/game-platform/admin/apps/angular/dist/game-admin-angular/browser/index.html;
    }

    location /admin-flutter/ {
        alias /opt/game-platform/admin/apps/flutter/build/web/;
        try_files $uri $uri/ /admin-flutter/index.html;
    }
    location = /admin-flutter/index.html {
        alias /opt/game-platform/admin/apps/flutter/build/web/index.html;
    }
}
```

> No deployment manual você mesmo coloca os artefatos compilados nesses diretórios (quatro árvores do lado C: `apps/flutter/platform`, `apps/react`, `apps/angular`, `apps/harmonyos`; os frontends de console ficam todos montados em `admin/apps/*` e no ponto genérico `admin/public`).
> Para Docker, veja os mounts de volume do nginx em `docker-compose.yml` e `nginx.conf.template` (os mesmos caminhos, raiz do contêiner `/var/www/...`). O HarmonyOS é distribuído como `.hap` e não passa pelo nginx.

Habilitar o site:
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 Certificado SSL

```bash
# Usar o Certbot para obter certificado Let's Encrypt automaticamente
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Renovação automática (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. Tarefas agendadas (Crontab)

```bash
# Editar o crontab
crontab -e

# Snapshot de estatísticas diárias (todo dia à 1:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# Backup do banco (todo dia às 2:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# Renovação automática do certificado SSL
0 3 * * * certbot renew --quiet && systemctl reload nginx

# Refresh do cache de rankings (a cada hora)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. Monitoramento

### 7.1 Métricas Prometheus

O painel administrativo expõe o endpoint `/metrics`, com as seguintes métricas:

| Métrica | Observação |
|------|------|
| openadmin_http_requests_total | total de requisições |
| openadmin_active_users | usuários ativos |
| openadmin_db_connection_status | conexão com o banco (0/1) |
| openadmin_redis_connection_status | conexão com o Redis (0/1) |
| openadmin_memory_usage_bytes | uso de memória |

### 7.2 Health check

```bash
# Painel administrativo
curl -f http://localhost:8789/health || echo "Admin DOWN"

# Negócio C-side
curl -f http://localhost:8792/health || echo "Service DOWN"

# Pode ser configurado no balanceador de carga ou sistema de monitoramento
```

### 7.3 Logs

```
admin/runtime/logs/
├── stdout.log          # saída padrão
└── webman-<date>.log   # logs do Webman

service/runtime/logs/
├── stdout.log
└── webman-<date>.log
```

---

## 8. Otimização de performance

### 8.1 PHP OPcache

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # desligar verificação de arquivos em produção
```

### 8.2 Otimização do MySQL

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # definir como 50-70% da memória física
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # priorizar performance
max_connections = 200
query_cache_type = 0               # removido no MySQL 8.0
```

### 8.3 Número de processos worker

```php
// config/process.php
'count' => cpu_count() * 2,  // recomendado 2-4x o número de núcleos da CPU em produção
```

### 8.4 Estratégia de cache Redis

| Chave de cache | TTL | Observação |
|--------|-----|------|
| dashboard:data | 300s | dados do dashboard |
| i18n:translations | 3600s | textos traduzidos |
| leaderboard:{id} | 3600s | rankings |
| rate_limit:{ip}:{route} | 60s | janela de rate limit |

---

## 9. Reforço de segurança

### 9.1 Geração de chaves

```bash
# Gerar chaves aleatórias
JWT_SECRET=$(openssl rand -hex 32)
HASHIDS_SALT=$(openssl rand -hex 16)
ENCRYPTION_KEY=$(openssl rand -hex 16)
ENCRYPTABLE_KEY=$(openssl rand -hex 16)
REDIS_PASSWORD=$(openssl rand -hex 16)
DB_PASSWORD=$(openssl rand -hex 16)

echo "JWT_SECRET=$JWT_SECRET"
echo "HASHIDS_SALT=$HASHIDS_SALT"
echo "ENCRYPTION_KEY=$ENCRYPTION_KEY"
echo "ENCRYPTABLE_KEY=$ENCRYPTABLE_KEY"
```

### 9.2 Firewall

```bash
# Abrir apenas as portas necessárias
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Portas internas não devem ser expostas
# 8789 (admin), 8792 (service), 8790/8791 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# Estas são as portas padrão; se o .env da raiz ou os .env respectivos foram alterados, valem os valores reais
# Acessar apenas via 127.0.0.1
```

### 9.3 Permissões de arquivos

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. Solução de problemas

### 10.1 Serviço não inicia

```bash
# Executar em primeiro plano para ver o erro
cd /opt/game-platform/admin && php start.php start

# Verificar ocupação de portas
ss -tlnp | grep -E '8789|8792'

# Verificar logs
tail -f runtime/logs/webman-$(date +%F).log
```

### 10.2 Falha de conexão com o banco

```bash
# Testar conexão
mysql -h 127.0.0.1 -u game-platform -p game-platform -e "SELECT 1"

# Verificar a configuração do .env
grep DB_ admin/.env
```

### 10.3 Falha de conexão com o Redis

```bash
# Testar conexão
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# Espera-se retorno PONG
```

### 10.4 Elasticsearch indisponível

```bash
# Testar conexão
curl http://127.0.0.1:9200

# A busca cai automaticamente para consulta LIKE, sem interromper o serviço
```

### 10.5 Problemas de performance

```bash
# Verificar número de processos worker
php start.php status

# Ver uso de memória
free -h

# Verificar consultas lentas do banco
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. Guia de upgrade

```bash
# 1. Baixar o código mais recente
cd /opt/game-platform && git pull origin main

# 2. Atualizar dependências
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. Executar novas migrações (se houver)
mysql -u game-platform -p game-platform < install/install.sql

# 4. Reinício suave (sem interromper o serviço)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
