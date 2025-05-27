# Laravel Time Tracking System

A minimal RESTful PHP time tracking system with users, clients, projects and project time logs.

## Requirements

- **PHP**: 8.2 - 8.4
- **Composer**: Dependency Manager for PHP
- **MySQL**: Two databases required (one for main usage and one for testing)

## Getting Started

Follow the steps below to set up the application on your local machine.

### 1. Clone the Repository

```
git clone project url
```

### 2. Navigate to the Project Directory
```bash
cd your-project
```
### 3. Copy Environment Files
```bash
cp .env.example .env
cp .env.example .env.testing
```
### 4. Configure Environment Variables

Open the .env and .env.testing files and update the following:

Database credentials:

DB_DATABASE

DB_USERNAME

DB_PASSWORD

DB_PORT

Mail SMTP settings for sending notification emails:

MAIL_MAILER

MAIL_HOST

MAIL_PORT

MAIL_USERNAME

MAIL_PASSWORD

MAIL_ENCRYPTION

MAIL_FROM_ADDRESS

MAIL_FROM_NAME

### 5. Install Dependencies
```bash

composer install
```
### 6. Run Database Migrations
```bash

php artisan migrate
```
### 7. Seed the Database (Optional: Mock Data)
```bash

php artisan db:seed
```
### 8. Serve the Application Locally
```bash

php artisan serve
```
The application will run on http://localhost:8000.

Running Scheduled Jobs
To enable Laravel’s scheduler (for sending periodic email notifications), add the following Cron job:

cron
```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```
Replace /path-to-your-project with the full path to your project directory.

Running Queue Workers
For queued jobs (such as dispatched emails), run:

```bash

php artisan queue:work
```
Running Tests
To run unit and feature tests:

```bash

php artisan test
```
File & Folder Permissions
Make sure the following directories are writable by your web server:

storage/

bootstrap/cache/

You can run:

```bash

chmod -R 775 storage bootstrap/cache
```

### The whole application can also be run with Nginx, Docker, and other methods. However, since no instructions are provided for those setups, they are not included here.