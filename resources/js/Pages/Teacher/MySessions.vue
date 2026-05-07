<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import EmptyState from '@/Components/Shared/EmptyState.vue';
import ErrorState from '@/Components/Shared/ErrorState.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const bookings = ref([]);
const earningsSummary = ref({ this_month: 0, total: 0, pending: 0 });
const loading = ref(true);
const filter = ref('all');
const loadError = ref('');

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

const formatDate = (d) => new Date(d.replace('Z', '')).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
const formatTime = (d) => new Date(d.replace('Z', '')).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
const durationMinutes = (b) => {
    if (b.video_session?.duration_minutes) return Number(b.video_session.duration_minutes);
    if (b.slot?.duration_minutes) return Number(b.slot.duration_minutes);
    return Math.max(0, Math.round((new Date(b.end_at) - new Date(b.start_at)) / 60000));
};

const canJoin = (b) => {
    if (b.status !== 'confirmed') return false;
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        return true;
    }
    const mins = (new Date(b.start_at.replace('Z', '')) - new Date()) / 60000;
    return mins <= 15;
};

const minutesUntil = (b) => {
    const mins = Math.round((new Date(b.start_at.replace('Z', '')) - new Date()) / 60000);
    if (mins <= 0) return 'Now';
    if (mins < 60) return `in ${mins} min`;
    return `in ${Math.round(mins / 60)}h`;
};

const monthEarnings = computed(() => {
    return parseFloat(earningsSummary.value.this_month || 0);
});

const monthSessions = computed(() => {
    // Use backend-calculated count from bookings API
    // Count completed bookings from current month
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();
    
    return bookings.value.filter(b => {
        if (b.status !== 'completed') return false;
        const bookingDate = new Date(b.start_at);
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

            <!-- Filter -->
            <div style="margin-bottom: 20px;">
                <select v-model="filter" @change="fetchBookings"
                    style="height: 56px; padding: 0 16px; border: 2px solid #F0E8E0; border-radius: 10px; font-family: 'Nunito', sans-serif; font-size: 18px; color: #333; background: #fff; min-width: 200px;">
                    <option value="all">All</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <!-- Loading -->
            <div v-if="loading" style="display: flex; flex-direction: column; gap: 16px;">
                <div v-for="index in 3" :key="index" class="skeleton-card skeleton" style="padding: 20px 24px; min-height: 80px;"></div>
            </div>

            <!-- Error -->
            <div v-else-if="loadError" style="background: #fff; border-radius: 12px; padding: 12px; border: 1px solid #E0E0E0;">
                <ErrorState
                    code="503"
                    title="Sessions unavailable"
                    :message="loadError"
                    :show-back="false"
                />
            </div>

            <!-- Table -->
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
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">{{ b.subject || '—' }}</td>
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

            <!-- Summary bar -->
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
    </TeacherLayout>
</template>
