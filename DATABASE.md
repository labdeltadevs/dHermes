# dHermes — Esquema PostgreSQL y contrato de integración SAP B1

Versión 0.7 — 29/10/2026 — Fuente: migraciones PostgreSQL + modelos Eloquent (ultimate truth). 
A diferencia de la v0.6 (20/10/2026), este documento refleja cambios posteriores: status de collection_events como VARCHAR, columnas voucher/proof, erp_docnum/reviewed_by, receipt_applications nullable + índice parcial, cash_session_items unique, two_factor en users, is_platform_admin, roles Spatie con tenant_id/team, índices parciales en invoices.

---

## 1. Alcance

dHermes controla la cartera y las cobranzas de vendedores: consulta de facturas pendientes, registro de cobros,arqueos de caja y verificación pública de comprobantes mediante QR.

Un complemento externo lee SAP Business One y escribe las tablas derivadas de SAP directamente en PostgreSQL. La aplicación no se conecta a SAP ni ejecuta importaciones. La aplicación sí escribe sus propias tablas transaccionales (cobros, arqueos, códigos de verificación y auditoría).

Esta versión no envía cobros al ERP. El administrador revisa cada cobro informado, registra el cobro manualmente en SAP Business One e ingresa el DocNum resultante para aceptarlo. Las tablas `receipts` y `receipt_applications` se siguen importando, pero ya no se usan para conciliar cobros.

## 2. Decisiones de diseño

- Esquema único `dhermes`; `search_path = dhermes,public`.
- PostgreSQL es la única base soportada en producción. Los tests usan SQLite en memoria y una suite PostgreSQL separada.
- **No se usa RLS.** El aislamiento por empresa se aplica en la capa de aplicación: `CurrentTenant`, global scope `BelongsToTenant`, policies y consultas con `tenant_id` explícito.
- Todas las tablas de dominio llevan `tenant_id`. Las referencias entre tablas de dominio usan FK compuestas `(tenant_id, id)` para impedir cruces entre empresas.
- Los identificadores internos son UUID (`gen_random_uuid()` en PostgreSQL). Las claves SAP se conservan como texto y no se inventa una segunda clave externa.
- Los importes usan `NUMERIC(19,6)`. Cada documento guarda el importe en moneda de documento y, opcionalmente, en moneda local. No se suman columnas de monedas distintas: la consulta elige una base (`total`/`paid`/`balance` o `local_*`).
- La pertenencia a una empresa vive en `tenant_users`. Un usuario puede pertenecer a varias empresas y tener un rol distinto en cada una.
- **Spatie Laravel Permission v8** con teams: los roles y permisos por empresa llevan `tenant_id` como `team_foreign_key` en tablas `roles`, `permissions`, `model_has_roles`, `model_has_permissions`. Sin `team_id` nulo, los roles Spatie no son aplicables a nivel global.
- `users.is_platform_admin` (boolean, default false) distingue al administrador de plataforma del admin de tenant. Los platform admins pueden permanecer logueados sin membresía activa (bypass en `EnsureUserCanAccess`).
- Los roles Spatie por tenant se provisionan automáticamente al crear el tenant (`TenantObserver` → `provisionTenant`).

## 3. Tablas

| Tabla | Origen | Escritura |
|---|---|---|
| `tenants` | Configuración | Aplicación |
| `users` | Configuración | Aplicación |
| `tenant_users` | Configuración | Aplicación |
| `sales_reps` | `OSLP` | Complemento externo |
| `customers` | `OCRD` | Complemento externo |
| `invoices` | `OINV` | Complemento externo |
| `receipts` | `ORCT` | Complemento externo |
| `receipt_applications` | `RCT2` (`InvType = '13'`) | Complemento externo |
| `collection_events` | Operación dHermes | Aplicación |
| `cash_sessions` | Operación dHermes | Aplicación |
| `cash_session_items` | Operación dHermes | Aplicación |
| `verification_codes` | Operación dHermes | Aplicación |
| `audit_events` | Operación dHermes | Aplicación |
| `roles` | Spatie | Aplicación (via TenantObserver) |
| `permissions` | Spatie | Aplicación (via TenantObserver) |
| `model_has_roles` | Spatie | Aplicación |
| `model_has_permissions` | Spatie | Aplicación |
| `role_has_permissions` | Spatie | Aplicación |
| `sessions`, `cache`, `jobs`, `cache_locks`, `job_batches`, `failed_jobs` | Laravel estándar | Laravel |

