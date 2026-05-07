<script setup>
import { computed, ref } from 'vue';
import SButton from './UI/SButton.vue';
import SubjectTag from './UI/SubjectTag.vue';
import StarRating from '../Shared/StarRating.vue';
import { HeartIcon } from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/vue/24/solid';
import axios from 'axios';
import { usePage, Link } from '@inertiajs/vue3';

const props = defineProps({
    teacher: {
        type: Object,
        required: true,
    },
    index: {
        type: Number,
        default: 0,
    },
    isOnline: {
        type: Boolean,
        default: null,
    },
});

const emit = defineEmits(['view', 'toggle-bookmark']);

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);

const bookmarkBusy = ref(false);
const heartFx = ref('');

const subjects = computed(() => {
    if (Array.isArray(props.teacher.subjects) && props.teacher.subjects.length) {
        return props.teacher.subjects;
    }

    if (Array.isArray(props.teacher.subjects_visible) && props.teacher.subjects_visible.length) {
        return props.teacher.subjects_visible;
    }

    return [];
});

const primarySubject = computed(() => {
    return subjects.value.length > 0 ? subjects.value[0] : 'default';
});

const subjectColorTheme = computed(() => {
    const map = {
        Math: ['#5BC4E5', '#47AECD'],
        Science: ['#4CB87E', '#3A9E68'],
        Languages: ['#9B72CF', '#7E58B3'],
        Arts: ['#FFAB76', '#F28E55'],
        default: ['#E8553E', '#FF7A5C'],
    };

    return map[primarySubject.value] || map.default;
});

const topStripStyle = computed(() => ({
    '--subject-color-1': subjectColorTheme.value[0],
    '--subject-color-2': subjectColorTheme.value[1],
}));

const visibleSubjects = computed(() => subjects.value.slice(0, 3));

const hiddenSubjectCount = computed(() => Math.max(0, subjects.value.length - 3));

const rating = computed(() => Number(props.teacher.rating_avg ?? props.teacher.rating ?? 0));

const reviewCount = computed(() => Number(props.teacher.total_reviews ?? props.teacher.reviews_count ?? 0));

const hourlyRate = computed(() => {
    const direct = Number(props.teacher.hourly_rate ?? props.teacher.price_per_hour ?? 0);
    if (direct > 0) {
        return direct;
    }

    const parsedLabel = Number(String(props.teacher.price_label || '').replace(/[^\d]/g, ''));
    return Number.isFinite(parsedLabel) ? parsedLabel : 0;
});

const isFree = computed(() => {
    if (typeof props.teacher.is_free === 'boolean') {
        return props.teacher.is_free;
    }

    return hourlyRate.value === 0;
});

const formattedPrice = computed(() => {
    if (!isFree.value && typeof props.teacher.price_label === 'string' && props.teacher.price_label.trim().length > 0) {
        return props.teacher.price_label;
    }

    const hourly = hourlyRate.value;

    if (!hourly) {
        return 'FREE 🎓';
    }

    return `₹${hourly}/hr`;
});

const teacherAvatar = computed(() => {
    return props.teacher.avatar_url || props.teacher.avatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(props.teacher.name)}&background=F5C518&color=fff`;
});

const resolvedOnline = computed(() => {
    if (props.isOnline !== null) {
        return props.isOnline;
    }

    return Boolean(props.teacher.is_online);
});

const handleView = () => {
    emit('view', props.teacher.teacher_id ?? props.teacher.id);
};

const toggleBookmark = async () => {
    if (!currentUser.value) {
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname)}`;
        return;
    }

    if (bookmarkBusy.value) {
        return;
    }

    const teacherId = props.teacher.teacher_id ?? props.teacher.id;
    const isSaved = props.teacher.is_saved;
    const nextMode = isSaved ? 'unsave' : 'save';

    bookmarkBusy.value = true;
    heartFx.value = nextMode;

    const endpoint = `/api/students/saved-teachers/${teacherId}`;
    try {
        if (isSaved) {
            await axios.delete(endpoint);
            props.teacher.is_saved = false;
        } else {
            await axios.post(endpoint);
            props.teacher.is_saved = true;
        }
        emit('toggle-bookmark', props.teacher);
    } catch (error) {
        console.error('Failed to toggle bookmark:', error);
    } finally {
        setTimeout(() => {
            heartFx.value = '';
        }, 460);
        bookmarkBusy.value = false;
    }
};

</script>

