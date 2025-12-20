# Installation

Requirements

- PHP 8.0 or newer
- PDO + sqlite (`pdo_sqlite` PHP extension)
- Composer (for development / tests)

Install

1. Clone the repository:

```bash
git clone <repo> pocketdb
cd pocketdb
```

2. Install dependencies:

```bash
composer install
```

3. Ensure `pdo_sqlite` is enabled in your CLI `php.ini`.

Running tests

```bash
vendor/bin/phpunit -c phpunit.xml
```

Notes for Windows

- If you experience file lock errors when tests attempt to unlink `.sqlite` files, ensure connections are closed (`Database::close()` and `Client::close()`), and avoid having other processes (editors, tools) open the DB files.
