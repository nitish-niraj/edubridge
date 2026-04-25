<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { AcademicCapIcon, ChatBubbleLeftRightIcon, CalendarDaysIcon, MagnifyingGlassIcon, UserCircleIcon } from '@heroicons/vue/24/outline';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ inheritAttrs: false });

const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);

const firstName = computed(() => {
    const full = user.value?.name || 'Learner';
    return full.split(' ')[0] || full;
});

const quickActions = [
    {
        label: 'Find Teachers',
        sub: 'Discover verified mentors by subject and availability.',
        route: 'teachers.index',
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
                        <Link :href="route('teachers.index')" class="primary-btn">Find teachers</Link>
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