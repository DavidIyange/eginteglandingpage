<?php
/**
 * Enquiry form handler - EG Integrated Services Ltd
 * Emails each valid enquiry and (optionally) saves it to a CSV file.
 */
declare(strict_types=1);

/* ================= SETTINGS (edit these) ================= */
const TO_EMAIL     = 'info@eginteg.com';          // who receives enquiries
const FROM_EMAIL   = 'no-reply@eginteg.com';      // must be an address on YOUR domain, or mail may be rejected
const SITE_NAME    = 'EG Integrated Services Ltd';
const THANK_YOU    = 'thank-you.html';
const SAVE_TO_CSV  = true;
const LEADS_DIR    = __DIR__ . '/leads';          // best moved OUTSIDE the public web folder, see README
const MIN_SECONDS  = 2;                           // faster than this = probably a bot
const MAX_PER_HOUR = 5;                           // submissions allowed per IP per hour
const INTERESTS    = ['Netwrix Auditor', 'Microsoft Sentinel', 'Both products', 'Not sure yet, I need advice'];
/* ========================================================== */

date_default_timezone_set('Africa/Lagos');

$wantsJson = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch')
          || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function respond(bool $ok, string $message = '', array $errors = [], int $status = 200): void
{
    global $wantsJson;
    if ($wantsJson) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok'       => $ok,
            'message'  => $message,
            'errors'   => $errors,
            'redirect' => $ok ? THANK_YOU : null,
        ]);
    } else {
        if ($ok) {
            header('Location: ' . THANK_YOU, true, 303);
        } else {
            http_response_code($status);
            header('Content-Type: text/plain; charset=utf-8');
            echo $message . "\nPlease go back and try again, or call +234 703 222 2399.";
        }
    }
    exit;
}

/** Trim, strip control characters and limit length. Also blocks header injection. */
function clean($v, int $max): string
{
    if (is_array($v)) { return ''; }
    $v = trim((string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}
function oneLine(string $v): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $v) ?? '');
}
/** Stop spreadsheet formulas in the CSV file */
function csvSafe(string $v): string
{
    return (strlen($v) && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) ? "'" . $v : $v;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'This page only accepts form submissions.', [], 405);
}

/* ---------- Spam checks ---------- */
if (clean($_POST['website'] ?? '', 200) !== '') {            // honeypot filled in
    respond(true);                                             // pretend success, store nothing
}
$elapsed = (int)($_POST['elapsed'] ?? 0);
if ($elapsed < MIN_SECONDS) {
    respond(false, 'Please take a moment to complete the form and try again.', [], 400);
}

/* ---------- Rate limit per IP ---------- */
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!is_dir(LEADS_DIR)) { @mkdir(LEADS_DIR, 0750, true); }
$rateFile = LEADS_DIR . '/.rate';
$now = time();
$recent = [];
$fh = @fopen($rateFile, 'c+');
if ($fh && flock($fh, LOCK_EX)) {
    $lines = array_filter(explode("\n", (string)stream_get_contents($fh)));
    foreach ($lines as $line) {
        [$t, $i] = array_pad(explode('|', $line, 2), 2, '');
        if ((int)$t > $now - 3600) { $recent[] = [(int)$t, $i]; }
    }
    $mine = count(array_filter($recent, fn($r) => $r[1] === $ip));
    if ($mine >= MAX_PER_HOUR) {
        flock($fh, LOCK_UN); fclose($fh);
        respond(false, 'Too many requests from your network. Please try again later or call us.', [], 429);
    }
    $recent[] = [$now, $ip];
    ftruncate($fh, 0); rewind($fh);
    fwrite($fh, implode("\n", array_map(fn($r) => $r[0] . '|' . $r[1], $recent)));
    flock($fh, LOCK_UN);
}
if ($fh) { fclose($fh); }

/* ---------- Validate ---------- */
$name     = clean($_POST['name'] ?? '', 100);
$org      = clean($_POST['organisation'] ?? '', 150);
$email    = clean($_POST['email'] ?? '', 150);
$phone    = clean($_POST['phone'] ?? '', 25);
$interest = clean($_POST['interest'] ?? '', 60);
$message  = clean($_POST['message'] ?? '', 1500);
$consent  = ($_POST['consent'] ?? '') === 'yes';

