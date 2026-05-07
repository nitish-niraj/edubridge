# Section 6 — Frontend Implementation Guide

## 🚀 Quick Start

### Prerequisites
```bash
# Install required packages
npm install @excalidraw/excalidraw @daily-co/daily-js
```

### API Endpoints Reference

#### Group Management
```javascript
// Create group
POST /api/groups
Body: { name, subject, description?, max_students? }
Response: { conversation, invite_code, invite_link }

// List teacher's groups
GET /api/groups
Response: [{ id, title, subject, student_count, ... }]

// Show group details
GET /api/groups/{id}
Response: { id, title, subject, teacher, activeClassMembers, ... }

// Update group settings
PATCH /api/groups/{id}
Body: { name?, subject?, description?, max_students? }
Response: { message, conversation }

// Preview group (public)
GET /api/groups/preview/{inviteCode}
Response: { id, name, subject, teacher, student_count, max_students }

// Join group via invite code
POST /api/groups/join/{inviteCode}
Response: { message, conversation }

// Teacher adds student by email
POST /api/groups/{groupId}/add-member
Body: { email }
Response: { message, student }

// Remove member
DELETE /api/groups/{groupId}/members/{userId}
Response: { message }

// Toggle mute
PATCH /api/groups/{groupId}/members/{userId}/mute
Response: { is_muted, message }

// Toggle draw permission
PATCH /api/groups/{groupId}/members/{userId}/draw
Response: { can_draw, message }
```

#### Video Sessions
```javascript
// Teacher starts group session
POST /api/video-sessions/group/{conversationId}/start
Response: { token, room_url, room_name, identity, video_session_id }

// Student joins group session
POST /api/video-sessions/group/{conversationId}/join
Response: { token, room_url, room_name, identity, video_session_id }

// Alternative: Get token for group
POST /api/video-sessions/group/{groupId}/token
Response: { token, room_url, room_name, identity, video_session_id }

// End group session (teacher only)
PATCH /api/video-sessions/group/{sessionId}/end
Response: { message, duration_minutes }
```

#### Whiteboard
```javascript
// Sync whiteboard elements
POST /api/video-sessions/{sessionId}/whiteboard
Body: { conversation_id, elements: [...] }
Response: { message: 'Synced.' }
```

#### Recording
```javascript
// Request recording consent
POST /api/video-sessions/{sessionId}/recording/consent
Body: { conversation_id }
Response: { message }

// Get recording download URL
GET /api/recordings/{sessionId}
Response: { url, expires_in }
```

---

## 📦 Component Structure

```
resources/js/
├── Pages/
│   ├── Groups/
│   │   ├── Index.vue              # List all groups
│   │   ├── Create.vue             # Create new group
│   │   ├── Manage.vue             # Manage group members
│   │   ├── Join.vue               # Public join page
│   │   └── VideoSession.vue       # Group video session
│   └── ...
├── Components/
│   ├── Groups/
│   │   ├── GroupCard.vue          # Group list item
│   │   ├── MemberList.vue         # List of members
│   │   ├── InviteCodeDisplay.vue  # Show/copy invite code
│   │   └── JoinSessionBanner.vue  # Session start notification
│   ├── Video/
│   │   ├── DailyVideoFrame.vue    # Daily.co video container
│   │   ├── ParticipantsPanel.vue  # Participants list
│   │   ├── VideoControls.vue      # Mute, camera, screen share
│   │   └── Whiteboard.vue         # Excalidraw whiteboard
│   └── Chat/
│       ├── GroupChatPanel.vue     # Group chat UI
│       └── AnnouncementBanner.vue # Pinned announcement
└── Services/
    ├── groupApi.js                # Group API calls
    ├── videoApi.js                # Video session API calls
    └── pusherService.js           # Real-time events
```

---

## 🎨 Component Examples

### 1. CreateClass.vue (Basic Structure)

