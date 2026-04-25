<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import axios from 'axios';
import EmptyState from '@/Components/Shared/EmptyState.vue';
import ErrorState from '@/Components/Shared/ErrorState.vue';
import { HeartIcon, MagnifyingGlassIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/vue/24/solid';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { enforceMinimumDelay } from '@/composables/useMinimumDelay';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);

const teachers = ref([]);
const pageNumber = ref(1);
const hasMore = ref(true);
const isLoading = ref(false);
const isMobileFilterOpen = ref(false);
const pageError = ref('');

const searchQuery = ref('');
const sort = ref('rating_desc');
const searchFocused = ref(false);
const searchRowVisible = ref(false);
const filterPanelVisible = ref(false);
const filterResetting = ref(false);
const sortMenuOpen = ref(false);
const sortDropdownRef = ref(null);

let searchDebounceTimer = null;
const heartFx = ref({});
const bookmarkBusyIds = ref(new Set());
const heartFxTimers = new Map();

const filters = ref({
    subjects: [],
    languages: [],
    price: 'any',
    min_rating: null,
    availability_days: [],
    gender: 'any',
});

const allSubjects = ['Math', 'Science', 'Languages', 'Arts', 'Other'];
const allLanguages = ['English', 'Hindi', 'Tamil', 'Telugu', 'Bengali', 'Marathi'];
const allDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const isSearchMode = computed(() => searchQuery.value.trim().length > 0);
const showInitialSkeleton = computed(() => isLoading.value && teachers.value.length === 0);
const sortOptions = computed(() => {
    const base = [
        { label: 'Rating: High to Low', value: 'rating_desc' },
        { label: 'Price: Low to High', value: 'price_asc' },
        { label: 'Price: High to Low', value: 'price_desc' },
        { label: 'Most Experienced', value: 'experienced' },
        { label: 'Newest', value: 'newest' },
    ];

    if (isSearchMode.value) {
        return [{ label: 'Relevance', value: 'relevance' }, ...base];
    }

    return base;
});

const currentSortLabel = computed(() => {
    return sortOptions.value.find((option) => option.value === sort.value)?.label || 'Sort';
});

const activeFilterChips = computed(() => {
    const chips = [];

    filters.value.subjects.forEach((subject) => chips.push({ key: `subject:${subject}`, label: `Subject: ${subject}` }));
    filters.value.languages.forEach((language) => chips.push({ key: `language:${language}`, label: `Language: ${language}` }));
    filters.value.availability_days.forEach((day) => chips.push({ key: `day:${day}`, label: `Day: ${day}` }));

    if (filters.value.price !== 'any') chips.push({ key: 'price', label: `Price: ${filters.value.price}` });
    if (filters.value.min_rating) chips.push({ key: 'min_rating', label: `${filters.value.min_rating}+ stars` });
    if (filters.value.gender !== 'any') chips.push({ key: 'gender', label: `Gender: ${filters.value.gender}` });

    return chips;
});

const subjectColor = (subject) => ({
    Math: '#5BC4E5',
    Science: '#4CB87E',
    Languages: '#9B72CF',
    Arts: '#FFAB76',
    Other: '#F5C518',
}[subject] ?? '#F5C518');

const buildParams = () => {
    const params = {
        page: pageNumber.value,
        per_page: 12,
        sort: sort.value,
    };

    if (filters.value.subjects.length) params.subjects = filters.value.subjects;
    if (filters.value.languages.length) params.languages = filters.value.languages;
    if (filters.value.availability_days.length) params.availability_days = filters.value.availability_days;
    if (filters.value.price !== 'any') params.price = filters.value.price;
    if (filters.value.min_rating) params.min_rating = filters.value.min_rating;
    if (filters.value.gender !== 'any') params.gender = filters.value.gender;

    if (isSearchMode.value) {
        params.q = searchQuery.value.trim();
        if (sort.value === 'rating_desc') params.sort = 'relevance';
    }

    return params;
};

const fetchTeachers = async (reset = false) => {
    if (isLoading.value) return;

    const requestStartedAt = performance.now();
    pageError.value = '';

    if (reset) {
        teachers.value = [];
        pageNumber.value = 1;
        hasMore.value = true;
    }

    isLoading.value = true;
    try {
        const endpoint = isSearchMode.value ? '/api/teachers/search' : '/api/teachers';
        const response = await axios.get(endpoint, { params: buildParams() });
        const payload = response.data;
        const items = payload.data ?? [];

        if (reset) {
            teachers.value = items;
        } else {
            teachers.value = [...teachers.value, ...items];
        }

        hasMore.value = Boolean(payload.links?.next);
        if (hasMore.value) {
            pageNumber.value += 1;
        }
    } catch (error) {
        const statusCode = Number(error?.response?.status || 0);

        if (statusCode >= 500) {
            pageError.value = 'Teacher listings are temporarily unavailable. Please try again in a few minutes.';
        } else {
            pageError.value = error?.response?.data?.message || 'We could not load teachers right now. Please try again.';
        }
    } finally {
        await enforceMinimumDelay(requestStartedAt, 400);
        isLoading.value = false;
    }
};

const applyFilters = async () => {
    await fetchTeachers(true);
    isMobileFilterOpen.value = false;
};

const clearFilters = async () => {
    filterResetting.value = true;
    filters.value = {
        subjects: [],
        languages: [],
        price: 'any',
        min_rating: null,
        availability_days: [],
        gender: 'any',
    };
    await fetchTeachers(true);
    window.setTimeout(() => {
        filterResetting.value = false;
    }, 320);
};

const removeChip = async (chipKey) => {
    const [type, value] = chipKey.split(':');

    if (type === 'subject') filters.value.subjects = filters.value.subjects.filter((s) => s !== value);
    if (type === 'language') filters.value.languages = filters.value.languages.filter((l) => l !== value);
    if (type === 'day') filters.value.availability_days = filters.value.availability_days.filter((d) => d !== value);
    if (chipKey === 'price') filters.value.price = 'any';
    if (chipKey === 'min_rating') filters.value.min_rating = null;
    if (chipKey === 'gender') filters.value.gender = 'any';

    await fetchTeachers(true);
};

const setBookmarkBusy = (teacherId, busy) => {
    const key = String(teacherId);
    const next = new Set(bookmarkBusyIds.value);

    if (busy) {
        next.add(key);
    } else {
        next.delete(key);
    }

    bookmarkBusyIds.value = next;
};

const isBookmarkBusy = (teacherId) => bookmarkBusyIds.value.has(String(teacherId));

const setHeartFx = (teacherId, mode) => {
    const key = String(teacherId);

    heartFx.value = {
        ...heartFx.value,
        [key]: mode,
    };

    const existingTimer = heartFxTimers.get(key);
    if (existingTimer) {
        window.clearTimeout(existingTimer);
    }

    const timer = window.setTimeout(() => {
        const next = { ...heartFx.value };
        if (next[key] === mode) {
            delete next[key];
            heartFx.value = next;
        }
        heartFxTimers.delete(key);
    }, 460);

    heartFxTimers.set(key, timer);
};

const heartFxClass = (teacherId) => {
    const mode = heartFx.value[String(teacherId)];
    if (mode === 'save') return 'fx-save';
    if (mode === 'unsave') return 'fx-unsave';
    return '';
};

const toggleBookmark = async (teacher) => {
    if (!currentUser.value) {
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname)}`;
        return;
    }

    if (isBookmarkBusy(teacher.teacher_id)) {
        return;
    }

    const nextMode = teacher.is_saved ? 'unsave' : 'save';
    setBookmarkBusy(teacher.teacher_id, true);
    setHeartFx(teacher.teacher_id, nextMode);

    const endpoint = `/api/students/saved-teachers/${teacher.teacher_id}`;
    try {
        if (teacher.is_saved) {
            await axios.delete(endpoint);
            teacher.is_saved = false;
        } else {
            await axios.post(endpoint);
            teacher.is_saved = true;
        }
    } finally {
        setBookmarkBusy(teacher.teacher_id, false);
    }
};

const selectSort = (value) => {
    sort.value = value;
    sortMenuOpen.value = false;
};

const handleClickOutsideSort = (event) => {
    if (!sortDropdownRef.value) return;
    if (!sortDropdownRef.value.contains(event.target)) {
        sortMenuOpen.value = false;
    }
};

watch(searchQuery, () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        fetchTeachers(true);
    }, 300);
});

watch(sort, () => {
    fetchTeachers(true);
});

onMounted(async () => {
    document.body.setAttribute('data-portal', 'student');
    window.addEventListener('click', handleClickOutsideSort);

    await fetchTeachers(true);

    requestAnimationFrame(() => {
        searchRowVisible.value = true;
        filterPanelVisible.value = true;
    });
});

onBeforeUnmount(() => {
    clearTimeout(searchDebounceTimer);

    heartFxTimers.forEach((timer) => {
        window.clearTimeout(timer);
    });
    heartFxTimers.clear();

    window.removeEventListener('click', handleClickOutsideSort);
});
</script>

<template>
    <StudentLayout>
        <div class="teacher-search-page">
            <div class="content">
                <div class="search-row" :class="{ 'is-visible': searchRowVisible }">
                    <div class="search-input-wrap" :class="{ 'is-focused': searchFocused }">
                        <MagnifyingGlassIcon class="search-icon" aria-hidden="true" />
                        <input
                            v-model="searchQuery"
                            name="search"
                            type="text"
                            placeholder="Search by subject or teacher name..."
                            class="search-input"
                            @focus="searchFocused = true"
                            @blur="searchFocused = false"
                        />
                    </div>

                    <button class="mobile-filter-btn" type="button" @click="isMobileFilterOpen = true">Filters</button>

                    <div ref="sortDropdownRef" class="sort-dropdown">
                        <button type="button" class="sort-trigger" @click="sortMenuOpen = !sortMenuOpen">
                            <span>{{ currentSortLabel }}</span>
                            <ChevronDownIcon class="sort-trigger-icon" :class="{ open: sortMenuOpen }" aria-hidden="true" />
                        </button>

                        <transition name="sort-pop">
                            <ul v-if="sortMenuOpen" class="sort-menu" role="listbox">
                                <li
                                    v-for="(option, index) in sortOptions"
                                    :key="option.value"
                                    class="sort-option"
                                    :class="{ active: sort === option.value }"
                                    :style="{ '--stagger': `${index * 60}ms` }"
                                >
                                    <button type="button" @click="selectSort(option.value)">{{ option.label }}</button>
                                </li>
                            </ul>
                        </transition>
                    </div>
                </div>

                <TransitionGroup v-if="activeFilterChips.length" name="chip-pop" tag="div" class="chip-row">
                    <button v-for="chip in activeFilterChips" :key="chip.key" type="button" class="filter-chip" @click="removeChip(chip.key)">
                        {{ chip.label }} ×
                    </button>
                </TransitionGroup>

                <div v-if="showInitialSkeleton" class="teacher-grid">
                    <article v-for="index in 6" :key="index" class="teacher-card teacher-card--skeleton skeleton-card">
                        <div class="skeleton subject-strip-skeleton"></div>
                        <div class="skeleton avatar-skeleton"></div>
                        <div class="skeleton skeleton-line skeleton-title"></div>
                        <div class="skeleton skeleton-line skeleton-rating"></div>
                        <div class="skeleton-tag-row">
                            <span v-for="tag in 4" :key="`tag-${index}-${tag}`" class="skeleton skeleton-tag"></span>
                        </div>
                        <div class="skeleton-action-row">
                            <span class="skeleton skeleton-price"></span>
                            <span class="skeleton skeleton-button"></span>
                        </div>
                    </article>
                </div>

                <div v-else-if="pageError && !teachers.length" class="empty-state">
                    <ErrorState
                        code="503"
                        title="Unable to load teachers"
                        :message="pageError"
                        :show-back="false"
                    />
                </div>

                <div v-else-if="!teachers.length && !isLoading" class="empty-state">
                    <EmptyState
                        illustration="search"
                        title="No teachers found"
                        body="Try different keywords or clear your filters."
                        cta-text="Clear filters"
                        @cta="searchQuery = ''; clearFilters();"
                    />
                </div>

                <TransitionGroup v-else name="teacher-grid" tag="div" class="teacher-grid">
                    <article v-for="(teacher, index) in teachers" :key="teacher.id" class="teacher-card" :style="{ '--stagger': `${index * 80}ms` }">
                        <div class="subject-stripe" :style="{ background: subjectColor(teacher.subjects?.[0] || 'Other') }" />
                        <button
                            class="bookmark-btn"
                            type="button"
                            :class="{ saved: teacher.is_saved }"
                            :disabled="isBookmarkBusy(teacher.teacher_id)"
                            :aria-label="teacher.is_saved ? 'Remove teacher from saved list' : 'Save teacher'"
                            @click="toggleBookmark(teacher)"
                        >
                            <span class="heart-shell" :class="[teacher.is_saved ? 'is-saved' : '', heartFxClass(teacher.teacher_id)]">
                                <HeartIcon class="bookmark-icon outline" aria-hidden="true" />
                                <HeartSolidIcon class="bookmark-icon fill" aria-hidden="true" />
                            </span>
                        </button>

                        <img :src="teacher.avatar || '/favicon.ico'" loading="lazy" alt="Teacher avatar" class="avatar" width="96" height="96" />
                        <h3>{{ teacher.name }}</h3>
                        <p class="rating">⭐ {{ teacher.rating_avg.toFixed(1) }} ({{ teacher.total_reviews }} reviews)</p>

                        <div class="tag-row">
                            <span
                                v-for="subject in teacher.subjects_visible"
                                :key="subject"
                                class="subject-tag"
                                :style="{ background: subjectColor(subject) }"
                            >
                                {{ subject }}
                            </span>
                            <span v-if="teacher.subjects_extra_count > 0" class="subject-tag" style="background:#f5c518;">
                                +{{ teacher.subjects_extra_count }} more
                            </span>
                        </div>

                        <div class="tag-row">
                            <span v-for="language in teacher.languages" :key="language" class="language-tag">{{ language }}</span>
                        </div>

                        <div class="card-footer">
                            <Link :href="route('teachers.show', { teacher: teacher.teacher_id })" class="view-btn view-profile-btn">View Profile</Link>
                            <span class="price-badge" :class="{ free: teacher.is_free }">{{ teacher.price_label }}</span>
                        </div>
                    </article>
                </TransitionGroup>

                <div v-if="hasMore && teachers.length" class="load-more-wrap">
                    <button class="load-more-btn" :disabled="isLoading" @click="fetchTeachers(false)">
                        {{ isLoading ? 'Loading...' : 'Load More' }}
                    </button>
                </div>
            </div>

            <aside class="filter-panel" :class="{ 'is-visible': filterPanelVisible, 'is-resetting': filterResetting }">
                <h3>Filters</h3>

                <div class="filter-section">
                    <label>Subjects</label>
                    <div class="inline-grid">
                        <button
                            v-for="subject in allSubjects"
                            :key="subject"
                            class="pill-btn"
                            :class="{ active: filters.subjects.includes(subject) }"
                            @click="filters.subjects = filters.subjects.includes(subject) ? filters.subjects.filter((s) => s !== subject) : [...filters.subjects, subject]"
                        >
                            {{ subject }}
                        </button>
                    </div>
                </div>

                <div class="filter-section">
                    <label>Languages</label>
                    <div class="inline-grid">
                        <button
                            v-for="language in allLanguages"
                            :key="language"
                            class="pill-btn"
                            :class="{ active: filters.languages.includes(language) }"
                            @click="filters.languages = filters.languages.includes(language) ? filters.languages.filter((l) => l !== language) : [...filters.languages, language]"
                        >
                            {{ language }}
                        </button>
                    </div>
                </div>

                <div class="filter-section">
                    <label>Price</label>
                    <select v-model="filters.price" class="field-select">
                        <option value="any">Any</option>
                        <option value="free">Free only</option>
                        <option value="under_200">Under ₹200</option>
                        <option value="200_500">₹200-500</option>
                        <option value="500_plus">₹500+</option>
                    </select>
                </div>

                <div class="filter-section">
                    <label>Minimum Rating</label>
                    <div class="rating-row">
                        <button v-for="n in 5" :key="n" class="star-btn" :class="{ active: (filters.min_rating || 0) >= n }" @click="filters.min_rating = n">
                            ★
                        </button>
                    </div>
                </div>

                <div class="filter-section">
                    <label>Availability Day</label>
                    <div class="inline-grid">
                        <button
                            v-for="day in allDays"
                            :key="day"
                            class="pill-btn"
                            :class="{ active: filters.availability_days.includes(day) }"
                            @click="filters.availability_days = filters.availability_days.includes(day) ? filters.availability_days.filter((d) => d !== day) : [...filters.availability_days, day]"
                        >
                            {{ day }}
                        </button>
                    </div>
                </div>

                <div class="filter-section">
                    <label>Gender Preference</label>
                    <select v-model="filters.gender" class="field-select">
                        <option value="any">Any</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <button class="apply-btn" type="button" @click="applyFilters">Apply Filters</button>
                <button class="clear-link" type="button" @click="clearFilters">Clear All</button>
            </aside>
        </div>

        <div v-if="isMobileFilterOpen" class="mobile-filter-overlay" @click.self="isMobileFilterOpen = false">
            <div class="mobile-filter-sheet">
                <div class="sheet-handle" />
                <h3>Filters</h3>
                <button class="apply-btn" type="button" @click="applyFilters">Apply Filters</button>
                <button class="clear-link" type="button" @click="clearFilters">Clear All</button>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.teacher-search-page {
    background: #fff8f0;
    min-height: 100vh;
    padding: 24px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) 280px;
    gap: 24px;
}

.content {
    min-width: 0;
}

.search-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    gap: 12px;
    align-items: center;
    opacity: 0;
    transform: translateY(-24px);
}

.search-row.is-visible {
    animation: search-row-in 480ms var(--s-spring) forwards;
}

.search-input-wrap {
    position: relative;
    display: flex;
    justify-content: flex-end;
}

.search-icon {
    position: absolute;
    left: calc(40% + 20px);
    top: 50%;
    transform: translateY(-50%);
    width: 22px;
    height: 22px;
    color: #e8553e;
    transition: left 0.35s var(--s-spring);
}

.search-input {
    width: 60%;
    height: 60px;
    border-radius: 50px;
    border: 2px solid #f1d8cc;
    padding: 0 20px 0 48px;
    font-family: Nunito, sans-serif;
    font-size: 18px;
    font-style: italic;
    transition: width 0.35s var(--s-spring), box-shadow 0.35s var(--s-spring), border-color 0.3s ease;
}

.search-input-wrap.is-focused .search-input {
    width: 100%;
    border-color: #e8553e;
    box-shadow: 0 8px 24px rgba(232, 85, 62, 0.15);
}

.search-input-wrap.is-focused .search-icon {
    left: 20px;
}

.sort-dropdown {
    position: relative;
}

.sort-trigger,
.mobile-filter-btn {
    min-height: 44px;
    border-radius: 999px;
    border: 1px solid #f0ddd5;
    background: #fff;
    padding: 0 14px;
    font-family: Nunito, sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.sort-trigger-icon {
    width: 16px;
    height: 16px;
    transition: transform 0.2s var(--s-spring);
}

.sort-trigger-icon.open {
    transform: rotate(180deg);
}

.sort-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 240px;
    background: #fff;
    border: 1px solid #f0ddd5;
    border-radius: 14px;
    box-shadow: 0 20px 44px rgba(232, 85, 62, 0.14);
    padding: 8px;
    z-index: 15;
    transform-origin: top center;
}

.sort-option {
    animation: sort-option-enter 280ms var(--s-spring) both;
    animation-delay: var(--stagger);
}

.sort-option button {
    width: 100%;
    text-align: left;
    border: none;
    border-radius: 10px;
    background: transparent;
    min-height: 38px;
    padding: 0 10px;
    font-family: Nunito, sans-serif;
    cursor: pointer;
    transform: translateX(0);
    transition: background 0.2s ease, transform 0.2s var(--s-spring);
}

.sort-option button:hover {
    background: #fff3ef;
    transform: translateX(4px);
}

.sort-option.active button {
    background: #fff3ef;
    color: #e8553e;
    font-weight: 700;
}

.sort-pop-enter-active,
.sort-pop-leave-active {
    transition: transform 0.28s var(--s-spring), opacity 0.2s ease;
}

.sort-pop-enter-from,
.sort-pop-leave-to {
    opacity: 0;
    transform: scale(0.8);
}

.mobile-filter-btn {
    display: none;
}

.chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 16px 0;
}

.filter-chip {
    border: none;
    border-radius: 999px;
    background: #fff3ef;
    color: #e8553e;
    padding: 8px 12px;
    font-family: Nunito, sans-serif;
    cursor: pointer;
}

.chip-pop-enter-active,
.chip-pop-leave-active {
    transition: transform 0.25s var(--s-spring), opacity 0.2s ease;
}

.chip-pop-enter-from {
    opacity: 0;
    transform: scale(0);
}

.chip-pop-leave-to {
    opacity: 1;
    transform: scale(1);
}

.chip-pop-leave-active {
    position: absolute;
    animation: chip-shake-shrink 350ms ease forwards;
}

.chip-pop-move {
    transition: transform 0.25s var(--s-spring);
}

.teacher-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
    margin-top: 20px;
}

.teacher-card {
    position: relative;
    background: #fff;
    border-radius: 20px;
    padding: 24px 20px 20px;
    box-shadow: 0 4px 20px rgba(232, 85, 62, 0.08);
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.teacher-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(232, 85, 62, 0.16);
}

.teacher-grid-enter-active {
    transition: transform 400ms var(--s-spring), opacity 280ms ease;
    transition-delay: var(--stagger);
}

.teacher-grid-leave-active {
    transition: transform 220ms ease, opacity 180ms ease;
}

.teacher-grid-enter-from {
    opacity: 0;
    transform: translateY(20px) scale(0.9);
}

.teacher-grid-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

.teacher-grid-move {
    transition: transform 0.35s var(--s-spring);
}

.teacher-card--skeleton {
    min-height: 350px;
    padding-top: 0;
}

.subject-stripe {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    border-radius: 20px 20px 0 0;
}

.bookmark-btn {
    position: absolute;
    top: 14px;
    right: 16px;
    border: none;
    background: transparent;
    color: #e8553e;
    cursor: pointer;
    padding: 0;
    min-height: 44px;
    min-width: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s ease;
}

.bookmark-btn.saved {
    transform: scale(1.06);
}

.bookmark-btn:disabled {
    cursor: wait;
}

.heart-shell {
    position: relative;
    width: 24px;
    height: 24px;
    display: inline-grid;
    place-items: center;
}

.bookmark-icon {
    position: absolute;
    inset: 0;
    width: 24px;
    height: 24px;
}

.bookmark-icon.outline {
    color: #e8553e;
}

.bookmark-icon.fill {
    color: #ff7b67;
    fill: currentColor;
    clip-path: circle(0% at 50% 50%);
}

.heart-shell.is-saved .bookmark-icon.fill {
    clip-path: circle(50% at 50% 50%);
}

.heart-shell.fx-save {
    animation: heart-pump 420ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.heart-shell.fx-save .bookmark-icon.fill {
    animation: heart-fill-in 420ms ease-out forwards;
}

.heart-shell.fx-unsave {
    animation: heart-pump 420ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.heart-shell.fx-unsave .bookmark-icon.fill {
    animation: heart-fill-out 350ms ease-in forwards;
}

.avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid #f5c518;
    object-fit: cover;
    margin-bottom: 12px;
}

h3 {
    margin: 0;
    font-family: Nunito, sans-serif;
    font-weight: 700;
    font-size: 18px;
}

.rating {
    margin: 6px 0 10px;
    font-family: Nunito, sans-serif;
    color: #f5c518;
    font-size: 15px;
}

.tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}

.subject-tag {
    color: #fff;
    border-radius: 999px;
    padding: 4px 9px;
    font-family: Nunito, sans-serif;
    font-size: 12px;
    font-weight: 700;
}

.language-tag {
    background: #f3f4f6;
    color: #6b7280;
    border-radius: 999px;
    padding: 4px 8px;
    font-size: 11px;
    font-family: Nunito, sans-serif;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
}

.view-btn {
    text-decoration: none;
    background: #e8553e;
    color: #fff;
    border-radius: 999px;
    padding: 9px 14px;
    font-family: 'Fredoka One', cursive;
    font-size: 14px;
}

.price-badge {
    background: #e8553e;
    color: #fff;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 12px;
    font-family: Nunito, sans-serif;
    font-weight: 700;
}

.price-badge.free {
    background: #4cb87e;
}

.load-more-wrap {
    display: flex;
    justify-content: center;
    margin: 24px 0;
}

.load-more-btn,
.apply-btn {
    background: #e8553e;
    color: #fff;
    border: none;
    border-radius: 999px;
    padding: 12px 22px;
    font-family: 'Fredoka One', cursive;
    cursor: pointer;
}

.filter-panel {
    background: #fff;
    border-radius: 20px;
    padding: 18px;
    height: fit-content;
    box-shadow: 0 4px 20px rgba(232, 85, 62, 0.08);
    opacity: 0;
    transform: translateX(30px);
}

.filter-panel.is-visible {
    animation: filter-panel-in 480ms var(--s-spring) forwards;
}

.filter-panel.is-resetting {
    max-height: 180px;
    overflow: hidden;
    transition: max-height 320ms ease;
}

.filter-section {
    transition: opacity 260ms ease, transform 260ms ease;
}

.filter-panel.is-resetting .filter-section {
    opacity: 0;
    transform: translateY(-8px);
}

.filter-panel h3 {
    font-family: 'Fredoka One', cursive;
    color: #e8553e;
    margin-bottom: 10px;
}

.filter-panel label {
    display: block;
    margin: 14px 0 8px;
    font-family: Nunito, sans-serif;
    font-weight: 700;
}

.inline-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.pill-btn {
    border: 1px solid #f0ddd5;
    background: #fff;
    border-radius: 999px;
    padding: 6px 10px;
    cursor: pointer;
    font-family: Nunito, sans-serif;
}

.pill-btn.active {
    background: #fff3ef;
    border-color: #e8553e;
    color: #e8553e;
}

.field-select {
    width: 100%;
    height: 40px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    padding: 0 10px;
}

.rating-row {
    display: flex;
    gap: 6px;
}

.star-btn {
    border: none;
    background: #f3f4f6;
    color: #9ca3af;
    border-radius: 8px;
    width: 34px;
    height: 34px;
    cursor: pointer;
}

.star-btn.active {
    background: #fef3c7;
    color: #f59e0b;
}

.clear-link {
    margin-top: 10px;
    border: none;
    background: transparent;
    color: #e8553e;
    font-family: Nunito, sans-serif;
    text-decoration: underline;
    cursor: pointer;
}

.empty-state {
    margin-top: 12px;
    background: #fff;
    border-radius: 20px;
    padding: 18px;
}

.avatar-skeleton {
    width: 80px;
    height: 80px;
    margin-bottom: 12px;
    border-radius: 999px;
}

.subject-strip-skeleton {
    width: 100%;
    height: 10px;
    border-radius: 10px;
    margin-bottom: 14px;
}

.skeleton-title {
    width: 62%;
    margin: 0 0 10px;
}

.skeleton-rating {
    width: 48%;
    margin: 0 0 14px;
}

.skeleton-tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}

.skeleton-tag {
    display: inline-block;
    width: 62px;
    height: 22px;
    border-radius: 999px;
}

.skeleton-action-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
}

.skeleton-button {
    width: 118px;
    height: 36px;
    border-radius: 999px;
}

.skeleton-price {
    width: 92px;
    height: 28px;
    border-radius: 999px;
}

.mobile-filter-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: flex;
    align-items: flex-end;
    z-index: 40;
}

.mobile-filter-sheet {
    width: 100%;
    background: #fff;
    border-radius: 20px 20px 0 0;
    padding: 18px;
    max-height: 75vh;
}

.sheet-handle {
    width: 50px;
    height: 5px;
    border-radius: 999px;
    background: #d1d5db;
    margin: 0 auto 12px;
}

@keyframes search-row-in {
    0% {
        opacity: 0;
        transform: translateY(-24px);
    }

    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes filter-panel-in {
    0% {
        opacity: 0;
        transform: translateX(30px);
    }

    78% {
        opacity: 1;
        transform: translateX(-4px);
    }

    100% {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes sort-option-enter {
    0% {
        opacity: 0;
        transform: translateX(-12px);
    }

    100% {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes empty-character-bounce {
    0% {
        opacity: 0;
        transform: translateX(28px) scale(0.86);
    }

    70% {
        opacity: 1;
        transform: translateX(-6px) scale(1.04);
    }

    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

@keyframes chip-bounce-out {
    0% {
        opacity: 1;
        transform: scale(1);
    }

    45% {
        transform: scale(1.1);
    }

    100% {
        opacity: 0;
        transform: scale(0);
    }
}

@keyframes chip-shake-shrink {
    0% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }

    16% {
        transform: translateX(-3px) scale(1);
    }

    32% {
        transform: translateX(3px) scale(1);
    }

    48% {
        transform: translateX(-2px) scale(1);
    }

    57% {
        transform: translateX(0) scale(1);
    }

    100% {
        opacity: 0;
        transform: translateX(0) scale(0);
    }
}

@keyframes heart-pump {
    0% {
        transform: scale(1);
    }

    25% {
        transform: scale(0);
    }

    60% {
        transform: scale(1.3);
    }

    100% {
        transform: scale(1);
    }
}

@keyframes heart-fill-in {
    0% {
        clip-path: circle(0% at 50% 50%);
    }

    100% {
        clip-path: circle(50% at 50% 50%);
    }
}

@keyframes heart-fill-out {
    0% {
        clip-path: circle(50% at 50% 50%);
    }

    100% {
        clip-path: circle(0% at 50% 50%);
    }
}

@media (max-width: 1200px) {
    .teacher-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 960px) {
    .teacher-search-page {
        grid-template-columns: 1fr;
    }

    .filter-panel {
        display: none;
    }

    .mobile-filter-btn {
        display: inline-flex;
        align-items: center;
    }

    .search-input {
        width: 100%;
    }

    .search-icon {
        left: 20px;
    }
}

@media (max-width: 640px) {
    .teacher-grid {
        grid-template-columns: 1fr;
    }

    .search-row {
        grid-template-columns: 1fr auto;
    }

    .sort-dropdown {
        grid-column: span 2;
    }

    .sort-trigger {
        width: 100%;
        justify-content: space-between;
    }

    .sort-menu {
        width: 100%;
    }
}
</style>
