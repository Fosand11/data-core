# Despliegue gratuito en Oracle Cloud (Always Free)

Guía para publicar InsightFlow en una VM ARM gratuita de Oracle Cloud con Docker
Compose. Todo corre en una sola máquina: Caddy (HTTPS automático), PHP-FPM, el
worker de la cola, PostgreSQL y Redis. Ni PostgreSQL ni Redis se publican fuera
de la red de Docker.

> Los límites y la interfaz de Oracle cambian con el tiempo. Comprueba la
> política vigente de "Always Free" en la documentación oficial antes de empezar.

## Qué necesitas

- Una cuenta de Oracle Cloud con Always Free.
- Un nombre de dominio que apunte a la VM. Sirve un subdominio gratuito de
  [DuckDNS](https://www.duckdns.org) (por ejemplo `miapp.duckdns.org`) o uno propio.
- Una clave de [Google AI Studio](https://aistudio.google.com) para Gemini.
- El repositorio accesible por HTTPS desde la VM.

## 1. Crear la VM

En _Compute → Instances → Create instance_:

- **Imagen:** Canonical Ubuntu 24.04 (versión `aarch64`).
- **Forma:** `VM.Standard.A1.Flex` (Ampere ARM). 2 OCPU y 12 GB de RAM bastan
  (el límite gratuito es mayor).
- **Volumen de arranque:** 50 GB.
- **Red:** con IP pública. Añade tu clave SSH pública (`ssh-keygen -t ed25519`).

Si Oracle responde _Out of capacity_, reintenta más tarde o prueba otro dominio de
disponibilidad. Es habitual en las formas ARM gratuitas.

## 2. Abrir los puertos

En la VCN de la instancia (_Networking → Virtual Cloud Networks → Security Lists_)
añade reglas de entrada desde `0.0.0.0/0`:

| Protocolo | Puerto | Para qué |
| --- | --- | --- |
| TCP | 80 | Certificado y redirección a HTTPS |
| TCP | 443 | La aplicación |
| UDP | 443 | HTTP/3 (opcional) |

Deja el puerto 22 (SSH) restringido a tu IP si puedes.

Las imágenes Ubuntu de Oracle traen además reglas de `iptables` que rechazan lo
demás. Conéctate (`ssh ubuntu@IP_DE_LA_VM`) y ábrelos también en la máquina:

```bash
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p udp --dport 443 -j ACCEPT
sudo apt-get install -y iptables-persistent
sudo netfilter-persistent save
```

Comprueba con `sudo iptables -L INPUT --line-numbers` que esas reglas quedan
**antes** de la regla `REJECT`.

## 3. Instalar Docker

```bash
sudo apt-get update && sudo apt-get install -y git curl
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"
```

Cierra la sesión SSH y vuelve a entrar. Comprueba `docker compose version`.

## 4. Dominio

Apunta el dominio a la IP pública de la VM (en DuckDNS, el campo _current ip_) y
verifica que resuelve:

```bash
dig +short miapp.duckdns.org
```

## 5. Configurar la aplicación

```bash
git clone https://github.com/TU_USUARIO/TU_REPO.git insightflow
cd insightflow
cp .env.production.example .env.production
chmod 600 .env.production
```

Edita `.env.production` y rellena, como mínimo:

| Variable | Valor |
| --- | --- |
| `APP_URL` | `https://miapp.duckdns.org` |
| `APP_DOMAIN` | `miapp.duckdns.org` (sin `https://`) |
| `DB_PASSWORD` y `REDIS_PASSWORD` | `openssl rand -hex 24` (uno distinto para cada una) |
| `GEMINI_API_KEY` | tu clave de AI Studio |
| `DEMO_USER_EMAIL` y `DEMO_USER_PASSWORD` | la cuenta de demostración (mínimo 12 caracteres) |
| `DASHBOARD_AI_GLOBAL_PER_DAY` | generaciones diarias que toleras, compartidas por todos |

Este archivo **nunca** se sube a git (`.env.production` está en `.gitignore`).

Para escribir menos, define un alias (añádelo a `~/.bashrc` para conservarlo):

```bash
alias dc='docker compose --env-file .env.production -f compose.prod.yaml'
```

## 6. Construir y arrancar

```bash
dc build                                    # 5-10 min la primera vez en ARM
dc run --rm app php artisan key:generate --show
```

Copia el resultado (`base64:...`) en `APP_KEY` dentro de `.env.production` y arranca:

```bash
dc up -d
dc ps                                       # todos deben quedar "healthy"
```

Las migraciones **no** se ejecutan al arrancar. Se lanzan a mano, igual que
la cuenta demo:

```bash
dc run --rm app php artisan migrate --force
dc run --rm app php artisan demo:install
```

`demo:install` crea la cuenta con tres dashboards de ejemplo y es seguro repetirlo
(no toca una cuenta existente).

Abre `https://miapp.duckdns.org`. El certificado se emite en el primer acceso;
si tarda, mira `dc logs -f web`.

## Operación

```bash
dc logs -f app worker web         # registros
dc ps                             # estado y healthchecks
```

**Actualizar a una versión nueva**

```bash
git pull
# Sube APP_IMAGE_TAG en .env.production (por ejemplo 1 -> 2) para poder volver atrás.
dc build
dc up -d
dc run --rm app php artisan migrate --force
```

**Copia de seguridad de la base de datos**

```bash
dc exec -T saas-db sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > backup-$(date +%F).sql.gz
```

Guarda las copias fuera de la VM. Para restaurar:
`gunzip -c backup.sql.gz | dc exec -T saas-db sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"'`.

Los contenedores usan `restart: unless-stopped` y Docker arranca con la máquina,
así que el demo vuelve solo tras un reinicio.

## Cuidados

- **Cuota de Gemini.** El plan gratuito tiene límites y Google puede usar lo que
  se escriba para mejorar sus productos. Mantén bajos los límites por usuario y el
  tope diario compartido, y no uses datos personales en el demo.
- **Recuperación de contraseña.** No hay servidor de correo (`MAIL_MAILER=log`),
  así que los correos solo quedan en el registro. Para un demo basta con la cuenta
  compartida.
- **Instancias inactivas.** Según varias guías, Oracle puede recuperar máquinas
  Always Free con muy poco uso. Confirma la política vigente y conserva copias de
  seguridad: este despliegue se reconstruye con los pasos de arriba.
- **ARM.** Las imágenes se construyen en la propia VM, así que no hay problema de
  arquitectura. No copies imágenes construidas en una máquina x86.
- **Seguridad del servidor.** Accede solo con clave SSH, activa
  `unattended-upgrades` y no abras otros puertos. PostgreSQL y Redis no son
  accesibles desde fuera, y Redis exige contraseña.
