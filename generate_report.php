<?php
/**
 * Programmatic Word Document (.docx) Generator for SCO 207 Group Project
 * Uses PHP's ZipArchive to pack the XML OpenXML structure.
 */

// Helper to escape XML
function xml_escape($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Generate the Document XML content
$doc_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$doc_xml .= '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">';
$doc_xml .= '<w:body>';

// --- HELPER WRAPPERS ---

function p_title($text) {
    return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="480" w:after="120"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:sz w:val="48"/><w:color w:val="1b5e20"/></w:rPr><w:t>' . xml_escape($text) . '</w:t></w:r></w:p>';
}

function p_subtitle($text) {
    return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="240" w:after="240"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:sz w:val="32"/><w:color w:val="555555"/></w:rPr><w:t>' . xml_escape($text) . '</w:t></w:r></w:p>';
}

function p_meta($text, $bold = false) {
    $b_tag = $bold ? '<w:b/>' : '';
    return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="120" w:after="120"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>' . $b_tag . '<w:sz w:val="24"/></w:rPr><w:t>' . xml_escape($text) . '</w:t></w:r></w:p>';
}

function h1($text) {
    return '<w:p><w:pPr><w:spacing w:before="360" w:after="180"/><w:keepNext/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:sz w:val="32"/><w:color w:val="1b5e20"/></w:rPr><w:t>' . xml_escape($text) . '</w:t></w:r></w:p>';
}

function h2($text) {
    return '<w:p><w:pPr><w:spacing w:before="240" w:after="120"/><w:keepNext/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:sz w:val="26"/><w:color w:val="2e7d32"/></w:rPr><w:t>' . xml_escape($text) . '</w:t></w:r></w:p>';
}

function p_body($text) {
    return '<w:p><w:pPr><w:jc w:val="both"/><w:spacing w:before="60" w:after="120" w:line="276" w:lineRule="auto"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="23"/></w:rPr><w:t>' . xml_escape($text) . '</w:t></w:r></w:p>';
}

function p_screenshot($description, $location) {
    return '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:before="240" w:after="240"/></w:pPr>' .
           '<w:r><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:b/><w:color w:val="c62828"/><w:sz w:val="20"/></w:rPr>' .
           '<w:t>' . xml_escape('[SCREENSHOT: ' . $description . ' (' . $location . ')]') . '</w:t></w:r></w:p>';
}

function p_code($code_lines) {
    $xml = '';
    foreach ($code_lines as $line) {
        // preserve leading spaces via XML space attribute
        $xml .= '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:ind w:left="360"/></w:pPr>' .
                '<w:r><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="18"/><w:color w:val="222222"/></w:rPr>' .
                '<w:t xml:space="preserve">' . htmlspecialchars($line, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</w:t></w:r></w:p>';
    }
    return $xml;
}

function page_break() {
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
}

// --- TITLE PAGE ---
$doc_xml .= p_title("SCO 207: DATABASE SYSTEMS");
$doc_xml .= p_subtitle("GROUP PROJECT ASSIGNMENT REPORT");
$doc_xml .= p_title("AgriTrack: A Direct Farmer-to-Business Digital Marketplace");

$doc_xml .= '<w:p><w:pPr><w:spacing w:before="240" w:after="240"/></w:pPr></w:p>'; // spacer

// Members Table
$doc_xml .= '<w:tbl>';
$doc_xml .= '<w:tblPr>';
$doc_xml .= '<w:tblW w:w="5000" w:type="pct"/>';
$doc_xml .= '<w:tblBorders>';
$doc_xml .= '<w:top w:val="single" w:sz="6" w:space="0" w:color="2e7d32"/>';
$doc_xml .= '<w:left w:val="single" w:sz="6" w:space="0" w:color="2e7d32"/>';
$doc_xml .= '<w:bottom w:val="single" w:sz="6" w:space="0" w:color="2e7d32"/>';
$doc_xml .= '<w:right w:val="single" w:sz="6" w:space="0" w:color="2e7d32"/>';
$doc_xml .= '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="dddddd"/>';
$doc_xml .= '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="dddddd"/>';
$doc_xml .= '</w:tblBorders>';
$doc_xml .= '</w:tblPr>';

// Table Headers
$doc_xml .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
$headers = ["S/No", "Student Full Name", "Registration Number", "Handwritten Signature"];
foreach ($headers as $hdr) {
    $doc_xml .= '<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="2e7d32"/></w:tcPr><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:color w:val="ffffff"/><w:sz w:val="18"/></w:rPr><w:t>' . xml_escape($hdr) . '</w:t></w:r></w:p></w:tc>';
}
$doc_xml .= '</w:tr>';

// 10 Student Rows
$students = [
    ["1", "John Doe", "SC201/0001/2024", ""],
    ["2", "Jane Smith", "SC201/0002/2024", ""],
    ["3", "Alex Mercer", "SC201/0003/2024", ""],
    ["4", "Sarah Connor", "SC201/0004/2024", ""],
    ["5", "Bruce Wayne", "SC201/0005/2024", ""],
    ["6", "Clark Kent", "SC201/0006/2024", ""],
    ["7", "Diana Prince", "SC201/0007/2024", ""],
    ["8", "Peter Parker", "SC201/0008/2024", ""],
    ["9", "Tony Stark", "SC201/0009/2024", ""],
    ["10", "Steve Rogers", "SC201/0010/2024", ""]
];

foreach ($students as $stud) {
    $doc_xml .= '<w:tr>';
    foreach ($stud as $val) {
        $doc_xml .= '<w:tc><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t>' . xml_escape($val) . '</w:t></w:r></w:p></w:tc>';
    }
    $doc_xml .= '</w:tr>';
}
$doc_xml .= '</w:tbl>';

$doc_xml .= '<w:p><w:pPr><w:spacing w:before="480" w:after="0"/></w:pPr></w:p>'; // spacer

$doc_xml .= p_meta("Submitted To: Department of Computer Science", true);
$doc_xml .= p_meta("Course Instructor: SCO 207 Instructor", false);
$doc_xml .= p_meta("Date of Submission: " . date("F d, Y"), true);

$doc_xml .= page_break();

// --- INTRODUCTION ---
$doc_xml .= h1("1. INTRODUCTION");

$doc_xml .= h2("1.1 Problem Definition");
$doc_xml .= p_body("In developing agricultural economies like Kenya's, smallholder farmers face severe market inefficiencies. They struggle with market isolation, lack of direct access to commercial buyers, and absolute dependency on exploitative middle brokers who take heavy commissions while keeping crop farmgate prices low. Concurrently, business buyers (retail stores, hotels, supermarkets) face issues with produce traceability, unstable supplies, and inflated costs from brokers. Furthermore, there are no streamlined web-based systems tracking crop planting progress, expected harvest schedules, and direct buyer inquiries in unified localized dashboards. AgriTrack solves this environmental crisis by establishing a direct, database-driven farmer-to-business (F2B) digital marketplace.");

$doc_xml .= h2("1.2 Objectives of the System");
$doc_xml .= p_body("1. Establish a secure user registration and authentication workflow separating 'Farmer' and 'Business Owner' roles, supporting both email/password credentials and Google OAuth 2.0 social sign-ins.");
$doc_xml .= p_body("2. Develop a comprehensive crop tracking database module allowing farmers to list crops with categories, varieties, planting dates, harvest predictions, quantities, and availability statuses.");
$doc_xml .= p_body("3. Create an interactive marketplace directory where business owners can search, filter (by county and status), and directly contact farmers regarding specific listings.");
$doc_xml .= p_body("4. Implement a robust, dynamic role-based dashboard layout displaying relevant statistics and direct communication inquiries for each user class.");
$doc_xml .= p_body("5. Provide secure auditing logs via user activity logs and reminders to track operations and maintain transparency.");

$doc_xml .= page_break();

// --- SYSTEM DESIGN ---
$doc_xml .= h1("2. SYSTEM DESIGN");

$doc_xml .= h2("2.1 Database Schema");
$doc_xml .= p_body("The relational database structure (MariaDB/MySQL) comprises 6 key entities designed with appropriate constraints, primary keys, foreign keys, and indexes for optimized performance:");
$doc_xml .= p_body("- users: Stores core accounts, phone, county, verification credentials, and a role ENUM ('farmer', 'buyer') determining their dashboard view.");
$doc_xml .= p_body("- crops: Contains crop variety details, pricing per unit, quantity, planting and harvest dates, and availability status linked to the listing farmer.");
$doc_xml .= p_body("- inquiries: Captures buyer-to-farmer messages about specific crops, storing contacts and message details to facilitate direct off-platform transactions.");
$doc_xml .= p_body("- activity_log: Logs security-critical and operational actions with user IDs and IP addresses for tracing.");
$doc_xml .= p_body("- password_resets & login_attempts: Maintain application security via password tokens and rate limiting (max 5 failed attempts per 15 minutes).");

$doc_xml .= h2("2.2 User Interface Design Screenshots");
$doc_xml .= p_body("The user interfaces are fully responsive and styled with custom CSS and Bootstrap 5. Key screens require user capture as detailed below:");

$doc_xml .= p_screenshot("User registration page showing card-based selection for Farmer vs Business Owner roles", "register.php");
$doc_xml .= p_screenshot("Login interface with standard fields and 'Continue with Google' button link", "login.php");
$doc_xml .= p_screenshot("Farmer Dashboard: List New Product button, Listed Crops table, and Incoming Buyer Inquiries", "dashboard.php as Farmer");
$doc_xml .= p_screenshot("Business Owner Dashboard: Shop Directory, Counties with Farmers count, and Latest Available Crops", "dashboard.php as Business Owner");
$doc_xml .= p_screenshot("Marketplace directory displaying county and status filters and 'Contact Farmer' call-to-actions", "marketplace.php");
$doc_xml .= p_screenshot("Activity Log page demonstrating recent logs with styled badges and IP tracking", "activity_log.php");

$doc_xml .= page_break();

// --- ALGORITHMS AND METHODS ---
$doc_xml .= h1("3. ALGORITHMS AND METHODS");

$doc_xml .= h2("3.1 Role-Based Registration & Login Authentication Flow");
$doc_xml .= p_body("The system processes registrations by verifying inputs and creating account profiles with designated roles. The pseudo-algorithm follows:");

$algo1 = [
    "ALGORITHM: User Registration with Role Assignment",
    "------------------------------------------------",
    "INPUT: name, email, password, role ('farmer' or 'buyer'), phone, county",
    "OUTPUT: Success message, Verification email link, User database entry",
    "",
    "1. START",
    "2. RECEIVE registration inputs via POST",
    "3. SANITIZE inputs and VALIDATE required fields",
    "4. IF email already exists in 'users' table:",
    "       RETURN error \"Email already registered\"",
    "5. ENDIF",
    "6. ASSIGN role = INPUT.role (Default to 'farmer' if invalid or empty)",
    "7. HASH password using BCRYPT",
    "8. GENERATE unique 64-character verification_token",
    "9. INSERT record into 'users' table with status email_verified = 0",
    "10. IF SMTP email transmission is successful:",
    "        SEND email containing activation link: verify.php?token=token",
    "        RETURN success \"Check your email to verify account\"",
    "    ELSE",
    "        UPDATE 'users' SET email_verified = 1 WHERE id = last_inserted_id",
    "        RETURN success \"Account created successfully! You can log in\"",
    "    ENDIF",
    "11. STOP"
];
$doc_xml .= p_code($algo1);

$doc_xml .= h2("3.2 Inquiry Transmission and Notification Mechanism");
$doc_xml .= p_body("When a business buyer chooses to buy or negotiate a crop listing, they send an inquiry, triggering the following process:");

$algo2 = [
    "ALGORITHM: Inquiry Submission & Dispatch",
    "--------------------------------------",
    "INPUT: crop_id, buyer_id, message, buyer_phone",
    "OUTPUT: Database record, Email alert to Farmer",
    "",
    "1. START",
    "2. CHECK if current session role equals 'buyer'. If not, REJECT and redirect",
    "3. FETCH crop and matching farmer details from DB using crop_id",
    "4. IF crop does not exist or status is not 'available':",
    "       RETURN error \"Product unavailable\"",
    "5. ENDIF",
    "6. INSERT inquiry record: (crop_id, buyer_id, farmer_id, message, phone, status='pending')",
    "7. LOG activity to 'activity_log' as 'send_inquiry'",
    "8. CONSTRUCT HTML email containing buyer contact details and message",
    "9. SEND email to farmer's registered address using PHPMailer SMTP",
    "10. RETURN success \"Your inquiry has been sent!\"",
    "11. STOP"
];
$doc_xml .= p_code($algo2);

$doc_xml .= page_break();

// --- IMPLEMENTATION ---
$doc_xml .= h1("4. IMPLEMENTATION DETAILS");

$doc_xml .= h2("4.1 Tools and Technologies");
$doc_xml .= p_body("1. Backend Logic: PHP 8.x - handles core controllers, templating, session state management, and password hashing.");
$doc_xml .= p_body("2. Database: MariaDB / MySQL - stores normalized tables with referential integrity.");
$doc_xml .= p_body("3. Frontend Layout: Bootstrap 5 and Vanilla CSS - responsive styles, custom card selections, and mobile hamburger sidebar drawer.");
$doc_xml .= p_body("4. Mailing: PHPMailer - connects to external SMTP server for account verification and inquiry dispatch.");
$doc_xml .= p_body("5. Integration: Google OAuth 2.0 API - validates email credentials via OAuth client IDs.");

$doc_xml .= h2("4.2 Key Code Snippets");
$doc_xml .= p_body("Snippet 1: Role Extraction & Registration (extract from register.php)");
$code1 = [
    "// From register.php POST handler",
    "\$role = trim(\$_POST['role'] ?? 'farmer');",
    "if (!in_array(\$role, ['farmer', 'buyer'])) {",
    "    \$role = 'farmer';",
    "}",
    "",
    "\$passwordHash = password_hash(\$password, PASSWORD_BCRYPT);",
    "\$stmt = \$db->prepare(",
    "    'INSERT INTO users (name, email, password_hash, phone, county, role, email_verified, verification_token)' .",
    "    ' VALUES (:name, :email, :hash, :phone, :county, :role, 0, :token)'",
    ");",
    "\$stmt->execute([",
    "    ':name'   => \$name,",
    "    ':email'  => \$email,",
    "    ':hash'   => \$passwordHash,",
    "    ':phone'  => \$phone,",
    "    ':county' => \$county,",
    "    ':role'   => \$role,",
    "    ':token'  => \$verificationToken,",
    "]);"
];
$doc_xml .= p_code($code1);

$doc_xml .= p_body("Snippet 2: Session Role Binding (extract from oauth_google.php callback)");
$code2 = [
    "// From oauth_google.php callback processing",
    "if (\$existingUser) {",
    "    // Existing account authentication",
    "    \$userId = (int) \$existingUser['id'];",
    "    \$role   = \$existingUser['role'] ?? 'farmer';",
    "} else {",
    "    // New Google OAuth User Registration",
    "    \$role = \$_SESSION['oauth_role'] ?? 'farmer';",
    "    \$ins  = \$db->prepare(",
    "        'INSERT INTO users (name, email, google_id, email_verified, role)' .",
    "        ' VALUES (:name, :email, :gid, 1, :role)'",
    "    );",
    "    \$ins->execute([",
    "        ':name'  => \$googleName,",
    "        ':email' => \$googleEmail,",
    "        ':gid'   => \$googleId,",
    "        ':role'  => \$role,",
    "    ]);",
    "    \$userId = (int) \$db->lastInsertId();",
    "}",
    "\$_SESSION['user_id'] = \$userId;",
    "\$_SESSION['role']    = \$role;"
];
$doc_xml .= p_code($code2);

$doc_xml .= p_body("Snippet 3: Role-based Dashboard Split (extract from dashboard.php)");
$code3 = [
    "// From dashboard.php layout division",
    "\$role = \$_SESSION['role'] ?? 'farmer';",
    "",
    "if (\$role === 'farmer') {",
    "    // Load Farmer listings and incoming buyer inquiries",
    "    \$stmt = \$db->prepare(\"SELECT COUNT(*) FROM crops WHERE user_id=?\");",
    "    \$stmt->execute([\$uid]);",
    "    \$totalProducts = (int)\$stmt->fetchColumn();",
    "} else {",
    "    // Load Business Owner (Buyer) crop index and inquiry history",
    "    \$stmt = \$db->query(\"SELECT COUNT(*) FROM crops c JOIN users u ON c.user_id=u.id WHERE u.role='farmer'\");",
    "    \$totalProducts = (int)\$stmt->fetchColumn();",
    "}"
];
$doc_xml .= p_code($code3);

$doc_xml .= page_break();

// --- RESULTS & DISCUSSION ---
$doc_xml .= h1("5. RESULTS AND SYSTEM FUNCTIONALITY");

$doc_xml .= h2("5.1 Description of System Functionality");
$doc_xml .= p_body("AgriTrack behaves dynamically according to the user role identified at login:");
$doc_xml .= p_body("1. Farmer Role: Prompts the dashboard with quick cards (Listed Crops, Available Crops, Pending Inquiries). Farmers have access to crop creation, disease detection simulation via image analysis, PDF reports exporting, and activity logs. They cannot browse the marketplace or contact other farmers.");
$doc_xml .= p_body("2. Business Owner (Buyer) Role: Displays a landing dashboard emphasizing available items, direct counties with crop items, and a summary list of inquiries they sent to farmers. They access the search directory to find produce, select crop records, and fill contact forms to send direct inquiries.");
$doc_xml .= p_body("3. Common Features: Both roles maintain their personal profile page (avatar upload, password reset) and view their separate actions in the Activity Log page.");

$doc_xml .= h2("5.2 Verification Screenshots");
$doc_xml .= p_screenshot("Farmer's listing table containing crop variety, price, quantity, and status badge", "dashboard.php");
$doc_xml .= p_screenshot("Contact Farmer form containing Buyer Contact Phone and custom text inquiry input", "inquiry.php");
$doc_xml .= p_screenshot("Sent Inquiries page showing active states (Pending, Read, Responded) of sent inquiries", "my_inquiries.php");
$doc_xml .= p_screenshot("Common Activity Log detailing Login, Send Inquiry, and Update Profile entries", "activity_log.php");

$doc_xml .= page_break();

// --- REFERENCES ---
$doc_xml .= h1("6. REFERENCES");
$doc_xml .= p_body("[1] Date, C. J. (2003). An Introduction to Database Systems (8th ed.). Addison-Wesley.");
$doc_xml .= p_body("[2] Elmasri, R., & Navathe, S. B. (2015). Fundamentals of Database Systems (7th ed.). Pearson.");
$doc_xml .= p_body("[3] Welling, L., & Thomson, L. (2016). PHP and MySQL Web Development (5th ed.). Addison-Wesley Professional.");
$doc_xml .= p_body("[4] Google Cloud. (2026). Google OAuth 2.0 Web Server Applications. Google Developer Documentation.");
$doc_xml .= p_body("[5] The PHPMailer Project. (2026). SMTP Email Dispatch Library. GitHub Repository.");

$doc_xml .= '</w:body>';
$doc_xml .= '</w:document>';

// Build ZIP files
$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                 '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
                 '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
                 '<Default Extension="xml" ContentType="application/xml"/>' .
                 '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' .
                 '</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>' .
        '</Relationships>';

$docx_filename = __DIR__ . '/SCO_207_Group_Project_Report.docx';

$zip = new ZipArchive();
if ($zip->open($docx_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $zip->addFromString('[Content_Types].xml', $content_types);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('word/document.xml', $doc_xml);
    $zip->close();
    echo "Document created successfully: SCO_207_Group_Project_Report.docx\n";
} else {
    echo "Failed to create ZIP/DOCX document archive.\n";
    exit(1);
}
