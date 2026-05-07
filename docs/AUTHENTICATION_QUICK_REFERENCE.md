# Authentication System - Quick Reference Guide

## 🎯 Overview

Your EduBridge platform has a complete authentication system with 3 user roles, OTP verification, Google OAuth (students only), and advanced security features.

---

## 👥 User Roles

| Role | Registration | Login | OAuth | Dashboard |
|------|-------------|-------|-------|-----------|
| **Student** | `/register/student` | `/login` | ✅ Google | `/student/dashboard` |
| **Teacher** | `/register/teacher` | `/login` | ❌ No OAuth | `/teacher/dashboard` |
| **Admin** | Manual only | `/login` | ❌ No OAuth | `/admin/dashboard` |

---

## 🔐 Security Features

### Rate Limiting

| Action | Limit | Lockout | Duration |
|--------|-------|---------|----------|
| **Login** | 5 attempts/min | 10 attempts | 30 minutes |
| **OTP Verify** | 10 attempts/min | 10 attempts | 30 minutes |
| **OTP Resend** | 1 per minute | - | 60 seconds |
| **Password Reset** | 60 seconds | - | - |

### Password Requirements
- **Minimum**: 12 characters (stronger than spec's 8)
- **Must contain**: uppercase, lowercase, number, special character
- **Production**: Checks against compromised password database

---

## 📧 OTP Verification

### Flow
1. User registers → OTP sent to email
2. User enters 6-digit OTP
3. OTP valid for **15 minutes**
4. After 10 failed attempts → **30-minute lockout**
5. Can resend OTP after **60 seconds**

### Redis Keys
```
otp_lock:{user_id}       # Lockout flag (30 min TTL)
otp_attempts:{user_id}   # Failed attempts counter
```

---

## 🔑 Login System

### Flow
1. User enters email + password
2. System checks:
   - ✅ User exists?
   - ✅ Password correct?
   - ✅ Account active (not suspended)?
   - ✅ Email verified?
3. If pending → Send OTP
4. If active → Login successful

### Lockout Logic
- **5 attempts/minute** → Rate limited (429 response)
- **10 total attempts** → 30-minute IP lockout
- **Suspended account** → Permanent block (admin action required)

### Redis Keys
```
login_lock:{ip}          # Lockout flag (30 min TTL)
login_attempts:{ip}      # Failed attempts counter
```

---

## 🌐 Google OAuth (Students Only)

### Allowed
- ✅ New student registration
- ✅ Existing student login

### Blocked
- ❌ Teacher registration
- ❌ Teacher login
- ❌ Admin accounts

### Error Messages
- Teacher: "Teacher accounts must sign in with email and password. Google sign-in is only available for students."
- Admin: "Admin accounts must sign in with email and password."

---

## 🔄 Password Reset

### Flow
1. User clicks "Forgot Password"
2. Enters email
3. Receives reset link (valid **60 minutes**)
4. Sets new password
5. All sessions logged out (remember_token invalidated)

### Google OAuth Users
- ❌ Cannot reset password
- Error: "You signed up with Google. Password reset is not available. Please sign in with Google."

---

## 📊 Account Status States

| Status | Description | Can Login? | Actions |
|--------|-------------|------------|---------|
| **pending** | Registered, OTP not verified | ❌ No | Verify OTP |
| **active** | Email verified, fully functional | ✅ Yes | Normal use |
| **suspended** | Admin-suspended or auto-locked | ❌ No | Contact support |
| **deleted** | Soft-deleted (data preserved) | ❌ No | Cannot recover |

---

## 🛣️ Authentication Routes

### Public Routes
```php
GET  /login                    # Login form
POST /login                    # Login submit
GET  /register/student         # Student registration form
POST /register/student         # Student registration submit
GET  /register/teacher         # Teacher registration form
POST /register/teacher         # Teacher registration submit
GET  /verify-otp               # OTP verification form
POST /verify-otp               # OTP verification submit
POST /resend-otp               # Resend OTP
GET  /forgot-password          # Password reset request form
POST /forgot-password          # Password reset request submit
GET  /reset-password/{token}   # Password reset form
POST /reset-password           # Password reset submit
GET  /auth/google              # Google OAuth redirect
GET  /auth/google/callback     # Google OAuth callback
POST /logout                   # Logout
```

### Protected Routes
```php
# Student Portal (role:student)
GET /student/dashboard
GET /student/onboarding
GET /student/profile
GET /student/bookings
GET /student/chat

# Teacher Portal (role:teacher)
GET /teacher/dashboard
GET /teacher/profile/step/{1-5}
GET /teacher/availability
GET /teacher/sessions
GET /teacher/chat

# Admin Portal (role:admin + 2FA)
GET /admin/dashboard
GET /admin/verifications
GET /admin/users
GET /admin/analytics
```

---

## 🔧 Middleware

| Middleware | Purpose | Applied To |
|-----------|---------|------------|
| `guest` | Only unauthenticated users | Login, register routes |
| `auth` | Requires authentication | All protected routes |
| `role:student` | Requires student role | Student portal |
| `role:teacher` | Requires teacher role | Teacher portal |
| `role:admin` | Requires admin role | Admin portal |
| `admin.2fa` | Requires 2FA verification | Admin portal (after login) |
| `throttle:login` | Rate limit login | Login route |
| `throttle:otp-verify` | Rate limit OTP verify | OTP verify route |
| `throttle:otp-resend` | Rate limit OTP resend | OTP resend route |

---

## 📝 Database Tables

### users
```sql
- id
- name
- email (unique)
- phone (unique)
- password (hashed, nullable for Google OAuth)
- role (student|teacher|admin)
- status (pending|active|suspended|deleted)
- email_verified_at
- phone_verified_at
- avatar
- remember_token
- last_login_ip
- two_factor_secret (admin only)
- two_factor_enabled (admin only)
- warnings_count
- city
- created_at
- updated_at
- deleted_at (soft delete)
```

### verifications
```sql
- id
- user_id
- otp (hashed)
- type (email|phone)
- expires_at (15 minutes)
- used_at
- created_at
- updated_at
```

### student_profiles
```sql
- id
- user_id
- class_grade
- school_name
- onboarding_completed
- created_at
- updated_at
```

### teacher_profiles
```sql
- id
- user_id
- gender
- is_verified (false until admin approves)
- onboarding_step (1-5)
- ... (other profile fields)
- created_at
- updated_at
```

---

## 🧪 Testing Commands

### Manual Testing
```bash
# Start Redis
redis-server

# Start Laravel
php artisan serve

# Test student registration
curl -X POST http://localhost:8000/register/student \
  -d "name=John Doe" \
  -d "email=john@example.com" \
  -d "phone=+1234567890" \
  -d "password=SecurePass123!" \
  -d "password_confirmation=SecurePass123!" \
  -d "class_grade=Class 10"

# Check Redis keys
redis-cli KEYS "*"
redis-cli GET "otp_attempts:1"
redis-cli TTL "otp_lock:1"
```

### Automated Testing
```bash
# Run authentication tests
php artisan test --filter=StudentRegistrationTest
php artisan test --filter=TeacherRegistrationTest
php artisan test --filter=LoginTest
php artisan test --filter=SecurityTest
```

---

## 🚨 Common Issues & Solutions

### Issue: OTP not received
**Solution**: Check mail configuration in `.env`
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
```

### Issue: Redis connection failed
**Solution**: Ensure Redis is running
```bash
# Check Redis status
redis-cli ping  # Should return PONG

# Start Redis
redis-server
```

### Issue: Google OAuth not working
**Solution**: Check Google OAuth credentials
```env
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_REDIRECT=http://localhost:8000/auth/google/callback
```

### Issue: Account locked permanently
**Solution**: Clear Redis lockout keys
```bash
# Clear specific user lockout
redis-cli DEL "otp_lock:1"
redis-cli DEL "login_lock:192.168.1.1"

# Or clear all lockouts
redis-cli FLUSHDB
```

---

## 📞 Support

For issues or questions:
- **Email**: support@edubridge.com
- **Documentation**: See `SECTION_2_IMPLEMENTATION_COMPLETE.md`
- **Analysis**: See `SECTION_2_AUTHENTICATION_ANALYSIS.md`
