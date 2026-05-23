# 🌿 AgriTrack – Farmer & Business Owner Marketplace

A web platform connecting Kenyan farmers to business owners.  
Farmers list their produce → Buyers browse and send inquiries directly.

---

## 📋 What you need installed first

- [XAMPP](https://www.apachefriends.org/download.html) — gives you Apache + MySQL + PHP
- [Git](https://git-scm.com/downloads) — to clone the repo
- [Composer](https://getcomposer.org/download/) — **optional**, only needed for email features

---

## 🚀 Setup — Step by Step

### Step 1 — Clone the repo

Open **Command Prompt** or **Git Bash** and run:

```bash
git clone https://github.com/ynwklaus/agri_app.git
```

This creates a folder called `agri_app`. Move it into your XAMPP htdocs:

```
Windows:  C:\xampp\htdocs\agri_app
Mac:      /Applications/XAMPP/htdocs/agri_app
Linux:    /opt/lampp/htdocs/agri_app
```

So the final path should look like: `C:\xampp\htdocs\agri_app\index.php`

---

### Step 2 — Start XAMPP

1. Open **XAMPP Control Panel**
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**

Both rows should turn green. If MySQL says port 3306 is already in use, another MySQL is running — stop it first from Windows Services.

---

### Step 3 — Set up the database

1. Open your browser → go to `http://localhost/phpmyadmin`
2. Click **Import** in the top menu
3. Click **Choose File** → navigate to your `agri_app` folder → select **`database.sql`**
4. Scroll down → click **Go**

You should see: *"Import has been successfully finished"*. All tables are now created.

---

### Step 4 — Create your `.env` file

Inside the `agri_app` folder, find `.env.example`.

**Copy it and rename the copy to `.env`:**

```bash
# In Command Prompt (inside the agri_app folder):
cd C:\xampp\htdocs\agri_app
copy .env.example .env
```

Then open `.env` in any text editor and make sure it looks like this:

```env
DB_HOST=localhost
DB_NAME=agri_app
DB_USER=root
DB_PASS=
APP_URL=http://localhost/agri_app

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost/agri_app/oauth_google.php
```

> ✅ `DB_PASS` is blank by default in XAMPP — leave it blank  
> ✅ Leave SMTP and GOOGLE fields blank — the app works fine without them  
> ✅ Email verification is auto-skipped if SMTP is not set up

---

### Step 5 — Create the uploads folder

The app needs a folder to store product images:

```bash
mkdir C:\xampp\htdocs\agri_app\uploads
```

Or just create a folder named `uploads` inside `agri_app` manually in File Explorer.

---

### Step 6 — Open the app

Go to: **`http://localhost/agri_app`**

You should see the AgriTrack landing page. 🎉

---

## 👥 How to use it

### Register as a Farmer 🌾
- Click **Register** → select **Farmer**
- Fill in your details → submit
- Log in → you land on the farmer dashboard
- Click **"List New Product"** to add something for sale

### Register as a Business Owner 🏪
- Click **Register** → select **Business Owner**
- Log in → click **"Browse Marketplace"**
- Find a product → click **"Contact Farmer"** to send an inquiry

---

## 🌐 Features
- Email & password registration with verification
- Google OAuth login *(needs your own Google API keys in `.env`)*
- Farmer dashboard — manage listings, see buyer inquiries
- Buyer dashboard — browse marketplace, filter by county & category
- Buyer → Farmer inquiry system
- PDF export of listings
- QR code per product
- Disease check (demo)
- Activity log
- Swahili / English toggle
- Mobile responsive

---

## 🛠 Contributing

1. Fork this repo on GitHub
2. Clone your fork:
```bash
git clone https://github.com/YOUR_USERNAME/agri_app.git
```
3. Create a branch for your changes:
```bash
git checkout -b your-feature-name
```
4. Make your changes, then push:
```bash
git add -A
git commit -m "describe what you changed"
git push origin your-feature-name
```
5. Open a **Pull Request** on GitHub → `ynwklaus/agri_app`

---

## ❓ Troubleshooting

| Problem | Fix |
|---|---|
| Blank white page | Make sure Apache is running in XAMPP |
| "Database connection failed" | Check `.env` — confirm MySQL is running, DB_NAME is `agri_app` |
| "Table not found" errors | Re-import `database.sql` in phpMyAdmin |
| Images not uploading | Create the `uploads/` folder inside `agri_app/` |
| Port 3306 already in use | Open Windows Services → stop "MySQL" service, then start XAMPP MySQL |
| Google login doesn't work | Expected — add your own Google API keys to `.env` to enable it |
| Emails not sending | Expected — add Gmail SMTP credentials to `.env` to enable it |

---

*SCO 207 Group Project — 2026*
