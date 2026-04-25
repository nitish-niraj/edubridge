<script setup>
import StudentLayout from '@/Layouts/StudentLayout.vue';
import EmptyState from '@/Components/Shared/EmptyState.vue';
import ErrorState from '@/Components/Shared/ErrorState.vue';
import { useAnalytics } from '@/composables/useAnalytics';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const bookings = ref([]);
const loading = ref(true);
const tab = ref('upcoming');

// Read payment result from URL
const urlParams = new URLSearchParams(window.location.search);
const paymentStatus = ref(urlParams.get('payment'));
const paymentBookingId = ref(urlParams.get('booking'));
const showBanner = ref(!!paymentStatus.value);
const showSkeleton = computed(() => loading.value && bookings.value.length === 0);
const loadError = ref('');
const { trackEvent } = useAnalytics();

// Polling for pending payments
let pollTimer = null;

const fetchBookings = async () => {
    loading.value = true;
    loadError.value = '';
    try {
        const { data } = await axios.get('/api/bookings');
        bookings.value = data.data || data;
    } catch (e) {
        loadError.value = e?.response?.data?.message || 'Unable to load your bookings right now. Please try again.';
        bookings.value = [];
    }
    loading.value = false;
};

onMounted(() => {
    fetchBookings();

    if (paymentStatus.value === 'success') {
        trackEvent('booking_confirmed', {
            booking_id: paymentBookingId.value || null,
            source: 'callback',
        });
    }

    // Auto-dismiss banner after 8s
    if (showBanner.value && paymentStatus.value !== 'pending') {
        setTimeout(() => showBanner.value = false, 8000);
    }
    // Poll for pending
    if (paymentStatus.value === 'pending' && paymentBookingId.value) {
        pollTimer = setInterval(async () => {
            try {
                const { data } = await axios.get(`/api/bookings/${paymentBookingId.value}`);
                if (data.status === 'confirmed') {
                    paymentStatus.value = 'success';
                    showBanner.value = true;
                    trackEvent('booking_confirmed', {
                        booking_id: data.id,
                        source: 'polling',
                    });
                    clearInterval(pollTimer);
                    fetchBookings();
                } else if (data.payment_status === 'failed' || data.status === 'cancelled') {
                    paymentStatus.value = 'failed';
                    clearInterval(pollTimer);
                }
            } catch (e) {}
        }, 10000);
    }
});

const filtered = computed(() => {
    if (tab.value === 'upcoming') return bookings.value.filter(b => ['confirmed', 'pending'].includes(b.status));
    if (tab.value === 'completed') return bookings.value.filter(b => b.status === 'completed');
    if (tab.value === 'cancelled') return bookings.value.filter(b => b.status === 'cancelled');
    return bookings.value;
});

const statusColor = (s) => ({
    pending: '#FFA726', confirmed: '#66BB6A', completed: '#42A5F5', cancelled: '#EF5350', no_show: '#BDBDBD'
}[s] || '#999');

const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
const formatTime = (d) => new Date(d).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });

const canJoin = (b) => {
    if (b.status !== 'confirmed') return false;
    const mins = (new Date(b.start_at) - new Date()) / 60000;
    return mins <= 15;
};

const minutesUntil = (b) => {
    const mins = Math.round((new Date(b.start_at) - new Date()) / 60000);
    if (mins <= 0) return 'Now';
    if (mins < 60) return `in ${mins} min`;
    return `in ${Math.round(mins / 60)}h`;
};

const cancelBooking = async (b) => {
    if (!confirm('Are you sure you want to cancel this booking?')) return;
    try {
        await axios.patch(`/api/bookings/${b.id}/cancel`);
        fetchBookings();
    } catch (e) {
        alert(e.response?.data?.message || 'Cancel failed');
    }
};

const retryPayment = async (b) => {
    try {
        const { data } = await axios.post('/api/payments/initiate', { booking_id: b.id });
        window.location.href = data.redirect_url;
    } catch (e) {
        alert(e.response?.data?.message || 'Payment initiation failed');
    }
};
</script>

