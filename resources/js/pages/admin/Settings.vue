<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Save } from 'lucide-vue-next';
import { ref } from 'vue';

interface Setting {
    key: string;
    value: string | null;
    group: string;
}

interface SystemModuleOption {
    value: string;
    label: string;
    enabled: boolean;
}

const props = defineProps<{
    settings: Record<string, Setting>;
    systemModules: SystemModuleOption[];
}>();

const form = ref<Record<string, string>>({});
Object.values(props.settings).forEach((s) => {
    form.value[s.key] = s.value ?? '';
});

function moduleKey(module: string): string {
    return `module_enabled_${module}`;
}

props.systemModules.forEach((m) => {
    form.value[moduleKey(m.value)] = form.value[moduleKey(m.value)] ?? (m.enabled ? 'true' : 'false');
});

function submit() {
    const payload = Object.entries(form.value).map(([key, value]) => ({ key, value }));
    router.post('/settings', { settings: payload });
}

function val(key: string): string {
    return form.value[key] ?? '';
}

function isModuleEnabled(module: string): boolean {
    return form.value[moduleKey(module)] !== 'false';
}

function toggleModule(module: string): void {
    form.value[moduleKey(module)] = isModuleEnabled(module) ? 'false' : 'true';
}
</script>

<template>
    <Head title="إعدادات النظام" />

    <!-- ── Page header ── -->
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-hospital-text">⚙️ إعدادات النظام</h2>
            <p class="mt-0.5 text-xs text-hospital-text-3">إعدادات المستشفى والنظام المالي والبيئة</p>
        </div>
        <button
            class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-hospital-primary/90"
            @click="submit"
        >
            <Save class="h-4 w-4" />
            💾 حفظ الإعدادات
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

        <!-- ── Hospital Info ── -->
        <div class="settings-section">
            <div class="settings-title">🏥 بيانات المستشفى</div>
            <div class="settings-grid">
                <div class="fg col-span-2">
                    <label>اسم المستشفى</label>
                    <input v-model="form['hospital_name']" type="text" class="s-input" placeholder="مستشفى النور" />
                </div>
                <div class="fg">
                    <label>التخصص</label>
                    <input v-model="form['hospital_specialty']" type="text" class="s-input" placeholder="طب وجراحة العيون" />
                </div>
                <div class="fg">
                    <label>رقم التليفون</label>
                    <input v-model="form['hospital_phone']" type="tel" class="s-input" placeholder="086-xxxxxxx" />
                </div>
                <div class="fg col-span-2">
                    <label>العنوان</label>
                    <input v-model="form['hospital_address']" type="text" class="s-input" placeholder="المنيا، مصر" />
                </div>
                <div class="fg">
                    <label>البريد الإلكتروني</label>
                    <input v-model="form['hospital_email']" type="email" class="s-input" placeholder="info@hospital.com" />
                </div>
                <div class="fg">
                    <label>الرقم الضريبي</label>
                    <input v-model="form['hospital_tax_no']" type="text" class="s-input" placeholder="الرقم الضريبي" />
                </div>
            </div>
        </div>

        <!-- ── Financial Settings ── -->
        <div class="settings-section">
            <div class="settings-title">💰 الإعدادات المالية</div>
            <div class="settings-grid">
                <div class="fg">
                    <label>العملة</label>
                    <select v-model="form['default_currency']" class="s-input">
                        <option value="EGP">جنيه مصري (ج)</option>
                        <option value="USD">دولار ($)</option>
                        <option value="SAR">ريال سعودي (ر.س)</option>
                    </select>
                </div>
                <div class="fg">
                    <label>نسبة الضريبة %</label>
                    <input v-model="form['tax_pct']" type="number" min="0" max="100" class="s-input" placeholder="0" />
                </div>
                <div class="fg col-span-2">
                    <label>تنسيق رقم الملف</label>
                    <select v-model="form['mrn_format']" class="s-input">
                        <option value="MRN-{year}-{seq5}">MRN-YYYY-00001</option>
                        <option value="P-{seq5}">P-00001</option>
                        <option value="{year}-{seq5}">{year}-00001</option>
                    </select>
                </div>
                <div class="fg col-span-2 info-box">
                    <span>💡</span>
                    <span>نسبة كل طبيب تُحدَّد بشكل منفرد من صفحة <strong>إدارة الأطباء</strong> — يمكن تعيين نسبة مئوية أو قيمة ثابتة لكل طبيب على حدة.</span>
                </div>
            </div>
        </div>

        <!-- ── Inventory Settings ── -->
        <div class="settings-section">
            <div class="settings-title">📦 إعدادات المخزون</div>
            <div class="settings-grid">
                <div class="fg col-span-2">
                    <label>تنبيه المخزون عند وصوله إلى %</label>
                    <div class="flex items-center gap-2">
                        <input v-model="form['stock_alert_pct']" type="number" min="5" max="50" class="s-input" placeholder="20" />
                        <span class="text-sm text-hospital-text-2">%</span>
                    </div>
                </div>
                <div class="fg col-span-2">
                    <label>تفعيل تنبيهات المخزون</label>
                    <select v-model="form['low_stock_alerts_enabled']" class="s-input">
                        <option value="true">مفعّل</option>
                        <option value="false">معطّل</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ── Beds Config ── -->
        <div class="settings-section">
            <div class="settings-title">🛏️ إعدادات الأسرّة</div>
            <div class="settings-grid">
                <div class="fg">
                    <label>عدد أسرّة قسم الجراحة</label>
                    <input v-model="form['surgery_beds']" type="number" min="1" max="100" class="s-input" placeholder="30" />
                </div>
                <div class="fg">
                    <label>عدد أسرّة قسم الليزك</label>
                    <input v-model="form['lasik_beds']" type="number" min="1" max="100" class="s-input" placeholder="20" />
                </div>
            </div>
        </div>

        <!-- ── HR Payroll Settings ── -->
        <div class="settings-section">
            <div class="settings-title">👥 إعدادات الموارد البشرية</div>
            <div class="settings-grid">
                <div class="fg">
                    <label>أيام العمل في الشهر</label>
                    <input v-model="form['hr_working_days']" type="number" min="20" max="31" class="s-input" placeholder="26" />
                    <span class="s-hint">المعيار وفق قانون العمل المصري: 26 يوم</span>
                </div>
                <div class="fg">
                    <label>ساعات العمل في اليوم</label>
                    <input v-model="form['hr_hours_per_day']" type="number" min="4" max="12" class="s-input" placeholder="8" />
                </div>
                <div class="fg">
                    <label>معامل الوقت الإضافي</label>
                    <input v-model="form['hr_overtime_multiplier']" type="number" min="1" max="3" step="0.1" class="s-input" placeholder="1.5" />
                    <span class="s-hint">1.5 = وقت ونصف</span>
                </div>
                <div class="fg">
                    <label>نسبة خصم التأخير % من اليوم</label>
                    <input v-model="form['hr_late_deduction_pct']" type="number" min="0" max="100" class="s-input" placeholder="25" />
                    <span class="s-hint">25 = خصم ربع يوم عند التأخير</span>
                </div>
                <div class="fg col-span-2">
                    <label>نسبة خصم نصف اليوم % من اليوم</label>
                    <input v-model="form['hr_half_day_deduction_pct']" type="number" min="0" max="100" class="s-input" placeholder="50" />
                </div>
                <div class="fg col-span-2">
                    <label>أقسام الموارد البشرية</label>
                    <input v-model="form['hr_departments']" type="text" class="s-input" placeholder="العيادة,التمريض,الإدارة,..." />
                    <span class="s-hint">أدخل الأقسام مفصولةً بفاصلة — تُستخدم في قوائم اختيار الموظفين</span>
                </div>
            </div>
        </div>

        <!-- ── System Modules ── -->
        <div class="settings-section lg:col-span-2">
            <div class="settings-title">🧩 أقسام النظام</div>
            <p class="mb-3 text-xs text-hospital-text-3">
                يمكنك إخفاء أي قسم بالكامل عن كل المستخدمين مؤقتاً، ثم إعادة إظهاره لاحقاً في أي وقت.
            </p>
            <div class="module-toggle-list">
                <div v-for="m in systemModules" :key="m.value" class="module-toggle-row">
                    <span class="module-toggle-label">{{ m.label }}</span>
                    <button
                        type="button"
                        class="module-toggle-switch"
                        :class="{ 'module-toggle-switch--on': isModuleEnabled(m.value) }"
                        :aria-pressed="isModuleEnabled(m.value)"
                        @click="toggleModule(m.value)"
                    >
                        <span class="module-toggle-knob" />
                    </button>
                    <span class="module-toggle-state" :class="{ 'module-toggle-state--off': !isModuleEnabled(m.value) }">
                        {{ isModuleEnabled(m.value) ? 'مفعّل' : 'مخفي' }}
                    </span>
                </div>
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
}
.settings-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-hospital-primary, #0A4FA6);
    margin-bottom: 14px;
    padding-bottom: 9px;
    border-bottom: 2px solid var(--color-hospital-primary-pale, #E8F1FB);
}
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.fg {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.fg label {
    font-size: 11px;
    font-weight: 700;
    color: var(--color-hospital-text-2, #4A5878);
}
.col-span-2 { grid-column: span 2; }
.s-input {
    padding: 8px 11px;
    border: 1.5px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 7px;
    font-size: 12px;
    font-family: inherit;
    color: var(--color-hospital-text, #0D1F3C);
    background: #fff;
    direction: rtl;
    width: 100%;
    transition: border-color 0.15s;
}
.s-input:focus {
    outline: none;
    border-color: var(--color-hospital-primary, #0A4FA6);
    box-shadow: 0 0 0 3px rgba(10, 79, 166, 0.08);
}
.s-hint {
    font-size: 10px;
    color: var(--color-hospital-text-3, #8A96AE);
    margin-top: 2px;
}
.info-box {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 12px;
    background: var(--color-hospital-bg, #F3F6FA);
    border-radius: 7px;
    font-size: 11px;
    color: var(--color-hospital-text-2, #4A5878);
    line-height: 1.5;
}
.module-toggle-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.module-toggle-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border: 1.5px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 8px;
}
.module-toggle-label {
    flex: 1;
    font-size: 12px;
    font-weight: 700;
    color: var(--color-hospital-text, #0D1F3C);
}
.module-toggle-switch {
    position: relative;
    width: 38px;
    height: 21px;
    border-radius: 999px;
    border: none;
    background: #C9D2E3;
    cursor: pointer;
    transition: background-color 0.15s;
    flex-shrink: 0;
}
.module-toggle-switch--on {
    background: var(--color-hospital-primary, #0A4FA6);
}
.module-toggle-knob {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 17px;
    height: 17px;
    border-radius: 999px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
    transition: transform 0.15s;
}
.module-toggle-switch--on .module-toggle-knob {
    transform: translateX(-17px);
}
.module-toggle-state {
    width: 40px;
    font-size: 10px;
    font-weight: 700;
    color: var(--color-hospital-primary, #0A4FA6);
}
.module-toggle-state--off {
    color: var(--color-hospital-text-3, #8A96AE);
}
</style>
