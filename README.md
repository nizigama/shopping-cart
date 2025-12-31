# Shopping Cart

A shopping cart application built with **Laravel 12**, **Vue 3**, and **Inertia.js**. Features include user authentication, product browsing with stock constraints, cart functionality, and automated email notifications for low stock alerts and daily sales reports.

## Tech Stack

- **Backend**: Laravel 12
- **Frontend**: Inertia.js
- **Styling**: Tailwind CSS 4
- **Database**: SQLite
- **Testing**: Pest PHP
- **Email**: Mailpit (for development)

## Features

- **User Authentication**
  - Registration & Login
  - Email verification
  - Password reset

- **Product Management**
  - Product listing with search
  - Stock tracking per product
  - Pagination

- **Shopping Cart**
  - Add/remove products
  - Update quantities
  - Stock validation
  - Clear cart functionality

- **Automated Notifications**
  - Low stock email alerts (triggered when stock falls below threshold)
  - Daily sales report emails (scheduled at 6 PM)

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- Docker (optional, for Mailpit email testing)

## Installation

### 1. Clone the repository

```bash
git clone <repository-url>
cd shopping-cart
```

### 1.1. Create a database for sqlite

```bash
touch database/database.sqlite
```

### 2. Quick Setup (Recommended)

Run the automated setup script:

```bash
composer setup
```

This will:
- Install PHP dependencies
- Create `.env` file from `.env.example`
- Generate application key
- Run database migrations
- Install Node.js dependencies
- Build frontend assets

### 3. Manual Setup (Alternative)

If you prefer manual setup:

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database (if not exists)
touch database/database.sqlite

# Run migrations
php artisan migrate

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build
```

### 4. Seed the Database (Optional)

Populate the database with sample data:

```bash
php artisan db:seed
```

This creates:
- A test user: `test@example.com` (password: `password`)
- 20 sample products with stock

## Running the Application

### Development Mode

Start all services with a single command:

```bash
composer dev
```

This runs concurrently:
- **Laravel server** at `http://localhost:8000`
- **Queue worker** for background jobs
- **Pail** for real-time log viewing
- **Vite** dev server for hot module replacement

## Email Testing with Mailpit

For local email testing, start Mailpit using Docker:

```bash
docker compose up
```

Access the Mailpit dashboard at `http://localhost:8025` for testing emails.

## Testing

Run the test suite with Pest:

```bash
composer test
```

Or directly:

```bash
php artisan test
```