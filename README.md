# School Management System

The application is being delivered in sequenced phases. **Phase 1** establishes the Laravel 13, Inertia.js, React, and Tailwind CSS foundation, including session authentication, password reset, the responsive application shell, and the MySQL-backed Laravel infrastructure. **Phase 2** provides the eight protected primary roles, permission-gated user account creation/update/deactivation, custom role management, a protected system permission catalog with custom permission management, and the Super Admin authorization bypass. **Phase 3** adds school identity and public logo settings, configurable grade bands, fee currency and due-date defaults, notice delivery-channel preferences, academic years and terms, a single-active-year lifecycle, and locked-year protections. **Phase 4** adds year-scoped classes, sections, subject assignments, staff profiles, teacher-to-section/subject assignments, teacher-scoped academic structure views, permission-gated class/section and staff edit/delete workflows, and guards against modifying locked years or deleting records with academic dependencies. **Phase 5** adds permanent student profiles, guardian relationships, year-based enrolments, private student documents, and explicit guardian-account linking and invitation flows. **Phase 6** adds the admissions enquiry, application, review, approval/rejection, and enrolment pipeline. **Phase 7** provides private student notes with index/create/show/edit/delete workflows, private attachment replacement/removal, a durable student activity ledger covering profile/lifecycle, notes/documents, attendance, exam marks/results, homework submissions/grading, and fee invoices/payments, audited bulk promotion decisions, audited student transfers, and the academic enrolment ledger. **Phase 8** provides year/date/section-scoped daily attendance, assigned-teacher access, full-roster validation, atomic edits, locked-year protections, and read-only student/linked-parent attendance views scoped to the student or guardian account. **Phase 9** provides staff, student, and linked-parent leave requests, overlap prevention, manager review, applicant cancellation, and submitter-scoped request history. **Phase 10** provides academic-year, section, and subject-scoped homework assignments, student submissions with due-date enforcement, teacher edits, permission-scoped grading, and read-only parent views of linked children’s assignments and submissions. **Phase 11** provides year/class/term-scoped exam setup, assigned-teacher mark entry, roster-complete publication, correction/reopen controls, and read-only student/parent views of each permitted student’s own published results. **Phase 12** provides year/class-scoped fee heads and structures, idempotent itemized invoice generation, partial/full payments with unique receipts, locked-year protections, and student/linked-parent invoice views. **Phase 13** provides a permission-gated library catalogue, uniquely accessioned physical copies, transactional issue/return/renewal, overdue visibility, and student/linked-parent circulation views. **Phase 14 is in progress** with audience-scoped notice drafts and publishing, database notifications, queued email delivery, and per-user read receipts; editable notification templates and an SMS provider integration remain pending. **Phase 15 is in progress** with academic-year schedules, teacher/section conflict prevention, locked-year controls, and teacher/student/parent timetable views. **Phase 16** provides student/linked-parent certificate requests, principal review, unique issuance numbers, identity snapshots, and private print-ready views. **Phase 17 is in progress** with `results.view`-gated reports for published examination performance, academic-year/class/term filters, exemption-aware scoring summaries, and formula-safe CSV export. Attendance reporting includes teacher-scoped summaries and formula-safe CSV export. Fee collection reporting provides academic-year, class, and payment-date filters with permission-gated CSV export. Outstanding-fees reporting adds as-of balances, overdue totals, class/year filters, and formula-safe CSV export. Finance spreadsheet workflows import/upsert fee heads by code and fee structures by year/class/fee-head, and export fee heads, fee structures, invoices, and payments as formula-safe XLSX files. Importing invoices and payments is intentionally unsupported. Additional academic reports and server-generated PDF certificates remain pending. **Phase 18** replaces the initial foundation placeholder with role-specific operational metrics and permission-gated shortcuts, including student/parent academic and fee summaries, teacher workload, finance activity, admissions review, and library circulation.

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

6. Link public storage so uploaded school logos can be served by the application:

   ```powershell
   php artisan storage:link
   ```

7. Provision the first Super Admin explicitly. This command prompts for the password without echoing it and refuses to run when a Super Admin already exists:

   ```powershell
   php artisan school:provision-super-admin "School Administrator" admin@school.example
   ```

8. In separate terminals, start Laravel and Vite:

   ```powershell
   php artisan serve
   npm run dev
   ```

   Or build the production frontend with `npm run build`. Run `php artisan queue:work` in another terminal to deliver queued notice emails.

Local password-reset mail is written to the Laravel log by the default `MAIL_MAILER=log` configuration. Public self-registration is intentionally disabled; authenticated users with the appropriate permissions can manage accounts from the Users page.

## Database and environment

The application uses the MySQL connection and Laravel's database-backed session, cache, and queue drivers by default. Set `APP_TIMEZONE` to the school's chosen IANA timezone before configuring school data. Academic year records start as drafts; terms can be added before activation. An active year must be completed before another can be activated, and a completed year must be explicitly locked before its history becomes read-only. Do not commit `.env` or production credentials.

Use `php artisan migrate:status` to inspect applied migrations. User account deletion deactivates the account while retaining the user record and its historical relationships. Avoid `migrate:fresh` against a database containing data: it drops all tables.

## Tests

Feature tests use an in-memory SQLite database and do not modify the configured MySQL database:

```powershell
php artisan test
```

## Reporting

The attendance report supports academic-year and section filters, assigned-section scoping for teachers, and a formula-safe CSV download. The academic results report covers published examinations and filters by academic year, class, and term; exempt marks are removed from each student's maximum-mark denominator, while absent marks contribute zero. Phase 17 financial reporting includes fee collections by academic year, class, and date range, plus an outstanding-fees report with as-of balances, overdue totals, class/year filters, and a formula-safe CSV download. Payments dated after the selected snapshot date and invoices issued after it are excluded. Authorized finance users can also import fee-head and fee-structure workbooks and export fee heads, fee structures, invoices, and payments as XLSX; invoice/payment imports are not supported. The role-specific dashboard presents permission-scoped operational metrics and shortcuts, including linked-child views that are limited to students associated with the signed-in guardian. Issued certificates use a print-ready page that can be saved to PDF in the browser; server-side PDF generation is not yet configured. Further academic reports and Excel workbook import/export remain pending.

## Current scope

The application is under active development; the completed phases do not yet represent the full master specification. It does not seed demo accounts or create incomplete module pages. Use the explicit `school:provision-super-admin` command above to provision the first administrator in local or production environments. Passwords are entered through a hidden interactive prompt and are never supplied as command-line arguments.
