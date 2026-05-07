# Real-Time Messaging System - Quick Reference

## 🚀 Quick Start

### Start a 1:1 Conversation (Student Only)
```bash
POST /api/conversations
Authorization: Bearer {student_token}

{
  "teacher_id": 123,
  "message": "Hello, I need help with Math!"
}
```

### Send a Message
```bash
POST /api/conversations/{id}/messages
Authorization: Bearer {token}

# Text message
{
  "type": "text",
  "body": "Hello!"
}

# Image message
{
  "type": "image",
  "attachment": <file>  # Max 5MB, JPEG/PNG/WebP
}

# File message
{
  "type": "file",
  "attachment": <file>  # Max 10MB, PDF/Word
}
```

### Post Announcement (Teacher Only, Group Only)
```bash
POST /api/conversations/{id}/announcement
Authorization: Bearer {teacher_token}

{
  "body": "Class will start at 3 PM today!"
}
```

### Mark Conversation as Read
```bash
PATCH /api/conversations/{id}/read
Authorization: Bearer {token}
```

### Delete Message
```bash
DELETE /api/conversations/{id}/messages/{messageId}
Authorization: Bearer {token}

# Rules:
# - Sender can delete within 10 minutes
# - Teacher can delete any message in groups
# - Soft delete (admin can restore)
```

### Send Typing Indicator
```bash
POST /api/conversations/{id}/typing
Authorization: Bearer {token}

# Throttled to 1 per 2 seconds
```

---

## 🔌 Real-Time WebSocket

### Subscribe to Messages
```javascript
Echo.private(`conversation.${conversationId}`)
    .listen('MessageSent', (e) => {
        console.log('New message:', e.message);
        // Append to chat UI
    });
```

### Subscribe to Typing Indicators
```javascript
Echo.join(`conversation.${conversationId}`)
    .here((users) => {
        console.log('Online users:', users);
    })
    .joining((user) => {
        console.log('User joined:', user.name);
    })
    .leaving((user) => {
        console.log('User left:', user.name);
    })
    .listenForWhisper('typing', (e) => {
        console.log(`${e.name} is typing...`);
        // Show typing indicator
    });
```

### Send Typing Event
```javascript
// Throttle on client-side (1 per 2 seconds)
let typingTimeout;
inputField.addEventListener('input', () => {
    clearTimeout(typingTimeout);
    
    // Send typing event
    fetch(`/api/conversations/${conversationId}/typing`, {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    });
    
    // Clear typing indicator after 3 seconds
    typingTimeout = setTimeout(() => {
        // Hide typing indicator
    }, 3000);
});
```

---

## 📊 Message Types

| Type | Max Size | Allowed Formats | Notes |
|------|----------|----------------|-------|
| **text** | 2000 chars | Plain text | Most common |
| **image** | 5MB | JPEG, PNG, WebP | MIME validated |
| **file** | 10MB | PDF, Word (.doc, .docx) | Spec says PDF only |
| **announcement** | 2000 chars | Plain text | Teacher-only, group-only |

---

## 🔒 Access Rules

### 1:1 Conversations
- ✅ Students can initiate
- ❌ Teachers cannot initiate (only reply)
- ✅ Both can send messages
- ✅ Both can delete own messages (10-min window)
- ❌ Cannot delete entire conversation

### Group Conversations
- ✅ Teacher can post announcements
- ✅ Teacher can delete any message
- ✅ Students can delete own messages (10-min window)
- ✅ Muted students: messages hidden from others
- ✅ Teacher sees `[MUTED]` label on muted messages

---

## 📬 Notification Logic

### When Message Sent
1. Dispatch `SendChatNotification` job to queue
2. Job checks if recipient online via Pusher presence API
3. **If offline**: Send email with 50-char preview + "Reply Now" link
4. **If online**: Skip email (they see it live)
5. **SMS**: Send to teachers if `notification_preferences.new_message_sms = true`
6. **Rate limit**: Max 1 email per hour per conversation

### Email Content
- Sender name and avatar
- 50-character message preview
- "Reply Now" link to conversation
- Only sent if recipient offline

