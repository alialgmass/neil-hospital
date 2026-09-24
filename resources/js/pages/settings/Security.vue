<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { edit } from '@/routes/security';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'الأمان', href: edit() }],
    },
});
</script>

<template>
    <Head title="الأمان" />

    <!-- ── Password section ── -->
    <div class="settings-section">
        <div class="settings-title">🔒 تغيير كلمة المرور</div>
        <p class="settings-desc">استخدم كلمة مرور طويلة وعشوائية للحفاظ على أمان حسابك.</p>

        <Form
            v-bind="SecurityController.update.form()"
            :options="{ preserveScroll: true }"
            reset-on-success
            :reset-on-error="['password', 'password_confirmation', 'current_password']"
            class="settings-grid"
            v-slot="{ errors, processing }"
        >
            <div class="fg col-span-2">
                <label>كلمة المرور الحالية</label>
                <PasswordInput
                    name="current_password"
                    autocomplete="current-password"
                    placeholder="كلمة المرور الحالية"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="fg">
                <label>كلمة المرور الجديدة</label>
                <PasswordInput
                    name="password"
                    autocomplete="new-password"
                    placeholder="كلمة المرور الجديدة"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="fg">
                <label>تأكيد كلمة المرور</label>
                <PasswordInput
                    name="password_confirmation"
                    autocomplete="new-password"
                    placeholder="أعد كتابة كلمة المرور"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="col-span-2 flex justify-end">
                <button type="submit" :disabled="processing" class="settings-btn-primary">
                    {{ processing ? 'جارٍ الحفظ…' : '💾 حفظ كلمة المرور' }}
                </button>
            </div>
        </Form>
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
    margin-bottom: 14px;
}
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
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
:deep(.fg input),
:deep(input[type="password"]),
:deep(.password-input input) {
    padding: 8px 11px;
    border: 1.5px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 7px;
    font-size: 12px;
    font-family: inherit;
    color: var(--color-hospital-text, #0D1F3C);
    background: #fff;
    direction: rtl;
    transition: border-color 0.15s;
    width: 100%;
}
.col-span-2 { grid-column: span 2; }

.settings-btn-primary {
    padding: 8px 22px;
    background: var(--color-hospital-primary, #0A4FA6);
    color: #fff;
    border: none;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.settings-btn-primary:hover { background: #0B4A98; }
.settings-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }


</style>
