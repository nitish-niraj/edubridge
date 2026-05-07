<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import EmptyState from '@/Components/Shared/EmptyState.vue';
import ErrorState from '@/Components/Shared/ErrorState.vue';
import TeacherCard from '@/Components/Student/TeacherCard.vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

const teachers = ref([]);
const isLoading = ref(false);
const nextCursor = ref(null);
const hasMore = ref(true);
const loadError = ref('');

const loadSavedTeachers = async (reset = false) => {
    if (isLoading.value) return;

    if (reset) {
        teachers.value = [];
        nextCursor.value = null;
        hasMore.value = true;
    }

    isLoading.value = true;
    loadError.value = '';
    try {
        const response = await axios.get('/api/students/saved-teachers', {
            params: { 
                cursor: nextCursor.value,
                per_page: 12 
            },
        });

        const payload = response.data;
        const items = payload.data ?? [];

        if (reset) {
            teachers.value = items;
        } else {
            teachers.value = [...teachers.value, ...items];
        }

        const nextUrl = payload.links?.next;
        if (nextUrl) {
            const url = new URL(nextUrl);
            nextCursor.value = url.searchParams.get('cursor');
            hasMore.value = true;
        } else {
            nextCursor.value = null;
            hasMore.value = false;
        }
    } catch (error) {
        loadError.value = error?.response?.data?.message || 'Unable to load saved teachers right now. Please try again.';
        if (reset) {
            teachers.value = [];
        }
    } finally {
        isLoading.value = false;
    }
};

const handleToggleBookmark = (teacher) => {
    if (!teacher.is_saved) {
        teachers.value = teachers.value.filter((item) => (item.teacher_id ?? item.id) !== (teacher.teacher_id ?? teacher.id));
    }
};

onMounted(async () => {
    document.body.setAttribute('data-portal', 'student');
    await loadSavedTeachers(true);
});
</script>

<template>
    <StudentLayout>
        <div class="saved-page">
            <h1>Saved Teachers</h1>
            <p class="page-copy">
                Keep your shortlisted mentors here. Compare ratings, subjects, and teaching fit before booking your next class.
            </p>

            <div v-if="loadError && !teachers.length" class="empty-state">
                <ErrorState
                    code="503"
                    title="Saved teachers unavailable"
                    :message="loadError"
                    :show-back="false"
                />
            </div>

            <div v-else-if="!teachers.length && !isLoading" class="empty-state">
                <EmptyState
                    illustration="heart"
                    title="No saved teachers"
                    body="Browse teachers and save your favourites."
                    cta-text="Browse teachers"
                    :cta-route="route('student.teachers')"
                />
            </div>

            <div v-else class="grid">
                <TeacherCard
                    v-for="(teacher, index) in teachers"
                    :key="teacher.id"
                    :teacher="teacher"
                    :index="index"
                    @toggle-bookmark="handleToggleBookmark"
                />
            </div>

            <div v-if="hasMore && teachers.length" class="load-wrap">
                <button class="load-btn" :disabled="isLoading" @click="loadSavedTeachers(false)">
                    {{ isLoading ? 'Loading...' : 'Load More' }}
                </button>
            </div>
        </div>
    </StudentLayout>
</template>

<style scoped>
.saved-page {
    min-height: 100vh;
    padding: 24px;
    background: #fff8f0;
}

h1 {
    margin: 0 0 16px;
    font-family: 'Fredoka One', cursive;
    color: #e8553e;
}

.page-copy {
    margin: 0 0 20px;
    font-family: Nunito, sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: #4B5563;
}

.empty-state {
    text-align: center;
    background: #fff;
    border-radius: 20px;
    padding: 32px;
}

.illustration {
    font-size: 56px;
}

.empty-state p {
    font-family: Nunito, sans-serif;
    font-size: 20px;
    margin: 10px 0 14px;
}

.find-btn {
    background: #e8553e;
    color: #fff;
    border-radius: 999px;
    padding: 10px 16px;
    text-decoration: none;
    font-family: 'Fredoka One', cursive;
}

.grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
}

.card {
    background: #fff;
    border-radius: 20px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(232, 85, 62, 0.08);
    text-align: center;
}

.avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid #f5c518;
    object-fit: cover;
    margin: 0 auto 8px;
}

h3 {
    margin: 0;
    font-family: Nunito, sans-serif;
}

p {
    margin: 8px 0;
    font-family: Nunito, sans-serif;
    color: #f59e0b;
}

.teacher-topics {
    margin: 0;
    font-size: 13px;
    color: #6B7280;
}

.teacher-note {
    margin: 8px 0 14px;
    font-size: 13px;
    line-height: 1.55;
    color: #4B5563;
    min-height: 40px;
}

.view-btn,
.remove-btn,
.load-btn {
    border-radius: 999px;
    border: none;
    cursor: pointer;
    padding: 9px 12px;
    font-family: Nunito, sans-serif;
    font-weight: 700;
}

.view-btn {
    text-decoration: none;
    display: inline-block;
    background: #e8553e;
    color: #fff;
    margin-right: 8px;
}

.remove-btn {
    background: #fff3ef;
    color: #e8553e;
}

.load-wrap {
    margin-top: 20px;
    text-align: center;
}

.load-btn {
    background: #e8553e;
    color: #fff;
}

@media (max-width: 1024px) {
    .grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .grid {
        grid-template-columns: 1fr;
    }
}
</style>
