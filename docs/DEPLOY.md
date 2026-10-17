# Deploying a public demo on an Ubuntu VPS

This sets up AgriTrack on a fresh Ubuntu 22.04 or 24.04 server, with HTTPS from [Caddy](https://caddyserver.com) and the demo reset every 6 hours. With `DEMO_MODE=1` the demo logins are shown on the site and protected:

- Their passwords can't be changed.
- No real email or SMS is ever sent.
- New registrations are verified automatically.

A server with 1 GB of RAM is enough. You also need a domain or subdomain whose DNS you control.

Replace `demo.example.com` below with your domain.

## 1. Point the domain at the server

Create an `A` record for `demo.example.com` with the server's public IPv4 address, and an `AAAA` record if it has IPv6. Do this first: Caddy can only get a certificate once the name resolves to the server. To check:

```bash
dig +short demo.example.com      # must print the server's IP
```

## 2. Install Docker

Run these commands as a user with `sudo`:

```bash
sudo apt-get update && sudo apt-get install -y ca-certificates curl git
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh                 # Docker's official install script
sudo usermod -aG docker "$USER"       # then log out and back in
docker compose version                # needs 2.24.4 or newer
```

Open only SSH and the web ports in the firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp
sudo ufw enable
```

Only the Caddy container publishes ports (80 and 443). The app container and the database are reachable only on the internal Docker network.

## 3. Clone the repository

```bash
git clone https://github.com/f4zzie/agri_app.git
cd agri_app
```

## 4. Create `.env` with strong random passwords

```bash
cat > .env <<EOF
DOMAIN=demo.example.com
DB_PASS=$(openssl rand -hex 32)
DB_ROOT_PASS=$(openssl rand -hex 32)
DEMO_MODE=1
EOF
chmod 600 .env
```

`docker-compose.prod.yml` reads its settings from this file, and Compose refuses to start if `DOMAIN`, `DB_PASS` or `DB_ROOT_PASS` is missing. You can also add `SMTP_*`, `GOOGLE_*` and `AT_*` values (see `.env.example`). Email and SMS stay switched off while `DEMO_MODE=1`.

> The database passwords are set when the database volume is first created. Changing them in `.env` later does not change them in MariaDB. To start over with new passwords, run `docker compose -f docker-compose.yml -f docker-compose.prod.yml down -v`. This deletes all data.

## 5. Start the stack

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

On the first start:

1. The app container waits for MariaDB.
2. It applies the migrations and seeds the demo data.
3. Caddy requests a Let's Encrypt certificate.

After a minute, open `https://demo.example.com` and log in with `farmer@agritrack.test` or `buyer@agritrack.test` (password `Demo@1234`).

To check the containers and read the logs:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f caddy app
```

If the certificate fails, check that DNS points at this server and that ports 80 and 443 are open.

## 6. Reset the demo every 6 hours

`bin/reset_demo.php` does three things:

- Recreates the database.
- Re-seeds the demo data.
- Deletes everything visitors uploaded.

It refuses to run unless `DEMO_MODE=1`. To run it once by hand:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T app php bin/reset_demo.php
```

To schedule it, run `crontab -e` and add this line. It resets the demo every 6 hours, at 00:00, 06:00, 12:00 and 18:00 server time. Use the full path to your clone.

```cron
0 */6 * * * cd /home/ubuntu/agri_app && docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T app php bin/reset_demo.php >> /home/ubuntu/agritrack-reset.log 2>&1
```

`-T` is required because cron has no terminal.

## 7. Update the app later

```bash
cd ~/agri_app
git pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

New migrations are applied automatically when the app container starts. Data and uploads are kept in Docker volumes across updates. To also clean up old images, run `docker image prune -f`.
