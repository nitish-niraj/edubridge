<script setup>
import axios from 'axios';
import BookingModal from '@/Pages/Student/BookingModal.vue';
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { useAnalytics } from '@/composables/useAnalytics';
import { HeartIcon } from '@heroicons/vue/24/outline';
import { HeartIcon as HeartSolidIcon } from '@heroicons/vue/24/solid';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    teacherId: {
        type: Number,
        required: true,
    },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? null);
const teacher = ref(null);
const isLoading = ref(true);
const loadError = ref('');
const reportOpen = ref(false);
const reportReason = ref('');
const reporting = ref(false);
const bookingOpen = ref(false);
const bookingOriginRect = ref(null);
const avatarReady = ref(false);
const avatarPulse = ref(false);
const verifiedVisible = ref(false);
const showMobileCta = ref(false);
const scrollY = ref(0);
const saveBusy = ref(false);
const saveHeartFx = ref('');

let reviewObserver = null;
let saveHeartTimer = null;

const { trackEvent } = useAnalytics();

const heroParallax = computed(() => ({
    transform: `translateY(${Math.round(scrollY.value * 0.5)}px)`,
}));

const avatarParallax = computed(() => ({
    '--avatar-parallax': `${Math.round(scrollY.value * 0.08)}px`,
}));

const loadTeacher = async () => {
    isLoading.value = true;
    loadError.value = '';
    try {
        const response = await axios.get(`/api/teachers/${props.teacherId}`);
        teacher.value = response.data.data;
        trackEvent('teacher_profile_viewed', {
            teacher_id: teacher.value?.teacher_id,
        });

        await nextTick();
        setupReviewObserver();

        requestAnimationFrame(() => {
            avatarReady.value = true;

            window.setTimeout(() => {
                avatarPulse.value = true;
                verifiedVisible.value = true;
            }, 520);

            window.setTimeout(() => {
                avatarPulse.value = false;
            }, 1200);
        });
    } catch (error) {
        const statusCode = Number(error?.response?.status || 0);

        if (statusCode === 404) {
            loadError.value = 'Teacher profile not found.';
        } else if (statusCode >= 500) {
            loadError.value = 'Teacher profile is temporarily unavailable. Please try again in a few minutes.';
        } else {
            loadError.value = 'Unable to load this teacher profile right now. Please try again.';
        }
    } finally {
        isLoading.value = false;
    }
};