---

## 🎯 Read Receipts

### Single Tick (Sent)
- Message has `created_at` timestamp
- Shown immediately after sending

### Double Tick (Seen)
- Message has `read_at` timestamp
- Set when recipient opens conversation
- Endpoint: `PATCH /api/conversations/{id}/read`

### Unread Count
```php
// Calculated per conversation
$unreadCount = Message::where('conversation_id', $conversationId)
    ->where('sender_id', '!=', $currentUserId)
    ->whereNull('read_at')
    ->count();
```

---

## 🗂️ Database Schema

### conversations
```sql
id                  BIGINT
created_by          BIGINT          -- User who created
direct_student_id   BIGINT          -- For 1:1 conversations
teacher_id          BIGINT          -- For both types
title               VARCHAR(255)    -- For group conversations
is_group            BOOLEAN         -- false = 1:1, true = group
subject             VARCHAR(100)    -- For group conversations
description         TEXT            -- For group conversations
max_students        INT             -- For group conversations (default 30)
invite_code         VARCHAR(10)     -- For group invites
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

### messages
```sql
id                  BIGINT
conversation_id     BIGINT
sender_id           BIGINT
body                TEXT            -- Max 2000 chars
type                ENUM            -- text, image, file, announcement
file_url            VARCHAR(500)    -- For image/file types
read_at             TIMESTAMP       -- NULL = unread
created_at          TIMESTAMP
updated_at          TIMESTAMP
deleted_at          TIMESTAMP       -- Soft delete
```

### conversation_participants (1:1 only)
```sql
conversation_id     BIGINT
user_id             BIGINT
joined_at           TIMESTAMP
left_at             TIMESTAMP       -- NULL = active
```

### class_members (group only)
```sql
id                  BIGINT
conversation_id     BIGINT
user_id             BIGINT
role                ENUM            -- teacher, student
is_muted            BOOLEAN         -- For muted students
joined_at           TIMESTAMP
left_at             TIMESTAMP       -- NULL = active
```

---

## 🧪 Testing Scenarios

### Test 1: No Duplicate Conversations
```bash
# Student sends first message to teacher
POST /api/conversations
{ "teacher_id": 123, "message": "Hello!" }
# Response: conversation_id = 1

# Student sends another message to same teacher
POST /api/conversations
{ "teacher_id": 123, "message": "Another message" }
# Response: conversation_id = 1 (same conversation)
```

### Test 2: Message Deletion (10-minute rule)
```bash
# Student sends message
POST /api/conversations/1/messages
{ "type": "text", "body": "Test message" }
# Response: { "id": 456, "created_at": "2024-01-01 10:00:00" }

# Delete within 10 minutes (should work)
DELETE /api/conversations/1/messages/456
# Response: 200 OK

# Try to delete after 10 minutes (should fail)
# Wait 11 minutes or manually set created_at to 11 minutes ago
DELETE /api/conversations/1/messages/456
# Response: 403 "Students can delete group messages only within 10 minutes."
```

### Test 3: Muted Students
```bash
# Teacher mutes student
PATCH /api/groups/1/members/456
{ "is_muted": true }

# Muted student sends message
POST /api/conversations/1/messages
{ "type": "text", "body": "I am muted" }
# Response: 200 OK (message saved)

# Other students fetch messages
GET /api/conversations/1/messages
# Response: Muted student's message NOT included

# Teacher fetches messages
GET /api/conversations/1/messages
# Response: Muted student's message included with "muted_label": "[MUTED]"
```

### Test 4: Real-Time Broadcasting
```javascript
// User A subscribes to conversation
Echo.private('conversation.1')
    .listen('MessageSent', (e) => {
        console.log('New message:', e.message);
    });

// User B sends message
await fetch('/api/conversations/1/messages', {
    method: 'POST',
    body: JSON.stringify({ type: 'text', body: 'Hello!' })
});

// Expected: User A receives message in real-time
```

### Test 5: Smart Notifications
```bash
# Scenario 1: Recipient is online
# 1. Recipient joins presence channel
# 2. Send message
# Expected: No email sent (recipient is online)

