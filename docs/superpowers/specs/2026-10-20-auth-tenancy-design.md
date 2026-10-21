# Autenticación, perfil y administración de usuarios

**Fecha:** 2026-10-20
**Estado:** Aprobado por el usuario

## Objetivo

Incorporar autenticación y administración de usuarios a dHermes manteniendo Livewire 4,
Tailwind 4 y Vite 8. Jetstream no se usa porque su instalador exige Livewire 3 y Tailwind 3.

## Alcance

### Autenticación

- Fortify como backend de autenticación.
- Login, logout, restablecimiento de contraseña por email, perfil, cambio de contraseña,
  sesiones del navegador y 2FA TOTP con códigos de recuperación.
- Sin registro público, verificación de email, passkeys, API tokens ni teams.
- `users.password_hash` sigue siendo la única columna de contraseña.
- Usuarios inactivos o sin membresía activa no pueden iniciar sesión.

### Tenant automático

- Cada usuario tiene una sola membresía activa.
- El tenant se resuelve automáticamente después del login; no hay selector.
- Si el tenant o la membresía están inactivos, se rechaza el acceso.

### Administración de usuarios

- Ruta `/admin/usuarios`, exclusiva para `admin` del tenant actual.
- Listado, alta, edición, desactivación y reseteo de contraseña.
- No se eliminan usuarios físicamente.
- Campos: nombre, email, rol, vendedor asignado, estado y contraseña temporal.
- `seller` requiere un vendedor del tenant.
- El admin no puede desactivarse ni quitarse su propio rol de admin.
- No se puede dejar el tenant sin al menos un admin activo.
- Toda escritura pasa por autorización y deja un evento de auditoría.

### Rutas y UI

- `/` pública con CTA a login.
- `/dashboard` landing post-login con tenant, rol y accesos a módulos.
- `/user/profile` perfil, contraseña, 2FA y sesiones.
- `/verificar/{code}` permanece pública.
- Vistas propias con Tailwind 4 y componentes Livewire 4.

## Modelo de datos

- Migración nueva para `two_factor_secret`, `two_factor_recovery_codes` y
  `two_factor_confirmed_at` en `users`.
- Se reutilizan `users`, `tenant_users`, `sessions` y `password_reset_tokens`.
- No se crean tablas de equipos, passkeys ni tokens de API.

## Seguridad

- Gate `manage-users`: rol `admin` en la membresía del tenant actual.
- `User::getAuthPasswordName()` devuelve `password_hash`.
- Contraseñas se escriben con `Hash::make` en `password_hash`.
- Los códigos 2FA y de recuperación quedan ocultos en serialización.
- Los eventos de auditoría no incluyen contraseñas.

## Pruebas

- Login/logout, usuario inactivo, invitado redirigido, 2FA, perfil y contraseña.
- Admin: acceso, alta, edición, desactivación, aislamiento por tenant,
  protecciones de auto-bloqueo y auditoría.
- Resolución automática de tenant y redirección de la landing.
