<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import EmptyState from '@/Components/Shared/EmptyState.vue';
import ErrorState from '@/Components/Shared/ErrorState.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { charCount } from '@/composables/useFormValidation';

const bookings = ref([]);
const earningsSummary = ref({ this_month: 0, total: 0, pending: 0 });
const loading = ref(true);
const filter = ref('all');
const loadError = ref('');

const showReviewModal = ref(false);
const reviewTarget = ref(null);
const reviewRating = ref(0);
const reviewHover = ref(0);
const reviewComment = ref('');
const reviewSubmitting = ref(false);
const reviewError = ref('');
const reviewSuccess = ref(false);
const reviewSuccessName = ref('');

const COMMENT_MAX = 2000;
const commentCharCount = computed(() => charCount(reviewComment.value, COMMENT_MAX));

const reviewLabels = {
    1: '😞 Challenging',
    2: '😐 Could improve',
    3: '🙂 Cooperative',
    4: '😊 Great learner!',
    5: '🌟 Outstanding!',
};

const activeReviewLabel = computed(() => {
    const v = reviewHover.value || reviewRating.value;
    return reviewLabels[v] || 'Move across the stars to rate your student';
});

const fetchBookings = async () => {
    loading.value = true;
    loadError.value = '';
    try {
        const params = filter.value !== 'all' ? { status: filter.value } : {};
        const { data } = await axios.get('/api/bookings', { params });
        bookings.value = data.data || data;
        earningsSummary.value = data.earnings_summary || earningsSummary.value;
    } catch (e) {
        console.error(e);
        loadError.value = e?.response?.data?.message || 'Unable to load your sessions right now. Please try again.';
    } finally {
        loading.value = false;
    }
};

onMounted(fetchBookings);

const filteredBookings = computed(() => {
    if (filter.value === 'all') return bookings.value;
    if (filter.value === 'upcoming') return bookings.value.filter(b => ['confirmed', 'pending'].includes(b.status));
    return bookings.value.filter(b => b.status === filter.value);
});

const statusColor = (s) => ({
    pending: '#FFA726', confirmed: '#66BB6A', completed: '#42A5F5', cancelled: '#EF5350', no_show: '#BDBDBD'
}[s] || '#999');

const formatDate = (d) => {
    if (!d) return '—';
    const date = d.includes('Z') || d.includes('+') ? new Date(d) : new Date(d + 'Z');
    return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
};

const formatTime = (d) => {
    if (!d) return '—';
    const date = d.includes('Z') || d.includes('+') ? new Date(d) : new Date(d + 'Z');
    return date.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
};

const durationMinutes = (b) => {
    if (b.video_session?.duration_minutes) return Number(b.video_session.duration_minutes);
    if (b.slot?.duration_minutes) return Number(b.slot.duration_minutes);
    const start = b.start_at.includes('Z') ? new Date(b.start_at) : new Date(b.start_at + 'Z');
    const end = b.end_at.includes('Z') ? new Date(b.end_at) : new Date(b.end_at + 'Z');
    return Math.max(0, Math.round((end - start) / 60000));
};

const canJoin = (b) => {
    if (b.status !== 'confirmed') return false;
    const start = b.start_at.includes('Z') ? new Date(b.start_at) : new Date(b.start_at + 'Z');
    const now = new Date();
    const earlyWindowMins = (start - now) / 60000;
    const lateWindowMins = (now - start) / 60000;
    if (earlyWindowMins > 15 || lateWindowMins > 30) return false;

    const session = b.video_session;
    if (!session) return true;
    if (session.ended_at) return false;
    return !session.started_at || (session.started_at && !session.ended_at);
};

const minutesUntil = (b) => {
    const start = b.start_at.includes('Z') ? new Date(b.start_at) : new Date(b.start_at + 'Z');
    const mins = Math.round((start - new Date()) / 60000);
    if (mins <= 0) return 'Now';
    if (mins < 60) return `in ${mins} min`;
    return `in ${Math.round(mins / 60)}h`;
};

const myReviewFor = (b) => {
    const list = Array.isArray(b.reviews) ? b.reviews : [];
    return list.find((r) => r.reviewer_id === b.teacher_id) || null;
};

const isReviewable = (b) => {
    if (!['completed', 'no_show'].includes(b.status)) return false;
    if (myReviewFor(b)) return false;
    return true;
};

const openReviewModal = (b) => {
    reviewTarget.value = b;
    reviewRating.value = 0;
    reviewHover.value = 0;
    reviewComment.value = '';
    reviewError.value = '';
    reviewSuccess.value = false;
    reviewSuccessName.value = b.student?.name || 'the student';
    showReviewModal.value = true;
};

const closeReviewModal = () => {
    showReviewModal.value = false;
    reviewTarget.value = null;
};

