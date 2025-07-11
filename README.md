# DeskMint Backend

DeskMint is a source-available web application designed to simplify workflows for small businesses, teams, and organizations. It streamlines client communication, document management, invoicing, and task tracking through a centralized platform.

## Project Overview

This repository contains the backend for DeskMint. The backend is responsible for handling the business logic, API endpoints, database management, authentication, and integration services. It is designed to work seamlessly with the [deskmint-frontend](https://github.com/parthmp/deskmint-frontend) repository.

## Tech Stack

- **PHP 8.x**
- **Laravel** (Modern PHP web framework)
- **MySQL** (Relational database)
- **Sanctum** (Token-based API authentication)
- **Laravel Queues** (for background jobs)
- **Laravel Scheduler** (for recurring tasks)
- **Filesystem Abstraction** (for local and cloud storage)
- **Third-party Integrations** (planned for email, cloud storage, etc.)

## Features

- **RESTful API**: Built for modern SPAs like DeskMint frontend
- **Authentication**: Secure token-based login and user access control
- **Recurring Tasks**: Schedule and auto-generate repeatable items
- **Smart Document Handling**: File upload, tagging, and structured storage
- **Custom Branding Support**: White-label ready

## Installation

### Prerequisites

- **PHP** 8.1 or higher  
- **Composer**
- **MySQL** 8.x
- **Laravel CLI** *(optional but useful)*

### Setup

```bash
git clone https://github.com/parthmp/deskmint-backend.git
cd deskmint-backend
composer install
cp .env.example .env
php artisan key:generate
```
### Edit the `.env` file

Edit the `.env` file with your database and environment configuration, then run:

```bash
php artisan migrate --seed
php artisan serve
```

### Queue

```bash
php artisan queue:listen
```

### API
All endpoints are prefixed with `/api`. Authentication is handled using Laravel Sanctum.
For available routes, check:
```bash
routes/api.php
```

### License
🔒 **License**: DeskMint is source-available software licensed under the [Elastic License 2.0](./LICENSE). You are free to view, modify, and self-host it for personal and commercial usage. However, you are not allowed to offer it as a hosted service or sell it as a SaaS product without a commercial license.