const redirectToLogin = () => {
    window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname)}`;
};

const toggleSave = async () => {
    if (!currentUser.value) {
        redirectToLogin();
        return;
    }

    if (saveBusy.value) {
        return;
    }

    saveBusy.value = true;
    saveHeartFx.value = teacher.value.is_saved ? 'unsave' : 'save';
    window.clearTimeout(saveHeartTimer);
    saveHeartTimer = window.setTimeout(() => {
        saveHeartFx.value = '';
    }, 460);

    const endpoint = `/api/students/saved-teachers/${teacher.value.teacher_id}`;
    try {
        if (teacher.value.is_saved) {
            await axios.delete(endpoint);
            teacher.value.is_saved = false;
        } else {
            await axios.post(endpoint);
            teacher.value.is_saved = true;
        }
    } finally {
        saveBusy.value = false;
    }
};

const startConversation = async () => {
    if (!currentUser.value) {
        redirectToLogin();
        return;
    }

    const response = await axios.post('/api/conversations', {
        teacher_id: teacher.value.teacher_id,
        message: 'Hello teacher, I would like to connect.',
    });

    window.location.href = `/chat/${response.data.data.id}`;
};

const bookSession = (event) => {
    if (!currentUser.value) {
        redirectToLogin();
        return;
    }

    const source = event?.currentTarget;
    if (source instanceof Element) {
        const rect = source.getBoundingClientRect();
        bookingOriginRect.value = {
            left: rect.left,
            top: rect.top,
            width: rect.width,
            height: rect.height,
        };
    } else {
        bookingOriginRect.value = null;
    }

    bookingOpen.value = true;
};

const closeBooking = () => {
    bookingOpen.value = false;
    bookingOriginRect.value = null;
};

const handleBooked = (booking) => {
    bookingOpen.value = false;
    window.location.href = `/student/bookings?payment=success&booking=${booking.id}`;
};

const openReport = () => {
    if (!currentUser.value) {
        redirectToLogin();
        return;
    }

    reportOpen.value = true;
    reportReason.value = '';
};

const closeReport = () => {
    reportOpen.value = false;
    reportReason.value = '';
};

const submitReport = async () => {
    if (!teacher.value || reporting.value || !reportReason.value.trim()) return;
    reporting.value = true;
    try {
        await axios.post('/api/reports', {
            type: 'profile',
            reason: reportReason.value.trim(),
            reported_user_id: teacher.value.teacher_id,
        });
        closeReport();
        window.alert('Report submitted.');
    } finally {
        reporting.value = false;
    }
};

const setupReviewObserver = () => {
    reviewObserver?.disconnect();

    reviewObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('review-card--visible');
                    reviewObserver.unobserve(entry.target);
                }
            });
        },
        {
            threshold: 0.2,
        },
    );

    document.querySelectorAll('.review-card').forEach((card) => reviewObserver.observe(card));
};

const roundedRating = (value) => {
    return Math.max(0, Math.min(5, Math.round(Number(value) || 0)));
};

const handleScroll = () => {
    scrollY.value = window.scrollY;
    showMobileCta.value = window.scrollY > 220;
};

onMounted(async () => {
    document.body.setAttribute('data-portal', 'student');
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
    await loadTeacher();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
    reviewObserver?.disconnect();
    window.clearTimeout(saveHeartTimer);
});
</script>

<template>
    <StudentLayout>
        <div class="teacher-profile-page">
            <div v-if="isLoading" class="loading">Loading profile...</div>

            <template v-else-if="teacher">
                <div class="profile-stage" :class="{ 'is-blurred': bookingOpen || reportOpen }">
                <section class="hero">
                    <div class="hero-gradient" :style="heroParallax" />
                </section>

                <div class="profile-head">
                    <img
                        :src="teacher.avatar || '/favicon.ico'"
                        alt="Teacher avatar"
                        class="hero-avatar"
                        :class="{ 'hero-avatar--ready': avatarReady, 'hero-avatar--pulse': avatarPulse }"
                        :style="avatarParallax"
                        width="120"
                        height="120"
                        loading="lazy"
                    />

                    <div class="action-row">
                        <button class="save-btn" :class="{ 'is-saved': teacher.is_saved }" :disabled="saveBusy" @click="toggleSave">
                            <span class="save-heart-shell" :class="saveHeartFx ? `fx-${saveHeartFx}` : ''">
                                <HeartIcon class="save-heart save-heart-outline" aria-hidden="true" />
                                <HeartSolidIcon class="save-heart save-heart-fill" aria-hidden="true" />
                            </span>
                            <span>{{ teacher.is_saved ? 'Saved' : 'Save' }}</span>
                        </button>
                        <button class="report-link" @click="openReport">Report this Teacher</button>
                    </div>

                    <h1>{{ teacher.name }}</h1>

                    <span v-if="teacher.is_verified" class="verified-pill" :class="{ 'verified-pill--visible': verifiedVisible }">
                        <svg viewBox="0 0 24 24" class="verified-check-icon" aria-hidden="true">
                            <path class="verified-check-path" d="M5 12.5l4 4 10-10" />
                        </svg>
                        <span>Verified</span>
                    </span>

                    <div class="subject-row">
                        <span v-for="subject in teacher.subjects" :key="subject" class="subject-chip">{{ subject }}</span>
                    </div>

                    <p class="rating">⭐ {{ teacher.rating_avg.toFixed(1) }} out of 5 ({{ teacher.total_reviews }} reviews)</p>
                </div>

                <div class="profile-content">
                    <section class="bio-card">
                        <h2>About</h2>
                        <p>{{ teacher.bio || 'No bio shared yet.' }}</p>
                    </section>

                    <section class="chip-section">
                        <h3>Languages</h3>
                        <div class="chip-row">
                            <span v-for="language in teacher.languages" :key="language" class="neutral-chip">{{ language }}</span>
                        </div>
                    </section>

                    <section class="availability-card">
                        <h3>📅 Availability</h3>
                        <p>{{ teacher.availability_summary }}</p>
                    </section>

                    <section class="reviews">
                        <h3>Reviews</h3>
                        <article
                            v-for="(review, index) in teacher.latest_reviews"
                            :key="review.id || review.date"
                            class="review-card"
                            :style="{ '--review-delay': `${index * 80}ms` }"
                        >
                            <div class="review-stars">
                                <span
                                    v-for="starIndex in 5"
                                    :key="starIndex"
                                    class="review-star"
                                    :class="{ 'is-filled': starIndex <= roundedRating(review.rating) }"
                                    :style="{ '--star-delay': `${(starIndex - 1) * 60}ms` }"
                                >★</span>
                            </div>
                            <p class="review-name">{{ review.student_name }}</p>
                            <p class="review-comment">{{ review.comment }}</p>
                            <p class="review-date">{{ review.date }}</p>
                        </article>
                        <p v-if="!teacher.latest_reviews.length" class="no-reviews">No written reviews yet.</p>
                    </section>
                </div>

                <div class="desktop-cta">
                    <button class="message-btn" @click="startConversation">💬 Send Message</button>
                    <button class="book-btn" @click="bookSession($event)">📅 Book Session</button>
                </div>

                <div class="mobile-cta-bar" :class="{ 'mobile-cta-bar--visible': showMobileCta }">
                    <button class="message-btn" @click="startConversation">💬 Send Message</button>
                    <button class="book-btn" @click="bookSession($event)">📅 Book Session</button>
                </div>
            </div>

                <transition name="profile-modal">
                    <div v-if="reportOpen" class="modal-overlay" @click.self="closeReport">
                        <div class="modal-card">
                            <h3>Report this Teacher</h3>
                            <p>Share the reason for your report.</p>
                            <textarea v-model="reportReason" rows="4" class="modal-textarea" placeholder="Describe the issue" />
                            <div class="modal-actions">
                                <button class="secondary-button" type="button" @click="closeReport">Cancel</button>
                                <button class="primary-button" type="button" :disabled="reporting || !reportReason.trim()" @click="submitReport">
                                    {{ reporting ? 'Submitting...' : 'Submit report' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>

                <BookingModal
                    v-if="bookingOpen"
                    :teacher-id="teacher.teacher_id"
                    :teacher-name="teacher.name"
                    :teacher-avatar="teacher.avatar"
                    :subjects="teacher.subjects"
                    :origin-rect="bookingOriginRect"
                    @close="closeBooking"
                    @booked="handleBooked"
                />
            </template>

            <div v-else class="loading">{{ loadError || 'Unable to load profile.' }}</div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.teacher-profile-page {
    background: #fff8f0;
    min-height: 100vh;
    padding-bottom: 88px;
    overflow-x: clip;
}

.profile-stage {
    transition: transform 0.3s var(--s-spring), filter 0.3s ease;
}

.profile-stage.is-blurred {
    transform: scale(0.98);
    filter: blur(2px);
}

.loading {
    padding: 40px;
    font-family: Nunito, sans-serif;
}

.hero {
    position: relative;
    height: 210px;
    overflow: hidden;
}

.hero-gradient {
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, #e8553e 0%, #ffab76 100%);
    will-change: transform;
}

.profile-head {
    margin-top: -60px;
    padding: 0 20px;
    text-align: center;
    position: relative;
    z-index: 3;
}

.profile-content {
    max-width: 940px;
    margin: 16px auto 0;
    padding: 0 20px;
    display: grid;
    gap: 14px;
}

.hero-avatar {
    --avatar-parallax: 0px;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid #f5c518;
    object-fit: cover;
    margin: 0 auto 12px;
    background: #fff;
    transform: translateY(var(--avatar-parallax)) scale(0.6);
    transition: transform 620ms var(--s-spring);
}

.hero-avatar--ready {
    transform: translateY(var(--avatar-parallax)) scale(1);
}

.hero-avatar--pulse {
    animation: avatar-border-pulse 700ms ease-in-out 1;
}

h1 {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: 28px;
    color: #2d2d2d;
}

.verified-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    background: #4cb87e;
    color: #fff;
    padding: 6px 10px;
    border-radius: 999px;
    font-family: Nunito, sans-serif;
    font-size: 14px;
    transform: scale(0.72);
    opacity: 0;
}

.verified-pill--visible {
    opacity: 1;
    transform: scale(1);
    transition: transform 420ms var(--s-spring), opacity 260ms ease;
}

.verified-check-icon {
    width: 16px;
    height: 16px;
}

.verified-check-path {
    fill: none;
    stroke: currentColor;
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 24;
    stroke-dashoffset: 24;
}

.verified-pill--visible .verified-check-path {
    transition: stroke-dashoffset 420ms ease 140ms;
    stroke-dashoffset: 0;
}

.subject-row,
.chip-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
    margin-top: 12px;
}

.subject-chip {
    background: #fff3ef;
    color: #e8553e;
    padding: 6px 10px;
    border-radius: 999px;
    font-family: Nunito, sans-serif;
    font-weight: 700;
}

.rating {
    margin-top: 12px;
    color: #f59e0b;
    font-family: Nunito, sans-serif;
}

.action-row {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}

.save-btn,
.report-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    justify-content: center;
    border: none;
    background: #fff;
    color: #e8553e;
    border-radius: 999px;
    padding: 8px 14px;
    font-family: Nunito, sans-serif;
    cursor: pointer;
}

.save-btn:disabled {
    opacity: 0.8;
    cursor: wait;
}

.save-heart-shell {
    width: 18px;
    height: 18px;
    position: relative;
    display: inline-grid;
    place-items: center;
}

.save-heart {
    position: absolute;
    inset: 0;
    width: 18px;
    height: 18px;
}

.save-heart-outline {
    color: #e8553e;
}

.save-heart-fill {
    color: #ff7b67;
    fill: currentColor;
    clip-path: circle(0% at 50% 50%);
}

.save-btn.is-saved .save-heart-fill {
    clip-path: circle(50% at 50% 50%);
}

.save-heart-shell.fx-save {
    animation: save-heart-pump 420ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.save-heart-shell.fx-save .save-heart-fill {
    animation: save-heart-fill-in 420ms ease-out forwards;
}

.save-heart-shell.fx-unsave {
    animation: save-heart-pump 420ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.save-heart-shell.fx-unsave .save-heart-fill {
    animation: save-heart-fill-out 350ms ease-in forwards;
}

.report-link {
    color: #D44433;
    border: 1px solid #dbe3ef;
}

.bio-card,
.chip-section,
.reviews {
    margin: 0;
    background: #fff;
    border-radius: 20px;
    padding: 18px;
    border: 1px solid #f3e5dd;
    box-shadow: 0 4px 20px rgba(232, 85, 62, 0.08);
}

.bio-card {
    border-left: 4px solid #e8553e;
}

.bio-card h2,
.chip-section h3,
.reviews h3 {
    margin: 0 0 10px;
    font-family: 'Fredoka One', cursive;
    color: #e8553e;
}

.bio-card p,
.availability-card p,
.review-comment,
.review-date {
    font-family: Nunito, sans-serif;
    color: #374151;
}

.neutral-chip {
    background: #f3f4f6;
    color: #4b5563;
    border-radius: 999px;
    padding: 6px 10px;
    font-family: Nunito, sans-serif;
}

.availability-card {
    margin: 0;
    border-radius: 20px;
    padding: 18px;
    background: #FFF3EF;
    border: 1px solid #f3ddd4;
}

.availability-card h3 {
    margin: 0 0 8px;
    font-family: Nunito, sans-serif;
    font-weight: 700;
}

.review-card {
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: 14px;
    margin-bottom: 10px;
    opacity: 0;
    transform: translateY(20px);
}

.review-card--visible {
    animation: review-card-rise 420ms var(--s-spring) forwards;
    animation-delay: var(--review-delay);
}

.review-stars {
    display: inline-flex;
    gap: 3px;
    margin-bottom: 6px;
}

.review-star {
    position: relative;
    font-size: 18px;
    color: #e8e8e8;
    line-height: 1;
}

.review-star::after {
    content: '★';
    position: absolute;
    inset: 0;
    color: #f5c518;
    clip-path: inset(0 100% 0 0);
}

.review-card--visible .review-star.is-filled::after {
    animation: review-star-fill 360ms var(--s-spring) forwards;
    animation-delay: var(--star-delay);
}

.review-name {
    margin: 0 0 4px;
    font-weight: 700;
    font-family: Nunito, sans-serif;
}

.review-comment {
    margin: 0 0 4px;
}

.review-date {
    margin: 0;
    color: #9ca3af;
    font-size: 13px;
}

.no-reviews {
    margin: 0;
    color: #6b7280;
    font-family: Nunito, sans-serif;
}

.desktop-cta {
    position: fixed;
    right: 24px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 10px;
    z-index: 10;
}

.mobile-cta-bar {
    display: none;
}

.message-btn,
.book-btn {
    border-radius: 999px;
    padding: 12px 18px;
    cursor: pointer;
    font-family: 'Fredoka One', cursive;
    border: 2px solid #e8553e;
}

.message-btn {
    background: #fff;
    color: #e8553e;
}

.book-btn {
    background: #e8553e;
    color: #fff;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.38);
    z-index: 70;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-card {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 20px;
    padding: 22px;
    box-shadow: 0 24px 80px rgba(15, 23, 42, 0.22);
}

.modal-card h3 {
    margin: 0 0 8px;
    font-family: 'Fredoka One', cursive;
    color: #e8553e;
}

.modal-card p {
    margin: 0 0 14px;
    font-family: Nunito, sans-serif;
    color: #475569;
}

.modal-textarea {
    width: 100%;
    border: 1px solid #f0ddd5;
    border-radius: 14px;
    padding: 12px 14px;
    font: inherit;
    resize: vertical;
    outline: none;
}

.modal-actions {
    margin-top: 14px;
    display: flex;
    gap: 10px;
}

.primary-button,
.secondary-button {
    min-height: 42px;
    padding: 0 14px;
    border-radius: 14px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
}

.primary-button {
    background: #e8553e;
    color: #fff;
}

.secondary-button {
    background: #fff;
    color: #2D2D2D;
    border-color: #f0ddd5;
}

.primary-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.profile-modal-enter-active,
.profile-modal-leave-active {
    transition: transform 0.4s var(--s-spring), opacity 0.25s ease;
}

.profile-modal-enter-from,
.profile-modal-leave-to {
    opacity: 0;
    transform: translateY(20px) scale(0.94);
}

@keyframes avatar-border-pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(245, 197, 24, 0);
    }

    50% {
        box-shadow: 0 0 0 8px rgba(245, 197, 24, 0.25);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(245, 197, 24, 0);
    }
}

@keyframes review-card-rise {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes review-star-fill {
    from {
        clip-path: inset(0 100% 0 0);
    }

    to {
        clip-path: inset(0 0 0 0);
    }
}

@keyframes mobile-cta-spring {
    0% {
        opacity: 0;
        transform: translateY(100%);
    }

    75% {
        opacity: 1;
        transform: translateY(-4px);
    }

    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes save-heart-pump {
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

@keyframes save-heart-fill-in {
    from {
        clip-path: circle(0% at 50% 50%);
    }

    to {
        clip-path: circle(50% at 50% 50%);
    }
}

@keyframes save-heart-fill-out {
    from {
        clip-path: circle(50% at 50% 50%);
    }

    to {
        clip-path: circle(0% at 50% 50%);
    }
}

@media (max-width: 1024px) {
    .desktop-cta {
        display: none;
    }

    .mobile-cta-bar {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        background: #fff;
        border-top: 1px solid #e5e7eb;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 10px;
        z-index: 30;
        opacity: 0;
        transform: translateY(100%);
        pointer-events: none;
    }

    .mobile-cta-bar--visible {
        pointer-events: auto;
        animation: mobile-cta-spring 420ms var(--s-spring) forwards;
    }
}

@media (max-width: 900px) {
    .hero {
        height: 190px;
    }

    .profile-head {
        margin-top: -54px;
        padding: 0 16px;
    }

    .hero-avatar {
        width: 108px;
        height: 108px;
    }

    h1 {
        font-size: 24px;
    }

    .profile-content {
        padding: 0 14px;
        gap: 12px;
    }

    .bio-card,
    .chip-section,
    .reviews,
    .availability-card {
        padding: 16px;
    }
}

@media (max-width: 640px) {
    .teacher-profile-page {
        padding-bottom: 84px;
    }

    .hero {
        height: 174px;
    }

    .profile-head {
        margin-top: -48px;
    }

    .hero-avatar {
        width: 96px;
        height: 96px;
        border-width: 3px;
    }

    .subject-chip,
    .neutral-chip {
        padding: 5px 9px;
        font-size: 12px;
    }

    .action-row {
        gap: 8px;
    }

    .save-btn,
    .report-link {
        min-height: 40px;
        font-size: 14px;
        padding: 8px 12px;
    }

    .message-btn,
    .book-btn {
        padding: 10px 12px;
        font-size: 14px;
    }

    .mobile-cta-bar {
        gap: 6px;
        padding: 8px;
    }
}
</style>
