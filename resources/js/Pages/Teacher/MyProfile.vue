<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    profile: { type: Object, default: () => ({}) },
    latest_reviews: { type: Array, default: () => [] },
});

const roundedRating = (value) => Math.max(0, Math.min(5, Math.round(Number(value) || 0)));
</script>

<template>
    <Head title="My Profile" />

    <TeacherLayout page-title="My Profile">
        <section class="panel">
            <header class="panel-header">
                <h2>Profile</h2>
                <Link :href="route('teacher.profile.step', { step: 1 })" class="action-link">Edit profile</Link>
            </header>

            <div class="profile-grid">
                <div class="avatar">
                    <img :src="props.user?.avatar || '/favicon.ico'" alt="Profile avatar" />
                </div>

                <div class="profile-meta">
                    <p class="name">{{ props.user?.name || 'Teacher' }}</p>
                    <p class="email">{{ props.user?.email || '' }}</p>
                    <p class="subtle" v-if="props.profile?.subjects?.length">
                        Subjects: <strong>{{ props.profile.subjects.join(', ') }}</strong>
                    </p>
                    <p class="subtle" v-if="props.profile?.languages?.length">
                        Languages: <strong>{{ props.profile.languages.join(', ') }}</strong>
                    </p>
                    <p class="subtle">
                        Rating: <strong>{{ Number(props.profile?.rating_avg || 0).toFixed(1) }}</strong>
                        ({{ Number(props.profile?.total_reviews || 0) }} reviews)
                    </p>
                </div>
            </div>
        </section>

        <section class="panel">
            <header class="panel-header">
                <h2>Written reviews</h2>
            </header>

            <div v-if="props.latest_reviews.length" class="reviews-grid">
                <article v-for="review in props.latest_reviews" :key="review.id" class="review-card">
                    <div class="stars" aria-hidden="true">
                        <span
                            v-for="starIndex in 5"
                            :key="starIndex"
                            class="star"
                            :class="{ filled: starIndex <= roundedRating(review.rating) }"
                        >★</span>
                    </div>
                    <p class="reviewer">{{ review.student_name || 'Student' }}</p>
                    <p class="comment">{{ review.comment }}</p>
                    <p class="date">{{ review.date }}</p>
                </article>
            </div>

            <p v-else class="empty">No written reviews yet.</p>
        </section>
    </TeacherLayout>
</template>

<style scoped>
.panel {
    border: 1px solid #f0e8e0;
    border-radius: 14px;
    background: #fff;
    padding: 14px;
    margin-bottom: 14px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.panel-header h2 {
    margin: 0;
    font-size: 20px;
    color: #2D2D2D;
}

.action-link {
    color: #E8553E;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
}

.profile-grid {
    display: grid;
    grid-template-columns: 84px 1fr;
    gap: 12px;
    align-items: center;
}

.avatar img {
    width: 84px;
    height: 84px;
    border-radius: 16px;
    object-fit: cover;
    border: 1px solid #f0e8e0;
}

.name {
    margin: 0;
    font-size: 20px;
    font-weight: 900;
    color: #2D2D2D;
}

.email {
    margin: 4px 0 0;
    color: #64748B;
    font-size: 14px;
}

.subtle {
    margin: 8px 0 0;
    color: #475569;
    font-size: 14px;
}

.reviews-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.review-card {
    border: 1px solid #f0e8e0;
    border-radius: 12px;
    padding: 12px;
    background: #fff8f0;
}

.stars {
    display: inline-flex;
    gap: 3px;
    margin-bottom: 6px;
}

.star {
    color: #e5e7eb;
}

.star.filled {
    color: #f5c518;
}

.reviewer {
    margin: 0 0 6px;
    font-weight: 900;
    color: #2D2D2D;
}

.comment {
    margin: 0 0 6px;
    color: #374151;
    font-size: 14px;
    line-height: 1.45;
    white-space: pre-wrap;
}

.date {
    margin: 0;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 700;
}

.empty {
    margin: 0;
    color: #64748B;
    font-size: 14px;
}

@media (max-width: 900px) {
    .reviews-grid {
        grid-template-columns: 1fr;
    }
}
</style>

