<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import { useAnalytics } from '@/composables/useAnalytics';
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import confetti from 'canvas-confetti';
import { charCount } from '@/composables/useFormValidation';

const props = defineProps({ bookingId: Number });

const rating       = ref(0);
const hoverRating  = ref(0);
const hoveredStar  = ref(0);
const comment      = ref('');
const submitting   = ref(false);
const submitted    = ref(false);
const submitError  = ref('');   // inline error instead of alert()
const ratingError  = ref('');
const { trackEvent } = useAnalytics();

const COMMENT_MAX = 2000;
const commentCharCount = computed(() => charCount(comment.value, COMMENT_MAX));

const labels = {
    1: '😞 Disappointing',
    2: '😐 Could be better',
    3: '🙂 Decent',
    4: '😊 Great session!',
    5: '🌟 Outstanding!',
};

const activeRating = computed(() => hoverRating.value || rating.value);
const activeLabel  = computed(() => labels[activeRating.value] || 'Move across the stars to rate your session');

const onStarEnter = (star) => { hoverRating.value = star; hoveredStar.value = star; };
const onStarLeave = ()     => { hoverRating.value = 0; hoveredStar.value = 0; };

const submit = async () => {
    submitError.value = '';
    ratingError.value = '';
    if (rating.value === 0) {
        ratingError.value = 'Please select a star rating before submitting.';
        return;
    }
    submitting.value = true;
    try {
        await axios.post('/api/reviews', {
            booking_id: props.bookingId,
            rating:     rating.value,
            comment:    comment.value.trim() || null,
        });
        submitted.value = true;
        trackEvent('session_completed', {
            booking_id: props.bookingId,
            rating:     rating.value,
        });
        confetti({ particleCount: 120, spread: 80, origin: { y: 0.6 } });
    } catch (e) {
        // Rulebook £25: no alert() — display inline error
        submitError.value = e.response?.data?.message || 'Could not submit review. Please try again.';
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <StudentLayout>
        <div class="review-shell">
            <div v-if="submitted" class="review-success">
                <div class="success-emoji">🙏</div>
                <h1>Thank you!</h1>
                <p>Your review helps other students find great teachers.</p>
                <Link :href="route('student.bookings')" class="success-link">Back to Bookings</Link>
            </div>

            <div v-else class="review-card">
                <h1>How was your session? ⭐</h1>

                <!-- Stars + rating error (Rulebook §24: no silent failures) -->
                <div class="stars-row" @mouseleave="onStarLeave" role="radiogroup" aria-label="Session rating">
                    <button
                        v-for="s in 5"
                        :key="s"
                        type="button"
                        class="star-button"
                        :class="{
                            'is-filled': activeRating >= s,
                            'is-hovered': hoveredStar === s,
                        }"
                        :aria-label="`Rate ${s} out of 5 stars`"
                        :aria-pressed="rating === s ? 'true' : 'false'"
                        @click="rating = s; ratingError = '';"
                        @mouseenter="onStarEnter(s)"
                    >
                        <span class="star-glyph">★</span>
                    </button>
                </div>

                <!-- Rating error -->
                <div v-if="ratingError" role="alert" style="color:#e8553e; font-family:Nunito,sans-serif; font-size:14px; margin-bottom:8px;">
                    {{ ratingError }}
                </div>

                <div class="label-stage">
                    <Transition name="label-flip" mode="out-in">
                        <p :key="activeLabel" class="rating-label">{{ activeLabel }}</p>
                    </Transition>
                </div>

                <!-- Comment with char counter (Rulebook §8) -->
                <div style="position:relative;">
                    <textarea
                        v-model="comment"
                        rows="5"
                        placeholder="Tell us more about your experience..."
                        class="comment-box"
                        maxlength="2000"
                        aria-label="Write a comment about your session"
                        aria-describedby="review-char-count"
                    />
                    <div id="review-char-count" style="text-align:right; font-family:Nunito,sans-serif; font-size:12px; color:#aaa; margin-top:2px;">
                        {{ commentCharCount }}
                    </div>
                </div>

                <!-- Submit error (Rulebook §25: no alert()) -->
                <div v-if="submitError" role="alert" style="color:#e8553e; font-family:Nunito,sans-serif; font-size:14px; margin-bottom:12px;">
                    {{ submitError }}
                </div>

                <button
                    type="button"
                    class="submit-btn"
                    :disabled="rating === 0 || submitting"
                    @click="submit"
                >
                    {{ submitting ? 'Submitting…' : 'Submit Review' }}
                </button>

                <Link :href="route('student.bookings')" class="skip-link">Skip for now</Link>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.review-shell {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff8f0;
    padding: 24px;
}

.review-success,
.review-card {
    width: min(480px, 100%);
    text-align: center;
}

.review-success {
    max-width: 400px;
}

.success-emoji {
    font-size: 64px;
    margin-bottom: 16px;
}

.review-success h1,
.review-card h1 {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: 28px;
    color: #e8553e;
}

.review-success p {
    margin: 8px 0 0;
    font-family: Nunito, sans-serif;
    font-size: 16px;
    color: #777;
}

.success-link {
    display: inline-block;
    margin-top: 24px;
    padding: 12px 32px;
    background: #e8553e;
    color: #fff;
    border-radius: 20px;
    text-decoration: none;
    font-family: Nunito, sans-serif;
    font-weight: 700;
}

.review-card {
    background: #fff;
    border-radius: 20px;
    padding: 40px 32px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
}

.stars-row {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin: 24px 0 12px;
}

.star-button {
    border: none;
    background: none;
    cursor: pointer;
    padding: 4px;
}

.star-glyph {
    position: relative;
    display: inline-block;
    font-size: 48px;
    line-height: 1;
    color: #ddd;
    transform: translateY(0) scale(1);
}

.star-button.is-filled .star-glyph {
    color: #f5c518;
}

.star-button.is-hovered .star-glyph {
    animation: star-hover-bounce 220ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.star-button.is-filled .star-glyph::after {
    content: '';
    position: absolute;
    top: 0;
    left: -130%;
    width: 70%;
    height: 100%;
    background: linear-gradient(110deg, transparent 0%, rgba(255, 255, 255, 0.75) 50%, transparent 100%);
    transform: skewX(-20deg);
    animation: star-shimmer 1s linear infinite;
    pointer-events: none;
}

.label-stage {
    min-height: 34px;
    margin-bottom: 20px;
    perspective: 900px;
}

.rating-label {
    margin: 0;
    font-family: Nunito, sans-serif;
    font-size: 16px;
    color: #555;
    font-weight: 700;
}

.label-flip-enter-active,
.label-flip-leave-active {
    transition: transform 220ms ease, opacity 170ms ease;
    transform-style: preserve-3d;
}

.label-flip-enter-from {
    opacity: 0;
    transform: rotateX(-90deg);
}

.label-flip-leave-to {
    opacity: 0;
    transform: rotateX(90deg);
}

.comment-box {
    width: 100%;
    padding: 14px;
    border: 2px solid #f0ddd5;
    border-radius: 12px;
    font-family: Nunito, sans-serif;
    font-size: 15px;
    resize: vertical;
    margin-bottom: 20px;
    box-sizing: border-box;
    outline: none;
}

.comment-box:focus {
    border-color: #e8553e;
}

.submit-btn {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 20px;
    background: #e8553e;
    color: #fff;
    font-family: Nunito, sans-serif;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

.submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.skip-link {
    display: block;
    margin-top: 14px;
    color: #999;
    font-family: Nunito, sans-serif;
    font-size: 14px;
}

@keyframes star-hover-bounce {
    0% {
        transform: translateY(0) scale(1);
    }

    100% {
        transform: translateY(-4px) scale(1.2);
    }
}

@keyframes star-shimmer {
    0% {
        left: -130%;
    }

    100% {
        left: 130%;
    }
}
</style>
