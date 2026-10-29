# dHermes — Diagrama Entidad-Relación y Diccionario de Tablas

Fuente de verdad: migraciones PostgreSQL + modelos Eloquent.
Última actualización: 29/10/2026 (v0.7).

## 1. Diagrama Mermaid (`erDiagram`)

```mermaid
erDiagram
    %% --- Control de acceso ---
    TENANTS ||--o{ TENANT_USERS : "membresía"
    USERS ||--o{ TENANT_USERS : "membresía"
    TENANTS ||--o{ SALES_REPS : "empresa"
    TENANTS ||--o{ CUSTOMERS : "empresa"
    TENANTS ||--o{ INVOICES : "empresa"
    TENANTS ||--o{ RECEIPTS : "empresa"
    TENANTS ||--o{ RECEIPT_APPLICATIONS : "empresa"
    TENANTS ||--o{ COLLECTION_EVENTS : "empresa"
    TENANTS ||--o{ CASH_SESSIONS : "empresa"
    TENANTS ||--o{ CASH_SESSION_ITEMS : "empresa"
    TENANTS ||--o{ VERIFICATION_CODES : "empresa"
    TENANTS ||--o{ AUDIT_EVENTS : "empresa"
    TENANTS ||--o{ ROLES }:: "team (Spatie)"
    TENANTS ||--o{ PERMISSIONS }:: "team (Spatie)"

    USERS {
        string id PK
        string name
        string email UK
        string password_hash
        boolean active
        boolean is_platform_admin
        string auth_subject UK
        string remember_token
        string two_factor_secret
        string two_factor_recovery_codes
        timestamp two_factor_confirmed_at
    }

    TENANTS {
        string id PK
        string code UK
        string name
        string timezone
        char local_currency
        boolean active
    }

    TENANT_USERS {
        uuid tenant_id PK, FK
        uuid user_id PK, FK
        enum role "admin|supervisor|seller|collector"
        uuid sales_rep_id FK
        boolean active
    }
    UNIQUE "tenant_id + user_id"

    SALES_REPS {
        string id PK
        uuid tenant_id FK
        string slpcode UK "por tenant"
        string name
        string email
        boolean active
    }

    CUSTOMERS {
        string id PK
        uuid tenant_id FK
        string cardcode UK "por tenant"
        string name
        string tax_id
        string phone
        string email
        string address
        uuid sales_rep_id FK
        uuid collector_user_id FK "users que ven este cliente"
        char currency
        numeric credit_limit
        boolean active
    }

    INVOICES {
        string id PK
        uuid tenant_id FK
        string docentry UK "por tenant"
        string number
        string series
        uuid customer_id FK
        uuid sales_rep_id FK
        date issue_date
        date due_date
        enum status "open|closed|unknown"
        boolean cancelled
        char currency
        numeric total, paid, balance
        char local_currency
        numeric local_total, local_paid, local_balance
        timestamp synced_at
    }
    INDEX parcial "tenant_id + due_date WHERE cancelled = false AND balance > 0"

    RECEIPTS {
        string id PK
        uuid tenant_id FK
        string docentry UK "por tenant"
        string number
        uuid customer_id FK
        date receipt_date
        boolean cancelled
        char currency
        numeric total
        numeric local_total
        timestamp synced_at
    }
    UNIQUE "tenant_id + docentry"

    RECEIPT_APPLICATIONS {
        string id PK
        uuid tenant_id FK
        uuid receipt_id FK
        uuid invoice_id FK
        string docnum
        integer invoiceid
        integer instid
        string docentry
        varchar invtype "siempre '13'"
        numeric applied_amount
        numeric local_applied_amount
        date application_date
        timestamp synced_at
    }
    UNIQUE "tenant_id + receipt_id + invoice_id + instid"
    INDEX parcial "tenant_id + receipt_id + invoice_id WHERE instid IS NULL"

    COLLECTION_EVENTS {
        string id PK
        uuid tenant_id FK
        uuid invoice_id FK
        uuid seller_user_id FK
        numeric amount
        char currency
        numeric local_amount
        enum payment_method "cash|check|transfer|other"
        date collected_on
        enum kind "full|partial"
        varchar status "pending|accepted|rejected|cancelled"
        varchar erp_docnum
        uuid reviewed_by FK "users"
        timestamp reviewed_at
        text rejection_reason
        text notes
        varchar voucher_code
        varchar proof_disk
        varchar proof_path
        varchar proof_original_name
        varchar proof_mime_type
        bigint proof_size
    }
    UNIQUE "tenant_id + erp_docnum" "(permite nulos múltiples)"

    CASH_SESSIONS {
        string id PK
        uuid tenant_id FK
        uuid seller_user_id FK
        timestamp opened_at
        timestamp closed_at
        enum status "open|submitted|approved|rejected"
        char currency
        numeric expected_amount "0"
        numeric counted_amount
        numeric difference "counted - expected"
        text notes
        uuid approved_by FK "users"
        timestamp approved_at
    }

    CASH_SESSION_ITEMS {
        uuid tenant_id FK
        uuid cash_session_id FK
        uuid collection_event_id FK
        enum payment_method "cash|check|transfer|other"
        numeric amount
        varchar reference
    }
    UNIQUE "collection_event_id + cash_session_id" "(un solo cobro porarqueo)"

    VERIFICATION_CODES {
        string id PK
        uuid tenant_id FK
        varchar verifiable_type
        uuid verifiable_id FK
        varchar code UK "64"
        varchar signature "HMAC-SHA256"
        timestamp issued_at
        timestamp revoked_at
    }

    AUDIT_EVENTS {
        string id PK
        uuid tenant_id FK
        uuid actor_user_id FK "users"
        enum actor_kind "user|system"
        varchar action
        varchar entity
        varchar entity_id
        timestamp occurred_at
        jsonb details "{}"
    }

    %% --- Spatie Laravel Permission v8 (teams) ---
    ROLES {
        string id PK
        string name
        string guard_name
        string tenant_id FK "team (null = global)"
    }

    PERMISSIONS {
        string id PK
        string name
        string guard_name
        string tenant_id FK "team (null = global)"
    }

    MODEL_HAS_ROLES {
        bigint role_id PK, FK
        string model_type
        uuid model_id PK, FK
        string tenant_id FK "team"
    }

    MODEL_HAS_PERMISSIONS {
        bigint permission_id PK, FK
        string model_type
        uuid model_id PK, FK
        string tenant_id FK "team"
    }

    ROLE_HAS_PERMISSIONS {
        bigint permission_id PK, FK
        bigint role_id PK, FK
    }
```