$errors = [];
if (mb_strlen($name) < 2)                                  { $errors['name'] = 'Enter your full name.'; }
if (mb_strlen($org) < 2)                                   { $errors['organisation'] = 'Enter your organisation.'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL))            { $errors['email'] = 'Enter a valid email address.'; }
$digits = preg_replace('/\D/', '', $phone) ?? '';
if (strlen($digits) < 7 || strlen($digits) > 15 || !preg_match('/^[0-9+()\-\s]+$/', $phone)) {
    $errors['phone'] = 'Enter a valid phone number.';
}
if (!in_array($interest, INTERESTS, true))                 { $errors['interest'] = 'Choose an option.'; }
if (!$consent)                                             { $errors['consent'] = 'Consent is required.'; }
if ($errors) {
    respond(false, 'Please check the highlighted fields and try again.', $errors, 422);
}

/* ---------- Campaign data ---------- */
$track = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'page_url'] as $k) {
    $track[$k] = clean($_POST[$k] ?? '', 200);
}
$when = date('Y-m-d H:i:s') . ' (WAT)';
$ua   = clean($_SERVER['HTTP_USER_AGENT'] ?? '', 200);

/* ---------- Save to CSV ---------- */
$saved = false;
if (SAVE_TO_CSV && is_dir(LEADS_DIR) && is_writable(LEADS_DIR)) {
    $csv = LEADS_DIR . '/leads.csv';
    $isNew = !file_exists($csv);
    $out = @fopen($csv, 'a');
    if ($out && flock($out, LOCK_EX)) {
        if ($isNew) {
            fputcsv($out, ['Time', 'Name', 'Organisation', 'Email', 'Phone', 'Interest', 'Message',
                           'Source', 'Medium', 'Campaign', 'Keyword', 'Content', 'GCLID', 'Page', 'IP']);
        }
        fputcsv($out, array_map('csvSafe', [
            $when, $name, $org, $email, $phone, $interest, $message,
            $track['utm_source'], $track['utm_medium'], $track['utm_campaign'], $track['utm_term'],
            $track['utm_content'], $track['gclid'], $track['page_url'], $ip,
        ]));
        flock($out, LOCK_UN);
        $saved = true;
    }
    if ($out) { fclose($out); }
}

/* ---------- Email the team ---------- */
$subject = 'New enquiry: ' . oneLine($org) . ' (' . $interest . ')';
$body  = "New website enquiry - Cybersecurity landing page\n";
$body .= "------------------------------------------------\n";
$body .= "Name:         $name\n";
$body .= "Organisation: $org\n";
$body .= "Email:        $email\n";
$body .= "Phone:        $phone\n";
$body .= "Interested in: $interest\n\n";
$body .= "Message:\n" . ($message !== '' ? $message : '(none)') . "\n\n";
$body .= "------------------------------------------------\n";
$body .= "Campaign source:  {$track['utm_source']}\n";
$body .= "Campaign medium:  {$track['utm_medium']}\n";
$body .= "Campaign name:    {$track['utm_campaign']}\n";
$body .= "Keyword:          {$track['utm_term']}\n";
$body .= "Google click ID:  {$track['gclid']}\n";
$body .= "Page:             {$track['page_url']}\n";
$body .= "Received:         $when\n";
$body .= "IP / browser:     $ip / $ua\n";

$headers = [
    'From: ' . SITE_NAME . ' <' . FROM_EMAIL . '>',
    'Reply-To: ' . oneLine($name) . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];
$encodedSubject = '=?UTF-8?B?' . base64_encode(oneLine($subject)) . '?=';
$mailed = @mail(TO_EMAIL, $encodedSubject, $body, implode("\r\n", $headers), '-f' . FROM_EMAIL);

if ($mailed || $saved) {
    respond(true);
}
respond(false, 'We could not send your request. Please call +234 703 222 2399 or email info@eginteg.com.', [], 500);
