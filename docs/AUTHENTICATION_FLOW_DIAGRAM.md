# Authentication Flow Diagrams

## 1. Student Registration Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    STUDENT REGISTRATION FLOW                     │
└─────────────────────────────────────────────────────────────────┘

User visits /register/student
         │
         ▼
┌────────────────────────┐
│  Fill Registration     │
│  Form:                 │
│  - Name                │
│  - Email (unique)      │
│  - Phone (unique)      │
│  - Password (12 chars) │
│  - Class/Grade         │
│  - School Name         │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Submit Form           │
│  POST /register/student│
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Validation:           │
│  ✓ Email unique?       │
│  ✓ Phone unique?       │
│  ✓ Password strong?    │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Create Records:       │
│  1. users table        │
│     - role=student     │
│     - status=pending   │
│  2. student_profiles   │
│  3. Assign Spatie role │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Generate 6-digit OTP  │
│  - Hash with bcrypt    │
│  - Store in DB         │
│  - Expires in 15 min   │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Send OTP Email        │
│  via OtpMailSender     │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Redirect to           │
│  /verify-otp           │
└────────────────────────┘
```

---

## 2. Teacher Registration Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    TEACHER REGISTRATION FLOW                     │
└─────────────────────────────────────────────────────────────────┘

User visits /register/teacher
         │
         ▼
┌────────────────────────┐
│  Fill Registration     │
│  Form:                 │
│  - Name                │
│  - Email (unique)      │
│  - Phone (unique)      │
│  - Password (12 chars) │
│  - Gender              │
│  ❌ NO Google OAuth    │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Submit Form           │
│  POST /register/teacher│
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Create Records:       │
│  1. users table        │
│     - role=teacher     │
│     - status=pending   │
│  2. teacher_profiles   │
│     - is_verified=false│
│  3. Assign Spatie role │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Generate & Send OTP   │
│  (same as student)     │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Redirect to           │
│  /verify-otp           │
└────────────────────────┘
```

---

## 3. OTP Verification Flow (With Lockout)

```
┌─────────────────────────────────────────────────────────────────┐
│                    OTP VERIFICATION FLOW                         │
└─────────────────────────────────────────────────────────────────┘

User at /verify-otp
         │
         ▼
┌────────────────────────┐
│  Enter 6-digit OTP     │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Check Redis:          │
│  otp_lock:{user_id}    │
└────────┬───────────────┘
         │
         ├─── Locked? ───┐
         │                │
         NO              YES
         │                │
         ▼                ▼
┌────────────────────┐  ┌────────────────────┐
│  Verify OTP:       │  │  Show Error:       │
│  - Find in DB      │  │  "Account locked   │
│  - Check expiry    │  │   for X minutes"   │
│  - Compare hash    │  └────────────────────┘
└────────┬───────────┘
         │
         ├─── Valid? ────┐
         │                │
        YES              NO
         │                │
         ▼                ▼
┌────────────────────┐  ┌────────────────────┐
│  Success:          │  │  Increment Counter:│
│  1. Clear attempts │  │  otp_attempts:     │
│  2. Set used_at    │  │  {user_id}         │
│  3. Update user:   │  └────────┬───────────┘
│     - email_       │           │
│       verified_at  │           ▼
│     - status=active│  ┌────────────────────┐
│  4. Login user     │  │  Check Attempts    │
└────────┬───────────┘  └────────┬───────────┘
         │                       │
         │              ┌────────┴────────┐
         │              │                 │
         │           < 10              >= 10
         │              │                 │
         │              ▼                 ▼
         │     ┌────────────────┐  ┌────────────────┐
         │     │ Show Error:    │  │ Lock Account:  │
         │     │ "Invalid OTP.  │  │ Set Redis key  │
         │     │  X attempts    │  │ otp_lock:      │
         │     │  remaining"    │  │ {user_id}      │
         │     └────────────────┘  │ TTL: 30 min    │
         │                         └────────────────┘
         │
         ▼
┌────────────────────────┐
│  Redirect:             │
│  - Student → onboarding│
│  - Teacher → profile/1 │
└────────────────────────┘
```

---

## 4. Login Flow (With Lockout)

