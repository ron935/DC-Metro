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
$allowedOrigins = ['http://localhost:8888', 'https://aquamarine-peafowl-476925.hostingersite.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
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

// Load Supabase configuration
$supabaseConfig = require __DIR__ . '/../supabase-config.php';

// Strip any invisible/non-ASCII characters added by file editors
foreach ($supabaseConfig as $k => $v) { if (is_string($v)) $supabaseConfig[$k] = preg_replace('/[^\x20-\x7E]/', '', $v); }
foreach ($smtpConfig as $k => $v) { if (is_string($v)) $smtpConfig[$k] = preg_replace('/[^\x20-\x7E]/', '', $v); }

// Fetch business-specific email from Supabase
$businessId = 'dd466cdb-7d43-4230-9a98-0fb6fbb700e8';
$bizUrl = $supabaseConfig['url'] . '/rest/v1/businesses?id=eq.' . $businessId . '&select=contact_email,name';
$bizCh = curl_init($bizUrl);
curl_setopt_array($bizCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . $supabaseConfig['service_role_key'],
        'Authorization: Bearer ' . $supabaseConfig['service_role_key'],
    ],
    CURLOPT_TIMEOUT => 5,
]);
$bizResult = json_decode(curl_exec($bizCh), true);
curl_close($bizCh);

if (!empty($bizResult[0]['contact_email'])) {
    // from_email stays as SMTP authenticated account (ipwemails@gmail.com) to avoid spam filters
    $smtpConfig['from_name'] = $bizResult[0]['name'] ?? 'DC Metro Construction';
    $smtpConfig['to_email'] = $bizResult[0]['contact_email'];
}

// Get and sanitize form data with header injection protection and length limits
$name = isset($_POST['name']) ? mb_substr(htmlspecialchars(trim(str_replace(["\r", "\n"], '', $_POST['name']))), 0, 100) : '';
$email = isset($_POST['email']) ? mb_substr(filter_var(trim(str_replace(["\r", "\n"], '', $_POST['email'])), FILTER_SANITIZE_EMAIL), 0, 254) : '';
$phone = isset($_POST['phone']) ? mb_substr(htmlspecialchars(trim($_POST['phone'])), 0, 30) : 'Not provided';
$company = isset($_POST['company']) ? mb_substr(htmlspecialchars(trim($_POST['company'])), 0, 200) : 'Not provided';
$service = isset($_POST['service']) ? mb_substr(htmlspecialchars(trim($_POST['service'])), 0, 50) : 'Not specified';
$budget = isset($_POST['budget']) ? mb_substr(htmlspecialchars(trim($_POST['budget'])), 0, 50) : 'Not specified';
$timeline = isset($_POST['timeline']) ? mb_substr(htmlspecialchars(trim($_POST['timeline'])), 0, 50) : 'Not specified';
$message = isset($_POST['message']) ? mb_substr(htmlspecialchars(trim($_POST['message'])), 0, 5000) : '';

// Verify Turnstile CAPTCHA
$turnstileToken = isset($_POST['cf-turnstile-response']) ? $_POST['cf-turnstile-response'] : '';
if (empty($turnstileToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Security verification required. Please try again.']);
    exit();
}

$turnstileCh = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt_array($turnstileCh, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'secret' => $smtpConfig['turnstile_secret'],
        'response' => $turnstileToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
]);
$turnstileResult = curl_exec($turnstileCh);
curl_close($turnstileCh);

$turnstileData = json_decode($turnstileResult, true);
if (!$turnstileData || empty($turnstileData['success'])) {
    http_response_code(400);
    error_log('[DC Metro] Turnstile verification failed: ' . ($turnstileResult ?: 'no response'), 3, __DIR__ . '/quote-requests.log');
    echo json_encode(['success' => false, 'message' => 'Security verification failed. Please try again.']);
    exit();
}

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

// Save quote to Supabase (best-effort, don't block on failure)
try {
    $quoteData = json_encode([
        'business_id' => 'dd466cdb-7d43-4230-9a98-0fb6fbb700e8',
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'service' => $serviceDisplay,
        'budget' => $budgetDisplay,
        'timeline' => $timelineDisplay,
        'message' => $message
    ]);

    $ch = curl_init($supabaseConfig['url'] . '/rest/v1/quotes');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $quoteData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'apikey: ' . $supabaseConfig['service_role_key'],
            'Authorization: Bearer ' . $supabaseConfig['service_role_key'],
            'Prefer: return=minimal'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($httpCode !== 201) {
        error_log("[DC Metro] Supabase insert HTTP {$httpCode}: {$response} {$curlError}", 3, __DIR__ . '/quote-requests.log');
    }
} catch (Exception $e) {
    error_log('[DC Metro] Supabase insert failed: ' . $e->getMessage(), 3, __DIR__ . '/quote-requests.log');
}