```vue
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const form = ref({
  name: '',
  subject: '',
  description: '',
  max_students: 30,
});

const subjects = [
  'Mathematics', 'Physics', 'Chemistry', 'Biology',
  'English', 'History', 'Geography', 'Computer Science',
  'Economics', 'Business Studies', 'Accountancy', 'Other'
];

const errors = ref({});
const loading = ref(false);
const inviteCode = ref(null);

const createGroup = async () => {
  loading.value = true;
  errors.value = {};
  
  try {
    const response = await axios.post('/api/groups', form.value);
    inviteCode.value = response.data.invite_code;
    
    // Show success and redirect
    setTimeout(() => {
      router.visit(`/classes/${response.data.conversation.id}/manage`);
    }, 2000);
  } catch (error) {
    if (error.response?.data?.errors) {
      errors.value = error.response.data.errors;
    }
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Create New Class</h1>
    
    <form @submit.prevent="createGroup" class="space-y-4">
      <!-- Class Name -->
      <div>
        <label class="block text-sm font-medium mb-1">Class Name *</label>
        <input
          v-model="form.name"
          type="text"
          maxlength="150"
          required
          class="w-full px-3 py-2 border rounded-lg"
          placeholder="e.g., Class 12 Math - Batch A"
        />
        <p v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name[0] }}</p>
      </div>

      <!-- Subject -->
      <div>
        <label class="block text-sm font-medium mb-1">Subject *</label>
        <select v-model="form.subject" required class="w-full px-3 py-2 border rounded-lg">
          <option value="">Select subject</option>
          <option v-for="subject in subjects" :key="subject" :value="subject">
            {{ subject }}
          </option>
        </select>
        <p v-if="errors.subject" class="text-red-500 text-sm mt-1">{{ errors.subject[0] }}</p>
      </div>

      <!-- Description -->
      <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea
          v-model="form.description"
          rows="3"
          maxlength="2000"
          class="w-full px-3 py-2 border rounded-lg"
          placeholder="Optional description..."
        />
      </div>

      <!-- Max Students -->
      <div>
        <label class="block text-sm font-medium mb-1">Max Students</label>
        <input
          v-model.number="form.max_students"
          type="number"
          min="2"
          max="50"
          class="w-full px-3 py-2 border rounded-lg"
        />
        <p class="text-sm text-gray-500 mt-1">Between 2 and 50 students</p>
      </div>

      <!-- Submit -->
      <button
        type="submit"
        :disabled="loading"
        class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
      >
        {{ loading ? 'Creating...' : 'Create Class' }}
      </button>
    </form>

    <!-- Success Modal with Invite Code -->
    <div v-if="inviteCode" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
      <div class="bg-white p-6 rounded-lg max-w-md">
        <h2 class="text-xl font-bold mb-4">Class Created! 🎉</h2>
        <p class="mb-4">Share this invite code with your students:</p>
        <div class="bg-gray-100 p-4 rounded text-center">
          <code class="text-2xl font-mono font-bold">{{ inviteCode }}</code>
        </div>
        <button
          @click="copyInviteLink"
          class="w-full mt-4 bg-green-600 text-white py-2 rounded hover:bg-green-700"
        >
          Copy Invite Link
        </button>
      </div>
    </div>
  </div>
</template>
```

### 2. Whiteboard.vue (Excalidraw Integration)

```vue
<script setup>
import { ref, onMounted, watch } from 'vue';
import { Excalidraw } from '@excalidraw/excalidraw';
import axios from 'axios';
import { debounce } from 'lodash';

const props = defineProps({
  sessionId: Number,
  conversationId: Number,
  canDraw: Boolean,
  isTeacher: Boolean,
});

const excalidrawAPI = ref(null);
const elements = ref([]);

// Debounced sync function (150ms)
const syncWhiteboard = debounce(async (newElements) => {
  try {
    await axios.post(`/api/video-sessions/${props.sessionId}/whiteboard`, {
      conversation_id: props.conversationId,
      elements: newElements,
    });
  } catch (error) {
    console.error('Whiteboard sync failed:', error);
  }
}, 150);

// Handle local changes
const handleChange = (elements, appState) => {
  if (props.canDraw || props.isTeacher) {
    syncWhiteboard(elements);
  }
};

// Listen to WhiteboardUpdate events
onMounted(() => {
  window.Echo.private(`conversation.${props.conversationId}`)
    .listen('WhiteboardUpdate', (event) => {
      if (excalidrawAPI.value && event.sender_id !== window.userId) {
        excalidrawAPI.value.updateScene({ elements: event.elements });
      }
    });
});

// Listen to DrawPermissionGranted events
onMounted(() => {
  window.Echo.private(`conversation.${props.conversationId}`)
    .listen('DrawPermissionGranted', (event) => {
      if (event.student_id === window.userId) {
        // Show notification
        if (event.granted) {
          alert('You can now draw on the whiteboard!');
        } else {
          alert('Draw permission revoked.');
        }
      }
    });
});
</script>

<template>
  <div class="whiteboard-container h-full">
    <Excalidraw
      :ref="(api) => excalidrawAPI = api"
      :onChange="handleChange"
      :viewModeEnabled="!canDraw && !isTeacher"
      :zenModeEnabled="false"
      :gridModeEnabled="true"
    />
  </div>
</template>

<style scoped>
.whiteboard-container {
  width: 100%;
  height: 600px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}
</style>
```

