<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'المظهر', href: edit() }],
    },
});

const { appearance, updateAppearance } = useAppearance();

const modes = [
    { value: 'light',  Icon: Sun,     label: 'فاتح',   desc: 'واجهة بيضاء مضيئة' },
    { value: 'dark',   Icon: Moon,    label: 'داكن',   desc: 'واجهة داكنة للعمل الليلي' },
    { value: 'system', Icon: Monitor, label: 'تلقائي', desc: 'يتبع إعدادات الجهاز' },
] as const;
</script>

<template>
    <Head title="المظهر" />

    <!-- ── Appearance section ── -->
    <div class="settings-section">
        <div class="settings-title">🎨 المظهر والواجهة</div>
        <p class="settings-desc">اختر مظهر الواجهة الذي يناسبك.</p>

        <div class="modes-grid">
            <button
                v-for="mode in modes"
                :key="mode.value"
                class="mode-card"
                :class="{ 'mode-card-active': appearance === mode.value }"
                @click="updateAppearance(mode.value)"
            >
                <div class="mode-icon-wrap" :class="{ 'mode-icon-wrap-active': appearance === mode.value }">
                    <component :is="mode.Icon" class="h-5 w-5" />
                </div>
                <p class="mode-label">{{ mode.label }}</p>
                <p class="mode-desc">{{ mode.desc }}</p>
                <div v-if="appearance === mode.value" class="mode-check">✓</div>
            </button>
        </div>
    </div>

    <!-- ── Interface preferences (informational) ── -->
    <div class="settings-section">
        <div class="settings-title">🌐 تفضيلات الواجهة</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="info-lbl">اتجاه النص</span>
                <span class="info-val info-badge">RTL — عربي (من اليمين إلى اليسار)</span>
            </div>
            <div class="info-row">
                <span class="info-lbl">اللغة</span>
                <span class="info-val info-badge">العربية</span>
            </div>
            <div class="info-row">
                <span class="info-lbl">المظهر النشط</span>
                <span class="info-val">
                    <span class="info-badge info-badge-primary">
                        {{ modes.find(m => m.value === appearance)?.label ?? '—' }}
                    </span>
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.settings-section {
    background: #fff;
    border: 1px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 16px;
}
.settings-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-hospital-primary, #0A4FA6);
    margin-bottom: 8px;
    padding-bottom: 9px;
    border-bottom: 2px solid var(--color-hospital-primary-pale, #E8F1FB);
}
.settings-desc {
    font-size: 12px;
    color: var(--color-hospital-text-3, #8A96AE);
    margin-bottom: 16px;
}

/* ── Mode cards ── */
.modes-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.mode-card {
    position: relative;
    border: 2px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 10px;
    padding: 16px 12px 14px;
    text-align: center;
    cursor: pointer;
    transition: all 0.18s;
    background: var(--color-hospital-bg, #F3F6FA);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.mode-card:hover { border-color: var(--color-hospital-primary, #0A4FA6); background: #EDF3FB; }
.mode-card-active { border-color: var(--color-hospital-primary, #0A4FA6); background: #EDF3FB; }

.mode-icon-wrap {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-hospital-text-2, #4A5878);
    border: 1.5px solid var(--color-hospital-border, #DDE4EF);
    transition: all 0.18s;
}
.mode-icon-wrap-active {
    background: var(--color-hospital-primary, #0A4FA6);
    color: #fff;
    border-color: var(--color-hospital-primary, #0A4FA6);
}

.mode-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-hospital-text, #0D1F3C);
}
.mode-desc {
    font-size: 10px;
    color: var(--color-hospital-text-3, #8A96AE);
}
.mode-check {
    position: absolute;
    top: 8px;
    left: 10px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--color-hospital-primary, #0A4FA6);
    color: #fff;
    font-size: 10px;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ── Info grid ── */
.info-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    background: var(--color-hospital-bg, #F3F6FA);
    border-radius: 7px;
    font-size: 12px;
}
.info-lbl {
    font-weight: 600;
    color: var(--color-hospital-text-2, #4A5878);
}
.info-val {
    display: flex;
    align-items: center;
    gap: 6px;
}
.info-badge {
    padding: 3px 10px;
    background: #fff;
    border: 1px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    color: var(--color-hospital-text, #0D1F3C);
}
.info-badge-primary {
    background: var(--color-hospital-primary-pale, #E8F1FB);
    border-color: var(--color-hospital-primary, #0A4FA6)40;
    color: var(--color-hospital-primary, #0A4FA6);
}
</style>
