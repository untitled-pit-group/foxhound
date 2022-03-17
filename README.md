# foxhound

Primary PIFS backend.

## Setup

Install PHP 8.1 and PostgreSQL 14. Have [Composer][] handy.

[Composer]: https://getcomposer.org/

Clone this repo. Run `composer install`.

Copy [.env.example](./.env.example) to `.env`. Change the DB_\* variables as
appropriate. (You should probably create a separate user and database for
Foxhound. The user might not have a password by default; Postgres tends to just
trust all local users.)

Run `php artisan migrate` to set up the database schema.

To run the server, from the project's directory, launch
`php -S 127.0.0.1:8080 -t public public/index.php` (substitute the IP address,
port, and directory of `public` to taste.)

## Conventions

In general, follow [EditorConfig][][ and [PSR-1][], [PSR-2][] and [PSR-12][] for
coding. In general, braces for classes/functions should be on next line, and
braces for if/switch/etc blocks should be on the same line. Other than that, use
your best judgement :)