### Notaciones visuales

- **Línea gruesa:** PK (primary key)
- **Línea con ☐:** FK compuesta `(tenant_id, id)` o `(tenant_id, id_column)`
- **Notas al costado:** reglas críticas (únicos, parciales, "solo X por Y")
- **`::`** en etiquetas: relación Spatie *team* (FK a `roles.tenant_id` / `permissions.tenant_id`)

---

## 2. Diccionario resumido por tabla

| Tabla | Para qué sirve | Escritura | FKs compuestas clave | Columnas clave & significado |
|---|---|---|---|---|
| `tenants` | Configuración de empresas (code/name/tax/tz/currency/active) | Aplicación | `id` PK | `code` único, `active` soft-delete |
| `users` | Identidad global (login, 2FA, platform admin) | Aplicación | `id` PK | `is_platform_admin`, `two_factor_*` |
| `tenant_users` | Pertenencia empresa + rol (admin/supervisor/seller/collector) | Aplicación | `(tenant_id, user_id)` UK | `role`, `sales_rep_id` (solo seller) |
| `sales_reps` | Vendedores SLP por empresa | Complemento externo | `tenant_id` | `slpcode` único por tenant |
| `customers` | Clientes SAP OCRD por empresa | Complemento externo | `tenant_id` | `cardcode` único por tenant, `collector_user_id` |
| `invoices` | Facturas OINV por empresa | Complemento externo | `tenant_id` | `docentry` único, índice parcial por cartera activa |
| `receipts` | Cobros ORCT por empresa | Complemento externo | `tenant_id` | `docentry` único |
| `receipt_applications` | Aplicaciones RCT2 (solo InvType='13') | Complemento externo | `(tenant_id, receipt_id, invoice_id, instid)` UK | `docnum`/`invoiceid`/`instid` nullable para enlace manual + índice parcial |
| `collection_events` | Cobros registrados por vendedor/cobrador | Aplicación | `tenant_id` | `status` VARCHAR (no enum tras migración), `erp_docnum` obligatorio al aceptar, `voucher_code`/`proof_*` añadidos tras migración |
| `cash_sessions` |arqueos de caja | Aplicación | `tenant_id` | `difference = counted − expected` |
| `cash_session_items` | Líneas dearqueo (un solo cobro porarqueo) | Aplicación | `(collection_event_id, cash_session_id)` UK | Garantiza un solo cobro porarqueo |
| `verification_codes` | QR público de solo lectura | Aplicación | `tenant_id` | `signature` HMAC-SHA256 con `VERIFICATION_HMAC_KEY` |
| `audit_events` | Registro inserción-only de eventos | Aplicación | `tenant_id` | Solo INSERT; `details` JSONB |
| `roles` / `permissions` (Spatie) | Roles y permisos por empresa (teams) | Aplicación | `tenant_id` como `team_foreign_key` | Sin `team_id` nulo → roles no aplicables globalmente |
| `model_has_roles` / `model_has_permissions` | Pivot Spatie modelo→rol/permiso | Aplicación | `tenant_id` (team) + `model_id` + `model_type` | |
| `role_has_permissions` | Pivot rol→permiso | Aplicación | `role_id` + `permission_id` | |

