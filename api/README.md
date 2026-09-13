# Job Tracker API

Laravel REST API for the repository's Job Application Tracker.

It provides Sanctum session authentication, account-scoped companies and job applications, nested interview management, immutable application activity timelines, dashboard summaries, the original role-based user-management module, and generated OpenAPI documentation.

Setup instructions, demo credentials, endpoint tables, and the backend migration history are maintained in the [root README](../README.md).

Run API commands from this directory:

```powershell
composer install
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
php artisan test
```

The API documentation UI is available at `/api/v1/docs` while the server is running.
