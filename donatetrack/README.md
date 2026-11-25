# DonateTrack - Phase 2 (MySQL Database Development and Core Application)

IMDBSYS / DBSYS32, University of Cebu - Banilad, College of Computer Studies
Student: Newzel John C. Manginsay | ID 26244558 | Section: NonBlocked
Case Study 14: Community Donation Tracking | Stack: PHP + mysqli + Bootstrap 5 + MySQL

## What is in this folder

| Path | Purpose |
|---|---|
| `database/01_schema.sql` | Creates the `donatetrack` database: 6 tables, PKs, FKs, UNIQUE, CHECK, NOT NULL, indexes |
| `database/02_sample_data.sql` | Seed data: 7 users, 10 donors, 7 categories, 16 donations, 9 distributions |
| `database/03_app_user.sql` | Least-privilege MySQL account used by the application |
| `config/config.php` | Database connection settings (copy of `config.sample.php`) |
| `*.php`, `includes/`, `assets/` | The web application |
| `tests/run_tests.py` | Automated test run (57 test cases) against the real app and database |
| `tests/test_results.txt` | Output of the last test run |
| `docs/` | Phase 2 submission report (PDF) and screenshots |

## Setup (XAMPP on Windows)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel. PHP 8.1 or newer is required.
2. Copy this folder to `C:\xampp\htdocs\donatetrack`.
3. Open `http://localhost/phpmyadmin` -> **Import** and run, in this order:
   `database/01_schema.sql`, `database/02_sample_data.sql`, `database/03_app_user.sql`.
   (Or in a terminal: `mysql -u root < database/01_schema.sql` and so on.)
4. Check `config/config.php`. The defaults match `03_app_user.sql`
   (user `donatetrack_app`). For a quick start you can instead use user `root` with an empty password.
5. Open `http://localhost/donatetrack/` and log in.

Running without XAMPP: `php -S 127.0.0.1:8080 -t .` from this folder, then open `http://127.0.0.1:8080/`.

## Demo accounts (password for all: `DonateTrack#2026`)

| Username | Role | Notes |
|---|---|---|
| `coordinator` | COORDINATOR | Full access: verify, cancel, distribute, totals, categories, users |
| `volunteer1`, `volunteer2` | VOLUNTEER | Donors, record donations, edit own RECORDED donations, view lists |
| `volunteer3` | VOLUNTEER | Deactivated (cannot log in) |
| `donor1`, `donor2`, `donor3` | DONOR | Read-only "My Donations" for their linked donor record |

Change these passwords before any real use.

## Running the tests

```
pip install requests beautifulsoup4
php -S 127.0.0.1:8080 -t .            # in one terminal
python3 tests/run_tests.py            # in another (needs the mysql client and root access)
```

The test run drops and recreates the `donatetrack` database from the three SQL scripts, so run it
only on a development machine. Results are written to `tests/test_results.txt`.

## Scope of Phase 2

Phase 2 delivers the MySQL database and the core CRUD application with joins and basic validation,
as in the Revised Project Phases guideline. The stored procedures, stored functions, triggers and
the audit-trail screen are Phase 3 work; the `distribution_audit` table already exists but stays empty
and is read-only for the application account.
