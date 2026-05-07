<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    site_name: props.settings.site_name,
    maintenance_mode: props.settings.maintenance_mode,
    student_registration: props.settings.student_registration,
    teacher_registration: props.settings.teacher_registration,
    platform_fee_percent: props.settings.platform_fee_percent,
    contact_email: props.settings.contact_email,
});

const saveSettings = () => {
    form.post(route('admin.settings.platform.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Platform Settings" />

    <AdminLayout page-title="Platform Settings" :breadcrumb="['Admin', 'Settings', 'Platform']">
        <div class="settings-page">
            <div class="panel">
                <header class="panel-header">
                    <h2>General Configuration</h2>
                    <p>Adjust site-wide behavior and platform rules.</p>
                </header>

                <form @submit.prevent="saveSettings" class="settings-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input v-model="form.site_name" type="text" id="site_name" class="admin-input" placeholder="EduBridge" />
                            <p class="field-help">Displayed in browser tabs and emails.</p>
                        </div>

                        <div class="form-group">
                            <label for="contact_email">Support Email</label>
                            <input v-model="form.contact_email" type="email" id="contact_email" class="admin-input" placeholder="support@edubridge.com" />
                        </div>

                        <div class="form-group">
                            <label for="platform_fee">Platform Fee (%)</label>
                            <input v-model="form.platform_fee_percent" type="number" id="platform_fee" class="admin-input" min="0" max="100" />
                            <p class="field-help">Commission taken from teacher payouts.</p>
                        </div>
                    </div>

                    <div class="toggle-grid">
                        <div class="toggle-item">
                            <div class="toggle-info">
                                <strong>Maintenance Mode</strong>
                                <p>Disable all frontend access except for admins.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" v-model="form.maintenance_mode" />
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <strong>Student Registration</strong>
                                <p>Allow new students to sign up.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" v-model="form.student_registration" />
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <strong>Teacher Registration</strong>
                                <p>Allow new teachers to apply.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" v-model="form.teacher_registration" />
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit" class="admin-btn admin-btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                        <Transition name="fade">
                            <span v-if="form.recentlySuccessful" class="save-status">Settings saved!</span>
                        </Transition>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.settings-page {
    max-width: 800px;
}

.panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 24px;
}

.panel-header {
    margin-bottom: 30px;
}

.panel-header h2 {
    margin: 0;
    font-size: 20px;
    color: #1e293b;
    font-weight: 800;
}

.panel-header p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 14px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-bottom: 40px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 13px;
    font-weight: 700;
    color: #475569;
}

.admin-input {
    height: 42px;
    padding: 0 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 14px;
    color: #1e293b;
    transition: border-color 0.2s;
}

.admin-input:focus {
    outline: none;
    border-color: #E8553E;
    box-shadow: 0 0 0 3px rgba(232, 85, 62, 0.1);
}

.field-help {
    margin: 0;
    font-size: 12px;
    color: #94a3b8;
}

.toggle-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-top: 30px;
    border-top: 1px solid #f1f5f9;
    margin-bottom: 40px;
}

.toggle-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.toggle-info strong {
    display: block;
    font-size: 15px;
    color: #1e293b;
}

.toggle-info p {
    margin: 2px 0 0;
    font-size: 13px;
    color: #64748b;
}

.switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}

.switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #e2e8f0;
    transition: .3s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
}

input:checked + .slider {
    background-color: #E8553E;
}

input:checked + .slider:before {
    transform: translateX(20px);
}

.slider.round {
    border-radius: 34px;
}

.slider.round:before {
    border-radius: 50%;
}

.form-footer {
    display: flex;
    align-items: center;
    gap: 16px;
}

.save-status {
    font-size: 14px;
    color: #10b981;
    font-weight: 700;
}

.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s;
}

.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

@media (max-width: 600px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
