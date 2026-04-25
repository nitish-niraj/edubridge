<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import {
    EyeIcon,
    EyeSlashIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const reviews = ref({ data: [] });
const flaggedOnly = ref(false);
const loading = ref(false);
const visibilityLoadingId = ref(null);
const visibilityFxId = ref(null);

let visibilityFxTimer = null;

const fetchReviews = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/reviews', {
            params: { flagged_only: flaggedOnly.value ? 1 : 0 },
        });
        reviews.value = data;
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    document.body.setAttribute('data-portal', 'admin');
    fetchReviews();
});

onBeforeUnmount(() => {
    clearTimeout(visibilityFxTimer);
});

const triggerVisibilityFx = (reviewId) => {
    visibilityFxId.value = null;

    requestAnimationFrame(() => {
        visibilityFxId.value = reviewId;
        clearTimeout(visibilityFxTimer);
        visibilityFxTimer = window.setTimeout(() => {
            if (visibilityFxId.value === reviewId) {
                visibilityFxId.value = null;
            }
        }, 200);
    });
};

const toggleVisibility = async (review, index) => {
    if (visibilityLoadingId.value === review.id) {
        return;
    }

    visibilityLoadingId.value = review.id;
    triggerVisibilityFx(review.id);

    try {
        const { data } = await axios.patch(`/api/admin/reviews/${review.id}/visibility`);
        reviews.value.data[index].is_visible = data.is_visible;
        triggerVisibilityFx(review.id);
    } finally {
        visibilityLoadingId.value = null;
    }
};

const initials = (name = '') => {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    return (parts.slice(0, 2).map((part) => part[0]).join('') || '?').toUpperCase();
};

const truncate = (text = '', limit = 90) => {
    if (!text) return 'No comment';
    return text.length > limit ? `${text.slice(0, limit)}…` : text;
};

const formatDate = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

const ratingLabel = (value) => Number(value || 0).toFixed(1);

const visibleCount = computed(() => reviews.value.data.filter((review) => review.is_visible).length);
</script>

<template>
    <AdminLayout>
        <div class="reviews-page">
            <div class="page-header">
                <div>
                    <p class="eyebrow">Review moderation</p>
                    <h1>Reviews</h1>
                </div>
                <label class="toggle-chip">
                    <input v-model="flaggedOnly" type="checkbox" @change="fetchReviews" />
                    Show flagged only
                </label>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <span class="stat-label">Visible</span>
                    <strong>{{ visibleCount }}</strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Suspicious</span>
                    <strong>{{ reviews.data.filter((review) => review.is_flagged).length }}</strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Total</span>
                    <strong>{{ reviews.data.length }}</strong>
                </div>
            </div>

            <div class="card table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Teacher</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Visibility</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(review, index) in reviews.data" :key="review.id" class="table-row">
                            <td>
                                <div class="person-cell">
                                    <div class="avatar">
                                        <img v-if="review.reviewer?.avatar" :src="review.reviewer.avatar" alt="Reviewer avatar" width="40" height="40" />
                                        <span v-else>{{ initials(review.reviewer?.name) }}</span>
                                    </div>
                                    <div>
                                        <div class="person-name">{{ review.reviewer?.name }}</div>
                                        <div class="person-sub">{{ review.reviewer?.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="person-cell">
                                    <div class="avatar">
                                        <img v-if="review.reviewee?.avatar" :src="review.reviewee.avatar" alt="Teacher avatar" width="40" height="40" />
                                        <span v-else>{{ initials(review.reviewee?.name) }}</span>
                                    </div>
                                    <div>
                                        <div class="person-name">{{ review.reviewee?.name }}</div>
                                        <div class="person-sub">{{ review.reviewee?.email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="rating-cell">
                                    <span class="rating-pill">{{ ratingLabel(review.rating) }}</span>
                                    <div class="rating-star">★</div>
                                </div>
                            </td>
                            <td>
                                <div class="comment-cell">
                                    <span v-if="review.is_flagged" class="suspicious-badge">
                                        <ExclamationTriangleIcon class="icon-18" />
                                        Suspicious
                                    </span>
                                    <p>{{ truncate(review.comment) }}</p>
                                </div>
                            </td>
                            <td>{{ formatDate(review.created_at) }}</td>
                            <td>
                                <button
                                    class="visibility-button"
                                    type="button"
                                    :disabled="visibilityLoadingId === review.id"
                                    @click="toggleVisibility(review, index)"
                                >
                                    <component :is="review.is_visible ? EyeIcon : EyeSlashIcon" class="icon-18" />
                                    <span
                                        class="visibility-badge"
                                        :class="[
                                            review.is_visible ? 'is-visible' : 'is-hidden',
                                            visibilityFxId === review.id ? 'refocus' : '',
                                        ]"
                                    >
                                        {{ review.is_visible ? 'Visible' : 'Hidden' }}
                                    </span>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!reviews.data.length && !loading">
                            <td colspan="6" class="empty-state">No reviews available.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.reviews-page {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.page-header {
    display: flex;
    align-items: start;
    justify-content: space-between;
    gap: 16px;
}

.eyebrow {
    margin: 0 0 6px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #9CA3AF;
}

h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 800;
    color: #2D2D2D;
}

.toggle-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border: 1px solid #dbe3ef;
    border-radius: 999px;
    background: #fff;
    font-size: 13px;
    font-weight: 700;
    color: #2D2D2D;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.stat-card {
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #e5ebf3;
    border-radius: 16px;
}

.stat-label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #9CA3AF;
}

.stat-card strong {
    display: block;
    margin-top: 6px;
    font-size: 20px;
    color: #2D2D2D;
}

.card {
    background: #fff;
    border: 1px solid #e5ebf3;
    border-radius: 18px;
    box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06);
}

