<script setup>
import axios from 'axios';
import confetti from 'canvas-confetti';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { enforceMinimumDelay } from '@/composables/useMinimumDelay';

const props = defineProps({
    teacherId: {
        type: Number,
        required: true,
    },
    teacherName: {
        type: String,
        default: '',
    },
    teacherAvatar: {
        type: String,
        default: '',
    },
    subjects: {
        type: Array,
        default: () => [],
    },
    originRect: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'booked']);

const overlayRef = ref(null);
const modalRef = ref(null);
const overlayReady = ref(false);
const modalEntering = ref(true);

const step = ref(1);
const previousStep = ref(1);
const stepDirection = ref('forward');
const activePulseStep = ref(1);

const loadingAvailability = ref(false);
const currentMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const monthDirection = ref('next');
const availableDates = ref([]);
const slots = ref({});
const selectedDate = ref(null);
const selectedDateBounceKey = ref(0);

const selectedSlot = ref(null);
const slotRipples = ref({});
const slotFeedback = ref({});

const subject = ref('');
const notes = ref('');
const sessionType = ref('solo');

const bookingResult = ref(null);
const paymentLoading = ref(false);

const animatedPrice = ref(0);
const payButtonShimmering = ref(false);

const successVisible = ref(false);
const successDetailsVisible = ref(false);

let pulseTimer = null;
let advanceTimer = null;
let shimmerTimer = null;
let successDetailsTimer = null;
let priceCounterRaf = null;
const slotFeedbackTimers = new Map();

const currencyFormatter = new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

const monthLabel = computed(() => {
    return currentMonth.value.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
});

const monthKey = computed(() => {
    const monthDate = currentMonth.value;
    return `${monthDate.getFullYear()}-${String(monthDate.getMonth() + 1).padStart(2, '0')}`;
});