```
┌─────────────────────────────────────────────────────────────────┐
│                         LOGIN FLOW                               │
└─────────────────────────────────────────────────────────────────┘

User visits /login
         │
         ▼
┌────────────────────────┐
│  Enter Credentials:    │
│  - Email               │
│  - Password            │
│  - Remember Me?        │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Check Rate Limit:     │
│  throttle:login        │
│  (5 attempts/min)      │
└────────┬───────────────┘
         │
         ├─── Limited? ──┐
         │                │
         NO              YES
         │                │
         ▼                ▼
┌────────────────────┐  ┌────────────────────┐
│  Check Redis:      │  │  Return 429:       │
│  login_lock:{ip}   │  │  "Too many         │
└────────┬───────────┘  │   requests"        │
         │               └────────────────────┘
         ├─── Locked? ──┐
         │               │
         NO             YES
         │               │
         ▼               ▼
┌────────────────────┐  ┌────────────────────┐
│  Check User:       │  │  Show Error:       │
│  - Exists?         │  │  "Account locked   │
│  - Password OK?    │  │   for X minutes"   │
└────────┬───────────┘  └────────────────────┘
         │
         ├─── Valid? ────┐
         │                │
        YES              NO
         │                │
         ▼                ▼
┌────────────────────┐  ┌────────────────────┐
│  Check Status:     │  │  Increment Counter:│
│  - suspended?      │  │  login_attempts:   │
│  - pending?        │  │  {ip}              │
│  - active?         │  └────────┬───────────┘
└────────┬───────────┘           │
         │                       ▼
         ├─── Status? ───┐  ┌────────────────┐
         │                │  │  Check Attempts│
    suspended        pending └────────┬───────┘
         │                │           │
         ▼                ▼    ┌──────┴──────┐
┌────────────────┐  ┌──────────────┐  │      │
│ Block Login:   │  │ Send OTP:    │ < 10  >= 10
│ "Account       │  │ Redirect to  │  │      │
│  suspended"    │  │ /verify-otp  │  ▼      ▼
└────────────────┘  └──────────────┘ ┌──┐  ┌──────┐
         │                            │OK│  │ Lock │
         │                            └──┘  │ IP   │
         │                                  │30min │
         │                                  └──────┘
         │
         ▼ (active)
┌────────────────────────┐
│  Success:              │
│  1. Clear attempts     │
│  2. Regenerate session │
│  3. Update last_login  │
│  4. Set remember token │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Redirect by Role:     │
│  - Student → dashboard │
│  - Teacher → dashboard │
│  - Admin → 2FA or dash │
└────────────────────────┘
```

---

## 5. Google OAuth Flow (Students Only)

```
┌─────────────────────────────────────────────────────────────────┐
│                    GOOGLE OAUTH FLOW                             │
└─────────────────────────────────────────────────────────────────┘

User clicks "Sign in with Google"
         │
         ▼
┌────────────────────────┐
│  Redirect to Google    │
│  GET /auth/google      │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  User Authorizes       │
│  on Google             │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Google Callback       │
│  GET /auth/google/     │
│      callback          │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Get Google User Data: │
│  - Email               │
│  - Name                │
│  - Avatar              │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Check Existing User   │
│  by Email              │
└────────┬───────────────┘
         │
         ├─── Exists? ───┐
         │                │
        YES              NO
         │                │
         ▼                ▼
┌────────────────────┐  ┌────────────────────┐
│  Check Role:       │  │  Create New User:  │
│  - Admin?          │  │  1. users table    │
│  - Teacher?        │  │     - role=student │
│  - Student?        │  │     - status=active│
└────────┬───────────┘  │     - email_       │
         │               │       verified_at  │
    ┌────┼────┐          │     - no password  │
    │    │    │          │  2. student_profile│
  Admin Teacher Student  │  3. Assign role    │
    │    │    │          └────────┬───────────┘
    ▼    ▼    ▼                   │
┌────┐ ┌────┐ ┌────┐             │
│Block│Block│Allow│             │
│"Use│"Use│Login│             │
│pwd"│pwd"│     │             │
└────┘└────┘└─┬──┘             │
              │                 │
              └─────────┬───────┘
                        │
                        ▼
               ┌────────────────────┐
               │  Login User:       │
               │  1. auth()->login()│
               │  2. Regenerate     │
               │     session        │
               └────────┬───────────┘
                        │
                        ▼
               ┌────────────────────┐
               │  Check Onboarding: │
               │  - Completed?      │
               └────────┬───────────┘
                        │
                   ┌────┴────┐
                   │         │
                  YES       NO
                   │         │
                   ▼         ▼
            ┌──────────┐ ┌──────────┐
            │Dashboard │ │Onboarding│
            └──────────┘ └──────────┘
```

---

## 6. Password Reset Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    PASSWORD RESET FLOW                           │
└─────────────────────────────────────────────────────────────────┘

User clicks "Forgot Password"
         │
         ▼
┌────────────────────────┐
│  Enter Email           │
│  GET /forgot-password  │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Submit Email          │
│  POST /forgot-password │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Check User:           │
│  - Exists?             │
│  - Has password?       │
└────────┬───────────────┘
         │
         ├─── Has Password? ──┐
         │                     │
        YES                   NO
         │                     │
         ▼                     ▼
┌────────────────────┐  ┌────────────────────┐
│  Generate Token:   │  │  Show Error:       │
│  - Random string   │  │  "You signed up    │
│  - Store in DB     │  │   with Google.     │
│  - Expires 60 min  │  │   Password reset   │
└────────┬───────────┘  │   not available."  │
         │               └────────────────────┘
         ▼
┌────────────────────────┐
│  Send Reset Email      │
│  with Token Link       │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  User Clicks Link      │
│  GET /reset-password/  │
│      {token}           │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Enter New Password    │
│  - Password (12 chars) │
│  - Confirmation        │
└────────┬───────────────┘
         │
         ▼
