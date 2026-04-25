<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import {
    BellIcon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    ChevronDownIcon,
    Cog6ToothIcon,
    HomeIcon,
    UserCircleIcon,
    VideoCameraIcon,
} from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    pageTitle: {
        type: String,
        default: '',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const teacherUi = computed(() => page.props.teacher_ui ?? null);
const menuRef = ref(null);
const menuOpen = ref(false);
const TEACHER_CONTRAST_STORAGE_KEY = 'edubridge.teacher.high_contrast';

const navItems = [
    {
        id: 'nav-dashboard',
        label: 'Dashboard',
        route: 'teacher.dashboard',
        icon: HomeIcon,
        activePrefix: '/teacher/dashboard',
    },
    {
        id: 'nav-profile',
        label: 'My Profile',
        route: 'teacher.profile.show',
        icon: UserCircleIcon,
        activePrefix: '/teacher/profile',
    },
    {
        id: 'nav-availability',
        label: 'Availability',
        route: 'teacher.availability',
        icon: CalendarDaysIcon,
        activePrefix: '/teacher/availability',
    },
    {
        id: 'nav-sessions',
        label: 'Sessions',
        route: 'teacher.sessions',
        icon: VideoCameraIcon,
        activePrefix: '/teacher/sessions',
    },
    {
        id: 'nav-messages',
        label: 'Messages',
        route: 'teacher.chat',
        icon: ChatBubbleLeftRightIcon,
        activePrefix: '/teacher/chat',
    },
    {
        id: 'nav-settings',
        label: 'Settings',
        href: '/teacher/settings',
        icon: Cog6ToothIcon,
        activePrefix: '/teacher/settings',
    },
];

const mobileTabItems = [
    navItems[0],
    navItems[2],
    navItems[3],
    navItems[4],
    navItems[5],
];

const currentPath = computed(() => {
    return (page.url || '').split('?')[0] || '';
});

const resolveHref = (item) => {
    if (item.href) return item.href;
    if (item.params) return route(item.route, item.params);
    return route(item.route);
};

const isActive = (item) => currentPath.value.startsWith(item.activePrefix);

const resolvedPageTitle = computed(() => {
    if (props.pageTitle) return props.pageTitle;
    const active = navItems.find((item) => isActive(item));
    return active?.label || 'Teacher Portal';
});

const unreadNotifications = computed(() => {
    const count = Number(page.props.notifications?.unread_count ?? page.props.unread_notifications ?? 0);
    return Number.isFinite(count) ? Math.max(0, count) : 0;
});

const userInitial = computed(() => {
    const value = (user.value?.name || 'T').trim();
    return value.charAt(0).toUpperCase();
});

const fadeKey = computed(() => page.url || currentPath.value || 'teacher-page');

const closeMenu = () => {
    menuOpen.value = false;
};

const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
};

const handleDocumentClick = (event) => {
    if (!menuRef.value?.contains(event.target)) {
        closeMenu();
    }
};

const applyTeacherContrastClass = (enabled) => {
    document.body.classList.toggle('high-contrast', Boolean(enabled));
};

const persistTeacherContrastPreference = (enabled) => {
    try {
        localStorage.setItem(TEACHER_CONTRAST_STORAGE_KEY, enabled ? '1' : '0');
    } catch {
        // Ignore storage failures in private browsing environments.
    }
};

