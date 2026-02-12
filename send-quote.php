<?php
/**
 * DC Metro Construction - Quote Request Handler
 * Sends form submissions via Gmail SMTP
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8888');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Load SMTP configuration from outside webroot
$smtpConfig = require __DIR__ . '/../config.php';

// Get and sanitize form data with header injection protection and length limits
$name = isset($_POST['name']) ? mb_substr(htmlspecialchars(trim(str_replace(["\r", "\n"], '', $_POST['name']))), 0, 100) : '';
$email = isset($_POST['email']) ? mb_substr(filter_var(trim(str_replace(["\r", "\n"], '', $_POST['email'])), FILTER_SANITIZE_EMAIL), 0, 254) : '';
$phone = isset($_POST['phone']) ? mb_substr(htmlspecialchars(trim($_POST['phone'])), 0, 30) : 'Not provided';
$company = isset($_POST['company']) ? mb_substr(htmlspecialchars(trim($_POST['company'])), 0, 200) : 'Not provided';
$service = isset($_POST['service']) ? mb_substr(htmlspecialchars(trim($_POST['service'])), 0, 50) : 'Not specified';
$budget = isset($_POST['budget']) ? mb_substr(htmlspecialchars(trim($_POST['budget'])), 0, 50) : 'Not specified';
$timeline = isset($_POST['timeline']) ? mb_substr(htmlspecialchars(trim($_POST['timeline'])), 0, 50) : 'Not specified';
$message = isset($_POST['message']) ? mb_substr(htmlspecialchars(trim($_POST['message'])), 0, 5000) : '';

// Validate required fields
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($message)) {
    $errors[] = 'Project details are required';
}

// Return errors if validation fails
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit();
}

// Format service name for readability
$serviceNames = [
    'commercial' => 'Commercial Construction',
    'residential' => 'Residential Construction',
    'renovation' => 'Renovations & Remodeling',
    'design-build' => 'Design-Build Services',
    'management' => 'Project Management',
    'preconstruction' => 'Pre-Construction Planning',
    'other' => 'Other'
];
$serviceDisplay = isset($serviceNames[$service]) ? $serviceNames[$service] : $service;

// Format budget for readability
$budgetNames = [
    'under-100k' => 'Under $100,000',
    '100k-500k' => '$100,000 - $500,000',
    '500k-1m' => '$500,000 - $1,000,000',
    '1m-5m' => '$1,000,000 - $5,000,000',
    'over-5m' => 'Over $5,000,000'
];
$budgetDisplay = isset($budgetNames[$budget]) ? $budgetNames[$budget] : $budget;

// Format timeline for readability
$timelineNames = [
    'immediate' => 'Immediate (Within 1 month)',
    '1-3months' => '1-3 Months',
    '3-6months' => '3-6 Months',
    '6-12months' => '6-12 Months',
    'planning' => 'Still Planning'
];
$timelineDisplay = isset($timelineNames[$timeline]) ? $timelineNames[$timeline] : $timeline;

// Build email content
$subject = "New Quote Request - {$serviceDisplay} - {$name}";

$htmlBody = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1a5f7a 0%, #134a5f 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
        .field { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        .field:last-child { border-bottom: none; margin-bottom: 0; }
        .label { font-weight: bold; color: #1a5f7a; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .value { font-size: 16px; color: #1e293b; }
        .message-box { background: white; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 10px; }
        .footer { background: #1e293b; color: #94a3b8; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
        .highlight { background: #f97316; color: white; padding: 3px 10px; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>New Quote Request</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>DC Metro Construction</p>
        </div>
        <div class='content'>
            <div class='field'>
                <div class='label'>Contact Name</div>
                <div class='value'>{$name}</div>
            </div>
            <div class='field'>
                <div class='label'>Email Address</div>
                <div class='value'><a href='mailto:{$email}'>{$email}</a></div>
            </div>
            <div class='field'>
                <div class='label'>Phone Number</div>
                <div class='value'>{$phone}</div>
            </div>
            <div class='field'>
                <div class='label'>Company</div>
                <div class='value'>{$company}</div>
            </div>
            <div class='field'>
                <div class='label'>Service Requested</div>
                <div class='value'><span class='highlight'>{$serviceDisplay}</span></div>
            </div>
            <div class='field'>
                <div class='label'>Estimated Budget</div>
                <div class='value'>{$budgetDisplay}</div>
            </div>
            <div class='field'>
                <div class='label'>Project Timeline</div>
                <div class='value'>{$timelineDisplay}</div>
            </div>
            <div class='field'>
                <div class='label'>Project Details</div>
                <div class='message-box'>{$message}</div>
            </div>
        </div>
        <div class='footer'>
            <p>This quote request was submitted via the DC Metro Construction website.</p>
            <p>Submitted on: " . date('F j, Y \a\t g:i A') . "</p>
        </div>
    </div>
</body>
</html>
";

$textBody = "
NEW QUOTE REQUEST - DC METRO CONSTRUCTION
==========================================

Contact Information:
- Name: {$name}
- Email: {$email}
- Phone: {$phone}
- Company: {$company}

Project Details:
- Service: {$serviceDisplay}
- Budget: {$budgetDisplay}
- Timeline: {$timelineDisplay}

Message:
{$message}

---
Submitted on: " . date('F j, Y \a\t g:i A') . "
";

// Include the SMTP mailer class
require_once __DIR__ . '/smtp-mailer.php';

// Create mailer instance
$mailer = new SMTPMailer(
    $smtpConfig['host'],
    $smtpConfig['port'],
    $smtpConfig['username'],
    $smtpConfig['password']
);

// Uncomment next line for debugging
// $mailer->setDebug(true);

// Send the email
$sent = $mailer->send(
    $smtpConfig['from_email'],
    $smtpConfig['from_name'],
    $smtpConfig['to_email'],
    $subject,
    $htmlBody,
    $textBody,
    $email,
    $name
);

if ($sent) {
    // Send confirmation email to the customer (best-effort)
    try {
        $confirmHtmlBody = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1a5f7a 0%, #134a5f 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
        .field { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        .field:last-child { border-bottom: none; margin-bottom: 0; }
        .label { font-weight: bold; color: #1a5f7a; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
        .value { font-size: 16px; color: #1e293b; }
        .message-box { background: white; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 10px; }
        .footer { background: #1e293b; color: #94a3b8; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
        .highlight { background: #f97316; color: white; padding: 3px 10px; border-radius: 4px; font-size: 14px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Quote Request Received</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>DC Metro Construction</p>
        </div>
        <div class='content'>
            <p style='font-size: 16px; margin-top: 0;'>Hi {$name},</p>
            <p>Thank you for your quote request! We have received your submission and a member of our team will be in touch shortly.</p>
            <p style='font-weight: bold; color: #1a5f7a;'>Here is a summary of your request:</p>
            <div class='field'>
                <div class='label'>Service Requested</div>
                <div class='value'><span class='highlight'>{$serviceDisplay}</span></div>
            </div>
            <div class='field'>
                <div class='label'>Estimated Budget</div>
                <div class='value'>{$budgetDisplay}</div>
            </div>
            <div class='field'>
                <div class='label'>Project Timeline</div>
                <div class='value'>{$timelineDisplay}</div>
            </div>
            <div class='field'>
                <div class='label'>Project Details</div>
                <div class='message-box'>{$message}</div>
            </div>
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;'>
            <p style='margin-bottom: 5px;'>If you have any questions, feel free to reach out:</p>
            <p style='margin: 5px 0;'>Phone: <strong>(202) 555-1234</strong></p>
            <p style='margin: 5px 0;'>Email: <strong><a href='mailto:dcmetroipw@gmail.com'>dcmetroipw@gmail.com</a></strong></p>
        </div>
        <div class='footer'>
            <p>DC Metro Construction &mdash; Building Excellence in the DMV Area</p>
            <p>This is an automated confirmation of your quote request submitted on " . date('F j, Y \a\t g:i A') . ".</p>
        </div>
    </div>
</body>
</html>
";

        $confirmTextBody = "
QUOTE REQUEST RECEIVED - DC METRO CONSTRUCTION
================================================

Hi {$name},

Thank you for your quote request! We have received your submission and a member of our team will be in touch shortly.

Here is a summary of your request:

- Service: {$serviceDisplay}
- Budget: {$budgetDisplay}
- Timeline: {$timelineDisplay}

Project Details:
{$message}

---

If you have any questions, feel free to reach out:
Phone: (202) 555-1234
Email: dcmetroipw@gmail.com

---
DC Metro Construction - Building Excellence in the DMV Area
Submitted on: " . date('F j, Y \a\t g:i A') . "
";

        $mailer->send(
            $smtpConfig['from_email'],
            $smtpConfig['from_name'],
            $email,
            'Quote Request Received - DC Metro Construction',
            $confirmHtmlBody,
            $confirmTextBody,
            $smtpConfig['from_email'],
            ''
        );
    } catch (Exception $e) {
        error_log('[DC Metro] Confirmation email failed: ' . $e->getMessage(), 3, __DIR__ . '/quote-requests.log');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your quote request has been sent. We will contact you shortly.'
    ]);
} else {
    http_response_code(500);
    error_log('[DC Metro] Quote email failed: ' . $mailer->getLastError(), 3, __DIR__ . '/quote-requests.log');
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email. Please call us directly at (202) 555-1234.'
    ]);
}
?>
