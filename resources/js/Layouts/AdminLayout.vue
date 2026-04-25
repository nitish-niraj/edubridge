<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BellIcon,
    ChartBarIcon,
    ChevronDownIcon,
    ChevronRightIcon,
    Cog6ToothIcon,
    DocumentMagnifyingGlassIcon,
    MagnifyingGlassIcon,
    MegaphoneIcon,
    RectangleStackIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    UserCircleIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    pageTitle: {
        type: String,
        default: '',
    },
    breadcrumb: {
        type: [String, Array],
        default: '',
    },
    showSearch: {
        type: Boolean,
        default: true,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const shouldAnimateNav = ref(false);
const topbarSearch = ref('');

let navIndex = 0;

const navGroups = [
    {
        label: 'OVERVIEW',
        items: [
            {
                label: 'Dashboard',
                href: route('admin.dashboard'),
                icon: Squares2X2Icon,
                activePrefixes: ['/admin/dashboard'],
                navIndex: navIndex++,
            },
        ],
    },
    {
        label: 'USERS',
        items: [
            {
                label: 'Students',
                href: route('admin.users', { role: 'student' }),
                icon: UsersIcon,
                activeMatch: (path, query) => path === '/admin/users' && query.get('role') === 'student',
                navIndex: navIndex++,
            },
            {
                label: 'Teachers',
                href: route('admin.users', { role: 'teacher' }),
                icon: UsersIcon,
                activeMatch: (path, query) => path === '/admin/users' && query.get('role') === 'teacher',
                navIndex: navIndex++,
            },
        ],
    },
    {
        label: 'VERIFICATION',
        items: [
            {
                label: 'Pending Queue',
                href: route('admin.verifications'),
                icon: ShieldCheckIcon,
                activePrefixes: ['/admin/verifications'],
                navIndex: navIndex++,
            },
        ],
    },
    {
        label: 'CONTENT',
        items: [
            {
                label: 'Disputes',
                href: route('admin.disputes'),
                icon: RectangleStackIcon,
                activePrefixes: ['/admin/disputes', '/admin/bookings'],
                navIndex: navIndex++,
            },
            {
                label: 'Reviews',
                href: route('admin.reviews'),
                icon: DocumentMagnifyingGlassIcon,
                activePrefixes: ['/admin/reviews'],
                navIndex: navIndex++,
            },
            {
                label: 'Reports',
                href: route('admin.reports'),
                icon: DocumentMagnifyingGlassIcon,
                activePrefixes: ['/admin/reports'],
                navIndex: navIndex++,
            },
        ],
    },
    {
        label: 'COMMUNICATIONS',
        items: [
            {
                label: 'Announcements',
                href: route('admin.announcements'),
                icon: MegaphoneIcon,
                activePrefixes: ['/admin/announcements'],
                navIndex: navIndex++,
            },
        ],
    },
    {
        label: 'ANALYTICS',
        items: [
            {
                label: 'Analytics',
                href: route('admin.analytics'),
                icon: ChartBarIcon,
                activePrefixes: ['/admin/analytics'],
                navIndex: navIndex++,
            },
        ],
    },
    {
        label: 'SETTINGS',
        items: [
            {
                label: 'Platform',
                href: route('admin.settings.platform'),
                icon: Cog6ToothIcon,
                activePrefixes: ['/admin/settings/platform'],
                navIndex: navIndex++,
            },
            {
                label: 'Account',
                href: route('admin.settings.account'),
                icon: UserCircleIcon,
                activePrefixes: ['/admin/settings/account'],
                navIndex: navIndex++,
            },
        ],
    },
];

const query = computed(() => {
    const raw = page.url?.split('?')[1] || '';
    return new URLSearchParams(raw);
});

const currentPath = computed(() => page.url?.split('?')[0] || window.location.pathname);

const isActive = (item) => {
    if (typeof item.activeMatch === 'function') {
        return item.activeMatch(currentPath.value, query.value);
    }

    return (item.activePrefixes || []).some((prefix) => currentPath.value.startsWith(prefix));
};

const routeTitle = computed(() => {
    for (const group of navGroups) {
        const activeItem = group.items.find((item) => isActive(item));
        if (activeItem) {
            return activeItem.label;
        }
    }

    return 'Dashboard';
});

const resolvedPageTitle = computed(() => props.pageTitle || routeTitle.value);

const resolvedBreadcrumb = computed(() => {
    if (Array.isArray(props.breadcrumb) && props.breadcrumb.length) {
        return props.breadcrumb;
    }

    if (typeof props.breadcrumb === 'string' && props.breadcrumb.trim()) {
        return ['Admin', props.breadcrumb.trim()];
    }

    return ['Admin', resolvedPageTitle.value];
});

const notificationCount = computed(() => page.props.admin?.notifications_count ?? 0);
const avatarInitial = computed(() => (user.value?.name || 'A').charAt(0).toUpperCase());

const searchPlaceholder = computed(() => {
    if (currentPath.value.startsWith('/admin/users')) return 'Search users by name or email';
    if (currentPath.value.startsWith('/admin/verifications')) return 'Search teachers in verification queue';
    if (currentPath.value.startsWith('/admin/reports')) return 'Search reports by reason or user';
    if (currentPath.value.startsWith('/admin/disputes') || currentPath.value.startsWith('/admin/bookings')) return 'Search disputes by user, subject, or booking';

    return 'Search';
});

const isTopbarSearchSupported = computed(() => {
    return currentPath.value.startsWith('/admin/users')
        || currentPath.value.startsWith('/admin/verifications')
        || currentPath.value.startsWith('/admin/reports')
        || currentPath.value.startsWith('/admin/disputes')
        || currentPath.value.startsWith('/admin/bookings');
});

const syncTopbarSearchFromUrl = () => {
    topbarSearch.value = query.value.get('search') || '';
};

const submitTopbarSearch = () => {
    if (!isTopbarSearchSupported.value) {
        return;
    }

    const params = new URLSearchParams(query.value.toString());
    const term = topbarSearch.value.trim();

    if (term) {
        params.set('search', term);
    } else {
        params.delete('search');
    }

    if (currentPath.value === '/admin/users' && !params.get('role')) {
        params.set('role', 'student');
    }

    router.get(currentPath.value, Object.fromEntries(params.entries()), {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
};

watch(() => page.url, syncTopbarSearchFromUrl);

const navAnimationStyle = (item) => ({
    animationDelay: `${item.navIndex * 30}ms`,
});

onMounted(() => {
    document.body.setAttribute('data-portal', 'admin');
    syncTopbarSearchFromUrl();

    try {
        const key = 'edubridge-admin-nav-animated';
        if (!sessionStorage.getItem(key)) {
            shouldAnimateNav.value = true;
            sessionStorage.setItem(key, '1');
        }
    } catch {
        shouldAnimateNav.value = true;
    }
});
</script>

<template>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="brand-row">
                <img src="/images/logo.png" alt="EduBridge Logo" style="width: 28px; height: 28px; border-radius: 6px; object-fit: cover;">
                <h2>EduBridge</h2>
                <span class="brand-badge">Admin</span>
            </div>

            <nav class="sidebar-nav">
                <section v-for="group in navGroups" :key="group.label" class="nav-group">
                    <p class="nav-group-label">{{ group.label }}</p>

                    <Link
                        v-for="item in group.items"
                        :key="item.label"
                        :href="item.href"
                        class="nav-item"
                        :class="{
                            active: isActive(item),
                            'nav-item-enter': shouldAnimateNav,
                        }"
                        :style="shouldAnimateNav ? navAnimationStyle(item) : undefined"
                    >
                        <component :is="item.icon" class="nav-icon" />
                        <span>{{ item.label }}</span>
                    </Link>
                </section>
            </nav>

            <div class="sidebar-footer">
                <div class="footer-user">
                    <span class="avatar">{{ avatarInitial }}</span>
                    <div class="footer-user-copy">
                        <strong>{{ user?.name || 'Admin User' }}</strong>
                        <span>{{ user?.email || 'operations@edubridge.com' }}</span>
                    </div>
                </div>

                <Link :href="route('logout')" method="post" as="button" class="sidebar-signout">
                    Sign out
                </Link>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="topbar-left">
                    <h1>{{ resolvedPageTitle }}</h1>
                    <div class="topbar-breadcrumb" aria-label="Breadcrumb">
                        <template v-for="(item, index) in resolvedBreadcrumb" :key="`${item}-${index}`">
                            <span>{{ item }}</span>
                            <ChevronRightIcon v-if="index < resolvedBreadcrumb.length - 1" class="crumb-icon" />
                        </template>
                    </div>
                </div>

                <div class="topbar-right">
                    <label v-if="showSearch" class="topbar-search" aria-label="Search">
                        <MagnifyingGlassIcon class="search-icon" />
                        <input
                            v-model="topbarSearch"
                            type="search"
                            :placeholder="searchPlaceholder"
                            :disabled="!isTopbarSearchSupported"
                            @keydown.enter.prevent="submitTopbarSearch"
                        />
                    </label>

                    <button type="button" class="topbar-icon-button" aria-label="Notifications">
                        <BellIcon class="topbar-icon" />
                        <span v-if="notificationCount" class="notification-badge">{{ notificationCount }}</span>
                    </button>

                    <button type="button" class="topbar-avatar" aria-label="Admin account">
                        <span>{{ avatarInitial }}</span>
                        <ChevronDownIcon class="topbar-icon-small" />
                    </button>
                </div>
            </header>

            <main class="admin-content">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.admin-shell {
    min-height: 100vh;
    font-family: var(--s-font-body);
    background: #ffffff;
    color: var(--s-text);
}

.admin-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 240px;
    background: var(--s-coral-dark);
    display: flex;
    flex-direction: column;
    z-index: 50;
}

.brand-row {
    min-height: 68px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.brand-row h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1;
}

.brand-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 8px;
    background: var(--s-coral);
    color: #ffffff;
    font-size: 11px;
    line-height: 1;
    font-weight: 600;
}