const submitReview = async () => {
    reviewError.value = '';
    if (reviewRating.value === 0) {
        reviewError.value = 'Please select a star rating before submitting.';
        return;
    }
    if (!reviewTarget.value) return;
    reviewSubmitting.value = true;
    try {
        await axios.post('/api/reviews', {
            booking_id: reviewTarget.value.id,
            rating: reviewRating.value,
            comment: reviewComment.value.trim() || null,
        });
        reviewSuccess.value = true;
        await fetchBookings();
    } catch (e) {
        reviewError.value = e.response?.data?.message || 'Could not submit review. Please try again.';
    } finally {
        reviewSubmitting.value = false;
    }
};

const monthEarnings = computed(() => {
    return parseFloat(earningsSummary.value.this_month || 0);
});

const monthSessions = computed(() => {
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();

    return bookings.value.filter(b => {
        if (!['confirmed', 'completed'].includes(b.status)) return false;
        const bookingDate = b.start_at.includes('Z') ? new Date(b.start_at) : new Date(b.start_at + 'Z');
        return bookingDate.getMonth() === currentMonth && bookingDate.getFullYear() === currentYear;
    }).length;
});

const pendingRelease = computed(() => {
    return parseFloat(earningsSummary.value.pending || 0);
});
</script>

<template>
    <TeacherLayout>
        <div style="padding: 32px 40px;">
            <h1 style="font-family: 'Fredoka One', cursive; font-size: 26px; color: #E8553E; margin-bottom: 24px;">My Sessions</h1>

            <div style="margin-bottom: 20px; padding: 14px 18px; border:1px solid #F0E8E0; border-radius:12px; background:#FFF8F0;">
                <p style="margin:0; font-family:'Nunito', sans-serif; font-size:14px; line-height:1.65; color:#4B5563;">
                    Use this page to track upcoming classes, completed teaching time, and payout flow.
                    <strong style="color:#2D2D2D;">Pending release</strong> usually means the session payout is held temporarily for dispute review.
                </p>
            </div>

            <div style="margin-bottom: 20px;">
                <select v-model="filter" @change="fetchBookings"
                    style="height: 56px; padding: 0 16px; border: 2px solid #F0E8E0; border-radius: 10px; font-family: 'Nunito', sans-serif; font-size: 18px; color: #333; background: #fff; min-width: 200px;">
                    <option value="all">All</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div v-if="loading" style="display: flex; flex-direction: column; gap: 16px;">
                <div v-for="index in 3" :key="index" class="skeleton-card skeleton" style="padding: 20px 24px; min-height: 80px;"></div>
            </div>

            <div v-else-if="loadError" style="background: #fff; border-radius: 12px; padding: 12px; border: 1px solid #E0E0E0;">
                <ErrorState
                    code="503"
                    title="Sessions unavailable"
                    :message="loadError"
                    :show-back="false"
                />
            </div>

            <div v-else-if="filteredBookings.length" style="border-radius: 12px; overflow: hidden; border: 1px solid #E0E0E0;">
                <table style="width: 100%; border-collapse: collapse; font-family: 'Nunito', sans-serif;">
                    <thead>
                        <tr style="background: #FFF3EF;">
                            <th style="text-align: left; padding: 16px 20px; font-family: 'Fredoka One', cursive; font-size: 15px; color: #E8553E;">Date &amp; Time</th>
                            <th style="text-align: left; padding: 16px 20px; font-family: 'Fredoka One', cursive; font-size: 15px; color: #E8553E;">Student</th>
                            <th style="text-align: left; padding: 16px 20px; font-family: 'Fredoka One', cursive; font-size: 15px; color: #E8553E;">Subject</th>
                            <th style="text-align: left; padding: 16px 20px; font-family: 'Fredoka One', cursive; font-size: 15px; color: #E8553E;">Duration</th>
                            <th style="text-align: left; padding: 16px 20px; font-family: 'Fredoka One', cursive; font-size: 15px; color: #E8553E;">Amount</th>
                            <th style="text-align: left; padding: 16px 20px; font-family: 'Fredoka One', cursive; font-size: 15px; color: #E8553E;">Status</th>
                            <th style="text-align: center; padding: 16px 20px; font-family: 'Fredoka One', cursive; font-size: 15px; color: #E8553E;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(b, i) in filteredBookings" :key="b.id"
                            :style="{ background: i % 2 === 0 ? '#fff' : '#F5FAF7', minHeight: '64px' }">
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">
                                <div style="font-weight: 600;">{{ formatDate(b.start_at) }}</div>
                                <div style="font-size: 13px; color: #6B7280; margin-top: 2px;">{{ formatTime(b.start_at) }} – {{ formatTime(b.end_at) }}</div>
                            </td>
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">{{ b.student?.name || '—' }}</td>
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">
                                <div>{{ b.subject || '—' }}</div>
                                <span v-if="Number(b.price) === 0" style="display: inline-block; background: #FFF3EF; color: #B53A2D; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; margin-top: 2px;">
                                    🆓 Volunteer
                                </span>
                            </td>
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">
                                {{ durationMinutes(b) }} min
                            </td>
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">
                                {{ b.price > 0 ? '₹' + parseFloat(b.price || 0).toFixed(0) : 'Free' }}
                            </td>
                            <td style="padding: 14px 20px;">
                                <span :style="{
                                    display: 'inline-block', padding: '4px 12px', borderRadius: '20px', fontSize: '13px',
                                    fontWeight: 'bold', color: '#fff', background: statusColor(b.status), textTransform: 'capitalize'
                                }">{{ b.status }}</span>
                            </td>
                            <td style="padding: 14px 20px; text-align: center;">
                                <a v-if="canJoin(b)" :href="'/session/' + b.id"
                                    style="display: inline-block; padding: 10px 20px; background: #E8553E; color: #fff; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 14px;">
                                    🎥 Join Session
                                </a>
                                <button v-else-if="isReviewable(b)" type="button" @click="openReviewModal(b)"
                                    style="padding: 10px 18px; background: #F5C518; color: #2D2D2D; border: none; border-radius: 10px; cursor: pointer; font-weight: bold; font-size: 14px; font-family: 'Nunito', sans-serif;">
                                    ⭐ Rate Student
                                </button>
                                <span v-else-if="myReviewFor(b)" style="color: #4CB87E; font-size: 13px; font-weight: 600;">
                                    ✓ Rated
                                </span>
                                <span v-else-if="b.status === 'confirmed' && !canJoin(b)" style="color: #999; font-size: 13px;">
                                    Link opens {{ minutesUntil(b) }}
                                </span>
                                <span v-else style="color: #aaa; font-size: 13px;">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else style="text-align: center; padding: 30px 12px; background: #fff; border-radius: 12px; border: 1px solid #E0E0E0;">
                <EmptyState
                    v-if="filter === 'all' || filter === 'upcoming'"
                    illustration="calendar"
                    title="No sessions"
                    body="You don't have any sessions matching this filter."
                />
                <span v-else style="color: #999; font-family: 'Nunito', sans-serif;">No {{ filter }} sessions found.</span>
            </div>

            <div style="margin-top: 24px; padding: 16px 24px; background: #FFF3EF; border-radius: 12px; font-family: 'Fredoka One', cursive; font-size: 18px; color: #E8553E; display: flex; gap: 32px; flex-wrap: wrap;">
                <span>Sessions this month: <strong>{{ monthSessions }}</strong></span>
                <span>Earnings this month: <strong>₹{{ monthEarnings.toFixed(0) }}</strong></span>
                <span>Total earnings: <strong>₹{{ parseFloat(earningsSummary.total || 0).toFixed(0) }}</strong></span>
                <span>Pending release (temporary hold): <strong>₹{{ pendingRelease.toFixed(0) }}</strong></span>
            </div>

            <p style="margin:10px 0 0; font-family:'Nunito', sans-serif; font-size:13px; color:#6B7280; line-height:1.6;">
                Tip: keep session notes and attendance evidence updated for faster dispute resolution and payout release.
            </p>
        </div>

        <!-- Rate Student Modal -->
        <div v-if="showReviewModal" class="rate-modal-overlay" @click.self="closeReviewModal">
            <div class="rate-modal" role="dialog" aria-modal="true" aria-label="Rate Student">
                <div v-if="reviewSuccess" class="rate-success">
                    <div class="success-emoji">🙏</div>
                    <h2>Thanks for the feedback!</h2>
                    <p>Your review of {{ reviewSuccessName }} helps the community.</p>
                    <button type="button" class="rate-submit-btn" @click="closeReviewModal">Close</button>
                </div>

                <div v-else>
                    <div class="rate-header">
                        <div>
                            <p class="rate-kicker">Post-Session</p>
                            <h2 class="rate-title">Rate {{ reviewTarget?.student?.name || 'Student' }}</h2>
                        </div>
                        <button type="button" class="rate-close" @click="closeReviewModal" aria-label="Close">×</button>
                    </div>

                    <div class="rate-body">
                        <div class="rate-summary">
                            <div class="rate-avatar">{{ (reviewTarget?.student?.name || 'S').charAt(0) }}</div>
                            <div>
                                <p class="rate-name">{{ reviewTarget?.student?.name || 'Student' }}</p>
                                <p class="rate-meta">
                                    {{ reviewTarget?.subject || 'Session' }} ·
                                    {{ reviewTarget ? formatDate(reviewTarget.start_at) : '' }}
                                </p>
                            </div>
                        </div>

                        <div class="stars-row" @mouseleave="reviewHover = 0" role="radiogroup" aria-label="Student rating">
                            <button
                                v-for="s in 5"
                                :key="s"
                                type="button"
                                class="rate-star-btn"
                                :class="{
                                    'is-filled': (reviewHover || reviewRating) >= s,
                                    'is-hovered': reviewHover === s,
                                }"
                                :aria-label="`Rate ${s} out of 5 stars`"
                                :aria-pressed="reviewRating === s ? 'true' : 'false'"
                                @click="reviewRating = s; reviewError = '';"
                                @mouseenter="reviewHover = s"
                            >
                                ★
                            </button>
                        </div>

                        <p class="rate-label">{{ activeReviewLabel }}</p>

                        <div style="position:relative;">
                            <textarea
                                v-model="reviewComment"
                                rows="4"
                                placeholder="Share a few words about how the session went…"
                                class="rate-comment"
                                maxlength="2000"
                                aria-label="Comment about the student"
                            />
                            <div class="rate-counter">{{ commentCharCount }}</div>
                        </div>

                        <div v-if="reviewError" role="alert" class="rate-error">{{ reviewError }}</div>

                        <div class="rate-actions">
                            <button type="button" class="rate-cancel" @click="closeReviewModal">Cancel</button>
                            <button
                                type="button"
                                class="rate-submit-btn"
                                :disabled="reviewRating === 0 || reviewSubmitting"
                                @click="submitReview"
                            >
                                {{ reviewSubmitting ? 'Submitting…' : 'Submit Review' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </TeacherLayout>
</template>

<style scoped>
.rate-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 999;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.rate-modal {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
    padding: 24px 24px 20px;
    font-family: 'Nunito', sans-serif;
}

.rate-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.rate-kicker {
    margin: 0;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    color: #9CA3AF;
    font-weight: 800;
}

.rate-title {
    margin: 4px 0 0;
    font-family: 'Fredoka One', cursive;
    font-size: 22px;
    color: #E8553E;
}

.rate-close {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 999px;
    background: #FFF8F0;
    color: #9CA3AF;
    cursor: pointer;
    font-size: 24px;
    line-height: 0;
}

.rate-close:hover {
    background: #fee2e2;
    color: #be123c;
}

.rate-body {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.rate-summary {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #FFF8F0;
    border: 1px solid #F0E8E0;
    border-radius: 14px;
    padding: 10px 14px;
}

.rate-avatar {
    width: 42px;
    height: 42px;
    border-radius: 999px;
    background: #E8553E;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 16px;
    flex-shrink: 0;
}

.rate-name {
    margin: 0;
    font-weight: 800;
    font-size: 15px;
    color: #2D2D2D;
}

.rate-meta {
    margin: 2px 0 0;
    font-size: 12px;
    color: #6B7280;
}

.stars-row {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin: 10px 0 4px;
}

.rate-star-btn {
    border: none;
    background: none;
    cursor: pointer;
    padding: 4px;
    font-size: 38px;
    line-height: 1;
    color: #DDD;
    transition: transform 0.18s ease, color 0.18s ease;
}

.rate-star-btn.is-filled {
    color: #F5C518;
}

.rate-star-btn.is-hovered {
    transform: translateY(-2px) scale(1.18);
}

.rate-label {
    margin: 0 0 6px;
    text-align: center;
    font-size: 14px;
    color: #555;
    font-weight: 700;
    min-height: 22px;
}

.rate-comment {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #f0ddd5;
    border-radius: 12px;
    font: inherit;
    font-size: 14px;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
    color: #2D2D2D;
}

.rate-comment:focus {
    border-color: #E8553E;
}

.rate-counter {
    text-align: right;
    font-size: 12px;
    color: #9CA3AF;
    margin-top: 2px;
}

.rate-error {
    color: #E8553E;
    font-size: 13px;
}

.rate-actions {
    display: flex;
    gap: 10px;
    margin-top: 4px;
}

.rate-cancel {
    flex: 1;
    padding: 12px;
    border: 2px solid #e2e8f0;
    background: #fff;
    border-radius: 14px;
    cursor: pointer;
    font-weight: 800;
    font-size: 14px;
    color: #2D2D2D;
}

.rate-submit-btn {
    flex: 1.4;
    padding: 12px;
    border: none;
    background: #E8553E;
    color: #fff;
    border-radius: 14px;
    cursor: pointer;
    font-weight: 800;
    font-size: 14px;
    font-family: 'Nunito', sans-serif;
}

.rate-submit-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.rate-success {
    text-align: center;
    padding: 8px 0 4px;
}

.success-emoji {
    font-size: 56px;
    margin-bottom: 8px;
}

.rate-success h2 {
    margin: 0;
    font-family: 'Fredoka One', cursive;
    font-size: 24px;
    color: #E8553E;
}

.rate-success p {
    margin: 6px 0 16px;
    color: #555;
    font-size: 14px;
}
</style>