<template>
    <StudentLayout>
        <div style="max-width: 900px; margin: 0 auto; padding: 32px 24px;">
            <h1 style="font-family: 'Fredoka One', cursive; font-size: 26px; color: #E8553E; margin-bottom: 24px;">My Bookings</h1>

            <!-- Payment banners -->
            <div v-if="showBanner && paymentStatus === 'success'"
                style="background: #E8F5E9; color: #2E7D32; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; font-family: Nunito, sans-serif;">
                <span>🎉 Booking Confirmed! Your session has been scheduled.</span>
                <button @click="showBanner = false" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #2E7D32;">✕</button>
            </div>
            <div v-if="showBanner && paymentStatus === 'failed'"
                style="background: #FFEBEE; color: #C62828; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; font-family: Nunito, sans-serif;">
                <span>❌ Payment failed. Please try again.</span>
                <button @click="showBanner = false" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #C62828;">✕</button>
            </div>
            <div v-if="showBanner && paymentStatus === 'pending'"
                style="background: #FFF8E1; color: #F57F17; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-family: Nunito, sans-serif;">
                ⏳ Payment is being processed... this page will update automatically.
            </div>

            <!-- Tab pills -->
            <div style="display: flex; gap: 10px; margin-bottom: 24px;">
                <button v-for="t in ['upcoming', 'completed', 'cancelled']" :key="t" @click="tab = t"
                    :style="{
                        padding: '10px 24px', borderRadius: '20px', border: 'none', cursor: 'pointer',
                        background: tab === t ? '#E8553E' : '#F5F5F5',
                        color: tab === t ? '#fff' : '#555',
                        fontFamily: 'Nunito, sans-serif', fontWeight: '700', fontSize: '14px', textTransform: 'capitalize',
                    }">{{ t }}</button>
            </div>

            <!-- Loading -->
            <div v-if="showSkeleton" style="display: flex; flex-direction: column; gap: 16px;">
                <div v-for="index in 3" :key="index" class="booking-skeleton skeleton-card skeleton" style="padding: 20px 24px; min-height: 120px;"></div>
            </div>

            <div v-else-if="loadError" style="background: #fff; border-radius: 20px; padding: 12px;">
                <ErrorState
                    code="503"
                    title="Bookings unavailable"
                    :message="loadError"
                    :show-back="false"
                />
            </div>

            <!-- Booking cards -->
            <div v-else-if="filtered.length" style="display: flex; flex-direction: column; gap: 16px;">
                <div v-for="b in filtered" :key="b.id"
                    style="background: #fff; border-radius: 20px; padding: 20px 24px; box-shadow: 0 2px 12px rgba(232,85,62,0.08); display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">

                    <!-- Left: teacher info -->
                    <div style="display: flex; align-items: center; gap: 12px; min-width: 180px;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #E8553E; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold; flex-shrink: 0;">
                            {{ (b.teacher?.name || 'T').charAt(0) }}
                        </div>
                        <div>
                            <div style="font-family: Nunito, sans-serif; font-weight: 700; font-size: 16px; color: #333;">{{ b.teacher?.name || 'Teacher' }}</div>
                            <span v-if="b.subject" style="display: inline-block; background: #E8F5E9; color: #2E7D32; padding: 2px 10px; border-radius: 10px; font-size: 12px; margin-top: 2px;">{{ b.subject }}</span>
                        </div>
                    </div>

                    <!-- Centre: date & time -->
                    <div style="flex: 1; min-width: 140px;">
                        <span style="display: inline-block; background: #FFF3EF; color: #E8553E; padding: 4px 14px; border-radius: 10px; font-size: 14px; font-weight: 600; font-family: Nunito, sans-serif;">
                            {{ formatDate(b.start_at) }}
                        </span>
                        <div style="font-size: 14px; color: #777; margin-top: 4px;">{{ formatTime(b.start_at) }} – {{ formatTime(b.end_at) }}</div>
                    </div>

                    <!-- Right: price, status, actions -->
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <span style="font-family: Nunito, sans-serif; font-weight: 700; font-size: 16px; color: #333;">
                            {{ b.price > 0 ? '₹' + parseFloat(b.price).toFixed(0) : 'Free' }}
                        </span>
                        <span :style="{ display: 'inline-block', padding: '4px 12px', borderRadius: '20px', fontSize: '12px', fontWeight: 'bold', color: '#fff', background: statusColor(b.status), textTransform: 'capitalize' }">
                            {{ b.status }}
                        </span>

                        <!-- Action buttons -->
                        <a v-if="canJoin(b)" :href="'/session/' + b.id"
                            style="padding: 10px 20px; background: #E8553E; color: #fff; border-radius: 20px; text-decoration: none; font-weight: bold; font-size: 14px; font-family: Nunito, sans-serif;">
                            🎥 Join Session
                        </a>
                        <span v-else-if="b.status === 'confirmed' && !canJoin(b)"
                            style="font-size: 13px; color: #999; font-family: Nunito, sans-serif;">
                            Starts {{ minutesUntil(b) }}
                        </span>

                        <button v-if="b.status === 'pending' && b.payment_status === 'unpaid' && b.price > 0" type="button" @click="retryPayment(b)"
                            style="padding: 10px 20px; background: #E8553E; color: #fff; border: none; border-radius: 20px; cursor: pointer; font-weight: bold; font-size: 14px;">
                            Complete Payment
                        </button>

                        <a v-if="b.status === 'completed' && !b.review" :href="'/reviews/' + b.id"
                            style="padding: 10px 20px; background: #FFC107; color: #333; border-radius: 20px; text-decoration: none; font-weight: bold; font-size: 14px;">
                            ⭐ Leave Review
                        </a>

                        <button v-if="['confirmed', 'pending'].includes(b.status)" type="button" @click="cancelBooking(b)"
                            style="background: none; border: none; color: #999; cursor: pointer; font-size: 13px; text-decoration: underline;">
                            Cancel
                        </button>

                        <span v-if="b.status === 'cancelled' && b.payment_status === 'refunded'" style="color: #E8553E; font-size: 13px; font-weight: 600;">Refunded ✓</span>
                        <span v-else-if="b.status === 'cancelled'" style="color: #999; font-size: 13px;">No refund</span>
                    </div>
                </div>
            </div>

            <div v-else style="text-align: center; padding: 30px 12px; color: #999; font-family: Nunito, sans-serif;">
                <EmptyState
                    v-if="tab === 'upcoming'"
                    illustration="calendar"
                    title="No upcoming sessions"
                    body="Browse teachers and book your first session."
                    cta-text="Browse teachers"
                    :cta-route="route('teachers.index')"
                />
                <span v-else>No {{ tab }} bookings yet.</span>
            </div>
        </div>
    </StudentLayout>
</template>