.table-card {
    overflow: hidden;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: #f8fbff;
}

.data-table th {
    padding: 14px 16px;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #9CA3AF;
}

.table-row {
    border-top: 1px solid #eef2f7;
}

.data-table td {
    padding: 12px 16px;
    vertical-align: middle;
}

.person-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.avatar {
    width: 40px;
    height: 40px;
    border-radius: 14px;
    overflow: hidden;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #E8553E 0%, #D44433 100%);
    color: #fff;
    font-size: 13px;
    font-weight: 800;
    flex: 0 0 auto;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.person-name {
    font-weight: 800;
    color: #2D2D2D;
}

.person-sub {
    font-size: 13px;
    color: #9CA3AF;
}

.rating-cell {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.rating-pill {
    min-width: 48px;
    padding: 5px 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #D44433;
    font-size: 13px;
    font-weight: 800;
    text-align: center;
}

.rating-star {
    color: #f59e0b;
    font-size: 18px;
}

.comment-cell {
    max-width: 360px;
}

.comment-cell p {
    margin: 0;
    color: #2D2D2D;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.suspicious-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    padding: 4px 9px;
    border-radius: 999px;
    background: #fffbeb;
    color: #b45309;
    font-size: 12px;
    font-weight: 800;
}

.icon-18 {
    width: 18px;
    height: 18px;
}

.visibility-button {
    min-height: 38px;
    padding: 0 12px;
    border: 1px solid #dbe3ef;
    border-radius: 12px;
    background: #fff;
    color: #2D2D2D;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.visibility-button:disabled {
    cursor: wait;
}

.visibility-badge {
    min-width: 66px;
    text-align: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 800;
}

.visibility-badge.is-visible {
    background: #ecfdf5;
    color: #047857;
}

.visibility-badge.is-hidden {
    background: #FFF8F0;
    color: #475569;
}

.visibility-badge.refocus {
    animation: visibility-refocus 200ms ease both;
}

@keyframes visibility-refocus {
    0% {
        filter: blur(2px);
        opacity: 0.72;
    }

    100% {
        filter: blur(0);
        opacity: 1;
    }
}

.empty-state {
    padding: 34px 16px;
    text-align: center;
    color: #9CA3AF;
}

@media (max-width: 900px) {
    .page-header {
        flex-direction: column;
    }

    .stats-row {
        grid-template-columns: 1fr;
    }
}
</style>
