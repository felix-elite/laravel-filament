# Campaign and Lead Management System

A Laravel application with Filament admin panel for managing campaigns and leads, featuring bulk import functionality with validation and detailed error reporting.

## Features

- Campaign management (CRUD operations)
- Lead management with campaign association
- Bulk import of leads via Excel/CSV files
- Advanced validation for lead data
- Detailed error reporting for failed imports
- Modern and user-friendly interface using Filament

## Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Composer
- Node.js & NPM

## Installation

1. Clone the repository:
```bash
git clone https://github.com/felix-elite/filament-laravel.git
cd test-project
```

2. Install PHP dependencies:
```bash
composer install
```

3. Copy the environment file:
```bash
cp .env.example .env
```

4. Configure your database in the .env file:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=job_test
DB_USERNAME=root
DB_PASSWORD=
```

5. Generate application key:
```bash
php artisan key:generate
```

6. Run database migrations:
```bash
php artisan migrate
```

7. Create storage link:
```bash
php artisan storage:link
```

8. Create an admin user:
```bash
php artisan make:filament-user
```

9. Start the development server:
```bash
php artisan serve
```

## Usage

1. Access the admin panel at `http://localhost:8000/admin`
2. Log in with your admin credentials
3. Navigate to Campaigns to create and manage campaigns
4. Navigate to Leads to:
   - Create individual leads
   - Import leads in bulk using Excel/CSV files
   - View and manage existing leads

### Bulk Import Format

The Excel/CSV file should contain the following columns:
- name (required)
- email (required)
- phone_number (required)

Example:
```csv
name,email,phone_number
John Doe,john@example.com,+1234567890
Jane Smith,jane@example.com,+9876543210
```

## Error Handling

When importing leads:
- Valid leads will be imported successfully
- Invalid leads will be skipped
- A detailed error report (CSV) will be generated for failed imports
- The error report can be downloaded directly from the notification

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