const resolveTeacherContrastPreference = () => {
    const shared = teacherUi.value?.high_contrast;
    if (typeof shared === 'boolean') {
        persistTeacherContrastPreference(shared);
        return shared;
    }

    try {
        return localStorage.getItem(TEACHER_CONTRAST_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
};

const confirmLogout = (event) => {
    if (!window.confirm('Are you sure you want to log out?')) {
        event.preventDefault();
    }
};

watch(
    () => page.url,
    () => {
        closeMenu();
    },
);

watch(
    () => teacherUi.value?.high_contrast,
    (enabled) => {
        if (typeof enabled !== 'boolean') {
            return;
        }

        applyTeacherContrastClass(enabled);
        persistTeacherContrastPreference(enabled);
    }
);

onMounted(() => {
    document.body.setAttribute('data-portal', 'teacher');
    applyTeacherContrastClass(resolveTeacherContrastPreference());
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});
</script>

<template>
    <div class="teacher-shell">
        <aside class="teacher-sidebar" aria-label="Teacher navigation">
            <div class="teacher-brand" style="gap: 10px;">
                <img src="/images/logo.png" alt="EduBridge Logo" style="width: 32px; height: 32px; border-radius: 6px; object-fit: cover;">
                EduBridge
            </div>

            <nav class="teacher-sidebar-nav">
                <Link
                    v-for="item in navItems"
                    :id="item.id"
                    :key="item.id"
                    :href="resolveHref(item)"
                    class="teacher-nav-item"
                    :class="{ 'teacher-nav-item--active': isActive(item) }"
                >
                    <component :is="item.icon" class="teacher-nav-icon" aria-hidden="true" />
                    <span class="teacher-nav-label">{{ item.label }}</span>
                </Link>
            </nav>
        </aside>

        <header class="teacher-header">
            <h1 class="teacher-header-title">{{ resolvedPageTitle }}</h1>

            <div class="teacher-header-actions">
                <Link :href="route('teacher.chat')" class="teacher-notification-button" aria-label="Open notifications">
                    <BellIcon class="teacher-notification-icon" aria-hidden="true" />
                    <span v-if="unreadNotifications > 0" class="teacher-notification-badge">{{ unreadNotifications }}</span>
                </Link>

                <div ref="menuRef" class="teacher-user-menu">
                    <button type="button" class="teacher-user-trigger" @click.stop="toggleMenu">
                        <span class="teacher-avatar">{{ userInitial }}</span>
                        <span class="teacher-user-name">{{ user?.name || 'Teacher' }}</span>
                        <ChevronDownIcon class="teacher-chevron" aria-hidden="true" />
                    </button>

                    <div v-if="menuOpen" class="teacher-user-dropdown">
                        <Link :href="route('teacher.profile.show')" class="teacher-user-dropdown-item">View Profile</Link>
                        <Link :href="route('teacher.profile.step', { step: 1 })" class="teacher-user-dropdown-item">Edit Profile</Link>
                        <Link :href="route('teacher.settings')" class="teacher-user-dropdown-item">
                            Open Settings
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="teacher-user-dropdown-item teacher-user-dropdown-item--danger"
                            @click="confirmLogout"
                        >
                            Log out
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <main class="teacher-main">
            <Transition name="teacher-page-fade" mode="out-in">
                <div :key="fadeKey" class="teacher-page-frame">
                    <slot />
                </div>
            </Transition>
        </main>

        <nav class="teacher-mobile-tabs" aria-label="Teacher quick navigation">
            <Link
                v-for="tab in mobileTabItems"
                :key="`mobile-${tab.id}`"
                :href="resolveHref(tab)"
                class="teacher-mobile-tab"
                :class="{ 'teacher-mobile-tab--active': isActive(tab) }"
            >
                <component :is="tab.icon" class="teacher-mobile-tab-icon" aria-hidden="true" />
                <span class="teacher-mobile-tab-label">{{ tab.label }}</span>
            </Link>
        </nav>
    </div>
</template>

<style scoped>
.teacher-shell {
    min-height: 100vh;
    background: var(--s-cream);
}

.teacher-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    width: 260px;
    background: var(--s-cream-dark);
    border-right: 1px solid var(--s-border);
    z-index: 20;
    display: flex;
    flex-direction: column;
}

.teacher-brand {
    height: 64px;
    display: flex;
    align-items: center;
    padding: 0 18px;
    border-bottom: 1px solid var(--s-border);
    font-family: var(--s-font-display);
    font-size: 24px;
    font-weight: 700;
    color: var(--s-coral);
}

.teacher-sidebar-nav {
    padding: 14px 12px 18px;
    display: grid;
    gap: 8px;
}

.teacher-nav-item {
    min-height: 60px;
    width: 100%;
    border-radius: 8px;
    border-left: 4px solid transparent;
    color: var(--s-coral-dark);
    font-family: var(--s-font-display);
    font-size: 16px;
    font-weight: 700;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 14px;
    background: transparent;
    transition: background-color 150ms ease, border-color 150ms ease;
}

.teacher-nav-item:hover {
    background: #FFF3EF;
}

.teacher-nav-item--active {
    background: #FFE7DD;
    border-left-color: var(--s-coral);
}

.teacher-nav-icon {
    width: 24px;
    height: 24px;
    flex: 0 0 auto;
}

.teacher-nav-label {
    line-height: 1.2;
}

.teacher-header {
    position: fixed;
    top: 0;
    left: 260px;
    right: 0;
    height: 64px;
    background: #ffffff;
    border-bottom: 1px solid var(--s-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    z-index: 25;
}

.teacher-header-title {
    margin: 0;
    font-family: var(--s-font-display);
    font-size: 22px;
    font-weight: 700;
    color: var(--s-text);
}

.teacher-header-actions {
    display: flex;
    align-items: center;
    gap: 14px;
}

.teacher-notification-button {
    width: 44px;
    height: 44px;
    border: 1px solid var(--s-border);
    border-radius: 8px;
    background: #ffffff;
    color: var(--s-coral-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    text-decoration: none;
}

.teacher-notification-button:hover {
    background: var(--s-cream-dark);
}

.teacher-notification-icon {
    width: 24px;
    height: 24px;
}

.teacher-notification-badge {
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #DC2626;
    color: #ffffff;
    font-family: var(--s-font-body);
    font-size: 12px;
    font-weight: 700;
    position: absolute;
    top: -6px;
    right: -8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.teacher-user-menu {
    position: relative;
}

.teacher-user-trigger {
    min-height: 44px;
    border: 1px solid var(--s-border);
    border-radius: 8px;
    background: #ffffff;
    padding: 0 10px 0 8px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.teacher-user-trigger:hover {
    background: var(--s-cream-dark);
}

.teacher-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #FFE7DD;
    color: var(--s-coral-dark);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--s-font-display);
    font-size: 16px;
    font-weight: 700;
}

.teacher-user-name {
    font-family: var(--s-font-body);
    font-size: 16px;
    color: var(--s-text);
    font-weight: 700;
}

.teacher-chevron {
    width: 18px;
    height: 18px;
    color: var(--s-text-muted);
}

.teacher-user-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 220px;
    border: 1px solid var(--s-border);
    border-radius: 8px;
    background: #ffffff;
    overflow: hidden;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.08);
}

.teacher-user-dropdown-item {
    width: 100%;
    min-height: 48px;
    border: none;
    border-bottom: 1px solid var(--s-border);
    background: #ffffff;
    color: var(--s-text);
    font-family: var(--s-font-body);
    font-size: 16px;
    text-align: left;
    padding: 0 14px;
    text-decoration: none;
    display: flex;
    align-items: center;
    cursor: pointer;
}

.teacher-user-dropdown-item:last-child {
    border-bottom: none;
}

.teacher-user-dropdown-item:hover {
    background: var(--s-cream-dark);
}

.teacher-user-dropdown-item--danger {
    color: #DC2626;
}

.teacher-main {
    margin-left: 260px;
    padding-top: 64px;
    min-height: 100vh;
    background: var(--s-cream);
}

.teacher-page-frame {
    padding: 24px;
}

.teacher-mobile-tabs {
    display: none;
}

.teacher-page-fade-enter-active,
.teacher-page-fade-leave-active {
    transition: opacity 200ms ease;
}

.teacher-page-fade-enter-from,
.teacher-page-fade-leave-to {
    opacity: 0;
}

:deep(.teacher-btn) {
    min-height: 52px;
    min-width: 160px;
    border-radius: 10px;
    border: 2px solid transparent;
    cursor: pointer;
    font-size: 18px;
    font-weight: 700;
    font-family: var(--s-font-display);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 0 18px;
    transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
}

:deep(.teacher-btn:disabled) {
    opacity: 0.8;
    cursor: not-allowed;
}

:deep(.teacher-btn--primary) {
    background: var(--s-coral);
    color: #ffffff;
}

:deep(.teacher-btn--primary:hover) {
    background: var(--s-coral-dark);
}

:deep(.teacher-btn--primary:active) {
    background: #B53A2D;
}

:deep(.teacher-btn--secondary) {
    background: #ffffff;
    border-color: var(--s-coral);
    color: var(--s-coral);
}

:deep(.teacher-btn--secondary:hover) {
    background: var(--s-cream-dark);
}

:deep(.teacher-btn--secondary:active) {
    background: #FFF3EF;
}

:deep(.teacher-btn--destructive) {
    background: #DC2626;
    color: #ffffff;
}

:deep(.teacher-btn--destructive:hover) {
    background: #B91C1C;
}

:deep(.teacher-btn--destructive:active) {
    background: #991B1B;
}

:deep(.teacher-btn__spinner) {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.35);
    border-top-color: #ffffff;
    animation: teacher-button-spin 0.8s linear infinite;
    flex: 0 0 auto;
}

