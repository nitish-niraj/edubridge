# Form Validation — Complete Rulebook

> **Golden Rule:** Always validate on both **frontend** (for user convenience) and **backend** (for security). Never trust client-side validation alone.

---

## 1. General Rules for Every Field

Every field, regardless of type, should follow these basics:

- **Required vs optional** — decide upfront whether the field must be filled.
- **No blank-only input** — spaces alone do not count as a value.
- **Trim spaces** — remove leading and trailing whitespace before processing.
- **Length limits** — set both a minimum and maximum character count.
- **Allowed characters** — restrict unexpected symbols based on the field's purpose.
- **Format check** — ensure the value follows the expected pattern.
- **Clear error messages** — show a helpful message near the field when validation fails.
- **Prevent duplicate submission** — disable the submit button while processing; stop double-clicks.
- **Accessibility** — connect errors to fields using `aria-invalid` and `aria-describedby`; ensure screen reader compatibility.
- **Server-side revalidation** — always recheck everything on the backend, even if HTML/JS already validated it.

---

## 2. Text Input

For general text fields like name, city, company, subject, message, etc.:

- Do not allow only spaces.
- Set a minimum length where it makes sense (e.g., a name should not be 1 character).
- Set a maximum to avoid oversized input.
- Allow only valid characters relevant to the field — avoid over-restricting since real names can include hyphens, apostrophes, and accented characters.
- Strip or escape HTML tags to prevent injection.
- Sanitize input to block script injection and XSS.
- Show a character counter for longer fields (e.g., `"250/500 characters"`).
- Normalize line breaks in textareas.
- Optionally add a profanity filter if your app needs it.

**Character allowances by field:**

| Field | Allowed Characters |
|---|---|
| Full name | Letters, spaces, hyphens, apostrophes, accented characters |
| City | Letters, spaces, hyphens, dots |
| Username | Letters, numbers, underscores (see Username section) |
| Message | Letters, numbers, common punctuation |

**Textarea-specific limits:**

| Field | Min | Max |
|---|---|---|
| Short message / comment | 10–20 chars | 500–2000 chars |
| Description | 50 chars | 5000 chars |

---

## 3. Email

- Must contain exactly one `@` symbol.
- Must have text before `@` and a valid domain after it (e.g., `gmail.com`).
- Must have a valid TLD of at least 2 characters.
- No spaces anywhere inside the email.
- Local part and domain must not start or end with a dot.
- Max length: 254 characters.
- Convert to lowercase before storing.
- Check for duplicate email on registration.
- Optionally check for disposable email domains.
- Optionally send a verification link or OTP to confirm ownership.

**Regex patterns:**

```javascript
// Basic
/^[^\s@]+@[^\s@]+\.[^\s@]+$/

// Advanced
/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/
```

**Valid:** `user@gmail.com`
**Invalid:** `usergmail.com` · `user@` · `@gmail.com` · `user@ gmail.com`

**Implementation example:**

```javascript
function validateEmail(email) {
  if (!email || email.trim() === '')
    return { valid: false, error: 'Email is required' };

  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!regex.test(email))
    return { valid: false, error: 'Please enter a valid email address' };

  if (email.length > 254)
    return { valid: false, error: 'Email address is too long' };

  return { valid: true, error: null };
}
```

---

## 4. Password

**Strength rules:**

- Minimum 8 characters, maximum 128 characters (cap prevents DoS attacks).
- At least one uppercase letter.
- At least one lowercase letter.
- At least one number.
- At least one special character (`!@#$%^&*` etc.).
- Block common weak passwords (`password123`, `qwerty`, `12345678`).
- Password must not match username or email.
- No spaces (unless your app explicitly supports them).

**Confirmation field:**

- Must match the original password exactly.
- Validate in real time as the user types the second password.

**Storage and UX:**

- Never store plain-text passwords — always hash on the backend.
- Allow paste into the password field.
- Add a show/hide toggle.
- Show a real-time strength meter.
- Avoid overly harsh rules if your app does not require them — they hurt usability.

```javascript
const passwordRules = {
  minLength: 8,
  maxLength: 128,
  requireUppercase: true,
  requireLowercase: true,
  requireNumber: true,
  requireSpecialChar: true,
  noSpaces: true
};
```

---

## 5. Phone Number

