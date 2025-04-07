<?php
/**
 * SMTP Configuration Settings
 * 
 * This file contains the SMTP configuration settings for sending emails.
 * Update these settings with your actual SMTP credentials.
 */

// SMTP Server Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'alcedricabarientos@csab.edu.ph'); // Replace with your email
define('SMTP_PASSWORD', 'woyd hotf chlp gsvf'); // Replace with your app password
define('SMTP_FROM_EMAIL', 'alcedricabarientos@csab.edu.ph'); // Replace with your email
define('SMTP_FROM_NAME', 'Freedom Wall');

// Email Settings
define('EMAIL_SUBJECT_PREFIX', 'Freedom Wall - ');
define('OTP_EXPIRY_MINUTES', 10); 