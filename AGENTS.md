# Convenciones para agentes de código

## Alcance y arquitectura

- Mantener un monolito modular. El código de dominio vive bajo `app/Domain` en
  `Tenancy`, `Dashboards`, `Ai` o `Metabase`; no crear microservicios.
- Mantener controllers delgados. Validar HTTP con Form Requests, autorización
  con Policies, reglas de negocio en acciones/servicios y trabajo lento en Jobs.
- Toda integración externa debe depender de una interfaz. No añadir una
  implementación real de OpenAI o Metabase hasta que la fase correspondiente
  lo solicite.
- No leer `env()` fuera de `config/*.php`. No guardar secretos ni credenciales
  reales en archivos versionados.
- No añadir paquetes sin justificar que el framework o el starter no cubren la
  necesidad.

## Tenancy y seguridad

- Usar UUID para tenants y todos los recursos de negocio.
- Todo recurso tenant-scoped debe tener `tenant_id`, foreign key, índice,
  relación Eloquent y Policy.
- Obtener el tenant únicamente desde `CurrentTenant`; nunca confiar en un
  `tenant_id` del request. Revalidar membresía y estado mediante
  `ResolveCurrentTenant`.
- Toda query de recursos de negocio debe quedar restringida al tenant actual.
  Añadir una prueba positiva y otra cross-tenant por cada recurso nuevo.
- No ejecutar SQL generado por usuarios o IA. No interpolar identificadores o
  valores en SQL dinámico.

## Estilo y pruebas

- PHP 8.4 con tipos explícitos y convenciones de Laravel 12; Vue 3 con
  Composition API, `<script setup>` y TypeScript.
- Usar PHPUnit, la alternativa instalada por el starter fijado. Las feature
  tests usan `RefreshDatabase` y SQLite en memoria salvo pruebas explícitas de
  integración con PostgreSQL.
- Usar pnpm 10 fijado en `packageManager`; no mantener lockfiles de npm o yarn.
  Revisar y aprobar explícitamente cualquier lifecycle script de dependencias.
- Ejecutar antes de entregar: `composer test`, `composer format:check`,
  `corepack pnpm format:check`, `corepack pnpm lint:check`,
  `corepack pnpm types` y `corepack pnpm build`.
- Ejecutar migraciones contra PostgreSQL en Docker para detectar diferencias
  respecto a SQLite.
- No hacer commits. Preservar cambios ajenos y explicar cualquier eliminación.

## Infraestructura

- No usar tags `latest`. Todas las imágenes se parametrizan en `.env` a partir
  de `.env.example`, con versiones explícitas documentadas.
- No migrar automáticamente al arrancar contenedores. Usar el comando explícito
  documentado para evitar carreras y cambios de esquema inesperados.
- Mantener healthchecks y dependencias de Compose al añadir servicios.