- Allow digits and `+` for international numbers.
- Remove spaces, dashes, and brackets before storing.
- Validate total digit count based on country rules.
  - India: 10 digits (mobile)
  - International: use E.164 format (`+[country code][number]`)
- Optionally auto-format the number as the user types.
- Optionally verify using OTP.

```javascript
// US Phone
/^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/

// International (E.164)
/^\+?[1-9]\d{1,14}$/
```

---

## 6. Name Fields

**First / Last Name:**

- Min: 1–2 characters. Max: 50–100 characters.
- Allow letters, spaces, hyphens, apostrophes, and international characters (`é`, `ñ`, etc.).
- No numbers, unless your use case requires them.
- Trim whitespace; reject leading/trailing spaces and consecutive spaces.

**Username:**

- Min: 3–4 characters. Max: 20–30 characters.
- Alphanumeric only, or allow underscores/hyphens.
- No spaces.
- Block reserved words: `admin`, `root`, `support`, `null`, etc.
- Uniqueness check (case-insensitive).
- Clearly define and document your case sensitivity rules.

---

## 7. Date Validation

**General rules:**

- Required fields must not be empty.
- Validate the date format (e.g., `DD/MM/YYYY` vs `MM/DD/YYYY`).
- Reject impossible dates (e.g., February 30th).
- Account for timezone differences — "today" means different calendar dates in different regions.

**By use case:**

| Use case | Rule |
|---|---|
| Date of birth | Must be in the past; max age 120; minimum age if app requires (13, 18, 21) |
| Booking / appointment | Must be in the future; minimum advance notice (e.g., 24 hours); maximum lookahead (e.g., 6 months) |
| Date range | Start date must be ≤ end date; validate minimum and maximum duration; no overlapping ranges |
| Leave / event start | Must not be before today |

**Implementation example:**

```javascript
function validateBookingDate(date) {
  const selected = new Date(date);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (selected < today)
    return { valid: false, error: 'Date must be in the future' };

  const minDate = new Date(today);
  minDate.setDate(minDate.getDate() + 1);
  if (selected < minDate)
    return { valid: false, error: 'Please book at least 24 hours in advance' };

  const maxDate = new Date(today);
  maxDate.setMonth(maxDate.getMonth() + 6);
  if (selected > maxDate)
    return { valid: false, error: 'Cannot book more than 6 months ahead' };

  if (selected.getDay() === 0)
    return { valid: false, error: 'Bookings are not available on Sundays' };

  return { valid: true, error: null };
}
```

---

## 8. Time Validation

- Validate the time format (12-hour vs 24-hour — pick one and be consistent).
- Valid hours: 0–23 (24-hour) or 1–12 (12-hour). Valid minutes: 0–59.
- End time must always be after start time.
- If combined with a date: when the date is today, the time must not be in the past.
- Restrict times outside business hours if applicable.
- Handle timezones carefully for online or cross-region events.

**Example:** Start time `10:00 AM`, End time `9:30 AM` → reject.

---

## 9. Date and Time Together (Schedulers & Bookings)

This section applies to meeting schedulers, appointment systems, and booking forms:

- Date must not be in the past.
- If date is today, time must not be earlier than the current time.
- End time must be after start time.
- Duration must not exceed allowed limits.
- Do not allow overlapping bookings for the same slot.
- Disable already-booked or unavailable time slots in the UI.
- Respect business hours, holidays, and weekends.
- Always revalidate slot availability on the backend — another user may have booked it between the user opening the form and submitting it.

---

## 10. Number Fields

- Only accept numeric input.
- Set a minimum and maximum value.
- Decide whether decimals are allowed.
- Prevent negative values where they have no meaning.
- Reject scientific notation if your system does not handle it.

**By type:**

| Type | Min | Max | Decimals |
|---|---|---|---|
| Age | 0 (or 13/18) | 120 | No |
| Quantity | 1 | 9999 | No |
| Price / Money | 0 | — | Yes (2 places), format as `$1,234.56` |
| Percentage | 0 | 100 | Yes (2 places) |

---

## 11. Dropdown / Select

- The user must choose a real option — do not accept the placeholder (e.g., "Select country").
- Validate on the backend that the submitted value belongs to the allowed list.
- For multiple selection: validate minimum and maximum count.
- For cascading dropdowns (country → state → city): re-validate child dropdowns when parent changes.
- Offer search/filter for long lists.

