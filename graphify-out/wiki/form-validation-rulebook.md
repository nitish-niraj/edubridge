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
