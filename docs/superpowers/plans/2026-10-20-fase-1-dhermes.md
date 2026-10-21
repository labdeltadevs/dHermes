# dHermes Fase 1 — Infraestructura PostgreSQL, tenancy y policies

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Reconciliar la base Laravel stock con el modelo de dHermes: PostgreSQL, esquema `dhermes`, usuarios UUID con `password_hash`, tenancy a nivel de aplicación y autorización por policies.

**Architecture:** Monolito Laravel 13 + Livewire 4 sobre un único PostgreSQL. El complemento externo escribe los datos de SAP; la app solo lee esas tablas y gestiona cobranzas, arqueos y verificación QR. Sin RLS ni triggers: el aislamiento se implementa con `tenant_id`, global scope, policies y auditoría.

**Tech Stack:** Laravel 13, PHP 8.4, PostgreSQL 18, Livewire 4, Pest 5, Tailwind 4, SQLite in-memory para la suite rápida.

## Global Constraints

- Solo facturas, cobros existentes y aplicaciones (`invtype = '13'`).
- Preservar claves SAP (`cardcode`, `docentry`, `slpcode`, `instid`) como TEXT.
- Importes con moneda explícita; las variantes `local_*` son nullable y nunca se suman monedas distintas.
- `users.password_hash`, no `password`; UUID como PK.
- La app nunca escribe tablas alimentadas por SAP.
- Toda escritura de operación pasa por una policy y deja `audit_events`.
- Sin RLS, sin `set_config`, sin triggers ni procedimientos almacenados.

---

### Task 1: PostgreSQL y schema

**Files:** `config/database.php`, `.env.example`, `database/migrations/0000_01_01_000000_create_dhermes_schema.php`, `app/Support/Database/DatabaseSchema.php`.

- [x] Configurar `search_path` con `dhermes,public`.
- [x] Crear el schema en PG con guard de driver.
- [x] Verificar `php artisan migrate` y `database-schema`.

### Task 2: Auth UUID

**Files:** `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`.

- [x] Reemplazar la migración stock por `users` UUID + `password_hash`, `password_reset_tokens` y `sessions` UUID.
- [x] Ajustar modelo y factory.
- [x] Tests de factory, hash y `actingAs`.

### Task 3: Tenancy

**Files:** `app/Support/Tenancy/CurrentTenant.php`, `app/Http/Middleware/ResolveTenant.php`, `app/Models/Concerns/BelongsToTenant.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`.

- [x] `CurrentTenant` scoped.
- [x] Middleware que resuelve membresía activa.
- [x] Global scope fail-closed y autoasignación de `tenant_id`.
- [x] Tests de aislamiento.

### Task 4: Enums y policies

**Files:** `app/Enums/*`, `app/Policies/*`, `app/Models/User.php`.

- [x] Enums: rol, medio de pago, estado de cobranza, tipo de cobranza, estado de arqueo.
- [x] Policies para Invoice, Customer, CollectionEvent, CashSession, VerificationCode y AuditEvent.
- [x] Matriz de tests por rol y habilidad.

### Task 5: Migraciones de dominio

**Files:** migraciones de `sales_reps`, `tenants`, `tenant_users`, `customers`, `invoices`, `receipts`, `receipt_applications`, `collection_events`, `cash_sessions`, `cash_session_items`, `verification_codes`, `audit_events`.

- [x] Columnas, FKs compuestas por tenant, UNIQUEs e índices.
- [x] Defaults UUID en PG.
- [x] Tests de constraints.

### Task 6: Modelos, factories y acciones

**Files:** `app/Models/*`, `database/factories/*`, `app/Actions/**`, `app/Support/Audit/AuditLogger.php`.

- [x] Relaciones, casts, factories y seeder demo.
- [x] Acciones con `Gate::authorize` + transacción + auditoría.
- [x] Tests de acciones y auditoría.

### Task 7: Verificación QR

**Files:** `app/Support/Verification/*`, `app/Models/VerificationCode.php`, `app/Http/Controllers/VerifyCodeController.php`, `resources/views/verify/show.blade.php`, `routes/web.php`, `config/verification.php`.

- [x] Emisión de código + firma HMAC.
- [x] Verificación pública con rate limit.
- [x] Tests de alteración, revocación y código inexistente.

### Task 8: Cartera del vendedor

**Files:** `app/Queries/SellerPortfolioQuery.php`.

- [x] Facturas pendientes por rol, ordenadas por vencimiento.
- [x] Totales por moneda.
- [x] Tests de visibilidad.

### Task 9: PostgreSQL y CI

**Files:** `phpunit.pg.xml`, `composer.json`, `.github/workflows/tests.yml`.

- [x] Suite PG con base `dhermes_test`.
- [x] Script `composer test:pg`.
- [x] Servicio PostgreSQL en CI.

### Task 10: Documentación y cierre

**Files:** `DATABASE.md`, `AGENTS.md`.

- [x] Reescribir `DATABASE.md` a v0.6.
- [x] Actualizar estado en `AGENTS.md`.
- [x] Gate final: `composer test`, `composer test:pg`, `php artisan migrate:fresh`, `npm run build`.

## Acceptance

- La app arranca solo con PostgreSQL desde una base vacía.
- Tablas SAP de solo lectura.
- Aislamiento por tenant probado sin RLS.
- Policies cubiertas por tests para los cuatro roles.
- Toda transición crítica genera auditoría.
- QR detecta alteración de datos.
- Suites SQLite y PostgreSQL en verde.
