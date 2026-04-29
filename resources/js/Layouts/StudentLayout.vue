<script setup>
import axios from 'axios';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import PortalExperience from '@/Components/PortalExperience.vue';
import { useScrollReveal } from '@/composables/useScrollReveal';

defineOptions({ inheritAttrs: false });

const page = usePage();
const { props } = page;
const user = computed(() => props.auth?.user);
const logoutProcessing = ref(false);

useScrollReveal();

onMounted(() => {
    document.body.setAttribute('data-portal', 'student');
});

const navItems = [
    { label: 'Dashboard', route: 'student.dashboard', icon: '🏠', activePrefix: '/student/dashboard' },
    { label: 'Find Teachers', route: 'teachers.index', icon: '🔍', activePrefix: '/teachers' },
    { label: 'Saved', route: 'students.saved-teachers', icon: '❤️', activePrefix: '/students/saved-teachers' },
    { label: 'My Bookings', route: 'student.bookings', icon: '📅', activePrefix: '/student/bookings' },
    { label: 'Messages', route: 'student.chat', icon: '💬', activePrefix: '/student/chat' },
    { label: 'Profile', route: 'student.profile', icon: '👤', activePrefix: '/student/profile' },
    { label: 'Settings', route: 'student.settings', icon: '⚙️', activePrefix: '/student/settings' },
];

const isActive = (item) => {
    if (item.activePrefix === '/teachers') {
        return page.url.startsWith('/teachers');
    }

    return page.url.startsWith(item.activePrefix);
};

const logout = async () => {
    if (logoutProcessing.value) {
        return;
    }

    logoutProcessing.value = true;

    try {
        await axios.post(route('logout'));
        window.location.replace('/');
    } catch (error) {
        logoutProcessing.value = false;
        window.alert(error?.response?.data?.message || 'Unable to log out right now. Please try again.');
    }
};
</script>

<template>
    <div class="flex min-h-screen" style="background: var(--color-bg, #FFF8F0);">
        <!-- Sidebar -->
        <aside class="w-64 flex-shrink-0 flex flex-col" style="background: #FFF8F0; border-right: 1px solid #F0DDD5;">
            <!-- Logo -->
            <div class="p-6 flex items-center gap-3">
                <img src="/images/logo.png" alt="EduBridge Logo" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                <span style="font-family: 'Fredoka One', cursive; font-size: 28px; color: #E8553E;">EduBridge</span>
            </div>
            <!-- Nav -->
            <nav class="flex-1 px-4 pb-4">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="route(item.route)"
                    class="flex items-center gap-3 px-4 py-3 my-1 rounded-xl transition-all"
                    :style="isActive(item)
                        ? 'background: #FFF3EF; border-left: 4px solid #E8553E; color: #E8553E;'
                        : 'color: #555; border-left: 4px solid transparent;'"
                >
                    <span style="font-size: 20px;">{{ item.icon }}</span>
                    <span style="font-family: Nunito, sans-serif; font-weight: 600;">{{ item.label }}</span>
                </Link>
            </nav>
            <!-- User info + logout -->
            <div class="p-4 border-t border-orange-100">
                <template v-if="user">
                    <div style="font-family: Nunito, sans-serif; font-size: 14px; color: #888;">
                        {{ user?.name }}
                    </div>
                    <button
                        type="button"
                        :disabled="logoutProcessing"
                        @click="logout"
                        class="w-full mt-2 text-left px-3 py-2 rounded-lg text-sm"
                        style="color: #E8553E; font-family: Nunito, sans-serif;"
                    >
                        {{ logoutProcessing ? 'Logging out...' : 'Logout' }}
                    </button>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="w-full mt-2 text-left px-3 py-2 rounded-lg text-sm" style="color:#E8553E; font-family:Nunito,sans-serif;">
                        Login
                    </Link>
                    <Link :href="route('student.register')" class="w-full mt-1 text-left px-3 py-2 rounded-lg text-sm" style="color:#E8553E; font-family:Nunito,sans-serif;">
                        Register
                    </Link>
                </template>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 overflow-auto">
            <slot />
        </main>

        <PortalExperience />
    </div>
</template>
