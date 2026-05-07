<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import axios from 'axios';
import { AcademicCapIcon, ChatBubbleLeftRightIcon, CalendarDaysIcon, MagnifyingGlassIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    user: Object,
    profile: Object,
    announcements: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const activeGroupSessions = ref([]);
const closedAnnouncements = ref([]);

const user = computed(() => props.user ?? page.props.auth?.user ?? null);

const filteredAnnouncements = computed(() => {
    return props.announcements.filter(a => !closedAnnouncements.value.includes(a.id));
});

const closeAnnouncement = (id) => {
    closedAnnouncements.value.push(id);
    try {
        const saved = JSON.parse(localStorage.getItem('closed_announcements') || '[]');
        if (!saved.includes(id)) {
            saved.push(id);
            localStorage.setItem('closed_announcements', JSON.stringify(saved));
        }
    } catch (e) {
        console.error('Failed to save closed announcement', e);
    }
};

const fetchActiveSessions = async () => {
    try {
        const { data } = await axios.get('/api/conversations');
        // Find conversations with active video sessions
        // This is a bit simplified, ideally the backend returns active sessions directly
        const groupsWithSessions = data.data.filter(c => c.is_group && c.active_session_id);
        activeGroupSessions.value = groupsWithSessions;
    } catch (e) { /* noop */ }
};

onMounted(() => {
    fetchActiveSessions();

    try {
        closedAnnouncements.value = JSON.parse(localStorage.getItem('closed_announcements') || '[]');
    } catch (e) {
        closedAnnouncements.value = [];
    }

    if (window.Echo) {
        window.Echo.private(`App.Models.User.${user.value.id}`)
            .listen('.InAppNotificationCreated', (e) => {
                if (e.notification.type === 'group_session_started') {
                    fetchActiveSessions();
                }
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave(`App.Models.User.${user.value.id}`);
    }
});

const firstName = computed(() => {
    const full = user.value?.name || 'Learner';
    return full.split(' ')[0] || full;
});

const quickActions = [
    {
        label: 'Find Teachers',
        sub: 'Discover verified mentors by subject and availability.',
        route: 'student.teachers',
        cta: 'Explore now',
        icon: MagnifyingGlassIcon,
    },
    {
        label: 'Bookings',
        sub: 'Track upcoming classes and join sessions on time.',
        route: 'student.bookings',
        cta: 'View bookings',
        icon: CalendarDaysIcon,
    },
    {
        label: 'Messages',
        sub: 'Chat with teachers before and after each class.',
        route: 'student.chat',
        cta: 'Open chat',
        icon: ChatBubbleLeftRightIcon,
    },
    {
        label: 'Profile',
        sub: 'Keep your goals and learning preferences up to date.',
        route: 'student.profile',
        cta: 'Edit profile',
        icon: UserCircleIcon,
    },
];
</script>

<template>
    <Head title="Student Dashboard" />

    <StudentLayout>
        <div class="student-dashboard-page">
            <div class="dashboard-shell">
                <!-- Group Session Banner -->
                <transition-group name="banner-pop">
                    <div v-for="session in activeGroupSessions" :key="session.id" class="session-banner">
                        <div class="banner-content">
                            <span class="pulse-dot"></span>
                            <p><strong>{{ session.display_name }}</strong> session is live! Join your classmates now.</p>
                        </div>
                        <Link :href="`/group-session/${session.id}`" class="join-now-btn">Join Session</Link>
                    </div>
                </transition-group>

                <!-- Admin Announcements -->
                <section v-if="filteredAnnouncements.length" class="announcements-section">
                    <div v-for="announcement in filteredAnnouncements" :key="announcement.id" class="announcement-card">
                        <div class="announcement-content">
                            <span class="announcement-tag">ANNOUNCEMENT</span>
                            <button type="button" class="close-announcement" @click="closeAnnouncement(announcement.id)" aria-label="Close">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <h3>{{ announcement.title }}</h3>
                            <p>{{ announcement.message }}</p>
                        </div>
                    </div>
                </section>

                <section class="hero-card">
                    <div class="hero-badge">
                        <AcademicCapIcon class="h-5 w-5" aria-hidden="true" />
                        <span>Student Portal</span>
                    </div>

                    <h1>Welcome back, {{ firstName }}.</h1>
                    <p>
                        Your learning workspace is ready. Use the same streamlined experience you see in teacher discovery,
                        now across dashboard, bookings, chat, and profile.
                    </p>

                    <div class="hero-ctas">
                        <Link :href="route('student.teachers')" class="primary-btn">Find teachers</Link>
                        <Link :href="route('student.bookings')" class="ghost-btn">View bookings</Link>
                    </div>
                </section>

                <section class="quick-grid">
                    <article v-for="action in quickActions" :key="action.route" class="quick-card">
                        <component :is="action.icon" class="quick-icon" aria-hidden="true" />
                        <h2>{{ action.label }}</h2>
                        <p>{{ action.sub }}</p>
                        <Link :href="route(action.route)" class="card-link">{{ action.cta }}</Link>
                    </article>
                </section>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.student-dashboard-page {
    min-height: 100vh;
    padding: 24px;
    background:
        radial-gradient(1200px 500px at 85% -10%, rgba(245, 197, 24, 0.18), transparent 60%),
        radial-gradient(800px 420px at -5% 20%, rgba(232, 85, 62, 0.14), transparent 55%),
        #fff8f0;
}

.dashboard-shell {
    max-width: 1100px;
    margin: 0 auto;
}

.session-banner {
    background: #e8553e;
    color: #fff;
    border-radius: 16px;
    padding: 16px 24px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 10px 20px rgba(232, 85, 62, 0.2);
    font-family: Nunito, sans-serif;
}

.banner-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pulse-dot {
    width: 10px;
    height: 10px;
    background: #fff;
    border-radius: 50%;
    animation: banner-pulse 1.5s infinite;
}

@keyframes banner-pulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7); }
    70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(255, 255, 255, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
}

.session-banner p {
    margin: 0;
    font-size: 16px;
}

.join-now-btn {
    background: #fff;
    color: #e8553e;
    padding: 10px 24px;
    border-radius: 999px;
    text-decoration: none;
    font-weight: 800;
    font-size: 14px;
    transition: transform 0.2s;
}

.join-now-btn:hover {
    transform: scale(1.05);
}

.banner-pop-enter-active, .banner-pop-leave-active {
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.banner-pop-enter-from, .banner-pop-leave-to {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
}

.announcements-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.announcement-card {
    background: linear-gradient(135deg, #2D2D2D 0%, #1A1A1A 100%);
    color: #fff;
    border-radius: 16px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.announcement-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 150px;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(232, 85, 62, 0.1));
    pointer-events: none;
}

.close-announcement {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: rgba(255, 255, 255, 0.8);
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    z-index: 10;
}

.close-announcement:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
}

.announcement-tag {
    background: #E8553E;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 4px 8px;
    border-radius: 4px;
    letter-spacing: 0.05em;
    font-family: Nunito, sans-serif;
}

.announcement-content h3 {
    margin: 10px 0 6px;
    font-size: 18px;
    color: #fff;
    font-family: 'Fredoka One', cursive;
}

.announcement-content p {
    margin: 0;
    font-size: 14px;
    color: #E2E8F0;
    line-height: 1.5;
    font-family: Nunito, sans-serif;
}

.hero-card {
    border-radius: 20px;
    padding: 24px;
    background: linear-gradient(135deg, #ffffff 0%, #fff7f2 100%);
    box-shadow: 0 10px 30px rgba(232, 85, 62, 0.12);
    border: 1px solid #f3ddd4;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    background: #fff3ef;
    color: #e8553e;
    font-family: Nunito, sans-serif;
    font-weight: 700;
    font-size: 13px;
    padding: 7px 12px;
}

.hero-card h1 {
    margin: 12px 0 10px;
    color: #2d2d2d;
    font-family: 'Fredoka One', cursive;
    font-size: clamp(27px, 3.8vw, 36px);
    line-height: 1.2;
}

.hero-card p {
    margin: 0;
    max-width: 760px;
    color: #54606f;
    font-family: Nunito, sans-serif;
    font-size: 16px;
    line-height: 1.65;
}

.hero-ctas {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 16px;
}

.primary-btn,
.ghost-btn,
.card-link {
    text-decoration: none;
    border-radius: 999px;
    font-family: Nunito, sans-serif;
    font-weight: 700;
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.primary-btn {
    background: #e8553e;
    color: #fff;
    padding: 12px 18px;
    box-shadow: 0 8px 18px rgba(232, 85, 62, 0.24);
}

.ghost-btn {
    background: #ffffff;
    color: #e8553e;
    border: 1px solid #f1d9cf;
    padding: 11px 17px;
}

.quick-grid {
    margin-top: 16px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}

.quick-card {
    background: #fff;
    border: 1px solid #f3e5dd;
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 8px 24px rgba(17, 24, 39, 0.06);
}

.quick-icon {
    width: 26px;
    height: 26px;
    color: #e8553e;
}

.quick-card h2 {
    margin: 10px 0 8px;
    color: #2d2d2d;
    font-family: Nunito, sans-serif;
    font-size: 20px;
    font-weight: 800;
}

.quick-card p {
    margin: 0;
    color: #6b7280;
    font-family: Nunito, sans-serif;
    font-size: 15px;
    line-height: 1.55;
}

.card-link {
    display: inline-flex;
    margin-top: 12px;
    background: #fff3ef;
    color: #e8553e;
    padding: 9px 14px;
    font-size: 14px;
}

.primary-btn:hover,
.ghost-btn:hover,
.card-link:hover {
    transform: translateY(-1px);
}

@media (max-width: 900px) {
    .student-dashboard-page {
        padding: 18px;
    }

    .quick-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .student-dashboard-page {
        padding: 14px;
    }

    .hero-card {
        padding: 18px;
    }

    .hero-card p {
        font-size: 15px;
        line-height: 1.55;
    }

    .primary-btn,
    .ghost-btn {
        width: 100%;
        justify-content: center;
        text-align: center;
    }

    .quick-card {
        padding: 16px;
    }

    .quick-card h2 {
        font-size: 18px;
    }
}
</style>