# Teacher Profile System - Quick Reference Guide

## 🎯 Overview

Complete 5-step teacher profile setup with verification workflow, completeness scoring, and public profile display.

---

## 📝 5-Step Profile Setup

### Step 1: Bio & Experience
**Route**: `/teacher/profile/step/1`

**Fields**:
- `bio` - TEXT, min 50 chars, max 2000 chars (optional but required to proceed)
- `experience_years` - INTEGER, 0-50 years (required)
- `previous_school` - TEXT, max 150 chars (optional)

**Validation**:
```php
'bio' => ['nullable', 'string', 'min:50', 'max:2000']
'experience_years' => ['required', 'integer', 'min:0', 'max:50']
'previous_school' => ['nullable', 'string', 'max:150']
```

---

### Step 2: Subjects & Languages
**Route**: `/teacher/profile/step/2`

**Fields**:
- `subjects` - ARRAY, at least 1 required
- `languages` - ARRAY, at least 1 required

**Available Subjects** (22 total):
```
Math, Science, Physics, Chemistry, Biology
English, Hindi, Punjabi, Urdu, Sanskrit
History, Geography, Social Studies, Political Science
Economics, Commerce
Computer Science, Information Technology
Physical Education, Fine Arts, Music
Other
```

**Available Languages** (8 total):
```
English, Hindi, Punjabi, Bengali
Tamil, Telugu, Marathi, Gujarati
```

**Validation**:
```php
'subjects' => ['required', 'array', 'min:1']
'subjects.*' => ['string', Rule::in($allowedSubjects)]
'languages' => ['required', 'array', 'min:1']
'languages.*' => ['string', Rule::in($allowedLanguages)]
```

---

### Step 3: Rate Type
**Route**: `/teacher/profile/step/3`

**Fields**:
- `is_free` - BOOLEAN (required)
- `hourly_rate` - DECIMAL(8,2), ₹50-₹2000 (required if not free)

**Options**:
- **Free**: `is_free=true`, `hourly_rate=null`
- **Paid**: `is_free=false`, `hourly_rate` between ₹50 and ₹2000

**Validation**:
```php
'is_free' => ['required', 'boolean']
'hourly_rate' => ['required_if:is_free,false', 'nullable', 'numeric', 'min:50', 'max:2000']
```

---

### Step 4: Weekly Availability
**Route**: `/teacher/profile/step/4`

**Fields**:
- `availability` - JSON object with 7 days

**Format**:
```json
{
  "Monday": {
    "enabled": true,
    "start": "09:00",
    "end": "17:00"
  },
  "Tuesday": {
    "enabled": false
  },
  ...
}
```

**Requirements**:
- At least 1 day must be enabled
- End time must be after start time
- Time format: H:i (24-hour)

**Validation**:
```php
'availability' => ['required', 'array']
'availability.Monday.enabled' => ['required', 'boolean']
'availability.Monday.start' => ['required_if:availability.Monday.enabled,true', 'date_format:H:i']
'availability.Monday.end' => ['required_if:availability.Monday.enabled,true', 'date_format:H:i', 'after:availability.Monday.start']
// ... repeat for all 7 days
```

---

### Step 5: Photo & Documents
**Route**: `/teacher/profile/step/5`

**Fields**:
- `avatar` - IMAGE, max 2MB, JPEG/PNG/WebP (required)
- `degree` - FILE, max 10MB, PDF or image (required)
- `service_record` - FILE, max 10MB, PDF or image (required)
- `id_proof` - FILE, max 10MB, PDF or image (required)

**Validation**:
```php
'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']
'degree' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']
'service_record' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']
'id_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']
```

**Note**: If documents already uploaded, they become optional (can re-upload)

---

## 📊 Profile Completeness Score

### Calculation Method
```php
$teacher->getCompletenessScore(); // Returns 0-100
```

### Scoring Breakdown

| Criteria | Points | Check |
|----------|--------|-------|
| Bio filled (>50 chars) | +20% | `mb_strlen($bio) >= 50` |
| At least 1 subject | +20% | `count($subjects) > 0` |
| Availability set (1+ day) | +20% | At least 1 day enabled with times |
| Profile photo uploaded | +20% | `!empty($user->avatar)` |
| Documents uploaded (1+) | +20% | `$documents()->exists()` |