:deep(.teacher-btn--secondary .teacher-btn__spinner) {
    border-color: rgba(61, 107, 79, 0.25);
    border-top-color: var(--s-coral);
}

:deep(.teacher-field) {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

:deep(.teacher-label) {
    margin: 0;
    font-family: var(--s-font-display);
    font-size: 18px;
    font-weight: 700;
    color: var(--s-text);
}

:deep(.teacher-required) {
    color: var(--s-coral);
}

:deep(.teacher-input),
:deep(.teacher-select),
:deep(.teacher-textarea) {
    width: 100%;
    border: 2px solid var(--s-border);
    border-radius: 8px;
    font-family: var(--s-font-body);
    font-size: 18px;
    color: var(--s-text);
    background: #ffffff;
    padding: 0 16px;
    outline: none;
}

:deep(.teacher-input),
:deep(.teacher-select) {
    height: 56px;
}

:deep(.teacher-textarea) {
    min-height: 120px;
    resize: vertical;
    padding: 12px 16px;
}

:deep(.teacher-select) {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%23E8553E' stroke-width='2'%3E%3Cpath d='M6 8l4 4 4-4'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    background-size: 16px;
    padding-right: 44px;
}

:deep(.teacher-input::placeholder),
:deep(.teacher-select::placeholder),
:deep(.teacher-textarea::placeholder) {
    color: var(--s-text-muted);
    font-size: 16px;
}

:deep(.teacher-input:focus),
:deep(.teacher-select:focus),
:deep(.teacher-textarea:focus) {
    border-color: var(--s-coral);
    background: #FFF3EF;
}

:deep(.teacher-input--error),
:deep(.teacher-select--error),
:deep(.teacher-textarea--error) {
    border-color: #DC2626;
}

:deep(.teacher-field-error) {
    margin: 0;
    font-family: var(--s-font-body);
    font-size: 16px;
    color: #DC2626;
}

:deep(.teacher-checkbox-row) {
    display: flex;
    align-items: center;
    gap: 10px;
}

:deep(.teacher-checkbox) {
    width: 24px;
    height: 24px;
    accent-color: var(--s-coral);
}

:deep(.teacher-checkbox-label) {
    font-family: var(--s-font-body);
    font-size: 18px;
    color: var(--s-text);
}

@keyframes teacher-button-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 1024px) {
    .teacher-sidebar {
        display: none;
    }

    .teacher-header {
        left: 0;
        padding: 0 12px;
    }

    .teacher-header-title {
        font-size: 20px;
    }

    .teacher-user-name {
        display: none;
    }

    .teacher-main {
        margin-left: 0;
        padding-bottom: 78px;
    }

    .teacher-page-frame {
        padding: 16px 12px;
    }

    .teacher-mobile-tabs {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        height: 60px;
        background: #ffffff;
        border-top: 1px solid var(--s-border);
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        z-index: 30;
    }

    .teacher-mobile-tab {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        text-decoration: none;
        color: var(--s-text-muted);
    }

    .teacher-mobile-tab-icon {
        width: 20px;
        height: 20px;
    }

    .teacher-mobile-tab-label {
        font-family: var(--s-font-body);
        font-size: 12px;
        line-height: 1;
    }

    .teacher-mobile-tab--active {
        color: var(--s-coral);
    }

    .teacher-mobile-tab--active::after {
        content: '';
        position: absolute;
        left: 12px;
        right: 12px;
        bottom: 0;
        height: 3px;
        background: var(--s-coral);
        border-radius: 2px 2px 0 0;
    }

    :deep(.teacher-btn) {
        width: 100%;
        min-width: 0;
    }
}
</style>