### 3. GroupVideoSession.vue (Daily.co Integration)

```vue
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import DailyIframe from '@daily-co/daily-js';
import axios from 'axios';
import Whiteboard from '@/Components/Video/Whiteboard.vue';
import ParticipantsPanel from '@/Components/Video/ParticipantsPanel.vue';

const props = defineProps({
  conversationId: Number,
  isTeacher: Boolean,
});

const callFrame = ref(null);
const sessionId = ref(null);
const roomUrl = ref(null);
const showWhiteboard = ref(false);
const canDraw = ref(false);
const participants = ref([]);

const startSession = async () => {
  try {
    const response = await axios.post(`/api/video-sessions/group/${props.conversationId}/start`);
    sessionId.value = response.data.video_session_id;
    roomUrl.value = response.data.room_url;
    
    // Initialize Daily.co
    callFrame.value = DailyIframe.createFrame({
      showLeaveButton: true,
      iframeStyle: {
        width: '100%',
        height: '600px',
        border: '0',
        borderRadius: '8px',
      },
    });
    
    await callFrame.value.join({
      url: roomUrl.value,
      token: response.data.token,
    });
    
    // Listen to participant events
    callFrame.value.on('participant-joined', handleParticipantJoined);
    callFrame.value.on('participant-left', handleParticipantLeft);
    
  } catch (error) {
    console.error('Failed to start session:', error);
  }
};

const joinSession = async () => {
  try {
    const response = await axios.post(`/api/video-sessions/group/${props.conversationId}/join`);
    sessionId.value = response.data.video_session_id;
    roomUrl.value = response.data.room_url;
    
    callFrame.value = DailyIframe.createFrame({
      showLeaveButton: true,
      iframeStyle: {
        width: '100%',
        height: '600px',
        border: '0',
        borderRadius: '8px',
      },
    });
    
    await callFrame.value.join({
      url: roomUrl.value,
      token: response.data.token,
    });
    
  } catch (error) {
    console.error('Failed to join session:', error);
  }
};

const endSession = async () => {
  if (confirm('Are you sure you want to end this session for everyone?')) {
    try {
      await axios.patch(`/api/video-sessions/group/${sessionId.value}/end`);
      callFrame.value?.leave();
      callFrame.value?.destroy();
    } catch (error) {
      console.error('Failed to end session:', error);
    }
  }
};

const toggleWhiteboard = () => {
  showWhiteboard.value = !showWhiteboard.value;
};

onMounted(() => {
  if (props.isTeacher) {
    startSession();
  } else {
    joinSession();
  }
});

onUnmounted(() => {
  callFrame.value?.leave();
  callFrame.value?.destroy();
});
</script>

<template>
  <div class="video-session-container p-6">
    <div class="flex gap-4">
      <!-- Video Frame -->
      <div class="flex-1">
        <div id="daily-video-container"></div>
        
        <!-- Controls -->
        <div class="mt-4 flex gap-2">
          <button
            @click="toggleWhiteboard"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            {{ showWhiteboard ? 'Hide' : 'Show' }} Whiteboard
          </button>
          
          <button
            v-if="isTeacher"
            @click="endSession"
            class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
          >
            End Session
          </button>
        </div>
        
        <!-- Whiteboard -->
        <div v-if="showWhiteboard" class="mt-4">
          <Whiteboard
            :session-id="sessionId"
            :conversation-id="conversationId"
            :can-draw="canDraw"
            :is-teacher="isTeacher"
          />
        </div>
      </div>
      
      <!-- Participants Panel -->
      <ParticipantsPanel
        :conversation-id="conversationId"
        :is-teacher="isTeacher"
        :participants="participants"
      />
    </div>
  </div>
</template>
```

### 4. JoinSessionBanner.vue (Real-time Notification)

