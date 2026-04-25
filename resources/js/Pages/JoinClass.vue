<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ inviteCode: String });
const page = usePage();
const user = computed(() => page.props.auth?.user);

const group = ref(null);
const loading = ref(true);
const joining = ref(false);
const joined = ref(false);
const error = ref(null);

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/groups/preview/${props.inviteCode}`);
        group.value = data;
    } catch (e) {
        error.value = 'Class not found or link expired.';
    }
    loading.value = false;
});

const joinClass = async () => {
    if (!user.value) {
        window.location.href = `/login?redirect=/join/${props.inviteCode}`;
        return;
    }
    joining.value = true;
    error.value = null;
    try {
        await axios.post(`/api/groups/join/${props.inviteCode}`);
        joined.value = true;
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to join class.';
    }
    joining.value = false;
};
</script>

<template>
    <div style="min-height: 100vh; background: #FFF8F0; display: flex; align-items: center; justify-content: center; padding: 24px; font-family: Nunito, sans-serif;">

        <div v-if="loading" style="color: #999; font-size: 18px;">Loading...</div>

        <div v-else-if="error && !group" style="text-align: center;">
            <div style="font-size: 64px; margin-bottom: 16px;">😕</div>
            <h1 style="font-family: 'Fredoka One', cursive; font-size: 24px; color: #E8553E;">{{ error }}</h1>
            <Link :href="route('landing')" style="display: inline-block; margin-top: 16px; color: #E8553E;">← Go Home</Link>
        </div>

        <!-- Success -->
        <div v-else-if="joined" style="text-align: center; max-width: 400px;">
            <div style="font-size: 64px; margin-bottom: 16px;">🎉</div>
            <h1 style="font-family: 'Fredoka One', cursive; font-size: 28px; color: #E8553E;">You're in!</h1>
            <p style="color: #777; margin-top: 8px;">You've joined <strong>{{ group.name }}</strong>. You can now chat, receive announcements, and attend sessions.</p>
            <Link :href="route(user?.role === 'student' ? 'student.chat' : 'teacher.chat')"
                style="display: inline-block; margin-top: 24px; padding: 14px 32px; background: #E8553E; color: #fff; border-radius: 30px; text-decoration: none; font-weight: 700; font-size: 16px;">
                Go to Chat →
            </Link>
        </div>

        <!-- Join preview -->
        <div v-else style="background: #fff; border-radius: 24px; padding: 40px 32px; max-width: 440px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,0.08); text-align: center;">
            <div style="font-size: 48px; margin-bottom: 12px;">🏫</div>
            <h1 style="font-family: 'Fredoka One', cursive; font-size: 26px; color: #E8553E; margin-bottom: 16px;">Join a Class</h1>

            <div style="background: #FFF8F0; border-radius: 14px; padding: 20px; margin-bottom: 20px; text-align: left;">
                <div style="font-weight: 700; font-size: 20px; color: #333; margin-bottom: 8px;">{{ group.name }}</div>
                <div style="font-size: 14px; color: #777; line-height: 1.8;">
                    📚 {{ group.subject }}<br>
                    👨‍🏫 {{ group.teacher?.name }}<br>
                    👥 {{ group.student_count }} / {{ group.max_students }} students
                </div>
                <p v-if="group.description" style="margin-top: 8px; font-size: 14px; color: #555;">{{ group.description }}</p>
            </div>

            <div v-if="error" style="color: #C62828; font-size: 14px; margin-bottom: 12px;">{{ error }}</div>

            <button @click="joinClass" :disabled="joining"
                style="width: 100%; padding: 16px; border: none; border-radius: 30px; background: #E8553E; color: #fff; font-size: 18px; font-weight: 700; cursor: pointer;"
                :style="{ opacity: joining ? 0.7 : 1 }">
                {{ joining ? 'Joining...' : (user ? 'Join Class' : 'Login to Join') }}
            </button>

            <p v-if="group.student_count >= group.max_students" style="color: #EF5350; font-size: 14px; margin-top: 12px;">This class is full.</p>
        </div>
    </div>
</template>
