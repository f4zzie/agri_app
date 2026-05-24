# 🌿 AgriTrack – Farmer & Business Owner Marketplace

AgriTrack is a database-driven direct Farmer-to-Business (F2B) digital marketplace application built to solve inefficiencies in agricultural supply chains. It establishes a direct communication link between smallholder Kenyan farmers and commercial business owners (buyers), bypasses expensive middle brokers, and facilitates transparent pricing.

This project was developed and expanded as a practical submission for the **SCO 207: Database Systems** group project assignment.

---

## 📋 Table of Contents
1. [Prerequisites](#-prerequisites)
2. [Setup Instructions](#-setup-instructions)
3. [System Architecture & Database Schema](#-system-architecture--database-schema)
4. [Roles & User Experience](#-roles--user-experience)
5. [SCO 207 Academic Report Generation](#-sco-207-academic-report-generation)
6. [Troubleshooting](#-troubleshooting)

---

## 🛠 Prerequisites

Ensure you have the following installed on your local computer before starting:
- **[XAMPP](https://www.apachefriends.org/download.html)**: Provides Apache web server, MySQL/MariaDB database, and PHP runtime.
- **[Git](https://git-scm.com/downloads)**: Used to clone and manage codebase commits.
- **[Composer](https://getcomposer.org/)** (*Optional*): Required to pull third-party dependencies (like PHPMailer or Dompdf) if you modify emailing or PDF features.

---

## 🚀 Setup Instructions

### Step 1: Clone the Repository to htdocs
Open your command terminal (Command Prompt, PowerShell, or Git Bash) and run:
```bash
git clone https://github.com/f4zzie/agri_app.git C:\xampp\htdocs\agri_app
```
*(If on Mac or Linux, clone the project directly into your local XAMPP `htdocs` directory).*

### Step 2: Start Apache and MySQL in XAMPP
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Ensure both services turn green. 
   *(Note: If MySQL fails to start due to port 3306 conflicts, disable any running local MySQL instances from Windows Services).*

### Step 3: Setup the Relational Database
1. Open your web browser and navigate to: `http://localhost/phpmyadmin`
2. Click **Import** on the top menu bar.
3. Click **Choose File** and navigate to your project directory `C:\xampp\htdocs\agri_app\`.
4. Select the **`database.sql`** schema script.
5. Scroll to the bottom and click **Go** (or **Import**).
6. Verify that the tables (`users`, `crops`, `inquiries`, `activity_log`, etc.) are imported.

### Step 4: Configure Environment Variables
Inside `C:\xampp\htdocs\agri_app\`, copy the example environment file and name it `.env`:
```bash
# In your terminal:
cd C:\xampp\htdocs\agri_app
copy .env.example .env
```
Open the newly created `.env` file in a text editor. By default, XAMPP does not require a database password, so configure the settings as follows:
```env
DB_HOST=localhost
DB_NAME=agri_app
DB_USER=root
DB_PASS=
APP_URL=http://localhost/agri_app

# (Optional: Add credentials below to enable email sending and Google Sign-in)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password

GOOGLE_CLIENT_ID=your_google_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost/agri_app/oauth_google.php
```

### Step 5: Create Uploads Folders
The application requires upload directories to store product images and profile avatars. Create them manually or run:
```bash
mkdir C:\xampp\htdocs\agri_app\uploads
mkdir C:\xampp\htdocs\agri_app\uploads\avatars
```

### Step 6: Load the Application
Navigate to the local URL in your web browser:
👉 **`http://localhost/agri_app`**

---

## 👥 Roles & User Experience

AgriTrack divides system workflows into two distinct roles, complete with role-specific dashboards and sidebars:

### 🌾 The Farmer Role
- **Goal**: List and track crops to attract business inquiries.
- **Dashboard**: Features statistics cards (Total Products, Available for Sale, Pending Inquiries) and a table of listed crops.
- **Sidebar Options**:
  - *Dashboard*: Quick links to list crops and check inquiries.
  - *List Crop*: Form to insert crops with varieties, planting date, expected harvest date, quantities, price, and status.
  - *Disease Check*: Simulates image-based plant disease checkups.
  - *Export PDF*: Downloads a PDF statement of listed crops.
  - *Activity Log*: Displays actions performed by the farmer (e.g., crop updates, password changes).
  - *Send Reminders*: Simulates notifications for harvest schedules.

### 🏪 The Business Owner (Buyer) Role
- **Goal**: Browse, search, and purchase fresh agricultural products directly.
- **Dashboard**: Displays stats cards (Products Available, Inquiries Sent, Counties with Active Farmers), recent inquiries, and the latest crop listings.
- **Sidebar Options**:
  - *Dashboard*: Summary view of marketplace availability.
  - *Marketplace*: Directory of listed crops. Users can query crop names, filter by Kenyan County, or filter by crop growth status.
  - *My Inquiries*: Displays inquiry histories and their statuses (Pending, Read, Responded).
  - *Activity Log*: Displays all actions performed by the business owner (e.g., login, profile updates, sent inquiries).

---

## 📝 SCO 207 Academic Report Generation

To compile the required printed project report for evaluation, the codebase includes a built-in OpenXML Word Document compilation script (`generate_report.php`).

### 1. How to Customize with Group Member Details
Before compiling, open the [generate_report.php](generate_report.php) file and insert your group members' names and registration numbers on **lines 70-80**:
```php
$students = [
    ["1", "John Doe", "SC201/0001/2024", ""],
    ["2", "Jane Smith", "SC201/0002/2024", ""],
    ...
];
```

### 2. How to Compile the Report
Open your terminal and run XAMPP's PHP compiler to generate the document:
```bash
C:\xampp\php\php.exe generate_report.php
```
This generates a professionally formatted document: **`SCO_207_Group_Project_Report.docx`** in your root project folder.

### 3. Inserting screenshots
Open the compiled document in Microsoft Word. Locate the highlighted red placeholders (e.g. `[SCREENSHOT: User registration page...]`) and insert screenshots from your running system, then save and print.

---

## ❓ Troubleshooting

| Issue | Cause / Diagnostics | Solution |
| :--- | :--- | :--- |
| **"Database connection failed"** | MySQL service stopped or incorrect credentials in `.env` | Start MySQL in XAMPP; check `.env` for correct host/name/password. |
| **Blank White Screen** | Apache web server is not running | Open XAMPP Control Panel and start Apache. |
| **"Table not found"** | Missing tables in database | Re-import the `database.sql` script via phpMyAdmin import tab. |
| **Images Fail to Upload** | Missing directories | Ensure `uploads/` and `uploads/avatars/` folders exist. |
| **Port 3306 Conflict** | External MySQL instance running on Windows | Open Services (`services.msc`), stop the default "MySQL" service, then click start in XAMPP. |
| **Authentication Loops** | Session path is not writable | Check folder write permissions on your system's temporary directory. |
| **Report Compiler Fails** | PHP CLI not in PATH | Always run using the absolute path: `C:\xampp\php\php.exe generate_report.php`. |

---
*SCO 207 Database Systems Assignment – Group Project 2026*

