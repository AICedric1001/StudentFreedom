# Freedom Wall Student Registration System

A web-based registration system for students with email verification using OTP.

## Setup Instructions

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure SMTP Settings

Edit the `config/smtp_config.php` file with your SMTP credentials:

```php
// SMTP Server Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Replace with your email
define('SMTP_PASSWORD', 'your-app-password'); // Replace with your app password
define('SMTP_FROM_EMAIL', 'your-email@gmail.com'); // Replace with your email
define('SMTP_FROM_NAME', 'Freedom Wall');
```

#### For Gmail Users:

1. Enable 2-Step Verification in your Google Account
2. Generate an App Password:
   - Go to Google Account > Security > 2-Step Verification
   - Scroll down to "App passwords"
   - Select "Mail" and your device
   - Use the generated 16-character password as your SMTP_PASSWORD

### 3. Database Setup

Ensure your database is properly configured in the `includes/functions.php` file.

## Features

- Student registration with email verification
- OTP-based email verification
- Secure password handling
- Form validation for student ID and email formats
- Responsive design

## Email Formats

The system accepts the following email formats:
- `studentname@csab.edu.ph`
- `j.studentname@csab.edu.ph` (where j is any single letter)

## Student ID Formats

The system accepts the following Student ID formats:
- `00-00-0000`
- `00-0000-0000` 