```vue
<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  conversationId: Number,
});

const showBanner = ref(false);
const teacherName = ref('');
const sessionId = ref(null);

onMounted(() => {
  window.Echo.private(`conversation.${props.conversationId}`)
    .listen('GroupSessionStarted', (event) => {
      showBanner.value = true;
      teacherName.value = event.teacher_name;
      sessionId.value = event.booking_id;
    });
});

const joinSession = () => {
  router.visit(`/classes/${props.conversationId}/session`);
};

const dismiss = () => {
  showBanner.value = false;
};
</script>

<template>
  <div
    v-if="showBanner"
    class="fixed top-4 right-4 bg-green-500 text-white p-4 rounded-lg shadow-lg z-50 max-w-md"
  >
    <div class="flex items-center justify-between">
      <div>
        <p class="font-bold">Class Session Started!</p>
        <p class="text-sm">{{ teacherName }} has started a live session</p>
      </div>
      <button @click="dismiss" class="ml-4 text-white hover:text-gray-200">
        ✕
      </button>
    </div>
    <button
      @click="joinSession"
      class="mt-3 w-full bg-white text-green-600 py-2 rounded font-bold hover:bg-gray-100"
    >
      Join Now
    </button>
  </div>
</template>
```

---

## 🔌 Pusher Event Listeners

### Setup Echo (in app.js or bootstrap.js)

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: true,
  authEndpoint: '/broadcasting/auth',
  auth: {
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
  },
});
```

### Event Listeners

```javascript
// Listen to GroupSessionStarted
Echo.private(`conversation.${conversationId}`)
  .listen('GroupSessionStarted', (event) => {
    console.log('Session started:', event);
    // event.conversation_id, event.teacher_name, event.booking_id, event.room_name
  });

// Listen to DrawPermissionGranted
Echo.private(`conversation.${conversationId}`)
  .listen('DrawPermissionGranted', (event) => {
    console.log('Draw permission changed:', event);
    // event.student_id, event.granted
  });

// Listen to WhiteboardUpdate
Echo.private(`conversation.${conversationId}`)
  .listen('WhiteboardUpdate', (event) => {
    console.log('Whiteboard updated:', event);
    // event.elements, event.sender_id
  });

// Listen to RecordingConsentRequest
Echo.private(`conversation.${conversationId}`)
  .listen('RecordingConsentRequest', (event) => {
    console.log('Recording consent requested:', event);
    // Show consent modal
  });
```

---

## 🧪 Testing Checklist

### Unit Tests
- [ ] CreateClass form validation
- [ ] Whiteboard component rendering
- [ ] Participants panel display
- [ ] Join banner visibility

### Integration Tests
- [ ] Create group → receive invite code
- [ ] Join group → become member
- [ ] Start session → receive token
- [ ] Draw on whiteboard → sync to others
- [ ] Grant permission → enable drawing

### E2E Tests
- [ ] Teacher creates group
- [ ] Student joins via invite link
- [ ] Teacher starts session
- [ ] Student sees banner and joins
- [ ] Teacher grants draw permission
- [ ] Student draws on whiteboard
- [ ] Teacher ends session

---

## 📚 Additional Resources

### Daily.co Documentation
- [Getting Started](https://docs.daily.co/guides/products/prebuilt)
- [Daily.js Reference](https://docs.daily.co/reference/daily-js)
- [React/Vue Integration](https://docs.daily.co/guides/frameworks)

### Excalidraw Documentation
- [Excalidraw GitHub](https://github.com/excalidraw/excalidraw)
- [Integration Guide](https://docs.excalidraw.com/docs/@excalidraw/excalidraw/integration)
- [API Reference](https://docs.excalidraw.com/docs/@excalidraw/excalidraw/api)

### Laravel Echo Documentation
- [Broadcasting](https://laravel.com/docs/10.x/broadcasting)
- [Laravel Echo](https://laravel.com/docs/10.x/broadcasting#client-side-installation)
- [Pusher Channels](https://pusher.com/docs/channels)

---

## 🎯 Quick Win: Minimal Viable Implementation

If you need to get something working quickly, implement in this order:

1. **CreateClass.vue** - Let teachers create groups (1-2 hours)
2. **ClassList.vue** - Show list of groups (1 hour)
3. **Join.vue** - Let students join via invite link (1 hour)
4. **GroupVideoSession.vue** - Basic video session with Daily.co (2-3 hours)
5. **JoinSessionBanner.vue** - Show notification when session starts (1 hour)

**Total: 6-8 hours for MVP**

Then add:
- Whiteboard (3-4 hours)
- Participants panel (2-3 hours)
- Group chat (2-3 hours)
- Polish and testing (4-6 hours)

**Total: 17-24 hours for complete implementation**
