# Document de déploiement
<!-- lang-nav -->

Languages: [中文](DEPLOYMENT.md) · [English](DEPLOYMENT.en.md) · [한국어](DEPLOYMENT.ko.md) · [Русский](DEPLOYMENT.ru.md) · [Deutsch](DEPLOYMENT.de.md) · **Français** · [Español](DEPLOYMENT.es.md) · [Português](DEPLOYMENT.pt.md) · [हिन्दी](DEPLOYMENT.hi.md) · [العربية](DEPLOYMENT.ar.md) · [বাংলা](DEPLOYMENT.bn.md) · [Bahasa Indonesia](DEPLOYMENT.id.md) · [日本語](DEPLOYMENT.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Prérequis d'environnement

| Composant | Version minimale | Configuration recommandée |
|------|---------|---------|
| OS | Linux (Ubuntu 20.04+ / Debian 11+ / CentOS 8+) | Ubuntu 22.04 LTS |
| PHP | 8.3+ | 8.3+ (CLI, OPcache activé) |
| Extensions PHP | pdo, pdo_mysql, pcntl, redis, gd, mbstring, xml | Toutes |
| MySQL | 8.0+ | 8.0+ réplication maître-esclave |
| Redis | 6.0+ | 7.x mode sentinel |
| Elasticsearch | 7.x+ | 8.x nœud unique |
| Nginx | 1.20+ | Reverse proxy + gzip + SSL |
| Composer | 2.x | Dernière version stable |
| Flutter SDK | 3.x+ | Dernière version stable (requis uniquement pour construire le frontend) |

---

## 2. Assistant d'installation en un clic (recommandé pour les nouveaux déploiements)

```bash
# 1. Cloner le projet
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Démarrer l'assistant d'installation
php -S 0.0.0.0:8888 -t install/

# 3. Ouvrir http://<IP-du-serveur>:8888 dans le navigateur
#    Suivre l'assistant : vérification de l'environnement → configuration de la base → compte administrateur → installation automatique

# 4. Installer les dépendances
cd admin && composer install && cd ..
cd service && composer install && cd ..

# 5. Démarrer les services
cd admin && php start.php start -d && cd ..
cd service && php start.php start -d && cd ..

# 6. Nettoyage de sécurité
rm -rf install/

# 7. Accéder à l'administration : http://<IP-du-serveur>:8787
```

Opérations réalisées par l'assistant d'installation :
- Vérification de l'environnement PHP (version, extensions, permissions des répertoires)
- Exécution du SQL fusionné (`install/install.sql`), création des 52 tables et import des données de seed
- Création du compte super administrateur (chiffré bcrypt, lié au rôle super_admin)
- Génération automatique des clés JWT/Encryption/Hashids
- Écriture de `admin/.env` et `service/.env`
- Génération de `install/install.lock` pour empêcher les réinstallations

---

## 3. Déploiement Docker Compose

### 3.1 Démarrage en un clic

```bash
# 1. Cloner le projet
git clone <repo-url> /opt/game-platform
cd /opt/game-platform

# 2. Configurer l'environnement avec l'assistant d'installation en un clic (ou configurer manuellement les fichiers .env)
php -S 0.0.0.0:8888 -t install/
# Manuel : cp admin/.env.example admin/.env && cp service/.env.example service/.env

# 3. Construire et démarrer tous les services
docker-compose up -d

# 4. Voir l'état
docker-compose ps

# 5. Voir les journaux
docker-compose logs -f
```

### 3.2 Liste des services

| Service | Nom du conteneur | Port | Description |
|------|--------|------|------|
| nginx | game-platform-nginx | 80, 443 | Reverse proxy + fichiers statiques |
| admin | game-platform-admin | 8787 | API d'administration |
| service | game-platform-service | 8788 | API métier C |
| leaderboard-ws | game-platform-ws | 8789 | Classement WebSocket |
| mysql | game-platform-mysql | 3306 | Base principale |
| redis | game-platform-redis | 6379 | Cache/rate-limit |
| elasticsearch | game-platform-es | 9200 | Recherche plein texte |

### 3.3 Initialisation de la base

```bash
# Les fichiers de migration s'exécutent automatiquement au premier démarrage de MySQL
# ou manuellement :
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_16_000000_init_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000003_platform_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000004_i18n_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000005_standard_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000006_complete_tables.sql
docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform < admin/database/migrations/2026_05_22_000007_production_tables.sql
```

### 3.4 Persistance des données

Les volumes de données sont créés automatiquement, aucune gestion manuelle requise :

| Volume | Chemin | Contenu |
|----|------|------|
| mysql_data | /var/lib/mysql | Fichiers de base de données |
| redis_data | /data | Persistance Redis |
| es_data | /usr/share/elasticsearch/data | Index ES |

Sauvegarde :
```bash
# Sauvegarde MySQL
docker exec game-platform-mysql mysqldump -uroot -p${DB_PASSWORD} game_platform | gzip > backup_$(date +%Y%m%d).sql.gz

# Restauration
gunzip < backup_20260101.sql.gz | docker exec -i game-platform-mysql mysql -uroot -p${DB_PASSWORD} game_platform
```

---

## 4. Déploiement manuel

### 4.1 Configuration de l'environnement PHP

```bash
# Ubuntu/Debian
apt update && apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# CentOS/RHEL
dnf install -y php8.3-cli php8.3-mysqlnd php8.3-mbstring php8.3-gd php8.3-xml php8.3-pcntl php8.3-redis unzip git

# Activer OPcache (obligatoire en production)
echo "opcache.enable=1" >> /etc/php/8.3/cli/php.ini
echo "opcache.enable_cli=1" >> /etc/php/8.3/cli/php.ini
```

### 4.2 Installation des dépendances

```bash
cd /opt/game-platform

# Administration
cd admin
cp .env.example .env
# Éditer .env : connexion base de données, JWT_SECRET, HASHIDS_SALT, etc.
composer install --no-dev --optimize-autoloader

# Métier C
cd ../service
cp .env.example .env
# Éditer .env (attention : SNOWFLAKE_WORKER_ID=2)
composer install --no-dev --optimize-autoloader
```

### 4.3 Configuration du .env

**Configuration clé de admin/.env :**
```ini
APP_ENV=production
APP_DEBUG=false

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=game_platform
DB_USERNAME=game_platform
DB_PASSWORD=<mot de passe fort>

JWT_SECRET=<chaîne aléatoire de 64 caractères>
JWT_TTL=7200

HASHIDS_SALT=<sel aléatoire>
HASHIDS_MIN_LENGTH=16

SNOWFLAKE_DATACENTER_ID=1
SNOWFLAKE_WORKER_ID=1

ENCRYPTION_KEY=<clé aléatoire de 32 octets>
ENCRYPTABLE_KEY=<clé AES aléatoire>

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=<mot de passe Redis>

SCOUT_HOSTS=127.0.0.1:9200
```

**Configuration clé de service/.env :**
```ini
# Mêmes configurations base de données, Redis, ES que admin
SNOWFLAKE_WORKER_ID=2  # doit différer de admin

# OAuth
OAUTH_GOOGLE_CLIENT_ID=<obtenu sur Google Cloud Console>
OAUTH_GOOGLE_CLIENT_SECRET=<secret>
OAUTH_GOOGLE_REDIRECT_URI=https://your-domain.com/api/auth/oauth/google/callback

# Webhooks de paiement
STRIPE_WEBHOOK_SECRET=<obtenu sur le dashboard Stripe>
PAYPAL_WEBHOOK_ID=<obtenu sur PayPal Developer>
```

### 4.4 Démarrage des services

```bash
# Administration (port 8787)
cd /opt/game-platform/admin
php start.php start -d

# Métier C (port 8788)
cd /opt/game-platform/service
php start.php start -d

# Vérification
curl http://localhost:8787/health
curl http://localhost:8788/health
```

### 4.5 Gestion des processus (Systemd)

Créer `/etc/systemd/system/game-platform-admin.service` :

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

Créer de même `game-platform-service.service` (modifier WorkingDirectory en `/opt/game-platform/service`).

```bash
systemctl daemon-reload
systemctl enable --now game-platform-admin game-platform-service
```

---

## 5. Reverse proxy Nginx

### 5.1 Fichier de configuration

Créer `/etc/nginx/sites-available/game-platform` :

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # API d'administration
    location /admin/ {
        proxy_pass http://127.0.0.1:8787;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # API C
    location /api/ {
        proxy_pass http://127.0.0.1:8788;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Classement WebSocket
    location /ws/ {
        proxy_pass http://127.0.0.1:8789;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Health check
    location /health {
        proxy_pass http://127.0.0.1:8787;
    }

    # Métriques Prometheus
    location /metrics {
        proxy_pass http://127.0.0.1:8787;
    }

    # Frontend d'administration
    location /admin-panel {
        alias /opt/game-platform/admin/apps/flutter/build/web;
        try_files $uri $uri/ /admin-panel/index.html;
    }

    # Frontend de la plateforme C
    location / {
        root /opt/game-platform/apps/flutter/platform/build/web;
        try_files $uri $uri/ /index.html;
    }
}
```

Activer le site :
```bash
ln -s /etc/nginx/sites-available/game-platform /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 5.2 Certificat SSL

```bash
# Obtenir automatiquement un certificat Let's Encrypt avec Certbot
apt install certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# Renouvellement automatique (crontab)
0 3 * * * certbot renew --quiet && systemctl reload nginx
```

---

## 6. Tâches planifiées (Crontab)

```bash
# Éditer le crontab
crontab -e

# Instantané des statistiques quotidiennes (tous les jours à 1:00)
0 1 * * * cd /opt/game-platform/admin && php start.php queue ComputeDailyStats

# Sauvegarde de la base (tous les jours à 2:00)
0 2 * * * cd /opt/game-platform/admin/database/backup && bash backup.sh

# Renouvellement automatique du certificat SSL
0 3 * * * certbot renew --quiet && systemctl reload nginx

# Rafraîchissement du cache de classement (toutes les heures)
0 * * * * cd /opt/game-platform/admin && php start.php queue RefreshLeaderboards
```

---

## 7. Monitoring

### 7.1 Métriques Prometheus

L'administration expose le point `/metrics`, avec les métriques suivantes :

| Métrique | Description |
|------|------|
| openadmin_http_requests_total | Nombre total de requêtes |
| openadmin_active_users | Nombre d'utilisateurs actifs |
| openadmin_db_connection_status | Connexion base de données (0/1) |
| openadmin_redis_connection_status | Connexion Redis (0/1) |
| openadmin_memory_usage_bytes | Utilisation mémoire |

### 7.2 Health check

```bash
# Administration
curl -f http://localhost:8787/health || echo "Admin DOWN"

# Métier C
curl -f http://localhost:8788/health || echo "Service DOWN"

# Peut être configuré dans l'équilibreur de charge ou le système de monitoring
```

### 7.3 Journaux

```
admin/runtime/logs/
├── stdout.log          # Sortie standard
└── workerman.log       # Journal Workerman

service/runtime/logs/
├── stdout.log
└── workerman.log
```

---

## 8. Optimisation des performances

### 8.1 OPcache PHP

```ini
; /etc/php/8.3/cli/php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # désactiver le contrôle des fichiers en production
```

### 8.2 Optimisation MySQL

```ini
# /etc/mysql/conf.d/game-platform.cnf
[mysqld]
innodb_buffer_pool_size = 2G       # 50-70% de la mémoire physique
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2 # priorité performance
max_connections = 200
query_cache_type = 0               # retiré dans MySQL 8.0
```

### 8.3 Nombre de workers

```php
// config/process.php
'count' => cpu_count() * 2,  // en production, 2-4 fois le nombre de cœurs CPU
```

### 8.4 Stratégie de cache Redis

| Clé de cache | TTL | Description |
|--------|-----|------|
| dashboard:data | 300s | Données du tableau de bord |
| i18n:translations | 3600s | Textes de traduction |
| leaderboard:{id} | 3600s | Classements |
| rate_limit:{ip}:{route} | 60s | Fenêtre de rate-limit |

---

## 9. Durcissement de la sécurité

### 9.1 Génération des clés

```bash
# Générer des clés aléatoires
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

### 9.2 Pare-feu

```bash
# N'ouvrir que les ports nécessaires
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw enable

# Les ports internes ne doivent pas être exposés
# 8787 (admin), 8788 (service), 8789 (ws), 3306 (mysql), 6379 (redis), 9200 (es)
# Accessibles uniquement via 127.0.0.1
```

### 9.3 Permissions des fichiers

```bash
chown -R www-data:www-data /opt/game-platform
chmod -R 755 /opt/game-platform
chmod -R 775 /opt/game-platform/admin/runtime
chmod -R 775 /opt/game-platform/service/runtime
chmod 600 /opt/game-platform/admin/.env
chmod 600 /opt/game-platform/service/.env
```

---

## 10. Dépannage

### 10.1 Le service ne démarre pas

```bash
# Exécuter au premier plan pour voir l'erreur
cd /opt/game-platform/admin && php start.php start

# Vérifier l'occupation des ports
ss -tlnp | grep -E '8787|8788'

# Vérifier les journaux
tail -f runtime/logs/workerman.log
```

### 10.2 Échec de connexion à la base

```bash
# Tester la connexion
mysql -h 127.0.0.1 -u game_platform -p game_platform -e "SELECT 1"

# Vérifier la configuration .env
grep DB_ admin/.env
```

### 10.3 Échec de connexion Redis

```bash
# Tester la connexion
redis-cli -h 127.0.0.1 -p 6379 -a <password> ping

# Retour attendu : PONG
```

### 10.4 Elasticsearch indisponible

```bash
# Tester la connexion
curl http://127.0.0.1:9200

# La fonction de recherche retombe automatiquement sur les requêtes LIKE, sans interruption de service
```

### 10.5 Problèmes de performance

```bash
# Vérifier le nombre de workers
php start.php status

# Voir l'utilisation mémoire
free -h

# Vérifier les requêtes lentes de la base
mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
```

---

## 11. Guide de mise à niveau

```bash
# 1. Récupérer le dernier code
cd /opt/game-platform && git pull origin main

# 2. Mettre à jour les dépendances
cd admin && composer install --no-dev --optimize-autoloader
cd ../service && composer install --no-dev --optimize-autoloader

# 3. Exécuter les nouvelles migrations (si nécessaire)
mysql -u game_platform -p game_platform < admin/database/migrations/nouveau-fichier-de-migration.sql

# 4. Redémarrage à chaud (sans interruption de service)
cd /opt/game-platform/admin && php start.php reload
cd /opt/game-platform/service && php start.php reload
```
