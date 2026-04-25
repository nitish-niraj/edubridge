<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({ groupId: Number });

const group = ref(null);
const loading = ref(true);
const addEmail = ref('');
const addError = ref(null);
const addSuccess = ref(null);
const copied = ref(false);

const fetchGroup = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/groups/${props.groupId}`);
        group.value = data;
    } catch (e) { console.error(e); }
    loading.value = false;
};

onMounted(fetchGroup);

const inviteLink = computed(() => group.value ? `${window.location.origin}/join/${group.value.invite_code}` : '');

const copyLink = () => {
    navigator.clipboard.writeText(inviteLink.value);
    copied.value = true;
    setTimeout(() => copied.value = false, 2000);
};

const addStudent = async () => {
    addError.value = null;
    addSuccess.value = null;
    try {
        const { data } = await axios.post(`/api/groups/${props.groupId}/members`, { email: addEmail.value });
        addSuccess.value = data.message;
        addEmail.value = '';
        fetchGroup();
    } catch (e) {
        addError.value = e.response?.data?.message || 'Failed to add student.';
    }
};

const removeMember = async (userId) => {
    if (!confirm('Remove this student from the class?')) return;
    await axios.delete(`/api/groups/${props.groupId}/members/${userId}`);
    fetchGroup();
};

const toggleMute = async (userId) => {
    await axios.patch(`/api/groups/${props.groupId}/members/${userId}/mute`);
    fetchGroup();
};

const toggleDraw = async (userId) => {
    await axios.patch(`/api/groups/${props.groupId}/members/${userId}/draw`);
    fetchGroup();
};

const startingSession = ref(false);
const startGroupSession = async () => {
    startingSession.value = true;
    try {
        const { data } = await axios.post(`/api/video-sessions/group/${props.groupId}/start`);
        window.location.href = `/group-session/${props.groupId}`;
    } catch (e) {
        alert(e.response?.data?.message || 'Failed to start session.');
    } finally {
        startingSession.value = false;
    }
};

const students = computed(() => group.value?.active_class_members?.filter(m => m.role === 'student') || []);
</script>

<template>
    <TeacherLayout>
        <div style="padding: 32px 40px; max-width: 900px;">
            <div v-if="loading" style="text-align: center; padding: 60px; color: #999;">Loading...</div>

            <template v-else-if="group">
                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
                    <div>
                        <h1 style="font-family: 'Fredoka One', cursive; font-size: 26px; color: #E8553E; margin-bottom: 4px;">{{ group.title }}</h1>
                        <span style="display: inline-block; background: #FFF3EF; color: #E8553E; padding: 4px 14px; border-radius: 10px; font-size: 14px; font-weight: 600;">{{ group.subject }}</span>
                        <span style="margin-left: 8px; color: #999; font-size: 14px;">{{ students.length }} / {{ group.max_students }} students</span>
                    </div>
                    <button @click="startGroupSession" :disabled="startingSession"
                        style="padding: 14px 28px; background: #E8553E; color: #fff; border: none; border-radius: 14px; font-family: 'Fredoka One', cursive; font-size: 16px; font-weight: bold; cursor: pointer;">
                        {{ startingSession ? 'Starting...' : '▶ Start Group Session' }}
                    </button>
                </div>

                <div style="background:#FFF8F0; border:1px solid #F0E8E0; border-radius:14px; padding:16px 20px; margin-bottom:24px;">
                    <h2 style="font-family:'Fredoka One', cursive; font-size:18px; color:#E8553E; margin:0 0 8px;">Class control guide</h2>
                    <ul style="margin:0; padding-left:18px; font-family:'Nunito', sans-serif; font-size:14px; line-height:1.65; color:#4B5563;">
                        <li>Share the invite link only with enrolled students to keep the class secure.</li>
                        <li>Use mute controls for classroom discipline during explanations.</li>
                        <li>Enable draw access selectively when collaborative whiteboard work is needed.</li>
                    </ul>
                </div>

                <!-- Invite Link -->
                <div style="background: #FFF3EF; border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <span style="font-family: 'Nunito', sans-serif; font-size: 14px; color: #555;">Invite Link:</span>
                    <code style="flex: 1; font-size: 14px; color: #333; word-break: break-all;">{{ inviteLink }}</code>
                    <button @click="copyLink"
                        style="padding: 8px 20px; background: #E8553E; color: #fff; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: bold;">
                        {{ copied ? '✓ Copied' : '📋 Copy' }}
                    </button>
                </div>

                <!-- Add Student by Email -->
                <div style="margin-bottom: 24px;">
                    <h3 style="font-family: 'Fredoka One', cursive; font-size: 18px; color: #333; margin-bottom: 12px;">Add Student by Email</h3>
                    <p style="margin:0 0 10px; font-family:'Nunito', sans-serif; font-size:14px; color:#6B7280; line-height:1.55;">
                        Use this when a student cannot join by invite link. The email must match an existing student account.
                    </p>
                    <div style="display: flex; gap: 12px;">
                        <input v-model="addEmail" type="email" placeholder="student@email.com"
                            @keyup.enter="addStudent"
                            style="flex: 1; height: 48px; padding: 0 16px; border: 2px solid #F0E8E0; border-radius: 12px; font-size: 15px;">
                        <button @click="addStudent" :disabled="!addEmail"
                            style="padding: 0 24px; height: 48px; background: #E8553E; color: #fff; border: none; border-radius: 12px; cursor: pointer; font-weight: bold; font-size: 15px;"
                            :style="{ opacity: !addEmail ? 0.5 : 1 }">
                            Add
                        </button>
                    </div>
                    <p v-if="addError" style="color: #C62828; font-size: 14px; margin-top: 8px;">{{ addError }}</p>
                    <p v-if="addSuccess" style="color: #2E7D32; font-size: 14px; margin-top: 8px;">{{ addSuccess }}</p>
                </div>

                <!-- Members Table -->
                <h3 style="font-family: 'Fredoka One', cursive; font-size: 18px; color: #333; margin-bottom: 12px;">Class Members ({{ students.length }})</h3>
                <div v-if="students.length" style="border-radius: 12px; overflow: hidden; border: 1px solid #E0E0E0;">
                    <table style="width: 100%; border-collapse: collapse; font-family: 'Nunito', sans-serif;">
                        <thead>
                            <tr style="background: #FFF3EF;">
                                <th style="text-align: left; padding: 14px 20px; font-family: 'Fredoka One', cursive; font-size: 14px; color: #E8553E;">Student</th>
                                <th style="text-align: left; padding: 14px 20px; font-family: 'Fredoka One', cursive; font-size: 14px; color: #E8553E;">Email</th>
                                <th style="text-align: center; padding: 14px 20px; font-family: 'Fredoka One', cursive; font-size: 14px; color: #E8553E;">Muted</th>
                                <th style="text-align: center; padding: 14px 20px; font-family: 'Fredoka One', cursive; font-size: 14px; color: #E8553E;">Draw</th>
                                <th style="text-align: center; padding: 14px 20px; font-family: 'Fredoka One', cursive; font-size: 14px; color: #E8553E;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(m, i) in students" :key="m.id" :style="{ background: i % 2 === 0 ? '#fff' : '#F5FAF7' }">
                                <td style="padding: 12px 20px; font-size: 15px;">{{ m.user?.name }}</td>
                                <td style="padding: 12px 20px; font-size: 14px; color: #777;">{{ m.user?.email }}</td>
                                <td style="padding: 12px 20px; text-align: center;">
                                    <button @click="toggleMute(m.user_id)"
                                        :style="{ background: m.is_muted ? '#EF5350' : '#E0E0E0', color: '#fff', border: 'none', borderRadius: '20px', padding: '4px 14px', fontSize: '12px', cursor: 'pointer', fontWeight: 'bold' }">
                                        {{ m.is_muted ? '🔇 Muted' : '🔊 Active' }}
                                    </button>
                                </td>
                                <td style="padding: 12px 20px; text-align: center;">
                                    <button @click="toggleDraw(m.user_id)"
                                        :style="{ background: m.can_draw ? '#42A5F5' : '#E0E0E0', color: '#fff', border: 'none', borderRadius: '20px', padding: '4px 14px', fontSize: '12px', cursor: 'pointer', fontWeight: 'bold' }">
                                        {{ m.can_draw ? '✏️ Yes' : '🚫 No' }}
                                    </button>
                                </td>
                                <td style="padding: 12px 20px; text-align: center;">
                                    <button @click="removeMember(m.user_id)"
                                        style="background: none; border: none; color: #EF5350; cursor: pointer; font-size: 13px; text-decoration: underline;">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else style="color: #999; font-size: 14px;">No students yet. Share the invite link or add by email above.</p>
            </template>
        </div>
    </TeacherLayout>
</template>