# Scenario 2: Recipient is offline
# 1. Recipient not in presence channel
# 2. Send message
# Expected: Email sent to recipient

# Scenario 3: Rate limiting
# 1. Send message (email sent)
# 2. Send another message within 1 hour
# Expected: No email sent (rate limited)
# 3. Wait 1 hour, send message
# Expected: Email sent again
```

---

## 🔧 Troubleshooting

### Messages Not Broadcasting
1. Check Pusher credentials in `.env`
2. Verify `BROADCAST_DRIVER=pusher`
3. Check channel authentication in `routes/channels.php`
4. Verify user is participant in conversation

### Notifications Not Sending
1. Check queue worker is running: `php artisan queue:work`
2. Verify Pusher presence API is accessible
3. Check email configuration in `.env`
4. Verify rate limiting cache is working

### Typing Indicators Not Working
1. Check presence channel authentication
2. Verify throttling cache is working (2 seconds)
3. Check frontend is sending typing events correctly

### File Uploads Failing
1. Verify file size limits (5MB images, 10MB files)
2. Check MIME type validation
3. Verify storage disk is configured correctly
4. Check file permissions on storage directory

---

## 📈 Performance Tips

### Optimize Message Loading
```php
// Use cursor pagination (better than offset)
$messages = Message::where('conversation_id', $conversationId)
    ->orderByDesc('id')
    ->cursorPaginate(20);
```

### Cache Online Users
```php
// Cache Pusher presence API response for 30 seconds
$onlineUsers = Cache::remember(
    "conversation:{$conversationId}:online",
    30,
    fn() => $this->fetchPresenceUserIds($conversationId)
);
```

### Eager Load Relationships
```php
// Load sender and conversation in one query
$messages = Message::with(['sender:id,name,avatar', 'conversation:id,is_group,teacher_id'])
    ->where('conversation_id', $conversationId)
    ->get();
```

---

## 🎯 Best Practices

### Frontend
1. **Throttle typing events** to 1 per 2 seconds (client-side)
2. **Show typing indicator** for 3 seconds after last event
3. **Paginate messages** with "Load More" button
4. **Optimistically update UI** before server response
5. **Handle offline state** gracefully (queue messages)

### Backend
1. **Use queue workers** for notifications (don't block requests)
2. **Rate limit notifications** to prevent spam
3. **Soft delete messages** (admin can restore)
4. **Validate MIME types** (not just extensions)
5. **Eager load relationships** to avoid N+1 queries

### Security
1. **Authenticate channels** before granting access
2. **Verify participant** before allowing actions
3. **Validate file uploads** with MIME type checks
4. **Use private storage** for uploaded files
5. **Sanitize message content** to prevent XSS

---

## 📚 Related Files

### Controllers
- `app/Http/Controllers/Api/ChatController.php`

### Models
- `app/Models/Conversation.php`
- `app/Models/Message.php`
- `app/Models/ClassMember.php`

### Events
- `app/Events/MessageSent.php`
- `app/Events/UserTyping.php`

### Jobs
- `app/Jobs/SendChatNotification.php`

### Requests
- `app/Http/Requests/Api/StartConversationRequest.php`
- `app/Http/Requests/Api/SendMessageRequest.php`
- `app/Http/Requests/Api/AnnouncementStoreRequest.php`
- `app/Http/Requests/Api/MarkConversationReadRequest.php`
- `app/Http/Requests/Api/TypingRequest.php`

### Routes
- `routes/channels.php` - WebSocket channel authentication

---

## ✅ Checklist

- [ ] Pusher configured in `.env`
- [ ] Queue worker running
- [ ] Frontend Echo configured
- [ ] Channel authentication working
- [ ] File uploads tested
- [ ] Real-time messaging tested
- [ ] Typing indicators tested
- [ ] Read receipts tested
- [ ] Notifications tested
- [ ] Message deletion tested
- [ ] Muted students tested
- [ ] Group announcements tested

---

**Need more details?** See `README_SECTION_5.md` for complete documentation.