```javascript
// Cascading example
if (country === 'India') showStates(indianStates);
else if (country === 'USA') showStates(usStates);
```

---

## 12. Radio Buttons

- One option must be selected if the field is required.
- Accept only one value from the allowed option set.
- Never trust the selected value from the client alone — validate against the allowed list on the backend.

**Common uses:** gender, payment method, membership type.

---

## 13. Checkboxes

- Required checkboxes (e.g., "I agree to the terms") must be checked before submission.
- Optional checkboxes must remain optional.
- For groups, validate minimum and maximum selection counts if applicable.
- GDPR notice: newsletter opt-in must be a voluntary, unchecked-by-default checkbox.

**Examples:**

- "I agree to Terms & Conditions" → required, must be checked.
- Newsletter subscription → optional.
- "Select at least 2 skills" → minimum count enforced.
- "Select up to 3 interests" → maximum count enforced.

---

## 14. File Upload

**File type:**

- Check the file extension and also verify the actual MIME type — do not rely on extension alone.
- Block executable files: `.exe`, `.sh`, `.bat`, `.php`, etc.

```javascript
const allowedImages = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
const allowedDocs   = ['.pdf', '.doc', '.docx', '.txt'];
const allowedVideos = ['.mp4', '.mov', '.avi'];
```

**File size:**

- Set a minimum size to avoid empty uploads.
- Set a maximum per file (2 MB, 5 MB, 10 MB — based on use case).
- Set a total upload size limit for multi-file uploads.
- Display the size limit clearly to the user.

**Security:**

- Rename files on the server — never trust the original filename.
- Store uploaded files outside the web root.
- Scan for malware if possible.

**Image-specific:**

- Validate minimum and maximum pixel dimensions.
- Enforce aspect ratio if required (e.g., square avatar).

---

## 15. Address Fields

| Field | Required | Notes |
|---|---|---|
| Street | Yes | Allow numbers, letters, spaces, `#`, `/`, `-` |
| City | Yes | Min 2 chars, max 50 chars; letters, spaces, hyphens |
| State / Province | Yes | Prefer a dropdown; validate against selected country |
| Postal / ZIP code | Yes | Format varies by country; validate pattern |
| Country | Yes | Dropdown with search; optionally default by IP |
| Apartment / Landmark | No | Optional additional line |

- Avoid enforcing a single address format globally — address structures vary widely by country.
- Trim extra spaces from all address fields.

---

## 16. OTP / Verification Code

- Fixed length: typically 4, 5, or 6 digits.
- Digits only.
- Enforce an expiry time (e.g., 5–10 minutes).
- Limit the number of incorrect attempts before locking.
- Resend option must have a cooldown period (e.g., 30–60 seconds).
- Each OTP is single-use — invalidate immediately after use.
- Store OTPs hashed or using a secure mechanism on the backend.

---

## 17. Search Field

- Minimum 2–3 characters before triggering search.
- Maximum length limit (e.g., 100 characters).
- Trim extra spaces.
- Allow common special characters — do not over-restrict.
- Debounce input by 300 ms to avoid excessive API calls.
- Support partial and case-insensitive search.
- Sanitize input to prevent SQL injection and XSS.

---

## 18. URL

```javascript
// Basic
/^(https?:\/\/)?([\w\d\-]+\.)+[\w\-]+(\/.*)?$/

// Strict
/^https?:\/\/(www\.)?[\w\-]+(\.[\w\-]+)+[/#?]?.*$/
```

- Require `http` or `https` protocol.
- Valid domain structure required.
- No spaces.
- Max length: 2048 characters.
- Optionally restrict to specific allowed domains.
- Optionally check if the URL is reachable (do this asynchronously).

---

## 19. Payment / Credit Card

| Field | Rule |
|---|---|
| Card number | Must pass the Luhn algorithm check; detect card type (Visa, MC, Amex) |
| CVV | 3 digits for Visa/MC; 4 digits for Amex; numeric only |
| Expiry date | Format `MM/YY` or `MM/YYYY`; must be a future date; valid month `01–12` |
| Name on card | Must not be empty |
| Billing postal code | Required if your payment processor needs it |

```javascript
// Card type patterns
Visa:       /^4[0-9]{12}(?:[0-9]{3})?$/
MasterCard: /^5[1-5][0-9]{14}$/
Amex:       /^3[47][0-9]{13}$/
```

