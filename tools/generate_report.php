<?php
/**
 * SCO 207 group project report generator.
 *
 *   php tools/generate_report.php
 *
 * Writes SCO_207_Group_Project_Report.docx in the project root using raw
 * OpenXML packed with ZipArchive (no libraries). Before printing, edit the
 * $students array below and paste screenshots from docs/screenshots/ over
 * the red placeholders.
 *
 * Every technical statement here describes the code on this branch; if you
 * change the schema or features, update the matching paragraph.
 */
declare(strict_types=1);

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Group members – EDIT THESE before generating the final report
// ---------------------------------------------------------------------------
$students = [
    ['1', 'John Doe', 'SC201/0001/2024'],
    ['2', 'Jane Smith', 'SC201/0002/2024'],
    ['3', 'Alex Mercer', 'SC201/0003/2024'],
    ['4', 'Sarah Connor', 'SC201/0004/2024'],
    ['5', 'Bruce Wayne', 'SC201/0005/2024'],
    ['6', 'Clark Kent', 'SC201/0006/2024'],
    ['7', 'Diana Prince', 'SC201/0007/2024'],
    ['8', 'Peter Parker', 'SC201/0008/2024'],
    ['9', 'Tony Stark', 'SC201/0009/2024'],
    ['10', 'Steve Rogers', 'SC201/0010/2024'],
];
$placeholderNames = $students[0][1] === 'John Doe';

