# Data Core

Fundación de un SaaS multi-tenant para crear dashboards mediante lenguaje
natural. La Fase 1 incluye autenticación, organizaciones, aislamiento explícito
por tenant, una interfaz inicial y el entorno Docker; todavía no llama a OpenAI
ni a la API de Metabase.

## Inicio rápido con Docker

```bash
cp .env.example .env
docker compose build
docker compose run --rm app php artisan key:generate
corepack pnpm install --frozen-lockfile
corepack pnpm build
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Aplicación: <http://localhost:8080>. Metabase: <http://localhost:3000>.

## Comandos principales

```bash
# Dependencias en el host
composer install
corepack pnpm install --frozen-lockfile

# Migraciones y datos demo dentro de Docker
docker compose exec app php artisan migrate --seed

# Pruebas
composer test

# Formatters y análisis frontend
composer format
corepack pnpm format
corepack pnpm lint
corepack pnpm types

# Comprobaciones sin modificar archivos
composer format:check
corepack pnpm format:check
corepack pnpm lint:check
corepack pnpm types

# Servicios
docker compose up -d
docker compose ps
docker compose logs -f app worker web
docker compose down
```

Consulta [desarrollo local](docs/local-development.md),
[arquitectura](docs/architecture.md), [tenancy](docs/tenancy.md) y el
[plan de implementación](docs/implementation-plan.md) para más detalle.