Las tablas estándar de Laravel (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`) también viven en `dhermes`.

## 4. Diccionario de columnas

Todas las tablas tienen `created_at` y `updated_at` (`TIMESTAMPTZ`) salvo las tablas estándar de Laravel, que conservan sus columnas habituales. Todas las tablas de dominio tienen `id UUID` como PK.

### 4.1 `tenants`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `code` | `VARCHAR` único | No | — |
| `name` | `VARCHAR` | No | — |
| `tax_id` | `VARCHAR` | Sí | — |
| `timezone` | `VARCHAR` | No | — |
| `local_currency` | `CHAR(3)` | No | — |
| `active` | `BOOLEAN` | No | `true` |

### 4.2 `users`

Identidad global de acceso. No contiene `password`; usa `password_hash`.

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `name` | `VARCHAR` | No | — |
| `email` | `VARCHAR` único | No | — |
| `auth_subject` | `VARCHAR` único | Sí | — |
| `password_hash` | `VARCHAR` | Sí | — |
| `active` | `BOOLEAN` | No | `true` |
| `remember_token` | `VARCHAR(100)` | Sí | — |
| `is_platform_admin` | `BOOLEAN` | No | `false` |
| `two_factor_secret` | `TEXT` | Sí | — |
| `two_factor_recovery_codes` | `TEXT` | Sí | — |
| `two_factor_confirmed_at` | `TIMESTAMPTZ` | Sí | — |

### 4.3 `tenant_users`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `user_id` | `UUID` FK `users` | No | — |
| `role` | `admin`, `supervisor`, `seller`, `collector` | No | — |
| `sales_rep_id` | `UUID` FK `sales_reps` | Sí | — |
| `active` | `BOOLEAN` | No | `true` |

Restricción única `(tenant_id, user_id)`. Solo `seller` requiere `sales_rep_id`; `collector` se identifica con `user_id`.

### 4.4 `sales_reps`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `slpcode` | `VARCHAR` | No | — |
| `name` | `VARCHAR` | No | — |
| `email` | `VARCHAR` | Sí | — |
| `active` | `BOOLEAN` | Sí | — |

Restricción única `(tenant_id, slpcode)`.

### 4.5 `customers`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `cardcode` | `VARCHAR` | No | — |
| `name` | `VARCHAR` | No | — |
| `tax_id`, `phone`, `email`, `address` | `VARCHAR` | Sí | — |
| `sales_rep_id` | `UUID` FK `sales_reps` | Sí | — |
| `collector_user_id` | `UUID` FK `users` | Sí | — |
| `currency` | `CHAR(3)` | Sí | — |
| `credit_limit` | `NUMERIC(19,6)` | Sí | — |
| `active` | `BOOLEAN` | Sí | — |

Restricción única `(tenant_id, cardcode)`. `collector_user_id` define qué usuarios con rol `collector` ven al cliente.

### 4.6 `invoices`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `docentry` | `VARCHAR` | No | — |
| `number` | `VARCHAR` | No | — |
| `series` | `VARCHAR` | Sí | — |
| `customer_id` | `UUID` FK `customers` | No | — |
| `sales_rep_id` | `UUID` FK `sales_reps` | Sí | — |
| `issue_date` | `DATE` | No | — |
| `due_date` | `DATE` | Sí | — |
| `status` | `open`, `closed`, `unknown` | No | `unknown` |
| `cancelled` | `BOOLEAN` | No | `false` |
| `currency` | `CHAR(3)` | No | — |
| `total`, `paid`, `balance` | `NUMERIC(19,6)` | No | `paid = 0` |
| `local_currency` | `CHAR(3)` | Sí | — |
| `local_total`, `local_paid`, `local_balance` | `NUMERIC(19,6)` | Sí | — |
| `synced_at` | `TIMESTAMPTZ` | Sí | — |

Restricción única `(tenant_id, docentry)`. Una factura está abierta para cobranza cuando `cancelled = false` y `balance > 0`.

**Índice parcial (PostgreSQL):** `(tenant_id, due_date) WHERE cancelled = false AND balance > 0`.

### 4.7 `receipts`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `docentry` | `VARCHAR` | No | — |
| `number` | `VARCHAR` | No | — |
| `customer_id` | `UUID` FK `customers` | No | — |
| `receipt_date` | `DATE` | No | — |
| `cancelled` | `BOOLEAN` | No | `false` |
| `currency` | `CHAR(3)` | No | — |
| `total` | `NUMERIC(19,6)` | No | — |
| `local_total` | `NUMERIC(19,6)` | Sí | — |
| `synced_at` | `TIMESTAMPTZ` | Sí | — |

Restricción única `(tenant_id, docentry)`.

### 4.8 `receipt_applications`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `receipt_id` | `UUID` FK `receipts` | No | — |
| `invoice_id` | `UUID` FK `invoices` | No | — |
| `docnum` | `VARCHAR` | Sí | — |
| `invoiceid` | `INTEGER` | Sí | — |
| `instid` | `INTEGER` | Sí | — |
| `docentry` | `VARCHAR` | No | — |
| `invtype` | `VARCHAR(10)` | No | `'13'` |
| `applied_amount` | `NUMERIC(19,6)` | No | — |
| `local_applied_amount` | `NUMERIC(19,6)` | Sí | — |
| `application_date` | `DATE` | Sí | — |
| `synced_at` | `TIMESTAMPTZ` | Sí | — |

**Regla crítica:** Solo se importan aplicaciones con `InvType = '13'`.

Restricción única `(tenant_id, receipt_id, invoice_id, instid)`. Para el **enlace manual**, los campos `docnum`, `invoiceid`, `instid` son **nullable** y existe un índice único parcial:

```sql
CREATE UNIQUE INDEX receipt_applications_manual_link_unique
ON receipt_applications (tenant_id, receipt_id, invoice_id) WHERE instid IS NULL;
```

### 4.9 `collection_events`

Cobro registrado por un vendedor o cobrador.

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `invoice_id` | `UUID` FK `invoices` | No | — |
| `seller_user_id` | `UUID` FK `users` | No | — |
| `amount` | `NUMERIC(19,6)` | No | — |
| `currency` | `CHAR(3)` | No | — |
| `local_amount` | `NUMERIC(19,6)` | Sí | — |
| `payment_method` | `cash`, `check`, `transfer`, `other` | No | — |
| `collected_on` | `DATE` | No | — |
| `kind` | `full`, `partial` | No | — |
| `status` | `VARCHAR(64)` | No | `pending` |
| `erp_docnum` | `VARCHAR(32)` | Sí | — |
| `reviewed_by` | `UUID` FK `users` | Sí | — |
| `reviewed_at` | `TIMESTAMPTZ` | Sí | — |
| `rejection_reason` | `TEXT` | Sí | — |
| `notes` | `TEXT` | Sí | — |
| `voucher_code` | `VARCHAR(64)` | Sí | — |
| `proof_disk` | `VARCHAR(32)` | Sí | — |
| `proof_path` | `VARCHAR(512)` | Sí | — |
| `proof_original_name` | `VARCHAR(255)` | Sí | — |
| `proof_mime_type` | `VARCHAR(127)` | Sí | — |
| `proof_size` | `BIGINT` | Sí | — |

`signature` es un HMAC-SHA256 en hexadecimal sobre el payload canónico del registro (`collection_events` o `cash_sessions`). La clave se configura en `VERIFICATION_HMAC_KEY`; si no se define, se usa `APP_KEY`.

**Historia de estados:** El flujo `matched → verified` fue reemplazado por `erp_docnum` + revisión administrativa. Los estados legacy (`matched`, `verified`, `registered`, `discrepancy`) ya no son válidos en nuevos registros; solo `pending`, `accepted`, `rejected`, `cancelled` son estados activos.

`erp_docnum` es obligatorio a nivel de aplicación al aceptar un cobro, debe ser numérico y es único por empresa. El índice único `(tenant_id, erp_docnum)` permite múltiples valores nulos. `reviewed_by`, `reviewed_at` y `rejection_reason` registran la revisión del administrador o supervisor.

### 4.10 `cash_sessions`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `seller_user_id` | `UUID` FK `users` | No | — |
| `opened_at` | `TIMESTAMPTZ` | No | — |
| `closed_at` | `TIMESTAMPTZ` | Sí | — |
| `status` | `open`, `submitted`, `approved`, `rejected` | No | `open` |
| `currency` | `CHAR(3)` | No | — |
| `expected_amount` | `NUMERIC(19,6)` | No | `0` |
| `counted_amount` | `NUMERIC(19,6)` | Sí | — |
| `difference` | `NUMERIC(19,6)` | Sí | — |
| `notes` | `TEXT` | Sí | — |
| `approved_by` | `UUID` FK `users` | Sí | — |
| `approved_at` | `TIMESTAMPTZ` | Sí | — |

`difference = counted_amount - expected_amount`.

### 4.11 `cash_session_items`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `cash_session_id` | `UUID` FK `cash_sessions` | No | — |
| `collection_event_id` | `UUID` FK `collection_events` | No | — |
| `payment_method` | `cash`, `check`, `transfer`, `other` | No | — |
| `amount` | `NUMERIC(19,6)` | No | — |
| `reference` | `VARCHAR` | Sí | — |

**Regla crítica:** Un cobro (`collection_event`) solo puede pertenecer a **un** arqueo (`cash_session`). La restricción única `cash_session_items_collection_event_unique` sobre `(collection_event_id, cash_session_id)` (definida en migración `2026_10_21_000001`) garantiza esto.

### 4.12 `verification_codes`

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `verifiable_type` | `VARCHAR` | No | — |
| `verifiable_id` | `UUID` | No | — |
| `code` | `VARCHAR(64)` único | No | — |
| `signature` | `VARCHAR(64)` | No | — |
| `issued_at` | `TIMESTAMPTZ` | No | — |
| `revoked_at` | `TIMESTAMPTZ` | Sí | — |

`signature` es un HMAC-SHA256 en hexadecimal sobre el payload canónico del registro (`collection_events` o `cash_sessions`). La clave se configura en `VERIFICATION_HMAC_KEY`; si no se define, se usa `APP_KEY`.

### 4.13 `audit_events`

La auditoría es solo de inserción. Ninguna policy permite actualizar o eliminar eventos.

| Columna | Tipo | NULL | Default |
|---|---|---|---|
| `tenant_id` | `UUID` FK `tenants` | No | — |
| `actor_user_id` | `UUID` FK `users` | Sí | — |
| `actor_kind` | `user`, `system` | No | — |
| `action` | `VARCHAR` | No | — |
| `entity` | `VARCHAR` | No | — |
| `entity_id` | `VARCHAR` | Sí | — |
| `occurred_at` | `TIMESTAMPTZ` | No | — |
| `details` | `JSONB` | No | `{}` |

### 4.14 `roles` / `permissions` (Spatie Laravel Permission v8)

Dado que el proyecto usa **teams**, los roles y permisos por empresa llevan `tenant_id` como `team_foreign_key`. Sin `team_id` nulo, los roles Spatie no son aplicables a nivel global.

| Tabla | Columna relevante |
|---|---|
| `roles` | `id`, `name`, `guard_name`, `tenant_id` (FK, team) |
| `permissions` | `id`, `name`, `guard_name`, `tenant_id` (FK, team) |
| `model_has_roles` | `role_id`, `model_type`, `model_id`, `tenant_id` (FK, team) |
| `model_has_permissions` | `permission_id`, `model_type`, `model_id`, `tenant_id` (FK, team) |
| `role_has_permissions` | `permission_id`, `role_id` |

### 4.15 `sessions` / `cache` / `jobs` / `cache_locks` / `job_batches` / `failed_jobs`

Columnas estándar de Laravel 13. No detalladas: son gestión interna del framework.

## 5. Contrato del complemento externo

El complemento debe:

1. Ejecutar cada importación dentro de una transacción y fijar el tenant explícitamente. No depende de `search_path` ni de RLS.
2. Hacer upsert de `sales_reps`, `customers`, `invoices`, `receipts` y `receipt_applications` usando las claves únicas indicadas.
3. Resolver `tenant_id`, `customer_id`, `invoice_id` y `receipt_id` internos antes de insertar; no escribir UUID ajenos ni cruzar empresas.
4. Conservar `cardcode`, `docentry`, `slpcode`, `docnum`, `invoiceid`, `instid` como texto/originales. No crear una segunda clave externa.
5. Importar de `OINV` solo facturas y de `RCT2` solo aplicaciones con `InvType = '13'`.
6. Tratar facturas y cobros cancelados con `cancelled = true`; no borrar filas.
7. Actualizar `total`, `paid`, `balance`, `status` y `synced_at` en cada sincronización.
8. Registrar incidencias de referencias inexistentes fuera de las tablas de dominio o en el log del complemento; no inventar clientes ni facturas.

**Reglas de unión:** `OINV.CardCode = OCRD.CardCode`, `ORCT.CardCode = OCRD.CardCode`, `RCT2.DocNum = ORCT.DocEntry`, `RCT2.DocEntry = OINV.DocEntry` y `RCT2.InvType = '13'`.

## 6. Seguridad y autorización

- El contexto de empresa se resuelve en el middleware `ResolveTenant` a partir de la membresía activa del usuario y se guarda en sesión.
- `BelongsToTenant` aplica un global scope por `tenant_id` y asigna el tenant activo al crear. Sin contexto activo, las consultas de dominio no devuelven filas.
- Las policies verifican pertenencia y rol:
  - `admin` y `supervisor`: cartera completa, aceptación o rechazo de cobros y aprobación dearqueos.
  - `seller`: solo clientes/facturas de su `sales_rep_id`; registra cobros yarqueos propios.
  - `collector`: solo clientes con `collector_user_id` igual a su usuario; registra cobros yarqueos propios.
- Nadie puede eliminar facturas, clientes, cobros ni auditoría desde la aplicación.
- La verificación QR es pública y de solo lectura; el código se limita a caracteres alfanuméricos y la ruta usa `throttle:30,1`.

## 7. Verificación de comprobantes

1. Solo las cobranzas en estado `accepted` y losarqueos aprobados pueden emitir un `verification_codes`.
2. El código impreso apunta a `/verificar/{code}`.
3. La verificación busca el código sin el global scope de tenant, fija el tenant del código y recalcula el HMAC del registro.
4. Resultados: `valid`, `tampered` (firma no coincide), `revoked` (`revoked_at` no nulo) o `not_found` (404).
5. Cambiar cualquier campo firmado invalida el comprobante.

## 8. Pruebas

- `php artisan test` usa SQLite en memoria; cubre tenancy, policies, acciones, consultas y QR.
- `composer test:pg` usa `phpunit.pg.xml` contra PostgreSQL (`dhermes_test`) para validar tipos, FK compuestas y `JSONB`.
- CI ejecuta ambas suites con un servicio PostgreSQL 18.

---

**Nota de migración:** Esta versión (0.7) incorpora cambios migratorios posteriores a la v0.6 (20/10/2026): columnas `voucher_code`/`proof_*` en `collection_events`, `erp_docnum`+`reviewed_by` en `collection_events`, status `status` como VARCHAR en `collection_events`, receipt_applications `docnum`/`invoiceid`/`instid` nullable + índice parcial, `cash_session_items` unique sobre `collection_event_id`, `users.is_platform_admin` y `two_factor_*` campos, y roles Spatie con `tenant_id` como team foreign key.