---

## 3. Reglas críticas (no documentadas en el diagrama anterior)

1. **Aislamiento sin RLS:** Todo query de dominio debe tener `tenant_id` explícito (middleware `ResolveTenant` + global scope `BelongsToTenant`). Sin contexto activo, devuelve 0 filas.
2. **Soft-delete real:** No hay `deleted_at`. Se usa `active = false` en `tenants`, `users`, `tenant_users`, `cancelled = true` en `invoices`/`receipts`, `status = 'cancelled'` en `collection_events`.
3. **Base monetaria dual:** `total`/`paid`/`balance` en moneda de documento + `local_total`/`local_paid`/`local_balance` en moneda local. Nunca se suman entre sí en una misma operación.
4. **FK compuesta en tablas de dominio:** Toda tabla de negocio (`invoices`, `customers`, `collection_events`, etc.) referencia `tenants.id` mediante `(tenant_id, id)` nunca UUID suelto.
5. **Spatie + teams:** Los roles `admin`/`supervisor`/`seller`/`collector` son *por empresa*. Sin `tenant_id` en la tabla `roles`/`permissions` (team FK), el usuario hereda el rol en todos los tenants (comportamiento global, no recomendado para este proyecto).
6. **Índices parciales (PostgreSQL):** `invoices (tenant_id, due_date) WHERE cancelled = false AND balance > 0` y `receipt_applications (tenant_id, receipt_id, invoice_id) WHERE instid IS NULL`. No funcionan en SQLite.
7. **Un solo cobro porarqueo:** La única `cash_session_items_collection_event_unique` impide que un mismo `collection_event` aparezca en varios arqueos.
8. **QR pública y throttling:** La ruta `/verificar/{code}` usa `throttle:30,1` y no requiere autenticación; la firma se verifica comparando HMAC-SHA256 contra `config('verification.key')`.