# 👑 MAHADEV KING — Gaming Platform

A full-featured online gaming & wallet platform built with PHP, MySQL, Firebase FCM, and Android.

## 📁 Project Structure

```
MAHADEV KING Web Code/
├── includes/           # Core helpers (DB, FCM, SMS, Config)
│   ├── config.php      # Environment loader (.env)
│   ├── db.php          # Database connection
│   ├── fcm_helper.php  # Firebase push notifications
│   └── sms_helper.php  # AquaSMS OTP integration
├── api/                # REST API endpoints
├── pay/                # Payment gateway integrations (IMB, YuviPay, Pay0)
├── secure/             # Service account files (NOT in Git)
├── assets/             # CSS, JS, images
├── uploads/            # User uploads (NOT in Git)
└── .env.example        # Environment variables template
```

## ⚙️ Setup Instructions

### 1. Clone the repository
```bash
git clone https://github.com/shakyaanubhav645/mahadev-king-web.git
cd mahadev-king-web
```

### 2. Configure environment
```bash
cp .env.example .env
# Edit .env with your actual credentials
```

### 3. Import database
```bash
# Import the SQL file into your MySQL/phpMyAdmin
mysql -u root -p your_database < database.sql
```

### 4. Upload service account
- Place your Firebase `service-account.json` inside the `secure/` folder
- This file is **NOT** tracked by Git for security

## 🔐 Security

- All API keys and passwords are stored in `.env` (never committed to Git)
- `.env` is listed in `.gitignore`
- Use `.env.example` as a template for new deployments

## 🛠️ Tech Stack

- **Backend:** PHP 8.x
- **Database:** MySQL (via MySQLi)
- **Push Notifications:** Firebase FCM v1
- **SMS/OTP:** AquaSMS API
- **Payment Gateways:** IMB, YuviPay, Pay0
- **Android:** Kotlin (separate module)

## 📞 Support

For issues, contact the development team.