**Security:**

- Never store CVV — ever.
- Tokenize card numbers; do not store raw card data.
- Use HTTPS only on all payment pages.
- Aim for PCI-DSS compliance; use a reputable payment gateway when possible.

---

## 20. Conditional Validation

Some fields are only required based on the state of other fields:

```javascript
// Field becomes required if checkbox is ticked
if (needsShipping) {
  shippingAddress.required = true;
}

// Show different fields based on country
if (country === 'USA') {
  require(SSN);
} else {
  require(passportNumber);
}

// Age-based gating
if (age < 18) {
  require(parentalConsent);
}
```

Always revalidate conditional rules on the backend — the client-side state cannot be trusted.

---

## 21. Validation Timing

| Trigger | What to validate | Notes |
|---|---|---|
| On input (keystroke) | Character count, password strength, format hints | Keep it lightweight |
| On blur (leave field) | Full field validation, async checks (e.g., username uniqueness) | Main UX validation point |
| On change | Dropdowns, checkboxes, radio buttons | Trigger immediately |
| On submit | All fields together, final server-side check | Never skip this step |

**Do not** show aggressive errors while the user is still actively typing — it feels intrusive.

---

## 22. Error Messages

A good error message should be clear, specific, and actionable. It must appear close to the relevant field and use plain language.

| ❌ Bad | ✅ Good |
|---|---|
| "Invalid input" | "Please enter a valid email address." |
| "Error" | "Password must be at least 8 characters long." |
| "Wrong date" | "Meeting date cannot be in the past." |
| "Invalid" | "Phone number must be 10 digits." |

**Visual cues:**

- Red border / icon for errors.
- Green border / ✓ icon for valid fields.
- Show an error summary at the top of long forms.
- Move focus to the first error on submit.

---

## 23. Security

These apply to every form, without exception:

- **Server-side validation** — always, regardless of what the frontend does.
- **Input sanitization** — remove or escape dangerous characters before processing.
- **XSS prevention** — escape all user-supplied data before rendering it.
- **SQL injection prevention** — use parameterized queries or prepared statements; never concatenate user input into queries.
- **CSRF protection** — include CSRF tokens in all sensitive forms.
- **Rate limiting** — restrict how often a form can be submitted.
- **CAPTCHA** — add only where abuse is a real risk; do not add to every form unnecessarily.
- **Honeypot fields** — hidden fields that bots fill in, humans do not; use to detect bots silently.
- **No exposure of internals** — do not reveal internal error details or stack traces to the user.

**What to strip from inputs:**

- `<script>` tags and HTML
- SQL keywords in sensitive fields
- File path traversal sequences (`../`)
- NULL bytes

---

## 24. UX — Making Forms Easy to Use

- Keep forms short — ask only for what you actually need.
- Group related fields together.
- Use real labels, not just placeholders (placeholders disappear when typing).
- Mark required fields clearly (`*` with a legend).
- Auto-format where helpful (e.g., phone number, credit card number, date).
- Save partially entered data for long multi-step forms.
- Show a loading spinner after submit.
- Disable the submit button while the request is in progress.
- Use appropriate input types for mobile (`type="tel"`, `type="email"`, `type="number"`).
- Keep touch targets at least 44px for mobile.
- Use font-size ≥ 16px on inputs to prevent auto-zoom on iOS.

---

## 25. Form Submission Flow

**Before submitting:**

1. Run full frontend validation across all fields.
2. Show a loading indicator.
3. Disable the submit button.
4. Prevent duplicate submissions.
5. Optionally save a draft.

**On success:**

- Show a clear success message.
- Redirect the user if appropriate.
- Optionally clear the form or keep values for reference.
- Send a confirmation email if relevant.

**On error:**

- Keep the user's entered data in the form.
- Show specific, field-level error messages.
- Re-enable the submit button so the user can try again.
- Log the error on the backend for debugging.

---

## 26. Specific Form Types — Quick Reference

**Registration:**

```
✓ Email (unique, verified)
✓ Password (strong, confirmed)
✓ Username (unique, valid format)
✓ Age verification if applicable
✓ Terms & conditions checkbox
✓ CAPTCHA
```

**Login:**

```
✓ Email or username
✓ Password
✓ Rate limiting on failed attempts
✓ Failed attempt tracking / lockout
✓ Remember me (optional)
```