// --- Dashboard user notifications (best-effort) ---
try {
    $businessId = 'dd466cdb-7d43-4230-9a98-0fb6fbb700e8';
    $supabaseUrl = $supabaseConfig['url'];
    $serviceKey = $supabaseConfig['service_role_key'];
    $authHeaders = [
        'apikey: ' . $serviceKey,
        'Authorization: Bearer ' . $serviceKey,
    ];

    // 1. Get users associated with this business + admins
    $profilesUrl = $supabaseUrl . '/rest/v1/profiles?or=(business_id.eq.' . $businessId . ',role.eq.admin)&select=id,full_name';
    $ch = curl_init($profilesUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $authHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $profilesJson = curl_exec($ch);
    $profilesCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($profilesCode !== 200 || !$profilesJson) {
        throw new Exception("Profiles query HTTP {$profilesCode}");
    }

    $profiles = json_decode($profilesJson, true);
    if (empty($profiles)) {
        throw new Exception('No profiles found for notification');
    }

    $userIds = array_column($profiles, 'id');
    $nameMap = [];
    foreach ($profiles as $p) {
        $nameMap[$p['id']] = $p['full_name'] ?: 'there';
    }

    // 2. Check notification preferences — only users with notify_new_quote = true
    $idsParam = '(' . implode(',', $userIds) . ')';
    $prefsUrl = $supabaseUrl . '/rest/v1/notification_preferences?notify_new_quote=eq.true&user_id=in.' . $idsParam . '&select=user_id';
    $ch = curl_init($prefsUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $authHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $prefsJson = curl_exec($ch);
    $prefsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($prefsCode !== 200 || !$prefsJson) {
        throw new Exception("Prefs query HTTP {$prefsCode}");
    }

    $prefs = json_decode($prefsJson, true);

    // Also include users who have NO preferences row (default is opted-in)
    $optedOutIds = [];
    $prefsUrl2 = $supabaseUrl . '/rest/v1/notification_preferences?notify_new_quote=eq.false&user_id=in.' . $idsParam . '&select=user_id';
    $ch = curl_init($prefsUrl2);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $authHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $optedOutJson = curl_exec($ch);
    curl_close($ch);
    if ($optedOutJson) {
        $optedOut = json_decode($optedOutJson, true);
        if (is_array($optedOut)) {
            $optedOutIds = array_column($optedOut, 'user_id');
        }
    }

    // Final list: all user IDs minus those explicitly opted out
    $notifyIds = array_diff($userIds, $optedOutIds);

    if (empty($notifyIds)) {
        throw new Exception('No users opted in for new-quote notifications');
    }

    // 3 & 4. For each opted-in user: get email via Auth Admin API, then send notification
    $messagePreview = mb_substr(strip_tags($message), 0, 200);
    if (mb_strlen(strip_tags($message)) > 200) {
        $messagePreview .= '...';
    }

    foreach ($notifyIds as $userId) {
        try {
            // Get user email from Auth Admin API
            $authUrl = $supabaseUrl . '/auth/v1/admin/users/' . $userId;
            $ch = curl_init($authUrl);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $authHeaders,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $authJson = curl_exec($ch);
            $authCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($authCode !== 200 || !$authJson) {
                error_log("[DC Metro] Auth API error for user {$userId}: HTTP {$authCode}", 3, __DIR__ . '/quote-requests.log');
                continue;
            }

            $authUser = json_decode($authJson, true);
            $userEmail = $authUser['email'] ?? '';
            if (!$userEmail) {
                continue;
            }

            // Skip if this is the same as the business admin email (already gets the main quote email)
            if ($userEmail === $smtpConfig['to_email']) {
                continue;
            }

            $userName = $nameMap[$userId] ?? 'there';

            $notifSubject = "New Quote Request — {$name}";

            $notifHtml = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1a5f7a 0%, #134a5f 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
        .field { margin-bottom: 15px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .field:last-child { border-bottom: none; margin-bottom: 0; }
        .label { font-weight: bold; color: #1a5f7a; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .value { font-size: 15px; color: #1e293b; }
        .message-box { background: white; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 8px; font-size: 14px; color: #475569; }
        .cta { text-align: center; margin: 25px 0 10px 0; }
        .cta a { background: #f97316; color: white; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .footer { background: #1e293b; color: #94a3b8; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>New Quote Request</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>IPW Dashboard</p>
        </div>
        <div class='content'>
            <p style='font-size: 16px; margin-top: 0;'>Hi {$userName},</p>
            <p>A new quote request has been submitted:</p>
            <div class='field'>
                <div class='label'>Name</div>
                <div class='value'>{$name}</div>
            </div>
            <div class='field'>
                <div class='label'>Email</div>
                <div class='value'><a href='mailto:{$email}'>{$email}</a></div>
            </div>
            <div class='field'>
                <div class='label'>Phone</div>
                <div class='value'>{$phone}</div>
            </div>
            <div class='field'>
                <div class='label'>Service</div>
                <div class='value'>{$serviceDisplay}</div>
            </div>
            <div class='field'>
                <div class='label'>Budget</div>
                <div class='value'>{$budgetDisplay}</div>
            </div>
            <div class='field'>
                <div class='label'>Timeline</div>
                <div class='value'>{$timelineDisplay}</div>
            </div>
            <div class='field'>
                <div class='label'>Message</div>
                <div class='message-box'>{$messagePreview}</div>
            </div>
            <div class='cta'>
                <a href='https://aquamarine-peafowl-476925.hostingersite.com/dashboard/'>View in Dashboard</a>
            </div>
        </div>
        <div class='footer'>
            <p>IPW Dashboard &mdash; Notification</p>
            <p>You received this because you have New Quote Alerts enabled.</p>
        </div>
    </div>
</body>
</html>";

            $notifText = "NEW QUOTE REQUEST — IPW DASHBOARD
==========================================

Hi {$userName},

A new quote request has been submitted:

Name: {$name}
Email: {$email}
Phone: {$phone}
Service: {$serviceDisplay}
Budget: {$budgetDisplay}
Timeline: {$timelineDisplay}

Message:
{$messagePreview}

Log in to your dashboard to review and respond.

---
IPW Dashboard Notification
You received this because you have New Quote Alerts enabled.";

            $notifSent = $mailer->send(
                $smtpConfig['username'],
                'IPW Dashboard',
                $userEmail,
                $notifSubject,
                $notifHtml,
                $notifText,
                $email,
                $name
            );

            if ($notifSent) {
                error_log("[DC Metro] Notification sent to {$userEmail}\n", 3, __DIR__ . '/quote-requests.log');
            } else {
                error_log("[DC Metro] Notification send failed for {$userEmail}: " . $mailer->getLastError() . "\n", 3, __DIR__ . '/quote-requests.log');
            }

        } catch (Exception $e) {
            error_log("[DC Metro] Notification email failed for user {$userId}: " . $e->getMessage(), 3, __DIR__ . '/quote-requests.log');
        }
    }

} catch (Exception $e) {
    error_log('[DC Metro] Dashboard notification failed: ' . $e->getMessage(), 3, __DIR__ . '/quote-requests.log');
}

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
    $smtpError = $mailer->getLastError();
    error_log('[DC Metro] Quote email failed: ' . $smtpError, 3, __DIR__ . '/quote-requests.log');

    // Backup: try to alert admin that a quote submission email failed
    // The quote is still saved in Supabase, but the admin should know email delivery failed
    try {
        $failAlertSubject = '[ALERT] Quote email delivery failed — ' . $name;
        $failAlertHtml = "
<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;color:#333;padding:20px'>
<div style='max-width:600px;margin:0 auto'>
<div style='background:#ef4444;color:white;padding:20px;border-radius:8px 8px 0 0;text-align:center'>
<h2 style='margin:0'>Email Delivery Failed</h2></div>
<div style='background:#fef2f2;padding:25px;border:1px solid #fecaca'>
<p>A quote form submission email <strong>failed to deliver</strong>. The quote has been saved to the dashboard database.</p>
<p><strong>Customer:</strong> {$name} ({$email})</p>
<p><strong>Service:</strong> {$serviceDisplay}</p>
<p><strong>Error:</strong> {$smtpError}</p>
<p style='margin-top:20px'><a href='http://localhost:8888/dashboard/' style='background:#1a5f7a;color:white;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold'>View in Dashboard</a></p>
</div></div></body></html>";

        $failAlertText = "ALERT: Quote email delivery failed\n\nCustomer: {$name} ({$email})\nService: {$serviceDisplay}\nError: {$smtpError}\n\nThe quote has been saved in the dashboard. Please review it there.";

        $mailer->send(
            $smtpConfig['from_email'],
            'IPW Alert System',
            $smtpConfig['to_email'],
            $failAlertSubject,
            $failAlertHtml,
            $failAlertText,
            '',
            ''
        );
    } catch (Exception $e) {
        error_log('[DC Metro] Backup failure alert also failed: ' . $e->getMessage(), 3, __DIR__ . '/quote-requests.log');
    }

    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email. Please call us directly at (202) 555-1234.'
    ]);
}
?>
