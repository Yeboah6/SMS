# School Management System

The application is being delivered in sequenced phases. **Phase 1** establishes the Laravel 13, Inertia.js, React, and Tailwind CSS foundation, including session authentication, password reset, the responsive application shell, and the MySQL-backed Laravel infrastructure. **Phase 2** adds the eight primary roles, a granular Spatie permission catalog, permission-gated user administration, and the Super Admin authorization bypass. **Phase 3** adds school identity settings, academic years and terms, a single-active-year lifecycle, and locked-year protections. **Phase 4** adds year-scoped classes, sections, subject assignments, staff profiles, teacher-to-section/subject assignments, and teacher-scoped academic structure views.

## Requirements

- PHP 8.3 or newer with the `pdo_mysql` extension
- Composer 2
- Node.js 22 or newer and npm
- MySQL 8 or a compatible MariaDB server

## Local setup

1. Create a MySQL database (the provided environment examples use `sms`).
2. Install backend and frontend dependencies:

   ```powershell
   composer install
   npm install
   ```

3. Copy `.env.example` to `.env` if `.env` does not already exist. Configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` for the local database.
4. Generate an application key if one is not already present:

   ```powershell
   php artisan key:generate
   ```

5. Apply pending migrations:

   ```powershell
   php artisan migrate
   php artisan db:seed
   ```

   The seeder creates the baseline role and permission catalog; it does not create a user or assign a default password.

6. In separate terminals, start Laravel and Vite:

   ```powershell
   php artisan serve
   npm run dev
   ```

   Or build the production frontend with `npm run build`.

Local password-reset mail is written to the Laravel log by the default `MAIL_MAILER=log` configuration. Public self-registration is intentionally disabled; authenticated users with the appropriate permissions can manage accounts from the Users page.

## Database and environment

The application uses the MySQL connection and Laravel's database-backed session, cache, and queue drivers by default. Set `APP_TIMEZONE` to the school's chosen IANA timezone before configuring school data. Academic year records start as drafts; terms can be added before activation. An active year must be completed before another can be activated, and a completed year must be explicitly locked before its history becomes read-only. Do not commit `.env` or production credentials.

Use `php artisan migrate:status` to inspect applied migrations. Avoid `migrate:fresh` against a database containing data: it drops all tables.

## Tests

Feature tests use an in-memory SQLite database and do not modify the configured MySQL database:

```powershell
php artisan test
```

## Current scope

The application is under active development; the completed phases do not yet represent the full master specification. It does not seed demo accounts or create incomplete module pages. To bootstrap the first local Super Admin before using the Users page, create an account using Tinker and a unique, strong password:

```powershell
php artisan tinker
```

Then in the Tinker prompt:

```php
$password = Illuminate\Support\Str::password(32);
$user = App\Models\User::factory()->create([
    'name' => 'School Administrator',
    'email' => 'admin@example.test',
    'password' => $password,
]);
$user->assignRole('Super Admin');
$password;
```

Copy the generated password from Tinker output and keep it private. Do not use this method for production account provisioning.