const calendarDays = computed(() => {
    const monthDate = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth(), 1);
    const firstDay = monthDate.getDay();
    const daysInMonth = new Date(monthDate.getFullYear(), monthDate.getMonth() + 1, 0).getDate();
    const days = [];

    for (let i = 0; i < firstDay; i += 1) {
        days.push(null);
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const dateString = `${monthDate.getFullYear()}-${String(monthDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        days.push({
            day,
            date: dateString,
            available: availableDates.value.includes(dateString),
        });
    }

    return days;
});

const dateSlots = computed(() => {
    return slots.value[selectedDate.value] || [];
});

const selectedDateFormatted = computed(() => {
    if (!selectedDate.value) return '';
    return new Date(`${selectedDate.value}T00:00:00`).toLocaleDateString('en-IN', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const sessionPrice = computed(() => {
    const rawValue = selectedSlot.value?.price ?? selectedSlot.value?.amount ?? 0;
    const parsed = Number(rawValue);
    return Number.isFinite(parsed) ? parsed : 0;
});

const platformFee = computed(() => {
    const explicitFee = Number(selectedSlot.value?.platform_fee);
    if (Number.isFinite(explicitFee) && explicitFee > 0) {
        return explicitFee;
    }
    if (sessionPrice.value > 0) {
        return Number((sessionPrice.value * 0.05).toFixed(2));
    }
    return 0;
});

const displayPrice = computed(() => {
    return sessionPrice.value <= 0 ? 'FREE' : currencyFormatter.format(animatedPrice.value);
});

const displayPlatformFee = computed(() => {
    return currencyFormatter.format(platformFee.value);
});

const stepTransitionName = computed(() => {
    if (step.value === 5 || previousStep.value === 5) {
        return 'step-morph';
    }
    return stepDirection.value === 'forward' ? 'step-slide-forward' : 'step-slide-backward';
});

const calendarTransitionName = computed(() => {
    return monthDirection.value === 'next' ? 'calendar-slide-next' : 'calendar-slide-prev';
});

const formatSlotTime = (time) => {
    const [hourValue, minuteValue] = time.split(':');
    const hour = Number.parseInt(hourValue, 10);
    return `${hour > 12 ? hour - 12 : hour || 12}:${minuteValue} ${hour >= 12 ? 'PM' : 'AM'}`;
};

const setStep = (targetStep) => {
    if (targetStep === step.value) return;
    previousStep.value = step.value;
    stepDirection.value = targetStep > step.value ? 'forward' : 'backward';
    step.value = targetStep;
};

const fetchAvailability = async () => {
    const requestStartedAt = performance.now();

    loadingAvailability.value = true;
    try {
        const { data } = await axios.get(`/api/teachers/${props.teacherId}/availability`, {
            params: { month: monthKey.value },
        });

        availableDates.value = data.available_dates || [];
        slots.value = data.slots || {};
    } catch (error) {
        console.error(error);
    } finally {
        await enforceMinimumDelay(requestStartedAt, 400);
        loadingAvailability.value = false;
    }
};

const prevMonth = () => {
    monthDirection.value = 'prev';
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    monthDirection.value = 'next';
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1);
};

const selectDate = (dayInfo) => {
    if (!dayInfo?.available) return;

    selectedDate.value = dayInfo.date;
    selectedSlot.value = null;
    selectedDateBounceKey.value += 1;

    window.clearTimeout(advanceTimer);
    advanceTimer = window.setTimeout(() => {
        setStep(2);
    }, 130);
};

const createSlotRipple = (slotId, event, mode = 'select') => {
    const target = event.currentTarget;
    if (!(target instanceof HTMLElement)) return;

    const rect = target.getBoundingClientRect();
    const maxSize = Math.max(rect.width, rect.height) * 2.2;

    slotRipples.value = {
        ...slotRipples.value,
        [slotId]: {
            key: `${Date.now()}-${Math.random()}`,
            mode,
            left: `${event.clientX - rect.left}px`,
            top: `${event.clientY - rect.top}px`,
            size: `${maxSize}px`,
        },
    };
};

const clearSlotRipple = (slotId, rippleKey) => {
    if (!slotRipples.value[slotId]) return;
    if (slotRipples.value[slotId].key !== rippleKey) return;

    const copy = { ...slotRipples.value };
    delete copy[slotId];
    slotRipples.value = copy;
};

const setSlotFeedback = (slotId, mode) => {
    const key = String(slotId);

    slotFeedback.value = {
        ...slotFeedback.value,
        [key]: mode,
    };

    const existingTimer = slotFeedbackTimers.get(key);
    if (existingTimer) {
        window.clearTimeout(existingTimer);
    }

    const timer = window.setTimeout(() => {
        const next = { ...slotFeedback.value };
        if (next[key] === mode) {
            delete next[key];
            slotFeedback.value = next;
        }
        slotFeedbackTimers.delete(key);
    }, mode === 'deselect' ? 560 : 420);

    slotFeedbackTimers.set(key, timer);
};

const slotFeedbackClass = (slotId) => {
    const mode = slotFeedback.value[String(slotId)];
    return mode ? `feedback-${mode}` : '';
};

const selectSlot = (slot, event) => {
    const isSameSlot = selectedSlot.value?.id === slot.id;
    createSlotRipple(slot.id, event, isSameSlot ? 'deselect' : 'select');
    setSlotFeedback(slot.id, isSameSlot ? 'deselect' : 'select');

    if (isSameSlot) {
        selectedSlot.value = null;
        return;
    }

    selectedSlot.value = slot;
    window.clearTimeout(advanceTimer);
    advanceTimer = window.setTimeout(() => {
        setStep(3);
    }, 180);
};

const animatePriceCounter = () => {
    if (sessionPrice.value <= 0) {
        animatedPrice.value = 0;
        return;
    }

    window.cancelAnimationFrame(priceCounterRaf);
    animatedPrice.value = 0;

    const start = performance.now();
    const duration = 600;
    const target = sessionPrice.value;

    const easeOut = (progress) => 1 - Math.pow(1 - progress, 3);

    const frame = (timestamp) => {
        const progress = Math.min((timestamp - start) / duration, 1);
        animatedPrice.value = Number((target * easeOut(progress)).toFixed(2));

        if (progress < 1) {
            priceCounterRaf = window.requestAnimationFrame(frame);
        }
    };

    priceCounterRaf = window.requestAnimationFrame(frame);
};

const triggerPayButtonShimmer = () => {
    window.clearTimeout(shimmerTimer);
    payButtonShimmering.value = false;

    shimmerTimer = window.setTimeout(() => {
        payButtonShimmering.value = true;
        window.setTimeout(() => {
            payButtonShimmering.value = false;
        }, 950);
    }, 240);
};

const launchSuccessConfetti = () => {
    if (typeof window === 'undefined') return;

    const colors = ['#E8553E', '#F5C518', '#4CB87E'];
    confetti({
        particleCount: 120,
        spread: 85,
        startVelocity: 42,
        origin: { x: 0.5, y: 0.58 },
        colors,
    });

    window.setTimeout(() => {
        confetti({
            particleCount: 75,
            spread: 65,
            startVelocity: 34,
            origin: { x: 0.5, y: 0.62 },
            colors,
        });
    }, 140);
};

const runSuccessSequence = () => {
    successVisible.value = true;
    successDetailsVisible.value = false;
    launchSuccessConfetti();

    window.clearTimeout(successDetailsTimer);
    successDetailsTimer = window.setTimeout(() => {
        successDetailsVisible.value = true;
    }, 280);
};

const confirmBooking = async () => {
    if (!selectedSlot.value?.id || paymentLoading.value) return;

    paymentLoading.value = true;
    try {
        const { data } = await axios.post('/api/bookings', {
            slot_id: selectedSlot.value.id,
            subject: subject.value || null,
            notes: notes.value || null,
            session_type: sessionType.value,
        });

        bookingResult.value = data;

        if (data.requires_payment) {
            const paymentResponse = await axios.post('/api/payments/initiate', {
                booking_id: data.booking.id,
            });

            window.location.href = paymentResponse.data.redirect_url;
            return;
        }

        setStep(5);
    } catch (error) {
        window.alert(error.response?.data?.message || 'Booking failed');
    } finally {
        paymentLoading.value = false;
    }
};

const finishFreeBooking = () => {
    if (!bookingResult.value?.booking) return;
    emit('booked', bookingResult.value.booking);
};

const closeModal = () => {
    emit('close');
};

const handleEsc = (event) => {
    if (event.key === 'Escape') {
        closeModal();
    }
};

const setEntranceOrigin = () => {
    if (!modalRef.value) return;

    const rect = modalRef.value.getBoundingClientRect();

    const originX = props.originRect
        ? props.originRect.left + (props.originRect.width / 2)
        : window.innerWidth / 2;
    const originY = props.originRect
        ? props.originRect.top + (props.originRect.height / 2)
        : window.innerHeight * 0.82;

    const modalCenterX = rect.left + (rect.width / 2);
    const modalCenterY = rect.top + (rect.height / 2);

    const shiftX = originX - modalCenterX;
    const shiftY = originY - modalCenterY;

    modalRef.value.style.setProperty('--origin-shift-x', `${shiftX}px`);
    modalRef.value.style.setProperty('--origin-shift-y', `${shiftY}px`);
    modalRef.value.style.setProperty('--origin-local-x', `${originX - rect.left}px`);
    modalRef.value.style.setProperty('--origin-local-y', `${originY - rect.top}px`);
};

watch(currentMonth, fetchAvailability, { immediate: true });

watch(step, (newStep) => {
    if (newStep <= 4) {
        activePulseStep.value = newStep;
        window.clearTimeout(pulseTimer);
        pulseTimer = window.setTimeout(() => {
            activePulseStep.value = null;
        }, 760);
    }

    if (newStep === 4) {
        animatePriceCounter();
        triggerPayButtonShimmer();
    }

    if (newStep === 5) {
        runSuccessSequence();
    }
});

watch(
    () => props.originRect,
    () => {
        setEntranceOrigin();
    },
    { deep: true },
);

onMounted(async () => {
    await nextTick();
    setEntranceOrigin();

    window.requestAnimationFrame(() => {
        overlayReady.value = true;
        modalEntering.value = true;
    });

    window.setTimeout(() => {
        modalEntering.value = false;
    }, 470);

    window.addEventListener('keydown', handleEsc);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc);
    window.clearTimeout(pulseTimer);
    window.clearTimeout(advanceTimer);
    window.clearTimeout(shimmerTimer);
    window.clearTimeout(successDetailsTimer);
    window.cancelAnimationFrame(priceCounterRaf);

    slotFeedbackTimers.forEach((timer) => {
        window.clearTimeout(timer);
    });
    slotFeedbackTimers.clear();
});
</script>

<template>
    <div ref="overlayRef" class="booking-overlay" :class="{ 'booking-overlay--ready': overlayReady }" @click.self="closeModal">
        <div
            ref="modalRef"
            class="booking-modal"
            :class="{
                'booking-modal--origin-enter': modalEntering,
                'booking-modal--success': step === 5 && successVisible,
            }"
            role="dialog"
            aria-modal="true"
            aria-label="Book Session"
        >
            <header class="booking-header">
                <div>
                    <p class="booking-kicker">Live Session</p>
                    <h2 class="booking-title">Book Session</h2>
                </div>
                <button type="button" class="close-button" aria-label="Close booking modal" @click="closeModal">
                    <span>×</span>
                </button>
            </header>

            <div v-if="step <= 4" class="step-progress" aria-hidden="true">
                <template v-for="s in 4" :key="`progress-${s}`">
                    <div
                        class="step-circle"
                        :class="{
                            'step-circle--active': step === s,
                            'step-circle--complete': step > s,
                            'step-circle--pulse': activePulseStep === s && step === s,
                        }"
                    >
                        <svg v-if="step > s" class="step-check" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M4.2 10.3L8.2 14.2L15.8 6.6" />
                        </svg>
                        <span v-else>{{ s }}</span>
                    </div>

                    <div
                        v-if="s < 4"
                        class="step-connector"
                        :style="{ '--fill': step > s ? 1 : 0 }"
                    />
                </template>
            </div>

            <div class="step-content-shell">
                <Transition :name="stepTransitionName" mode="out-in">
                    <section :key="`step-${step}`" class="step-panel">
                        <template v-if="step === 1">
                            <div class="step-panel-head">
                                <h3>Pick a Date</h3>
                                <p>Select your learning window. Available days pulse into view.</p>
                            </div>

                            <div class="calendar-wrap">
                                <div class="calendar-nav">
                                    <button type="button" class="month-nav" @click="prevMonth">←</button>
                                    <strong>{{ monthLabel }}</strong>
                                    <button type="button" class="month-nav" @click="nextMonth">→</button>
                                </div>

                                <div v-if="loadingAvailability" class="calendar-skeleton-grid" aria-hidden="true">
                                    <span v-for="dot in 35" :key="`calendar-skeleton-${dot}`" class="calendar-skeleton-dot skeleton"></span>
                                </div>

                                <Transition v-else :name="calendarTransitionName" mode="out-in">
                                    <div :key="monthKey" class="calendar-grid">
                                        <span v-for="dayLabel in ['S', 'M', 'T', 'W', 'T', 'F', 'S']" :key="dayLabel" class="calendar-weekday">
                                            {{ dayLabel }}
                                        </span>

                                        <button
                                            v-for="(dayInfo, index) in calendarDays"
                                            :key="dayInfo?.date || `blank-${index}`"
                                            type="button"
                                            class="calendar-day"
                                            :class="{
                                                'is-blank': !dayInfo,
                                                'is-available': dayInfo?.available,
                                                'is-selected': selectedDate === dayInfo?.date,
                                            }"
                                            :disabled="!dayInfo?.available"
                                            @click="selectDate(dayInfo)"
                                        >
                                            <span
                                                v-if="selectedDate === dayInfo?.date"
                                                :key="`selected-${selectedDateBounceKey}`"
                                                class="calendar-selected-bg"
                                            />
                                            <span class="calendar-day-label">{{ dayInfo?.day || '' }}</span>
                                            <span
                                                v-if="dayInfo?.available && selectedDate !== dayInfo?.date"
                                                class="calendar-dot"
                                                :style="{ '--dot-delay': `${index * 20}ms` }"
                                            />
                                        </button>
                                    </div>
                                </Transition>
                            </div>
                        </template>

                        <template v-else-if="step === 2">
                            <div class="step-panel-head">
                                <h3>Pick a Time Slot</h3>
                                <p>{{ selectedDateFormatted }}</p>
                            </div>

                            <div v-if="dateSlots.length" class="slot-grid">
                                <button
                                    v-for="(slot, index) in dateSlots"
                                    :key="slot.id"
                                    type="button"
                                    class="slot-chip"
                                    :class="[
                                        { 'is-selected': selectedSlot?.id === slot.id },
                                        slotFeedbackClass(slot.id),
                                    ]"
                                    :style="{ '--slot-delay': `${index * 60}ms` }"
                                    @click="selectSlot(slot, $event)"
                                >
                                    <span class="slot-label">{{ formatSlotTime(slot.start_time) }} - {{ formatSlotTime(slot.end_time) }}</span>
                                    <span
                                        v-if="slotRipples[slot.id]"
                                        :key="slotRipples[slot.id].key"
                                        class="slot-ripple"
                                        :class="slotRipples[slot.id].mode === 'select' ? 'slot-ripple--select' : 'slot-ripple--deselect'"
                                        :style="{
                                            left: slotRipples[slot.id].left,
                                            top: slotRipples[slot.id].top,
                                            width: slotRipples[slot.id].size,
                                            height: slotRipples[slot.id].size,
                                        }"
                                        @animationend="clearSlotRipple(slot.id, slotRipples[slot.id].key)"
                                    />
                                </button>
                            </div>

                            <p v-else class="empty-state">No slots available on this date. Choose another day.</p>

                            <button type="button" class="ghost-link" @click="setStep(1)">← Back to calendar</button>
                        </template>

                        <template v-else-if="step === 3">
                            <div class="step-panel-head">
                                <h3>Session Details</h3>
                                <p>Customize your lesson so your teacher can prepare in advance.</p>
                            </div>

                            <label class="field-block">
                                <span>Subject</span>
                                <select v-model="subject" name="subject">
                                    <option value="">Select subject...</option>
                                    <option v-for="subjectOption in props.subjects" :key="subjectOption" :value="subjectOption">
                                        {{ subjectOption }}
                                    </option>
                                </select>
                            </label>

                            <label class="field-block">
                                <span>Notes (optional)</span>
                                <textarea
                                    v-model="notes"
                                    rows="3"
                                    placeholder="Any topics you want to focus on?"
                                />
                            </label>

                            <div class="session-type-row">
                                <button
                                    type="button"
                                    class="session-type"
                                    :class="{ 'is-selected': sessionType === 'solo' }"
                                    @click="sessionType = 'solo'"
                                >
                                    Solo Session
                                </button>
                                <button
                                    type="button"
                                    class="session-type"
                                    :class="{ 'is-selected': sessionType === 'group' }"
                                    @click="sessionType = 'group'"
                                >
                                    Group Session
                                </button>
                            </div>

                            <div class="action-row">
                                <button type="button" class="secondary-action" @click="setStep(2)">← Back</button>
                                <button type="button" class="primary-action" @click="setStep(4)">Continue</button>
                            </div>
                        </template>

                        <template v-else-if="step === 4">
                            <div class="step-panel-head">
                                <h3>Review and Pay</h3>
                                <p>Final checkpoint before your session is locked in.</p>
                            </div>

                            <div class="summary-card">
                                <div class="summary-header">
                                    <div class="summary-avatar">{{ (props.teacherName || 'T').charAt(0) }}</div>
                                    <div>
                                        <p class="summary-name">{{ props.teacherName }}</p>
                                        <p class="summary-date">{{ selectedDateFormatted }}</p>
                                    </div>
                                </div>

                                <div class="summary-lines">
                                    <p><strong>Time</strong> {{ selectedSlot ? `${formatSlotTime(selectedSlot.start_time)} - ${formatSlotTime(selectedSlot.end_time)}` : '--' }}</p>
                                    <p><strong>Subject</strong> {{ subject || 'General' }}</p>
                                    <p><strong>Duration</strong> {{ selectedSlot?.duration_minutes || 60 }} min</p>
                                </div>
                            </div>

                            <div class="price-card">
                                <p class="price-label">Session Price</p>
                                <p class="price-value">{{ displayPrice }}</p>
                                <p class="fee-line" :class="{ 'fee-line--visible': step === 4 }">
                                    Platform fee {{ displayPlatformFee }}
                                </p>
                            </div>

                            <p class="payment-note">
                                You will be redirected to complete secure payment if this session is paid.
                            </p>

                            <div class="action-row">
                                <button type="button" class="secondary-action" @click="setStep(3)">← Back</button>
                                <button
                                    type="button"
                                    class="primary-action pay-button"
                                    :class="{ 'pay-button--shimmer': payButtonShimmering }"
                                    :disabled="paymentLoading"
                                    @click="confirmBooking"
                                >
                                    {{ paymentLoading ? 'Processing...' : sessionPrice > 0 ? 'Pay' : 'Confirm Booking' }}
                                </button>
                            </div>
                        </template>

                        <template v-else>
                            <div class="success-screen" :class="{ 'success-screen--visible': successVisible }">
                                <div class="success-icon-shell">
                                    <span class="success-icon-disc" />
                                    <svg class="success-check" viewBox="0 0 120 120" aria-hidden="true">
                                        <path d="M36 62L53 79L84 46" />
                                    </svg>
                                </div>

                                <h3>Booking Confirmed</h3>
                                <p>Your session is now scheduled and ready.</p>

                                <div class="success-details" :class="{ 'success-details--visible': successDetailsVisible }">
                                    <p style="--detail-delay: 0ms;"><strong>Teacher:</strong> {{ props.teacherName }}</p>
                                    <p style="--detail-delay: 90ms;"><strong>Date:</strong> {{ selectedDateFormatted }}</p>
                                    <p style="--detail-delay: 180ms;"><strong>Time:</strong> {{ selectedSlot ? `${formatSlotTime(selectedSlot.start_time)} - ${formatSlotTime(selectedSlot.end_time)}` : '--' }}</p>
                                </div>

                                <button type="button" class="success-cta" @click="finishFreeBooking">View Bookings</button>
                            </div>
                        </template>
                    </section>
                </Transition>
            </div>
        </div>
    </div>
</template>

<style scoped>
.booking-overlay {
    position: fixed;
    inset: 0;
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0);
    opacity: 0;
    backdrop-filter: blur(0px);
    -webkit-backdrop-filter: blur(0px);
    animation: booking-overlay-enter 450ms cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.booking-overlay--ready {
    opacity: 1;
}

.booking-modal {
    --origin-shift-x: 0px;
    --origin-shift-y: 0px;
    --origin-local-x: 50%;
    --origin-local-y: 50%;
    width: min(760px, 100%);
    max-height: min(90vh, 860px);
    border-radius: 28px;
    background: #ffffff;
    box-shadow: 0 34px 90px rgba(15, 23, 42, 0.28);
    transform-origin: var(--origin-local-x) var(--origin-local-y);
    padding: 22px 24px 26px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.booking-modal--origin-enter {
    animation: booking-modal-origin-enter 450ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.booking-modal--success {
    background: #f4fff7;
    transition: background-color 560ms cubic-bezier(0.16, 1, 0.3, 1);
}

.booking-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.booking-kicker {
    margin: 0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-weight: 800;
    color: #9CA3AF;
    font-family: 'Nunito', sans-serif;
}

.booking-title {
    margin: 6px 0 0;
    font-family: 'Fredoka One', cursive;
    font-size: 28px;
    color: #e8553e;
    line-height: 1;
}

.close-button {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 999px;
    background: #FFF8F0;
    color: #9CA3AF;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    line-height: 0;
    transition: transform 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.close-button:hover {
    transform: scale(1.05);
    background: #fee2e2;
    color: #be123c;
}

.step-progress {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}

.step-circle {
    width: 34px;
    height: 34px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
    font-family: 'Nunito', sans-serif;
    background: #e5e7eb;
    color: #9CA3AF;
    position: relative;
}

.step-circle--active {
    background: #e8553e;
    color: #fff;
}

.step-circle--complete {
    background: #E8553E;
    color: #fff;
}

.step-circle--pulse::before {
    content: '';
    position: absolute;
    inset: -6px;
    border-radius: 999px;
    border: 2px solid rgba(232, 85, 62, 0.5);
    animation: step-pulse-once 700ms ease-out 1;
}

.step-connector {
    width: 56px;
    height: 4px;
    border-radius: 999px;
    background: #e5e7eb;
    margin: 0 4px;
    position: relative;
    overflow: hidden;
}

.step-connector::after {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: calc(var(--fill, 0) * 100%);
    border-radius: inherit;
    background: linear-gradient(90deg, #e8553e 0%, #ffab76 100%);
    transition: width 420ms cubic-bezier(0.16, 1, 0.3, 1);
}

.step-check {
    width: 18px;
    height: 18px;
}

.step-check path {
    fill: none;
    stroke: currentColor;
    stroke-width: 2.2;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 24;
    stroke-dashoffset: 24;
    animation: step-check-draw 280ms cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

.step-content-shell {
    position: relative;
    overflow: hidden;
    min-height: 430px;
}

.step-panel {
    display: flex;
    flex-direction: column;
    gap: 14px;
    height: 100%;
}

.step-panel-head h3 {
    margin: 0;
    font-family: 'Nunito', sans-serif;
    font-size: 23px;
    font-weight: 800;
    color: #2D2D2D;
}

.step-panel-head p {
    margin: 7px 0 0;
    font-size: 14px;
    color: #9CA3AF;
    font-family: 'Nunito', sans-serif;
}

.calendar-wrap {
    background: #fff8f0;
    border: 1px solid #f5e6d8;
    border-radius: 20px;
    padding: 14px;
}

.calendar-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-family: 'Nunito', sans-serif;
}

.calendar-nav strong {
    font-size: 16px;
    color: #2D2D2D;
}

.month-nav {
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 10px;
    background: #fff;
    color: #2D2D2D;
    cursor: pointer;
    font-size: 16px;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.month-nav:hover {
    transform: translateY(-1px);
    background: #fef2f2;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 6px;
}

.calendar-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 8px;
    padding: 6px 2px 2px;
}

.calendar-skeleton-dot {
    width: 24px;
    height: 24px;
    border-radius: 999px;
    justify-self: center;
}

.calendar-weekday {
    text-align: center;
    font-size: 11px;
    color: #9CA3AF;
    font-weight: 700;
    font-family: 'Nunito', sans-serif;
    padding: 5px 0;
}

.calendar-day {
    position: relative;
    min-height: 42px;
    border: none;
    border-radius: 12px;
    background: transparent;
    cursor: pointer;
    color: #2D2D2D;
    font-family: 'Nunito', sans-serif;
    font-size: 14px;
    font-weight: 700;
    transition: transform 0.2s ease, background-color 0.2s ease;
}

.calendar-day:disabled {
    cursor: default;
}

.calendar-day.is-blank {
    pointer-events: none;
}

.calendar-day.is-available:hover {
    transform: translateY(-1px);
    background: rgba(232, 85, 62, 0.08);
}

.calendar-day.is-selected {
    color: #fff;
}

.calendar-day-label {
    position: relative;
    z-index: 2;
}

.calendar-selected-bg {
    position: absolute;
    inset: 5px;
    border-radius: 999px;
    background: #e8553e;
    z-index: 1;
    animation: selected-day-pop 360ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.calendar-dot {
    position: absolute;
    left: 50%;
    bottom: 4px;
    transform: translate(-50%, 6px) scale(0.1);
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: #e8553e;
    opacity: 0;
    animation: calendar-dot-pop 320ms cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    animation-delay: var(--dot-delay, 0ms);
}

.slot-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.slot-chip {
    position: relative;
    min-height: 42px;
    border-radius: 999px;
    border: 2px solid #e2e8f0;
    background: #fff;
    padding: 0 16px;
    cursor: pointer;
    overflow: hidden;
    animation: slot-chip-pop 360ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
    animation-delay: var(--slot-delay, 0ms);
    transition: transform 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.slot-chip::before {
    content: '';
    position: absolute;
    inset: 0;
    background: #e8553e;
    opacity: 0;
    z-index: 1;
    pointer-events: none;
}

.slot-chip:hover {
    transform: translateY(-1px);
    border-color: #e8553e;
}

.slot-chip.is-selected {
    border-color: #e8553e;
    color: #fff;
}

.slot-chip.is-selected::before {
    opacity: 1;
}

.slot-chip.feedback-select::before {
    animation: slot-chip-fill-in 360ms ease-out forwards;
}

.slot-chip.feedback-deselect::before {
    animation: slot-chip-fill-drain 520ms ease forwards;
}

.slot-label {
    position: relative;
    z-index: 3;
    font-size: 14px;
    font-weight: 700;
    font-family: 'Nunito', sans-serif;
}

.slot-ripple {
    position: absolute;
    border-radius: 999px;
    background: rgba(232, 85, 62, 0.35);
    transform: translate(-50%, -50%) scale(0);
    pointer-events: none;
    z-index: 2;
}

.slot-ripple--select {
    animation: slot-ripple-select 360ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.slot-ripple--deselect {
    animation: slot-ripple-deselect 500ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.empty-state {
    margin: 0;
    color: #9CA3AF;
    font-size: 14px;
}

.ghost-link {
    margin-top: 6px;
    border: none;
    background: none;
    color: #e8553e;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    width: fit-content;
}

.field-block {
    display: grid;
    gap: 6px;
}

.field-block span {
    font-size: 14px;
    color: #2D2D2D;
    font-weight: 700;
    font-family: 'Nunito', sans-serif;
}

.field-block select,
.field-block textarea {
    width: 100%;
    border: 2px solid #f0ddd5;
    border-radius: 12px;
    font: inherit;
    color: #2D2D2D;
    padding: 10px 12px;
    outline: none;
    transition: border-color 0.2s ease;
}

.field-block select:focus,
.field-block textarea:focus {
    border-color: #e8553e;
}

.session-type-row {
    display: flex;
    gap: 10px;
}

.session-type {
    flex: 1;
    min-height: 46px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    background: #fff;
    cursor: pointer;
    font-size: 14px;
    font-weight: 700;
    font-family: 'Nunito', sans-serif;
    transition: border-color 0.2s ease, background-color 0.2s ease;
}

.session-type.is-selected {
    border-color: #e8553e;
    background: #fff3ef;
}

.action-row {
    display: flex;
    gap: 12px;
    margin-top: auto;
}

.secondary-action,
.primary-action {
    min-height: 46px;
    border-radius: 12px;
    border: none;
    font-size: 15px;
    font-weight: 800;
    font-family: 'Nunito', sans-serif;
    cursor: pointer;
}

.secondary-action {
    flex: 1;
    background: #fff;
    border: 2px solid #e2e8f0;
    color: #2D2D2D;
}

.primary-action {
    flex: 1.6;
    background: #e8553e;
    color: #fff;
}

.primary-action:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.summary-card {
    background: #fff8f0;
    border-radius: 18px;
    border: 1px solid #f5e6d8;
    padding: 14px;
}

.summary-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.summary-avatar {
    width: 44px;
    height: 44px;
    border-radius: 999px;
    background: #e8553e;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
}

.summary-name {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: #2D2D2D;
}

.summary-date {
    margin: 2px 0 0;
    font-size: 12px;
    color: #9CA3AF;
}

.summary-lines p {
    margin: 4px 0;
    font-size: 14px;
    color: #2D2D2D;
}

.summary-lines strong {
    margin-right: 8px;
}

.price-card {
    border-radius: 18px;
    background: #FFF8F0;
    border: 1px solid #e2e8f0;
    padding: 14px;
}

.price-label {
    margin: 0;
    font-size: 12px;
    color: #9CA3AF;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 800;
}

.price-value {
    margin: 8px 0 0;
    font-size: 36px;
    line-height: 1;
    font-weight: 900;
    color: #2D2D2D;
    font-family: 'Nunito', sans-serif;
}

.fee-line {
    margin: 8px 0 0;
    font-size: 13px;
    color: #9CA3AF;
    opacity: 0;
    transform: translateY(8px);
}

.fee-line--visible {
    animation: fee-line-slide 350ms cubic-bezier(0.16, 1, 0.3, 1) 140ms forwards;
}

.payment-note {
    margin: 0;
    font-size: 13px;
    color: #9CA3AF;
}

.pay-button {
    position: relative;
    overflow: hidden;
}

.pay-button--shimmer::after {
    content: '';
    position: absolute;
    top: -20%;
    left: -42%;
    width: 40%;
    height: 150%;
    background: linear-gradient(110deg, transparent 0%, rgba(255, 255, 255, 0.15) 18%, rgba(255, 255, 255, 0.85) 52%, rgba(255, 255, 255, 0.2) 84%, transparent 100%);
    transform: skewX(-22deg);
    animation: pay-button-glint 860ms ease-out 1;
}

.success-screen {
    text-align: center;
    padding: 16px 8px 6px;
    opacity: 0;
    transform: translateY(8px);
}

.success-screen--visible {
    opacity: 1;
    transform: translateY(0);
    transition: opacity 340ms ease, transform 400ms cubic-bezier(0.16, 1, 0.3, 1);
}

.success-icon-shell {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 2px auto 14px;
    display: grid;
    place-items: center;
}

.success-icon-disc {
    position: absolute;
    inset: 0;
    border-radius: 999px;
    background: radial-gradient(circle at 30% 30%, #d1fae5 0%, #6ee7b7 100%);
    clip-path: circle(0% at 50% 50%);
    animation: success-disc-fill 460ms cubic-bezier(0.16, 1, 0.3, 1) 280ms forwards;
}

.success-check {
    width: 70px;
    height: 70px;
    position: relative;
    z-index: 2;
}

.success-check path {
    fill: none;
    stroke: #0f5132;
    stroke-width: 7;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 86;
    stroke-dashoffset: 86;
    animation: success-check-draw 500ms cubic-bezier(0.34, 1.56, 0.64, 1) 120ms forwards;
}

.success-screen h3 {
    margin: 0;
    font-size: 30px;
    color: #14532d;
    font-family: 'Fredoka One', cursive;
    letter-spacing: 0.01em;
}

.success-screen p {
    margin: 8px 0 0;
    color: #3f3f46;
    font-size: 14px;
}

.success-details {
    margin-top: 14px;
    display: grid;
    gap: 7px;
}

.success-details p {
    margin: 0;
    opacity: 0;
    transform: translateY(10px);
}

.success-details--visible p {
    animation: success-detail-fade 340ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
    animation-delay: var(--detail-delay, 0ms);
}

.success-cta {
    margin-top: 18px;
    min-height: 48px;
    padding: 0 24px;
    border: none;
    border-radius: 999px;
    background: #e8553e;
    color: #fff;
    font-size: 15px;
    font-weight: 800;
    cursor: pointer;
    transform: scale(0.82);
    opacity: 0;
    animation: success-button-pop 560ms cubic-bezier(0.34, 1.56, 0.64, 1) 460ms forwards;
}

.step-slide-forward-enter-active {
    animation: step-forward-enter 420ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.step-slide-forward-leave-active {
    animation: step-forward-leave 280ms ease both;
}

.step-slide-backward-enter-active {
    animation: step-back-enter 420ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.step-slide-backward-leave-active {
    animation: step-back-leave 280ms ease both;
}

.step-morph-enter-active,
.step-morph-leave-active {
    transition: opacity 300ms ease, transform 360ms cubic-bezier(0.16, 1, 0.3, 1);
}

.step-morph-enter-from,
.step-morph-leave-to {
    opacity: 0;
    transform: scale(0.98);
}

.calendar-slide-next-enter-active {
    animation: calendar-next-enter 380ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.calendar-slide-next-leave-active {
    animation: calendar-next-leave 280ms ease both;
}

.calendar-slide-prev-enter-active {
    animation: calendar-prev-enter 380ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.calendar-slide-prev-leave-active {
    animation: calendar-prev-leave 280ms ease both;
}

@keyframes booking-overlay-enter {
    0% {
        opacity: 0;
        background: rgba(15, 23, 42, 0);
        backdrop-filter: blur(0px);
        -webkit-backdrop-filter: blur(0px);
    }

    100% {
        opacity: 1;
        background: rgba(15, 23, 42, 0.38);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
}

@keyframes booking-modal-origin-enter {
    0% {
        opacity: 0;
        transform: translate(var(--origin-shift-x), var(--origin-shift-y)) scale(0.3);
    }

    100% {
        opacity: 1;
        transform: translate(0, 0) scale(1);
    }
}

@keyframes step-pulse-once {
    0% {
        opacity: 0;
        transform: scale(0.7);
    }

    25% {
        opacity: 0.55;
    }

    100% {
        opacity: 0;
        transform: scale(1.55);
    }
}

@keyframes step-check-draw {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes selected-day-pop {
    0% {
        transform: scale(0);
    }

    72% {
        transform: scale(1.12);
    }

    100% {
        transform: scale(1);
    }
}

@keyframes calendar-dot-pop {
    to {
        opacity: 1;
        transform: translate(-50%, 0) scale(1);
    }
}

@keyframes slot-chip-pop {
    0% {
        opacity: 0;
        transform: translateY(14px) scale(0.86);
    }

    70% {
        opacity: 1;
        transform: translateY(-2px) scale(1.04);
    }

    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes slot-ripple-select {
    0% {
        opacity: 0.55;
        transform: translate(-50%, -50%) scale(0);
    }

    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(1);
    }
}

@keyframes slot-ripple-deselect {
    0% {
        opacity: 0.18;
        transform: translate(-50%, -50%) scale(0);
    }

    55% {
        opacity: 0.45;
        transform: translate(-50%, -50%) scale(1);
    }

    100% {
        opacity: 0;
        transform: translate(-50%, -50%) scale(1.15);
    }
}

@keyframes slot-chip-fill-in {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes slot-chip-fill-drain {
    0% {
        opacity: 0;
    }

    45% {
        opacity: 1;
    }

    100% {
        opacity: 0;
    }
}

@keyframes fee-line-slide {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pay-button-glint {
    0% {
        transform: skewX(-22deg) translateX(0%);
    }

    100% {
        transform: skewX(-22deg) translateX(360%);
    }
}

@keyframes success-check-draw {
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes success-disc-fill {
    to {
        clip-path: circle(70% at 50% 50%);
    }
}

@keyframes success-detail-fade {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes success-button-pop {
    0% {
        opacity: 0;
        transform: scale(0.82);
    }

    72% {
        opacity: 1;
        transform: scale(1.08);
    }

    100% {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes step-forward-enter {
    0% {
        opacity: 0;
        transform: translateX(48px) scale(0.98);
    }

    70% {
        opacity: 1;
        transform: translateX(-9px) scale(1.01);
    }

    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

@keyframes step-forward-leave {
    to {
        opacity: 0;
        transform: translateX(-46px) scale(0.98);
    }
}

@keyframes step-back-enter {
    0% {
        opacity: 0;
        transform: translateX(-48px) scale(0.98);
    }

    70% {
        opacity: 1;
        transform: translateX(9px) scale(1.01);
    }

    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

@keyframes step-back-leave {
    to {
        opacity: 0;
        transform: translateX(46px) scale(0.98);
    }
}

@keyframes calendar-next-enter {
    0% {
        opacity: 0;
        transform: translateX(36px) scale(0.98);
    }

    72% {
        opacity: 1;
        transform: translateX(-6px) scale(1.01);
    }

    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

@keyframes calendar-next-leave {
    to {
        opacity: 0;
        transform: translateX(-30px);
    }
}

@keyframes calendar-prev-enter {
    0% {
        opacity: 0;
        transform: translateX(-36px) scale(0.98);
    }

    72% {
        opacity: 1;
        transform: translateX(6px) scale(1.01);
    }

    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

@keyframes calendar-prev-leave {
    to {
        opacity: 0;
        transform: translateX(30px);
    }
}

@media (max-width: 640px) {
    .booking-overlay {
        padding: 10px;
    }

    .booking-modal {
        width: 100%;
        max-height: 94vh;
        border-radius: 22px;
        padding: 16px 14px 18px;
    }

    .booking-title {
        font-size: 24px;
    }

    .step-connector {
        width: 34px;
        margin: 0 2px;
    }

    .step-content-shell {
        min-height: 468px;
    }

    .action-row {
        flex-direction: column;
    }

    .secondary-action,
    .primary-action {
        width: 100%;
    }

    .price-value {
        font-size: 30px;
    }
}
</style>
