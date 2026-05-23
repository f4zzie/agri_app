# 🌿 AgriTrack – Farmer & Business Owner Marketplace

A web platform connecting Kenyan farmers to business owners.  
Farmers list their produce → Buyers browse and send inquiries directly.

---

## 📋 Requirements

- [XAMPP](https://www.apachefriends.org/download.html) (includes Apache + MySQL + PHP)
- [Composer](https://getcomposer.org/download/) (for email features — optional)
- [Git](https://git-scm.com/downloads)
- A browser

---

## 🚀 Setup from Scratch

### Step 1 — Clone the repo

Open a terminal / command prompt and run:

```bash
git clone https://github.com/YOUR_USERNAME/agri_app.git
```

Then move (or copy) the `agri_app` folder into your XAMPP `htdocs` folder:

```
C:\xampp\htdocs\agri_app\     ← Windows
/opt/lampp/htdocs/agri_app/   ← Linux
/Applications/XAMPP/htdocs/agri_app/  ← Mac
```

---

### Step 2 — Start XAMPP

1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**

Both should show green. If MySQL says port 3306 is in use, it means another MySQL is running — stop it first.

---

### Step 3 — Create the database

1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **Import** (top menu)
3. Click **Choose File** → select `agri_app/database.sql`
4. Scroll down → click **Import / Go**

You should see a success message. The `agri_app` database with all tables is now created.

---

### Step 4 — Configure your environment

Inside the `agri_app` folder, find the file `.env.example`.  
Make a copy of it and rename the copy to `.env`:

**Windows (File Explorer):** Right-click `.env.example` → Copy → Paste → Rename to `.env`

**Or via terminal:**
```bash
cd C:\xampp\htdocs\agri_app
copy .env.example .env
```

Now open `.env` in any text editor (Notepad, VS Code, etc.) and set these values:

```env
DB_HOST=localhost
DB_NAME=agri_app
DB_USER=root
DB_PASS=           # leave blank — XAMPP default has no password
APP_URL=http://localhost/agri_app
```

> **Leave SMTP and GOOGLE fields blank for now.**  
> The app works fine without them:
> - No SMTP → email verification is skipped, accounts activate instantly
> - No Google credentials → Google login button won't work, but normal email/password login works perfectly

---

### Step 5 — Install Composer dependencies (optional)

This is only needed if you want **real email sending** (verification emails, inquiry notifications).

1. Download and install [Composer](https://getcomposer.org/download/)  
   During install, point it to your XAMPP PHP: `C:\xampp\php\php.exe`

2. Open a terminal in the `agri_app` folder and run:
```bash
composer install
```

> If you skip this step, the app still works — emails just won't be sent.

---

### Step 6 — Open the app

Go to: **`http://localhost/agri_app`**

You should see the AgriTrack landing page. 🎉

---

## 👥 How to use it

### Register as a Farmer 🌾
- Click **Register** → select **Farmer**
- Fill in your details → submit
- Log in → you're on the farmer dashboard
- Click **List New Product** to add a product for sale

### Register as a Business Owner 🏪
- Click **Register** → select **Business Owner**
- Fill in your details → submit
- Log in → click **Browse Marketplace** to see all farmer listings
- Click **Contact Farmer** on any product to send an inquiry

---

## 🌐 Features
- Email/password registration & login
- Google OAuth (requires your own Google API credentials in `.env`)
- Farmer dashboard — manage product listings, view buyer inquiries
- Buyer dashboard — browse marketplace, filter by county/category
- Inquiry system — buyers contact farmers, farmers get notified
- PDF export of listings
- QR code sharing per product
- Crop disease check (demo)
- Activity log
- Swahili / English language toggle
- Mobile-responsive

---

## 🔐 Security
- `.env` is in `.gitignore` — **never shared, never committed**
- All DB queries use PDO prepared statements (no SQL injection)
- Passwords hashed with `password_hash()`
- CSRF tokens on all forms

---

## 🛠 Contributing (for group members)

1. Fork this repo
2. Create a branch: `git checkout -b your-feature-name`
3. Make your changes
4. Push: `git push origin your-feature-name`
5. Open a **Pull Request** on GitHub

---

## ❓ Troubleshooting

| Problem | Fix |
|---|---|
| Blank page / PHP errors | Make sure Apache is running in XAMPP |
| "DB connection failed" | Check `.env` DB settings, make sure MySQL is running |
| "Table not found" | Re-import `database.sql` in phpMyAdmin |
| Composer not found | Install Composer and make sure it's added to PATH |
| Port 3306 in use | Stop any other MySQL service running on your PC |
| Images not showing | Make sure `uploads/` folder exists inside `agri_app/` (create it manually if needed) |

---

*SCO 207 Group Project — 2026*
