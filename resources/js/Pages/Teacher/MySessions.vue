<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const bookings = ref([]);
const loading = ref(true);
const filter = ref('all');

const fetchBookings = async () => {
    loading.value = true;
    try {
        const params = filter.value !== 'all' ? { status: filter.value } : {};
        const { data } = await axios.get('/api/bookings', { params });
        bookings.value = data.data || data;
    } catch (e) {
        console.error(e);
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

const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
const formatTime = (d) => new Date(d).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });

const canJoin = (b) => {
    if (b.status !== 'confirmed') return false;
    const mins = (new Date(b.start_at) - new Date()) / 60000;
    return mins <= 30;
};

const monthEarnings = computed(() => {
    const now = new Date();
    return bookings.value
        .filter(b => b.status === 'completed' && new Date(b.start_at).getMonth() === now.getMonth())
        .reduce((sum, b) => sum + parseFloat(b.teacher_payout || 0), 0);
});

const monthSessions = computed(() => {
    const now = new Date();
    return bookings.value
        .filter(b => b.status === 'completed' && new Date(b.start_at).getMonth() === now.getMonth())
        .length;
});

const pendingRelease = computed(() => {
    return bookings.value
        .filter(b => b.payment_status === 'held')
        .reduce((sum, b) => sum + parseFloat(b.teacher_payout || 0), 0);
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
            <div v-if="loading" style="text-align: center; padding: 60px; color: #999; font-family: 'Nunito', sans-serif;">Loading sessions...</div>

            <!-- Table -->
            <div v-else-if="filteredBookings.length" style="border-radius: 12px; overflow: hidden; border: 1px solid #E0E0E0;">
                <table style="width: 100%; border-collapse: collapse; font-family: 'Nunito', sans-serif;">
                    <thead>
                        <tr style="background: #FFF3EF;">
                            <th style="text-align: left; padding: 16px 20px; font-family: 'Fredoka One', cursive; font-size: 15px; color: #E8553E;">Date</th>
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
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">{{ formatDate(b.start_at) }}</td>
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">{{ b.student?.name || '—' }}</td>
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">{{ b.subject || '—' }}</td>
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">
                                {{ b.video_session?.duration_minutes ? b.video_session.duration_minutes + ' min' : '60 min' }}
                            </td>
                            <td style="padding: 14px 20px; font-size: 15px; color: #333;">
                                {{ b.price > 0 ? '₹' + parseFloat(b.teacher_payout || 0).toFixed(0) : 'Free' }}
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
                                <span v-else style="color: #aaa; font-size: 13px;">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else style="text-align: center; padding: 60px; color: #999; font-family: 'Nunito', sans-serif;">
                No sessions found.
            </div>

            <!-- Summary bar -->
            <div style="margin-top: 24px; padding: 16px 24px; background: #FFF3EF; border-radius: 12px; font-family: 'Fredoka One', cursive; font-size: 18px; color: #E8553E; display: flex; gap: 32px; flex-wrap: wrap;">
                <span>Sessions this month: <strong>{{ monthSessions }}</strong></span>
                <span>Earnings this month: <strong>₹{{ monthEarnings.toFixed(0) }}</strong></span>
                <span>Pending release (temporary hold): <strong>₹{{ pendingRelease.toFixed(0) }}</strong></span>
            </div>

            <p style="margin:10px 0 0; font-family:'Nunito', sans-serif; font-size:13px; color:#6B7280; line-height:1.6;">
                Tip: keep session notes and attendance evidence updated for faster dispute resolution and payout release.
            </p>
        </div>
    </TeacherLayout>
</template>