.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 14px 0 18px;
}

.nav-group {
    margin-bottom: 14px;
}

.nav-group-label {
    margin: 0;
    padding: 0 20px 6px;
    font-size: 11px;
    line-height: 1.1;
    letter-spacing: 0.08em;
    color: #FFD6C9;
    text-transform: uppercase;
    font-weight: 600;
}

.nav-item {
    min-height: 40px;
    padding: 0 16px 0 17px;
    margin-left: 3px;
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.75);
    border-left: 3px solid transparent;
    transition: background-color 120ms ease, color 120ms ease, border-color 120ms ease;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.nav-item.active {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
    border-left-color: var(--s-coral);
}

.nav-item-enter {
    opacity: 0;
    transform: translateX(-16px);
    animation: admin-nav-enter 250ms ease-out forwards;
}

.nav-icon {
    width: 17px;
    height: 17px;
    flex: 0 0 auto;
}

.sidebar-footer {
    padding: 14px 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.footer-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avatar {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.footer-user-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.footer-user-copy strong {
    color: #ffffff;
    font-size: 13px;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.footer-user-copy span {
    color: rgba(255, 255, 255, 0.62);
    font-size: 12px;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-signout {
    margin-top: 10px;
    width: 100%;
    min-height: 34px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    background: transparent;
    color: #ffffff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 120ms ease;
}

.sidebar-signout:hover {
    background: rgba(255, 255, 255, 0.08);
}

.admin-main {
    margin-left: 240px;
    min-height: 100vh;
    background: #ffffff;
}

.admin-topbar {
    position: sticky;
    top: 0;
    z-index: 40;
    height: 56px;
    border-bottom: 1px solid var(--s-border);
    background: #ffffff;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.topbar-left {
    min-width: 0;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
}

.topbar-left h1 {
    margin: 0;
    color: var(--s-text);
    font-size: 16px;
    font-weight: 700;
    line-height: 1.15;
}

.topbar-breadcrumb {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--s-text-muted);
    font-size: 12px;
}

.crumb-icon {
    width: 12px;
    height: 12px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.topbar-search {
    width: 220px;
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid var(--s-border);
    border-radius: 8px;
    padding: 0 10px;
    transition: width 180ms ease, border-color 120ms ease;
}

.topbar-search:focus-within {
    width: 300px;
    border-color: var(--s-coral);
}

.search-icon {
    width: 16px;
    height: 16px;
    color: var(--s-text-muted);
}

.topbar-search input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    font-size: 13px;
    color: var(--s-text);
}

.topbar-icon-button,
.topbar-avatar {
    border: 1px solid var(--s-border);
    border-radius: 8px;
    background: #ffffff;
    min-height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--s-text);
    cursor: pointer;
}

.topbar-icon-button {
    position: relative;
    width: 36px;
}

.topbar-icon {
    width: 17px;
    height: 17px;
}

.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 16px;
    height: 16px;
    border-radius: 999px;
    background: var(--s-coral);
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
}

.topbar-avatar {
    padding: 0 9px;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
}

.topbar-avatar > span {
    width: 20px;
    height: 20px;
    border-radius: 999px;
    background: var(--s-coral-dark);
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
}

.topbar-icon-small {
    width: 14px;
    height: 14px;
}

.admin-content {
    padding: 24px;
}

@keyframes admin-nav-enter {
    from {
        opacity: 0;
        transform: translateX(-16px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

:deep(.admin-btn) {
    min-height: 36px;
    border-radius: 8px;
    border: 1px solid transparent;
    padding: 0 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

:deep(.admin-btn-primary) {
    background: var(--s-coral);
    border-color: var(--s-coral);
    color: #ffffff;
}

:deep(.admin-btn-secondary) {
    background: #ffffff;
    border-color: var(--s-border);
    color: var(--s-text);
}

:deep(.admin-btn-danger) {
    background: #FFE8E2;
    border-color: #FFD3C8;
    color: #dc2626;
}

:deep(.admin-field),
:deep(.admin-select),
:deep(.admin-textarea) {
    width: 100%;
    min-height: 38px;
    border-radius: 8px;
    border: 1px solid var(--s-border);
    background: #ffffff;
    padding: 0 12px;
    font-size: 13px;
    color: var(--s-text);
}

:deep(.admin-textarea) {
    min-height: 94px;
    padding: 10px 12px;
    resize: vertical;
}

:deep(.admin-field:focus),
:deep(.admin-select:focus),
:deep(.admin-textarea:focus) {
    outline: none;
    border-color: var(--s-coral);
}
</style>