// ---------------------------------------------------------------------------
// OpenXML helpers
// ---------------------------------------------------------------------------
function x(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function run(string $text, string $rPr = ''): string
{
    return '<w:r>' . ($rPr !== '' ? "<w:rPr>{$rPr}</w:rPr>" : '') . '<w:t xml:space="preserve">' . x($text) . '</w:t></w:r>';
}

function para(string $runs, string $pPr = ''): string
{
    return '<w:p>' . ($pPr !== '' ? "<w:pPr>{$pPr}</w:pPr>" : '') . $runs . '</w:p>';
}

function p_title(string $t): string     { return para(run($t), '<w:pStyle w:val="Title"/>'); }
function p_subtitle(string $t): string  { return para(run($t), '<w:pStyle w:val="Subtitle"/>'); }
function h1(string $t): string          { return para(run($t), '<w:pStyle w:val="Heading1"/>'); }
function h2(string $t): string          { return para(run($t), '<w:pStyle w:val="Heading2"/>'); }
function p_meta(string $t, bool $b = false): string
{
    return para(run($t, $b ? '<w:b/>' : ''), '<w:jc w:val="center"/><w:spacing w:before="60" w:after="60"/>');
}

/** Body paragraph; **bold** segments are supported. */
function p_body(string $t): string
{
    $runs = '';
    foreach (preg_split('/(\*\*[^*]+\*\*)/', $t, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
        $runs .= str_starts_with($part, '**') ? run(substr($part, 2, -2), '<w:b/>') : run($part);
    }
    return para($runs, '<w:jc w:val="both"/>');
}

function bullet(string $t): string
{
    return para(run('•  ') . substr(p_body($t), strlen('<w:p><w:pPr><w:jc w:val="both"/></w:pPr>'), -strlen('</w:p>')),
        '<w:ind w:left="360" w:hanging="240"/><w:spacing w:after="60"/>');
}

function p_code(array $lines): string
{
    $xml = '';
    foreach ($lines as $line) {
        $xml .= para(run($line, '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="17"/>'),
            '<w:pStyle w:val="Code"/>');
    }
    return $xml;
}

function p_screenshot(string $file, string $caption): string
{
    return para(run("[SCREENSHOT: docs/screenshots/{$file} — {$caption}]",
        '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:b/><w:color w:val="C62828"/><w:sz w:val="19"/>'),
        '<w:jc w:val="center"/><w:spacing w:before="200" w:after="200"/>');
}

/** Simple bordered table; first row is a shaded header. */
function table(array $rows, array $widthsPct = []): string
{
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>';
    foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
        $xml .= "<w:{$side} w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"9E9E9E\"/>";
    }
    $xml .= '</w:tblBorders><w:tblCellMar><w:left w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr>';
    foreach ($rows as $i => $row) {
        $xml .= '<w:tr>' . ($i === 0 ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
        foreach (array_values($row) as $c => $cell) {
            $w   = isset($widthsPct[$c]) ? '<w:tcW w:w="' . ($widthsPct[$c] * 50) . '" w:type="pct"/>' : '';
            $shd = $i === 0 ? '<w:shd w:val="clear" w:color="auto" w:fill="2E7D32"/>' : '';
            $rPr = ($i === 0 ? '<w:b/><w:color w:val="FFFFFF"/>' : '') . '<w:sz w:val="18"/>';
            $xml .= "<w:tc><w:tcPr>{$w}{$shd}</w:tcPr>" . para(run((string) $cell, $rPr), '<w:spacing w:before="20" w:after="20"/>') . '</w:tc>';
        }
        $xml .= '</w:tr>';
    }
    return $xml . '</w:tbl>' . para('');
}

function page_break(): string
{
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
}

// ---------------------------------------------------------------------------
// Document body
// ---------------------------------------------------------------------------
$b = '';

// --- Title page ---
$b .= p_title('SCO 207: DATABASE SYSTEMS');
$b .= p_subtitle('GROUP PROJECT REPORT');
$b .= p_title('AgriTrack: A Direct Farmer-to-Business Digital Marketplace');
$b .= para('');
$memberRows = [['S/No', 'Student Full Name', 'Registration Number', 'Signature']];
foreach ($students as [$n, $name, $reg]) {
    $memberRows[] = [$n, $name, $reg, ''];
}
$b .= table($memberRows, [10, 40, 30, 20]);
$b .= p_meta('Submitted to: Department of Computer Science', true);
$b .= p_meta('Course: SCO 207 Database Systems');
$b .= p_meta('Date of submission: ' . date('F j, Y'), true);
$b .= page_break();

// --- 1. Introduction ---
$b .= h1('1. Introduction');
$b .= h2('1.1 Problem definition');
$b .= p_body('Smallholder farmers in Kenya usually sell through brokers who set farm-gate prices and take a large margin, while businesses that buy produce (hotels, shops, schools, caterers) have little visibility of who grows what, where, and at what price. Neither side has a shared record of listings, conversations or past prices.');
$b .= p_body('AgriTrack is a web application backed by a relational database (MariaDB/MySQL) in which farmers list produce, business owners find it and contact the farmer directly, both sides negotiate in a message thread, and every listed price is kept in a history that shows market trends per product and county.');
$b .= h2('1.2 Objectives');
foreach ([
    'Design a normalised relational schema (to third normal form) for users, listings, conversations, ratings and price history, with integrity enforced by keys, CHECK constraints and triggers in the database itself.',
    'Provide secure accounts for two roles, Farmer and Business Owner, with email/password and optional Google sign-in.',
    'Let farmers list products with availability, growth stage, planting and expected harvest dates, and receive harvest reminders.',
    'Let business owners search and filter listings by product, county, category, availability and growth stage, and contact farmers through an in-app conversation, WhatsApp or SMS.',
    'Record every listed price and present monthly and per-county price trends using SQL aggregation.',
    'Measure query performance with EXPLAIN and add indexes only where they help.',
] as $i => $o) {
    $b .= p_body(($i + 1) . '. ' . $o);
}
$b .= page_break();

// --- 2. System design ---
$b .= h1('2. System Design');
$b .= h2('2.1 Architecture');
$b .= p_body('AgriTrack is a server-rendered PHP 8 application. Only the public/ directory is web-accessible; configuration (.env), SQL migrations, CLI tools and shared code (src/, includes/) sit outside the document root. All database access uses PDO prepared statements. The schema is built by eight ordered migration files (migrations/001_init.sql to 008_indexes.sql) applied by bin/migrate.php, which records applied versions in a schema_migrations table, so the schema can evolve without losing data.');

$b .= h2('2.2 Entities and relationships');
$b .= p_body('The final schema has twelve application tables. Primary keys (PK) and foreign keys (FK) are listed below.');
$b .= table([
    ['Table', 'Purpose', 'Keys'],
    ['users', 'Farmers and business owners (role ENUM), contact details, password hash, hashed verification token', 'PK id; UNIQUE email; FK county_id → counties'],
    ['counties', 'The 47 counties of Kenya (official codes 1–47)', 'PK id; UNIQUE name'],
    ['categories', 'Product categories (Grains & Cereals, Vegetables, …)', 'PK id; UNIQUE name'],
    ['crops', 'Product listings: price, unit, quantity, availability (status), optional growth_stage, planting/harvest dates', 'PK id; FK user_id → users (CASCADE); FK category_id → categories'],
    ['inquiries', 'One conversation per (crop, buyer) with a status', 'PK id; FK crop_id → crops, buyer_id → users (CASCADE); UNIQUE (crop_id, buyer_id)'],
    ['messages', 'Every message in a conversation', 'PK id; FK inquiry_id → inquiries, sender_id → users (CASCADE)'],
    ['ratings', 'One 1–5 rating per completed conversation', 'PK/FK inquiry_id → inquiries (CASCADE)'],
    ['price_history', 'Append-only ledger of observed listing prices', 'PK id; FK crop_id → crops (SET NULL); FK county_id → counties'],
    ['activity_log', 'Audit trail of user actions with IP address', 'PK id; FK user_id → users (CASCADE)'],
    ['password_resets', 'SHA-256 hashes of one-time reset tokens with expiry', 'PK id; FK user_id → users (CASCADE); UNIQUE token_hash'],
    ['login_attempts', 'Failed logins, for rate limiting', 'PK id'],
    ['schema_migrations', 'Applied migration files (tooling)', 'PK version'],
], [20, 50, 30]);
$b .= p_body('Cardinalities: a county has many users; a farmer (user) has many crops; a category classifies many crops; a crop has many inquiries; a buyer (user) has many inquiries but at most one per crop; an inquiry has many messages and at most one rating.');
$b .= p_screenshot('er-diagram.png', 'ER diagram (the Mermaid diagram in README.md rendered on GitHub)');

$b .= h2('2.3 Normalisation');
$b .= p_body('The first version stored several facts redundantly. The migrations remove those redundancies:');
$b .= bullet('**Repeating free-text values (002).** users.county and crops.category were free text validated only in PHP. They were replaced by foreign keys to the counties and categories lookup tables, so spelling cannot drift and the database guarantees the value exists.');
$b .= bullet('**Two facts in one column (003).** crops.status mixed marketplace availability with the agronomic growth stage. It was split into status (available / out_of_stock) and an optional growth_stage (planted / growing / harvested / failed).');
$b .= bullet('**Update anomaly (004).** inquiries copied buyer_name and buyer_email from users; a profile change left stale copies. Both columns were dropped and are now obtained by joining users.');
$b .= bullet('**Transitive dependency (004).** inquiries.farmer_id repeated crops.user_id (inquiry → crop → farmer). It was dropped; the farmer is derived through the crop. Duplicate inquiries for the same (crop, buyer) were merged before a UNIQUE constraint was added, and each inquiry\'s message moved into the new messages table.');
$b .= p_body('After these steps every non-key attribute depends only on its table\'s key (3NF), with two documented, deliberate exceptions:');
$b .= bullet('**inquiries.contact_phone** is a snapshot of the phone number the buyer gave for that deal, which may differ from the profile phone. It is a fact about the deal, not a copy of users.phone.');
$b .= bullet('**price_history.product_key, unit and county_id** record what was true when a price was observed. The table is a historical ledger: if a listing is renamed, moved or deleted (crop_id becomes NULL), past observations must not change.');

$b .= h2('2.4 Integrity constraints');
$b .= p_body('Rules that were previously only checked in PHP are enforced by MariaDB CHECK constraints (migrations 004, 005 and 007), so no code path or manual SQL can violate them. Migration 007 first repairs existing rows that would break a new rule.');
$b .= table([
    ['Constraint', 'Rule'],
    ['chk_crops_price_positive', 'price IS NULL OR price > 0 (NULL = negotiable)'],
    ['chk_crops_quantity_nonneg', 'quantity IS NULL OR quantity >= 0'],
    ['chk_crops_harvest_after_planting', 'expected_harvest is not before planting_date'],
    ['chk_crops_unit', "unit IN ('kg','bag','crate','litre','dozen','piece','tonne','bundle')"],
    ['chk_crops_name_not_blank / chk_users_name_not_blank', 'names are not empty or whitespace'],
    ['chk_msg_body_not_blank', 'messages cannot be blank'],
    ['chk_rating_score', 'score BETWEEN 1 AND 5'],
    ['chk_ph_price_positive', 'price_history.price > 0'],
    ['uq_inquiry_crop_buyer', 'one conversation per buyer per product'],
], [40, 60]);

$b .= h2('2.5 Views');
$b .= bullet('**v_marketplace** – every farmer listing joined with the farmer\'s name, phone, county and the category name. The marketplace, dashboards, product page and inquiry form query this view instead of repeating a four-table join.');
$b .= bullet('**v_farmer_ratings** – average score and number of ratings per farmer (GROUP BY over ratings → inquiries → crops).');
$b .= bullet('**v_inquiry_threads** – one row per conversation with both parties\' names, the latest message and the message count.');

$b .= h2('2.6 Triggers');
$b .= p_body('Two triggers on crops maintain price_history automatically (migration 005): trg_crops_price_after_insert records the price of a new listing, and trg_crops_price_after_update appends a row when a listing\'s price, unit or name changes. Prices that are NULL or not positive are ignored. Each trigger body is a single INSERT … SELECT, so the migration needs no DELIMITER and can also be imported through phpMyAdmin.');

$b .= h2('2.7 Indexes and query plans');
$b .= p_body('Apart from primary keys, UNIQUE columns and the indexes InnoDB creates for foreign keys, indexes were added only for queries the application runs (migration 008). tools/explain_demo.php builds a scratch database with the demo data plus about 210,000 synthetic rows, records EXPLAIN plans and the median of 30 timings before and after migration 008, and writes docs/EXPLAIN.md. Results:');
$b .= table([
    ['Query', 'Index', 'Est. rows before → after', 'Median time before → after'],
    ['Login rate limit', 'idx_login_attempts_ip_time', '49,909 → 1', '13.66 → 0.26 ms'],
    ['Password reset token lookup', 'uq_password_resets_token', '19,966 → 0', '6.79 → 0.22 ms'],
    ['Buyer dashboard: newest available', 'idx_crops_status_created', '2,043 → 5,987 *', '54.56 → 0.66 ms'],
    ['Price trend for one product', 'idx_price_history_lookup', '73,184 → 173', '24.56 → 0.99 ms'],
    ['Farmer dashboard: my listings', 'idx_crops_user_created', '13 → 13', '0.37 → 0.55 ms'],
    ['Activity log page', 'idx_activity_user_created', '29 → 29', '0.36 → 0.29 ms'],
    ['Marketplace by county', 'none added', '65 → 65', '2.48 → 2.68 ms'],
], [30, 30, 20, 20]);
$b .= p_body('* For the buyer dashboard the estimated row count rises because the optimizer now scans the index range in created_at order, but it stops after the LIMIT and no longer builds a temporary table and sorts, so the query is about 80 times faster. For the farmer dashboard and activity log the gain is removing "Using filesort"; at a few dozen rows per user the time difference is within measurement noise. A composite (role, county_id) index on users was tried for the marketplace county filter, produced the same plan and timing, and was therefore left out, since every index slows writes.');
$b .= page_break();

// --- 3. Algorithms ---
$b .= h1('3. Algorithms and Methods');
$b .= h2('3.1 Registration');
$b .= p_code([
    'INPUT  name, email, password, confirm, role, phone, county',
    'IF name empty OR email invalid OR len(password) < 8 OR password != confirm',
    '   OR phone not like 7-15 digits OR county not in counties THEN show errors',
    'IF email already in users THEN show "already registered"',
    'needsVerification := SMTP is configured',
    'IF needsVerification THEN token := 32 random bytes (hex); tokenHash := SHA-256(token)',
    'BEGIN TRANSACTION',
    '   INSERT users(..., password_hash = password_hash(password),',
    '                email_verified = NOT needsVerification, verification_token_hash = tokenHash)',
    '   INSERT activity_log(user, "register")',
    'COMMIT',
    'IF needsVerification THEN email link verify.php?token=token',
    '   (if sending fails, mark the account verified so the user is not locked out)',
]);
$b .= h2('3.2 Login with rate limiting');
$b .= p_code([
    'accountFails := failed attempts for this email in the last 15 minutes',
    'ipFails      := failed attempts from this IP in the last 15 minutes',
    'IF accountFails >= 5 OR ipFails >= 20 THEN reject "too many attempts"',
    'user := SELECT * FROM users WHERE email = ?',
    'IF user AND password_verify(password, user.password_hash) THEN',
    '   IF NOT user.email_verified THEN reject "verify your email first"',
    '   rehash the password if the algorithm changed',
    '   DELETE login_attempts for this email; regenerate session id; log "login"',
    'ELSE INSERT login_attempts(email, ip); reject "invalid email or password"',
]);
$b .= p_body('The per-account limit stops password guessing against one account from many IP addresses; the per-IP limit is higher because many users can share one IP address (cyber cafés, mobile carrier NAT).');
$b .= h2('3.3 Inquiry conversation lifecycle');
$b .= p_code([
    'Buyer opens product (must be status = available, role = buyer):',
    '   IF an inquiry for (crop, buyer) exists THEN go to that thread',
    '   BEGIN; INSERT inquiries(status = pending); INSERT messages(first message); COMMIT',
    '   notify farmer by email and SMS (when configured)',
    'Farmer opens the thread:    pending -> read',
    'Farmer posts a message:     status -> responded   (unless completed)',
    'Buyer posts a message:      status -> pending     (unless completed)',
    'Either party marks done:    status -> completed',
    'Buyer, once completed and not yet rated: INSERT ratings(score 1..5, comment)',
]);
$b .= h2('3.4 Price history and trends');
$b .= p_code([
    'ON INSERT crops:  IF NEW.price > 0 THEN append (crop, lower(trim(name)), unit, farmer county, price)',
    'ON UPDATE crops:  IF NEW.price > 0 AND (price, unit or name changed) THEN append the same',
    'Trend page:  SELECT month, AVG(price), MIN(price), MAX(price), COUNT(*)',
    '             FROM price_history WHERE product_key = ? AND unit = ? [AND county_id = ?]',
    '             AND recorded_at within the chosen period GROUP BY month',
    '             plus AVG(price) per county over the last 90 days',
]);
$b .= page_break();

// --- 4. Implementation ---
$b .= h1('4. Implementation');
$b .= h2('4.1 Tools and technologies');
foreach ([
    '**PHP 8.1+** with PDO prepared statements; no framework. Shared logic in src/ (App\\Upload, App\\CropInput, App\\Inquiries, App\\Token, App\\Phone, App\\Sms, App\\Migrator).',
    '**MariaDB** (XAMPP or Docker): InnoDB tables, foreign keys, CHECK constraints, views and triggers.',
    '**Bootstrap 5** and Bootstrap Icons for a responsive interface; **Chart.js** for price charts; QR codes generated in the browser.',
    '**PHPMailer** for email (optional), **Dompdf** for PDF export, **Africa\'s Talking** API for SMS (optional), **Google OAuth 2.0** for sign-in (optional).',
    '**English and Kiswahili** interface: all visible text goes through t() with lang/en.php and lang/sw.php.',
    '**Progressive Web App**: installable, with a service worker that keeps the listing form available offline and saves drafts on the device.',
] as $item) {
    $b .= bullet($item);
}
$b .= h2('4.2 Security measures');
foreach ([
    'Passwords hashed with password_hash(); reset and verification tokens stored only as SHA-256 hashes; reset links expire after one hour and are single-use.',
    'CSRF token on every form; session cookies HttpOnly and SameSite=Lax; session id regenerated at login.',
    'Uploaded images: the stored extension comes from the detected MIME type, never the client file name, and uploads/ cannot execute scripts.',
    'Server-side role checks (farmers cannot use buyer pages and vice versa); conversations are visible only to their buyer and the crop owner.',
    'Google sign-in is refused when Google reports the email as unverified.',
] as $item) {
    $b .= bullet($item);
}
$b .= h2('4.3 Key code');
$b .= p_body('Thread status transition (src/Inquiries.php):');
$b .= p_code([
    'public static function statusAfterMessage(string $role, string $current): string',
    '{',
    "    if (\$current === 'completed') {",
    "        return 'completed';",
    '    }',
    "    return \$role === 'farmer' ? 'responded' : 'pending';",
    '}',
]);
$b .= p_body('Price history trigger (migrations/005_price_history.sql):');
$b .= p_code([
    'CREATE TRIGGER trg_crops_price_after_update',
    'AFTER UPDATE ON crops FOR EACH ROW',
    'INSERT INTO price_history (crop_id, product_key, unit, county_id, price)',
    'SELECT NEW.id, LOWER(TRIM(NEW.name)), NEW.unit, u.county_id, NEW.price',
    '  FROM users u',
    ' WHERE u.id = NEW.user_id',
    '   AND NEW.price > 0',
    '   AND (NOT (OLD.price <=> NEW.price)',
    '        OR OLD.unit <> NEW.unit',
    '        OR LOWER(TRIM(OLD.name)) <> LOWER(TRIM(NEW.name)));',
]);
$b .= p_body('Rate-limit counts in one round trip (public/login.php):');
$b .= p_code([
    "\$stmt = \$db->prepare(",
    "    'SELECT",
    "       (SELECT COUNT(*) FROM login_attempts WHERE email = ?      AND attempted_at >= NOW() - INTERVAL 15 MINUTE),",
    "       (SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at >= NOW() - INTERVAL 15 MINUTE)'",
    ');',
]);
$b .= page_break();

// --- 5. Results ---
$b .= h1('5. Results and Testing');
$b .= h2('5.1 Functionality');
$b .= bullet('**Farmers** list, edit and delete products; see inquiry counts, new inquiries and harvests due within seven days; send harvest reminder emails; print QR labels that open a public product page; export listings to CSV or PDF; and upload leaf photos for an extension officer.');
$b .= bullet('**Business owners** browse and filter the marketplace, view farmer profiles with ratings and reviews, start conversations, contact farmers on WhatsApp, and rate farmers after completed deals.');
$b .= bullet('**Both roles** use the price trends page, conversations, profile (photo, phone, county, password) and activity log, in English or Kiswahili.');
$b .= p_screenshot('landing.png', 'Landing page');
$b .= p_screenshot('dashboard-farmer.png', 'Farmer dashboard with listings, statuses and recent inquiries');
$b .= p_screenshot('dashboard-buyer.png', 'Business owner dashboard');
$b .= p_screenshot('marketplace.png', 'Marketplace with county, category, availability and growth-stage filters');
$b .= p_screenshot('add-product.png', 'Product listing form');
$b .= p_screenshot('inquiry-thread.png', 'Conversation between buyer and farmer with rating');
$b .= p_screenshot('price-trends.png', 'Monthly price trend and county comparison');
$b .= p_screenshot('mobile-marketplace.png', 'Marketplace on a phone-sized screen');
$b .= h2('5.2 Testing');
$b .= p_body('The tests/ directory contains PHPUnit tests: unit tests for the pure helpers (upload validation, listing validation, translations, CSRF and status rules) and integration tests that run the migrations against a separate test database and check constraints, triggers, views, cascades and access control. An end-to-end smoke test (tests/smoke.sh) exercises every page over HTTP for both roles, and tools/explain_demo.php reproduces the measurements in section 2.7. The test suites run automatically on every push.');
$b .= page_break();

// --- 6. Limitations ---
$b .= h1('6. Limitations and Future Work');
$b .= bullet('**Disease check is not automated.** The page stores the photo and shows a guide to common Kenyan crop problems; it does not diagnose. An image-recognition model could be added later.');
$b .= bullet('**No payments.** Deals are agreed in the app but paid outside it. M-Pesa (Daraja API) deposits are the natural next step.');
$b .= bullet('**SMS** is implemented against the Africa\'s Talking API and unit-tested with a fake transport, but has not been tested against the live service.');
$b .= bullet('Product names are free text, so price trends group listings by their lower-cased name; a reference table of products would make trends more robust.');

// --- 7. References ---
$b .= h1('7. References');
foreach ([
    '[1] Elmasri, R., & Navathe, S. B. (2016). Fundamentals of Database Systems (7th ed.). Pearson.',
    '[2] Date, C. J. (2003). An Introduction to Database Systems (8th ed.). Addison-Wesley.',
    '[3] MariaDB Foundation. MariaDB Server Documentation: CHECK constraints, triggers, views and EXPLAIN. https://mariadb.com/kb/',
    '[4] The PHP Group. PHP Manual: PDO and password hashing. https://www.php.net/manual/',
    '[5] OWASP Foundation. Cheat Sheet Series: File Upload, CSRF Prevention, Forgot Password. https://cheatsheetseries.owasp.org/',
    '[6] Google. Using OAuth 2.0 for Web Server Applications. https://developers.google.com/identity/protocols/oauth2/web-server',
    '[7] Africa\'s Talking. SMS API documentation. https://developers.africastalking.com/',
] as $ref) {
    $b .= p_body($ref);
}

$sect = '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="709" w:footer="709" w:gutter="0"/></w:sectPr>';
$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
    . $b . $sect . '</w:body></w:document>';

// ---------------------------------------------------------------------------
// Styles: real Heading 1/2 so Word's navigation pane and TOC work
// ---------------------------------------------------------------------------
$style = static function (string $id, string $name, string $pPr, string $rPr, bool $heading = false, int $level = 0): string {
    return '<w:style w:type="paragraph" w:styleId="' . $id . '"><w:name w:val="' . $name . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/>'
        . ($heading ? '<w:qFormat/>' : '')
        . '<w:pPr>' . $pPr . ($heading ? '<w:keepNext/><w:outlineLvl w:val="' . $level . '"/>' : '') . '</w:pPr>'
        . '<w:rPr>' . $rPr . '</w:rPr></w:style>';
};
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
    . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/></w:rPr></w:rPrDefault>'
    . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
    . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
    . $style('Title', 'Title', '<w:jc w:val="center"/><w:spacing w:before="360" w:after="120"/>', '<w:b/><w:sz w:val="44"/><w:color w:val="1B5E20"/>')
    . $style('Subtitle', 'Subtitle', '<w:jc w:val="center"/><w:spacing w:before="120" w:after="240"/>', '<w:b/><w:sz w:val="30"/><w:color w:val="555555"/>')
    . $style('Heading1', 'heading 1', '<w:spacing w:before="360" w:after="160"/>', '<w:b/><w:sz w:val="32"/><w:color w:val="1B5E20"/>', true, 0)
    . $style('Heading2', 'heading 2', '<w:spacing w:before="240" w:after="100"/>', '<w:b/><w:sz w:val="26"/><w:color w:val="2E7D32"/>', true, 1)
    . $style('Code', 'Code', '<w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:ind w:left="284"/><w:shd w:val="clear" w:color="auto" w:fill="F3F3F3"/>', '<w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="17"/>')
    . '</w:styles>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
    . '</Types>';
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '</Relationships>';
$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$out = __DIR__ . '/../SCO_207_Group_Project_Report.docx';
$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Failed to create {$out}\n");
    exit(1);
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels', $rels);
$zip->addFromString('word/document.xml', $document);
$zip->addFromString('word/styles.xml', $styles);
$zip->addFromString('word/_rels/document.xml.rels', $docRels);
$zip->close();

echo "Created SCO_207_Group_Project_Report.docx\n";
if ($placeholderNames) {
    echo "NOTE: the member table still has placeholder names (John Doe, ...).\n"
       . "      Edit \$students at the top of tools/generate_report.php and run this again.\n";
}
echo "Then replace each red [SCREENSHOT: ...] line with the image from docs/screenshots/.\n";