**Contact Form:**

```
✓ Name (required)
✓ Email (required, valid format)
✓ Phone (optional, but validate if provided)
✓ Message (min 10 characters)
✓ CAPTCHA
```

**Booking / Appointment:**

```
✓ Date (future only, minimum advance notice)
✓ Time (available slots, business hours)
✓ No overlapping bookings
✓ Buffer time between slots
✓ Maximum advance booking limit
✓ Exclude holidays / weekends if applicable
```

**Payment:**

```
✓ Card number (Luhn valid, type detected)
✓ Expiry (future date, valid month)
✓ CVV (correct digit count)
✓ Billing address
✓ Amount verification
✓ Terms acceptance
```

**Search:**

```
✓ Minimum 2–3 characters
✓ Debounce input (300ms)
✓ Max length
✓ Sanitize against injection
```

---

## 27. Internationalization

- Support date format variations: `MM/DD/YYYY` vs `DD/MM/YYYY`.
- Support varied phone number formats by country.
- Allow single-name entries — some cultures do not use a first/last name split.
- Address formats differ by country — do not enforce a single structure.
- Use UTF-8 throughout for full character set support.
- Support right-to-left languages where needed.
- Format currency and decimal separators based on locale (`.` vs `,`).

---

## 28. Mobile Considerations

- Use the correct `input type` so the right keyboard appears (`tel`, `email`, `number`, `date`).
- Touch targets must be at least 44×44px.
- Use `font-size: 16px` or larger on inputs to prevent iOS auto-zoom.
- Minimize typing by using dropdowns and date pickers.
- Auto-format inputs like phone and card numbers.
- Use native HTML5 date/time pickers where possible.

---

## 29. Implementation Reference

**HTML5 attributes:**

```html
<input
  type="email"
  required
  minlength="5"
  maxlength="254"
  pattern="[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$"
  aria-required="true"
  aria-invalid="false"
>
```

**Reusable validation function pattern:**

```javascript
// All validators return the same shape for consistency
function validateField(value) {
  if (!value || value.trim() === '')
    return { valid: false, error: 'This field is required' };

  // ... field-specific checks ...

  return { valid: true, error: null };
}
```

---

## 30. Testing Checklist

Before releasing any form, test it with:

- ✅ Valid data (happy path)
- ✅ Empty fields
- ✅ Spaces-only input
- ✅ Very short inputs (below minimum)
- ✅ Very long inputs (above maximum)
- ✅ Special characters
- ✅ SQL injection strings
- ✅ XSS strings (`<script>alert(1)</script>`)
- ✅ Boundary values (exactly at min/max)
- ✅ Double submission (click submit twice quickly)
- ✅ Back button behavior after submission
- ✅ Different browsers (Chrome, Firefox, Safari, Edge)
- ✅ Different devices (desktop, tablet, mobile)
- ✅ Keyboard-only navigation
- ✅ Screen reader behavior
- ✅ Slow network conditions
- ✅ Expired sessions mid-form

---

## 31. Pre-Submit Checklist (Coding Reference)

Use this as a final check before any form goes to production:

```
□ Required fields are enforced
□ Blank-only values are rejected
□ Whitespace is trimmed
□ Correct format validated per field
□ Min and max length enforced
□ Allowed character set defined and checked
□ Date/time rules applied (past/future/range)
□ Dropdown placeholder rejected as a valid value
□ File type, size, and count validated
□ Password confirmation matches
□ Duplicate records checked where needed
□ Conditional fields validated based on dependencies
□ Frontend and backend rules are identical
□ Server-side validation is always run
□ Input is sanitized against XSS and injection
□ CSRF token in place for sensitive forms
□ Rate limiting configured
□ Error messages are clear and field-specific
□ Submit button disabled during processing
□ Double submission prevented
```

---

## 32. Best Practices Summary

A robust form validation system has:

- **Frontend validation** — for real-time user feedback and convenience.
- **Backend validation** — for security and correctness; always runs, never skipped.
- **Reusable validation functions** — one function per field type, shared across the app.
- **Consistent rules** — if your frontend says password minimum is 8 characters, the backend must use the exact same rule.
- **Clear, specific error handling** — errors tell the user what went wrong and how to fix it.
- **Graceful failure** — on error, keep the user's data and let them correct it easily.