<template>
    <article class="teacher-card" :style="{ animationDelay: `${index * 80}ms` }">
        <div class="top-strip" :style="topStripStyle"></div>

        <button
            class="bookmark-btn"
            type="button"
            :class="{ saved: teacher.is_saved }"
            :disabled="bookmarkBusy"
            @click.stop="toggleBookmark"
        >
            <span class="heart-shell" :class="[teacher.is_saved ? 'is-saved' : '', heartFx ? `fx-${heartFx}` : '']">
                <HeartIcon class="bookmark-icon outline" aria-hidden="true" />
                <HeartSolidIcon class="bookmark-icon fill" aria-hidden="true" />
            </span>
        </button>

        <div class="card-body">
            <div class="avatar-section">
                <div class="avatar-wrapper">
                    <img :src="teacherAvatar" :alt="teacher.name" class="avatar-img" loading="lazy" width="80" height="80">
                    <span v-if="resolvedOnline" class="online-indicator pulse-ring" aria-label="Teacher online"></span>
                </div>
            </div>

            <div class="content-section">
                <h3 class="teacher-name">{{ teacher.name }}</h3>

                <div class="rating-row">
                    <StarRating :rating="rating" :count="reviewCount" size="md" />
                </div>

                <div class="subject-tags">
                    <SubjectTag
                        v-for="subject in visibleSubjects"
                        :key="subject"
                        :subject="subject"
                        size="md"
                    />
                    <span v-if="hiddenSubjectCount > 0" class="more-tag">
                        +{{ hiddenSubjectCount }} more
                    </span>
                </div>

                <div v-if="teacher.languages && teacher.languages.length" class="language-tags">
                    <span v-for="lang in teacher.languages" :key="lang" class="lang-tag">{{ lang }}</span>
                </div>

                <p class="bio-snippet">{{ teacher.bio_preview || teacher.bio || 'Experienced educator focused on student outcomes.' }}</p>
            </div>

            <div class="card-footer">
                <Link :href="route('teachers.show', { teacher: teacher.teacher_id ?? teacher.id })" class="view-link">
                    <SButton variant="primary" class="view-btn">
                        View Profile
                    </SButton>
                </Link>
            </div>

            <span class="price-badge" :class="{ 'price-free': isFree, 'price-paid': !isFree }">
                {{ formattedPrice }}
            </span>
        </div>
    </article>
</template>

<style scoped>
.teacher-card {
    position: relative;
    background: #FFFFFF;
    border-radius: 20px;
    box-shadow: var(--s-shadow-card);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    opacity: 0;
    transform: translateY(20px);
    animation: card-enter 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes card-enter {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.teacher-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--s-shadow-hover);
}

.top-strip {
    height: 4px;
    width: 100%;
    background: linear-gradient(90deg, var(--subject-color-1), var(--subject-color-2));
}

.bookmark-btn {
    position: absolute;
    top: 14px;
    right: 14px;
    border: none;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
    color: #e8553e;
    cursor: pointer;
    padding: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    transition: transform 0.2s ease, background 0.2s ease;
}

.bookmark-btn:hover {
    background: #fff;
    transform: scale(1.1);
}

.bookmark-btn.saved {
    background: #fff;
}

.bookmark-btn:disabled {
    cursor: wait;
    opacity: 0.7;
}

.heart-shell {
    position: relative;
    width: 20px;
    height: 20px;
    display: grid;
    place-items: center;
}

.bookmark-icon {
    position: absolute;
    inset: 0;
    width: 20px;
    height: 20px;
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

@keyframes heart-pump {
    0% { transform: scale(1); }
    25% { transform: scale(0.6); }
    60% { transform: scale(1.3); }
    100% { transform: scale(1); }
}

@keyframes heart-fill-in {
    from { clip-path: circle(0% at 50% 50%); }
    to { clip-path: circle(50% at 50% 50%); }
}

@keyframes heart-fill-out {
    from { clip-path: circle(50% at 50% 50%); }
    to { clip-path: circle(0% at 50% 50%); }
}

.card-body {
    padding: 0 20px 56px;
    display: flex;
    flex-direction: column;
    flex: 1;
    position: relative;
}

.avatar-section {
    display: flex;
    justify-content: center;
    margin-top: -22px;
    position: relative;
    z-index: 2;
}

.avatar-wrapper {
    width: 86px;
    height: 86px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #F5C518, #FFAB76);
    box-shadow: 0 8px 16px rgba(245, 197, 24, 0.22);
    position: relative;
}

.avatar-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #F5C518;
    background: #FFFFFF;
}

.online-indicator {
    position: absolute;
    right: 4px;
    bottom: 6px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #4CB87E;
    border: 2px solid #FFFFFF;
    z-index: 3;
}

.content-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    margin-top: 12px;
    flex: 1;
}

.teacher-name {
    margin: 0 0 4px;
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-weight: 700;
    font-size: 18px;
    color: #2D2D2D;
}

.rating-row {
    margin-bottom: 12px;
}

.subject-tags {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin-bottom: 14px;
}

.more-tag {
    background: #E8553E;
    color: #FFFFFF;
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-weight: 600;
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 100px;
}

.language-tags {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 4px;
    margin-bottom: 12px;
}

.lang-tag {
    background: #f3f4f6;
    color: #6b7280;
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 100px;
}

.bio-snippet {
    margin: 0 0 18px;
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-size: 14px;
    line-height: 1.5;
    color: #9CA3AF;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-footer {
    margin-top: auto;
    width: 100%;
}

.view-link {
    text-decoration: none;
    width: 100%;
}

.view-btn {
    width: 100%;
    transform: translateY(0);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.teacher-card:hover .card-footer .view-btn {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(232, 85, 62, 0.35);
}

.price-badge {
    position: absolute;
    right: 14px;
    bottom: 16px;
    padding: 6px 12px;
    border-radius: 100px;
    font-family: var(--s-font-body, 'Nunito', sans-serif);
    font-size: 13px;
    font-weight: 700;
    z-index: 2;
}

.price-free {
    background: #4CB87E;
    color: #FFFFFF;
}

.price-paid {
    background: #FFF3EF;
    color: #E8553E;
}
</style>
