# Auth and Tenancy Implementation Plan

> **For agentic workers:** Use `superpowers:executing-plans` or `superpowers:subagent-driven-development`.

**Goal:** Add Fortify-based auth, automatic tenant loading, profile/2FA, and admin-only user management without downgrading Livewire or Tailwind.

**Architecture:** Fortify owns auth routes and actions. Livewire 4 page components own dashboard, profile, and admin users. `ResolveTenant` loads the user's single active membership. Authorization uses a `manage-users` gate plus per-write checks.

**Tech Stack:** Laravel 13, Livewire 4, Fortify 1.41, Tailwind 4, Pest 5, PostgreSQL/SQLite.

## Global Constraints

- No code comments.
- `password_hash` is the only password column.
- Livewire `^4.1`, Tailwind `^4`, Vite `^8` remain.
- No public registration, passkeys, API tokens, or teams.
- Every admin write is authorized and audited.
- Run `vendor/bin/pint --dirty --format agent`, `composer types:check`, and focused tests.

---

### Task 1: Dependencies, Fortify, user model, migration

**Files:**
- Modify: `composer.json`
- Create: `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/*`
- Create: `database/migrations/*_add_two_factor_columns_to_users_table.php`
- Modify: `app/Models/User.php`, `bootstrap/providers.php`

- Remove `laravel/jetstream`; require `laravel/fortify:^1.41`.
- Run `php artisan fortify:install --no-interaction`.
- Keep features: reset passwords, profile, password update, 2FA with confirmation.
- Remove registration, email verification, and passkeys.
- Set `home` to `/dashboard`.
- Add 2FA trait and columns.
- Override `getAuthPasswordName(): string` to return `password_hash`.
- Adapt `UpdateUserPassword` and `ResetUserPassword` to write `password_hash`.
- Add `Fortify::authenticateUsing` that accepts only active users with an active tenant membership.

**Evidence:** `php artisan route:list --name=login`, migration runs, unit test for password column.

---

### Task 2: Tenant guard, auth views, routes

**Files:**
- Modify: `app/Http/Middleware/ResolveTenant.php`
- Create: `app/Http/Middleware/EnsureUserCanAccess.php`
- Create: `resources/views/components/guest-layout.blade.php`
- Create: `resources/views/auth/{login,forgot-password,reset-password,two-factor-challenge,confirm-password}.blade.php`
- Modify: `bootstrap/app.php`, `routes/web.php`

- `ResolveTenant` chooses the first active membership whose tenant is active.
- `EnsureUserCanAccess` logs out inactive users and users without an active tenant.
- Fortify routes handle auth; app routes add `/dashboard`, `/user/profile`, `/admin/usuarios`.
- `GET /` redirects authenticated users to `/dashboard`; otherwise shows welcome.

**Evidence:** guest redirected to login; inactive user rejected; active user reaches dashboard.

---

### Task 3: Layout and dashboard

**Files:**
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/pages/⚡dashboard.blade.php`
- Modify: `resources/views/welcome.blade.php`

- App layout includes navigation, role badge, profile link, admin link, logout form.
- Dashboard shows tenant name/code, role, currency/timezone, and module cards.
- Welcome gets login/dashboard CTA and keeps the public verification entry point.

**Evidence:** `/dashboard` renders for an authenticated member.

---

### Task 4: Profile and 2FA

**Files:**
- Create: `resources/views/pages/⚡profile.blade.php`

- Update name/email with unique email rule.
- Update password with current-password check.
- Enable, confirm, disable 2FA and regenerate recovery codes using Fortify actions.
- Show QR SVG, secret, recovery codes only after current-password validation.
- List database sessions and log out other browsers after current-password validation.

**Evidence:** Livewire tests for profile update, password update, and 2FA enable/confirm; session deletion test.

---

### Task 5: Admin user management

**Files:**
- Create: `resources/views/pages/admin/⚡users.blade.php`
- Modify: `app/Providers/AppServiceProvider.php`

- Define `manage-users` gate for active admins.
- List memberships for the current tenant.
- Create user + membership in one transaction.
- Edit name/email/role/sales rep/active; optional new password.
- Prevent self-deactivation/demotion and removal of the last active admin.
- Audit `user.created`, `user.updated`, `user.activated`, `user.deactivated`.

**Evidence:** Livewire tests for CRUD, tenant isolation, self-protection, last-admin protection, audit rows.

---

### Task 6: Verification

- `php artisan migrate:fresh --seed`
- `php artisan test --compact tests/Feature/AuthTest.php tests/Feature/AdminUsersTest.php tests/Feature/ProfileTest.php`
- `composer test`
- `composer test:pg`
- `npm run build`