┌────────────────────────┐
│  Verify Token:         │
│  - Valid?              │
│  - Not expired?        │
└────────┬───────────────┘
         │
         ├─── Valid? ────┐
         │                │
        YES              NO
         │                │
         ▼                ▼
┌────────────────────┐  ┌────────────────────┐
│  Update Password:  │  │  Show Error:       │
│  1. Hash new pwd   │  │  "Invalid or       │
│  2. Save to DB     │  │   expired token"   │
│  3. Invalidate     │  └────────────────────┘
│     remember_token │
│  4. Logout all     │
│     sessions       │
└────────┬───────────┘
         │
         ▼
┌────────────────────────┐
│  Redirect to /login    │
│  with success message  │
└────────────────────────┘
```

---

## 7. Account Status State Machine

```
┌─────────────────────────────────────────────────────────────────┐
│                    ACCOUNT STATUS STATES                         │
└─────────────────────────────────────────────────────────────────┘

                    ┌──────────────┐
                    │   PENDING    │
                    │ (registered, │
                    │  no OTP yet) │
                    └──────┬───────┘
                           │
                           │ OTP Verified
                           │
                           ▼
                    ┌──────────────┐
              ┌────▶│    ACTIVE    │◀────┐
              │     │  (verified,  │     │
              │     │  functional) │     │
              │     └──────┬───────┘     │
              │            │              │
              │            │              │
    Admin     │            │ Admin        │ Admin
    Unsuspend │            │ Suspend      │ Unsuspend
              │            │              │
              │            ▼              │
              │     ┌──────────────┐     │
              └─────│  SUSPENDED   │─────┘
                    │ (blocked by  │
                    │  admin or    │
                    │  auto-lock)  │
                    └──────┬───────┘
                           │
                           │ Admin Delete
                           │ (soft delete)
                           ▼
                    ┌──────────────┐
                    │   DELETED    │
                    │ (soft delete,│
                    │ data kept)   │
                    └──────────────┘

Login Permissions:
┌──────────┬──────────┬────────────────────────────┐
│  Status  │ Can Login│         Action             │
├──────────┼──────────┼────────────────────────────┤
│ pending  │    ❌    │ Redirect to /verify-otp    │
│ active   │    ✅    │ Allow login                │
│suspended │    ❌    │ Show "Account suspended"   │
│ deleted  │    ❌    │ Show "Account not found"   │
└──────────┴──────────┴────────────────────────────┘
```

---

## 8. Redis Keys & TTL

```
┌─────────────────────────────────────────────────────────────────┐
│                    REDIS KEYS STRUCTURE                          │
└─────────────────────────────────────────────────────────────────┘

OTP Verification:
┌────────────────────────────────────────────────────────────┐
│ Key: otp_lock:{user_id}                                    │
│ Value: "1"                                                 │
│ TTL: 1800 seconds (30 minutes)                             │
│ Purpose: Lock account after 10 failed OTP attempts         │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│ Key: otp_attempts:{user_id}                                │
│ Value: Integer (1-10)                                      │
│ TTL: 1800 seconds (30 minutes)                             │
│ Purpose: Count failed OTP attempts                         │
└────────────────────────────────────────────────────────────┘

Login:
┌────────────────────────────────────────────────────────────┐
│ Key: login_lock:{ip}                                       │
│ Value: "1"                                                 │
│ TTL: 1800 seconds (30 minutes)                             │
│ Purpose: Lock IP after 10 failed login attempts            │
└────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────┐
│ Key: login_attempts:{ip}                                   │
│ Value: Integer (1-10)                                      │
│ TTL: 1800 seconds (30 minutes)                             │
│ Purpose: Count failed login attempts per IP                │
└────────────────────────────────────────────────────────────┘

Example Redis Commands:
┌────────────────────────────────────────────────────────────┐
│ # Check if user is locked                                  │
│ redis-cli EXISTS otp_lock:123                              │
│                                                            │
│ # Get remaining TTL                                        │
│ redis-cli TTL otp_lock:123                                 │
│                                                            │
│ # Get attempt count                                        │
│ redis-cli GET otp_attempts:123                             │
│                                                            │
│ # Manually unlock user                                     │
│ redis-cli DEL otp_lock:123                                 │
│ redis-cli DEL otp_attempts:123                             │
│                                                            │
│ # Clear all locks (development only)                       │
│ redis-cli FLUSHDB                                          │
└────────────────────────────────────────────────────────────┘
```

---

## Legend

```
┌─────────────────────────────────────────────────────────────────┐
│                           LEGEND                                 │
└─────────────────────────────────────────────────────────────────┘

Symbols:
  │  ▼  ▲  ◀  ▶  ├  └  ┌  ┐  ┘  ┴  ┬  ─  │  = Flow direction
  ✅ = Success / Allowed
  ❌ = Failure / Blocked
  ⚠️ = Warning / Caution
  ⭐ = Important / Priority

Flow Types:
  ──▶ = Normal flow
  ═══▶ = Important flow
  ···▶ = Optional flow
  ━━▶ = Error flow

Decision Points:
  ┌────┴────┐
  │         │
 YES       NO
  │         │
  ▼         ▼
```
