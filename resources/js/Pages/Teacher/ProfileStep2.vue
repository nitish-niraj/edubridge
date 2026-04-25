<script setup>
import TeacherLayout from '@/Layouts/TeacherLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { onMounted } from 'vue';

onMounted(() => { document.body.setAttribute('data-portal', 'teacher'); });

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
});

const allSubjects = [
    { name: 'Math', desc: 'e.g. Class 9–12, Algebra, Calculus' },
    { name: 'Science', desc: 'e.g. General Science, CBSE/ICSE' },
    { name: 'English', desc: 'e.g. Grammar, Literature, Writing' },
    { name: 'History', desc: 'e.g. Indian History, World History' },
    { name: 'Geography', desc: 'e.g. Physical Geography, Maps' },
    { name: 'Physics', desc: 'e.g. Class 11–12, JEE preparation' },
    { name: 'Chemistry', desc: 'e.g. Organic, Inorganic, NEET prep' },
    { name: 'Biology', desc: 'e.g. Botany, Zoology, NEET prep' },
    { name: 'Hindi', desc: 'e.g. Grammar, Literature, Composition' },
    { name: 'Punjabi', desc: 'e.g. Language, Literature' },
    { name: 'Computer Science', desc: 'e.g. Python, C++, Class 11–12' },
    { name: 'Economics', desc: 'e.g. Micro/Macro, Class 11–12' },
    { name: 'Commerce', desc: 'e.g. Accountancy, Business Studies' },
    { name: 'Other', desc: 'Any other subject you teach' },
];

const allLanguages = [
    { name: 'English',   desc: 'Teach in English' },
    { name: 'Hindi',     desc: 'Teach in Hindi' },
    { name: 'Punjabi',   desc: 'Teach in Punjabi' },
    { name: 'Bengali',   desc: 'Teach in Bengali' },
    { name: 'Tamil',     desc: 'Teach in Tamil' },
    { name: 'Telugu',    desc: 'Teach in Telugu' },
    { name: 'Marathi',   desc: 'Teach in Marathi' },
    { name: 'Gujarati',  desc: 'Teach in Gujarati' },
];

const form = useForm({
    subjects:  props.profile?.subjects || [],
    languages: props.profile?.languages || [],
});

const toggle = (arr, val) => {
    const i = arr.indexOf(val);
    if (i > -1) arr.splice(i, 1); else arr.push(val);
};

const submit = (saveForLater = false) => {
    form.post(route('teacher.profile.step2.save'), {
        data: { ...form.data(), save_for_later: saveForLater },
    });
};
</script>

<template>
    <TeacherLayout>
        <div style="padding:36px; background:#FFF8F0; min-height:100vh;">
            <div style="max-width:700px; margin:0 auto;">
                <div style="font-family:'Fredoka One',cursive; font-size:26px; color:#E8553E; margin-bottom:8px;">Step 2 of 5</div>
                <div style="display:flex; gap:8px; margin-bottom:36px;">
                    <div v-for="i in 5" :key="i" :style="`flex:1; height:6px; border-radius:3px; background:${i<=2 ? '#E8553E' : '#F0E8E0'};`"></div>
                </div>
                <h2 style="font-family:'Fredoka One',cursive; font-size:28px; color:#E8553E; margin-bottom:28px;">Subjects & Languages</h2>

                <div style="background:#fff; border:1px solid #F0E8E0; border-radius:10px; padding:32px;">
                    <!-- Subjects -->
                    <div style="margin-bottom:36px;">
                        <h3 style="font-family:'Fredoka One',cursive; font-size:22px; color:#E8553E; margin-bottom:16px;">Subjects you teach</h3>
                        <div v-for="s in allSubjects" :key="s.name"
                            @click="toggle(form.subjects, s.name)"
                            :style="`display:flex; align-items:center; gap:16px; padding:16px 20px; border:2px solid ${form.subjects.includes(s.name) ? '#E8553E' : '#F0E8E0'}; background:${form.subjects.includes(s.name) ? '#FFF3EF' : '#fff'}; border-left:${form.subjects.includes(s.name) ? '5px solid #E8553E' : '5px solid transparent'}; border-radius:8px; margin-bottom:10px; cursor:pointer; min-height:64px;`">
                            <div :style="`width:28px; height:28px; border:2px solid ${form.subjects.includes(s.name) ? '#E8553E' : '#aaa'}; border-radius:4px; background:${form.subjects.includes(s.name) ? '#E8553E' : '#fff'}; display:flex; align-items:center; justify-content:center; flex-shrink:0;`">
                                <span v-if="form.subjects.includes(s.name)" style="color:#fff; font-size:18px;">✓</span>
                            </div>
                            <div>
                                <div style="font-family:'Fredoka One',cursive; font-size:20px; color:#333;">{{ s.name }}</div>
                                <div style="font-family:'Nunito',sans-serif; font-size:16px; color:#888; margin-top:2px;">{{ s.desc }}</div>
                            </div>
                        </div>
                        <div v-if="form.errors.subjects" style="color:#c0392b; font-size:16px; margin-top:8px;">{{ form.errors.subjects }}</div>
                    </div>

                    <!-- Languages -->
                    <div style="margin-bottom:36px;">
                        <h3 style="font-family:'Fredoka One',cursive; font-size:22px; color:#E8553E; margin-bottom:16px;">Languages you can teach in</h3>
                        <div v-for="l in allLanguages" :key="l.name"
                            @click="toggle(form.languages, l.name)"
                            :style="`display:flex; align-items:center; gap:16px; padding:16px 20px; border:2px solid ${form.languages.includes(l.name) ? '#E8553E' : '#F0E8E0'}; background:${form.languages.includes(l.name) ? '#FFF3EF' : '#fff'}; border-left:${form.languages.includes(l.name) ? '5px solid #E8553E' : '5px solid transparent'}; border-radius:8px; margin-bottom:10px; cursor:pointer; min-height:64px;`">
                            <div :style="`width:28px; height:28px; border:2px solid ${form.languages.includes(l.name) ? '#E8553E' : '#aaa'}; border-radius:4px; background:${form.languages.includes(l.name) ? '#E8553E' : '#fff'}; display:flex; align-items:center; justify-content:center; flex-shrink:0;`">
                                <span v-if="form.languages.includes(l.name)" style="color:#fff; font-size:18px;">✓</span>
                            </div>
                            <div>
                                <div style="font-family:'Fredoka One',cursive; font-size:20px; color:#333;">{{ l.name }}</div>
                                <div style="font-family:'Nunito',sans-serif; font-size:16px; color:#888; margin-top:2px;">{{ l.desc }}</div>
                            </div>
                        </div>
                    </div>

                    <button type="button" @click="submit(false)" :disabled="form.processing"
                        style="width:100%; padding:18px; background:#E8553E; color:#fff; border:none; border-radius:8px; font-family:'Nunito',sans-serif; font-size:20px; font-weight:bold; cursor:pointer; min-height:56px; margin-bottom:16px;">
                        {{ form.processing ? 'Saving...' : 'Save & Continue →' }}
                    </button>
                    <div style="text-align:center;">
                        <button type="button" @click="submit(true)"
                            style="background:none; border:none; font-family:'Nunito',sans-serif; font-size:18px; color:#E8553E; cursor:pointer; text-decoration:underline; min-height:44px;">
                            Save for Later
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </TeacherLayout>
</template>