### Usage Example
```php
$teacher = auth()->user()->teacherProfile;
$score = $teacher->getCompletenessScore();

if ($score < 100) {
    // Show nudge to complete profile
    $missing = [];
    if (empty($teacher->bio) || mb_strlen($teacher->bio) < 50) {
        $missing[] = 'Add a bio (at least 50 characters)';
    }
    if (empty($teacher->subjects)) {
        $missing[] = 'Select at least one subject';
    }
    // ... check other criteria
}
```

---

## 🔐 Verification Workflow

### Document Status States

| Status | Description | Can Edit? |
|--------|-------------|-----------|
| `pending` | Awaiting admin review | ✅ Yes |
| `approved` | Admin approved | ❌ No |
| `rejected` | Admin rejected with reason | ✅ Yes (can re-upload) |

### Verification Requirements

Teacher is **fully verified** (`is_verified=true`) ONLY when:
- ✅ At least 1 `degree` document is `approved`
- ✅ At least 1 `service_record` document is `approved`

### Admin Actions

**Approve**:
```php
POST /admin/verifications/{id}/approve
```
- Sets `is_verified=true`
- Sets `user.status=active`
- Marks all documents as `approved`
- Sends `TeacherApprovedMail`

**Reject**:
```php
POST /admin/verifications/{id}/reject
Body: { "reason": "Degree certificate is not clear" }
```
- Sets `is_verified=false`
- Marks documents as `rejected` with reason
- Sends `TeacherRejectedMail`
- Teacher can re-upload documents

### Document URLs

**Signed URL** (5-minute expiry):
```php
$url = URL::temporarySignedRoute(
    'admin.documents.show',
    now()->addMinutes(5),
    ['document' => $documentId]
);
```

---

## 🌐 Public Teacher Profile

### Access

**Routes**:
- Web: `/teachers/{id}`
- API: `/api/teachers/{teacher}`

**Visibility**:
- ✅ Accessible without login
- ✅ Shows only if `user.status=active` AND `is_verified=true`
- ❌ Teachers cannot view their own public profile (404 error)

### Content Shown

**Public Information**:
- Name, avatar, verified badge
- Subjects, languages
- Experience years
- Bio
- Hourly rate (or FREE badge)
- Rating average
- Total reviews count
- Recent 5 reviews
- Availability summary

**Hidden Information** (privacy):
- ❌ Phone number
- ❌ Email address
- ❌ Exact school name

### Login-Required Actions

**Send Message**:
- Requires login
- Redirects to `/login?redirect=/teachers/{id}`

**Book Session**:
- Requires login
- Redirects to `/login?redirect=/teachers/{id}`

### Teacher Self-View Block

```php
// In TeacherController::show()
if ($currentUser && $currentUser->isTeacher() && $currentUser->id === $teacher) {
    throw new NotFoundHttpException('Teachers cannot view their own public profile...');
}
```

---

## 🗄️ Database Schema

### teacher_profiles Table

```sql
CREATE TABLE teacher_profiles (
    id BIGINT PRIMARY KEY,
    user_id BIGINT UNIQUE,
    bio TEXT,
    experience_years INT,
    previous_school VARCHAR(150),
    subjects JSON,
    languages JSON,
    hourly_rate DECIMAL(8,2),
    is_free BOOLEAN DEFAULT false,
    is_verified BOOLEAN DEFAULT false,
    rating_avg DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    gender ENUM('male','female','other'),
    availability JSON,
    onboarding_step INT DEFAULT 1,
    tour_completed BOOLEAN DEFAULT false,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### teacher_documents Table

```sql
CREATE TABLE teacher_documents (
    id BIGINT PRIMARY KEY,
    teacher_id BIGINT,
    type ENUM('degree','service_record','id_proof'),
    file_path VARCHAR(255),
    original_filename VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    reviewed_by BIGINT,
    reviewed_at TIMESTAMP,
    uploaded_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (teacher_id) REFERENCES teacher_profiles(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);
```

---

## 🔧 Configuration

### Config File: `config/edubridge.php`

```php
return [
    'subjects' => [
        'Math', 'Science', 'Physics', 'Chemistry', 'Biology',
        'English', 'Hindi', 'Punjabi', 'Urdu', 'Sanskrit',
        'History', 'Geography', 'Social Studies', 'Political Science',
        'Economics', 'Commerce',
        'Computer Science', 'Information Technology',
        'Physical Education', 'Fine Arts', 'Music',
        'Other',
    ],
    'languages' => [
        'English', 'Hindi', 'Punjabi', 'Bengali',
        'Tamil', 'Telugu', 'Marathi', 'Gujarati',
    ],
];
```

---

## 🧪 Testing Commands

### Test Profile Completeness
```bash
php artisan tinker
>>> $teacher = User::where('role', 'teacher')->first()->teacherProfile;
>>> $teacher->getCompletenessScore();
```

### Test Validation
```bash
# Test hourly rate minimum
curl -X POST /teacher/profile/step/3 \
  -d "is_free=false" \
  -d "hourly_rate=49"
# Expected: Error "Hourly rate must be at least ₹50."

# Test hourly rate maximum
curl -X POST /teacher/profile/step/3 \
  -d "is_free=false" \
  -d "hourly_rate=2001"
# Expected: Error "Hourly rate cannot exceed ₹2,000."
```

### Test Teacher Self-View Block
```bash
# Login as teacher ID 123
curl -X GET /api/teachers/123 \
  -H "Authorization: Bearer {teacher_token}"
# Expected: 404 "Teachers cannot view their own public profile..."
```

---

## 📝 Common Tasks

### Get Teacher Profile
```php
$teacher = auth()->user()->teacherProfile;
```

### Check Completeness
```php
$score = $teacher->getCompletenessScore();
if ($score < 100) {
    // Show completion nudge
}
```

### Check Verification Status
```php
if ($teacher->is_verified) {
    // Teacher is verified and appears in search
} else {
    // Teacher is not verified yet
}
```

### Check if Fully Verifiable
```php
if ($teacher->isFullyVerifiable()) {
    // Has all required documents uploaded
    // Admin can approve
}
```

### Get Documents by Type
```php
$degree = $teacher->documents()->where('type', 'degree')->first();
$serviceRecord = $teacher->documents()->where('type', 'service_record')->first();
$idProof = $teacher->documents()->where('type', 'id_proof')->first();
```

### Update Profile Step
```php
$teacher->update([
    'bio' => 'My teaching bio...',
    'experience_years' => 10,
    'subjects' => ['Math', 'Physics'],
    'languages' => ['English', 'Hindi'],
]);
```

---

## 🚨 Common Issues & Solutions

### Issue: Profile completeness shows 0% but profile is filled
**Solution**: Check if bio has at least 50 characters
```php
if (mb_strlen($teacher->bio) < 50) {
    // Bio too short
}
```

### Issue: Teacher can't proceed to next step
**Solution**: Check validation errors
```php
// Step 1: Bio must be filled to proceed
// Step 2: At least 1 subject and 1 language required
// Step 3: Must select Free OR Paid with valid rate
// Step 4: At least 1 day must be enabled
// Step 5: Photo and documents required
```

### Issue: Teacher not appearing in search
**Solution**: Check verification status
```php
if (!$teacher->is_verified) {
    // Not verified yet - won't appear in search
}
if ($teacher->user->status !== 'active') {
    // Account not active - won't appear in search
}
```

### Issue: Document upload fails
**Solution**: Check file size and type
```php
// Avatar: max 2MB, JPEG/PNG/WebP
// Documents: max 10MB, PDF or image
```

---

## 📞 Support

For issues or questions:
- **Documentation**: See `SECTION_3_IMPLEMENTATION_COMPLETE.md`
- **Analysis**: See `SECTION_3_TEACHER_PROFILE_ANALYSIS.md`
- **Email**: support@edubridge.com
