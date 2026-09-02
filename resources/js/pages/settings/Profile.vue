<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'بيانات الحساب', href: edit() }],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="بيانات الحساب" />

    <!-- ── Profile section ── -->
    <div class="settings-section">
        <div class="settings-title">👤 بيانات المستخدم</div>

        <Form
            v-bind="ProfileController.update.form()"
            class="settings-grid"
            v-slot="{ errors, processing }"
        >
            <div class="fg">
                <label>الاسم الكامل</label>
                <input
                    name="name"
                    type="text"
                    :default-value="user.name"
                    placeholder="الاسم الكامل"
                    required
                    autocomplete="name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="fg">
                <label>اسم المستخدم</label>
                <input
                    name="username"
                    type="text"
                    :default-value="user.username"
                    placeholder="اسم المستخدم"
                    required
                    autocomplete="username"
                />
                <InputError :message="errors.username" />
            </div>

            <div class="fg">
                <label>البريد الإلكتروني</label>
                <input
                    name="email"
                    type="email"
                    :default-value="user.email"
                    placeholder="example@domain.com"
                    autocomplete="email"
                />
                <InputError :message="errors.email" />
            </div>

            <div v-if="mustVerifyEmail && !user.email_verified_at" class="fg col-span-2">
                <div class="verify-alert">
                    <span>⚠️</span>
                    <div>
                        البريد الإلكتروني غير مُفعَّل.
                        <Link
                            :href="send()"
                            as="button"
                            class="underline underline-offset-2 hover:text-hospital-primary"
                        >إعادة إرسال رابط التفعيل</Link>
                    </div>
                </div>
                <p v-if="status === 'verification-link-sent'" class="mt-1 text-xs text-hospital-success">
                    تم إرسال رابط التفعيل إلى بريدك الإلكتروني.
                </p>
            </div>

            <div class="col-span-2 flex justify-end">
                <button type="submit" :disabled="processing" class="settings-btn-primary">
                    {{ processing ? 'جارٍ الحفظ…' : '💾 حفظ البيانات' }}
                </button>
            </div>
        </Form>
    </div>

    <!-- ── Delete account ── -->
    <div class="settings-section settings-section-danger">
        <div class="settings-title settings-title-danger">🗑️ حذف الحساب</div>
        <DeleteUser />
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
.settings-section-danger {
    border-color: #FECACA;
    background: #FFF5F5;
}
.settings-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-hospital-primary, #0A4FA6);
    margin-bottom: 16px;
    padding-bottom: 9px;
    border-bottom: 2px solid var(--color-hospital-primary-pale, #E8F1FB);
}
.settings-title-danger {
    color: #DC2626;
    border-bottom-color: #FECACA;
}

.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

/* .fg mirrors the mockup's form field style */
:deep(.fg),
.fg {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
:deep(.fg label),
.fg label {
    font-size: 11px;
    font-weight: 700;
    color: var(--color-hospital-text-2, #4A5878);
}
:deep(.fg input),
:deep(.fg select),
:deep(.fg textarea),
.fg input,
.fg select {
    padding: 8px 11px;
    border: 1.5px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 7px;
    font-size: 12px;
    font-family: inherit;
    color: var(--color-hospital-text, #0D1F3C);
    background: var(--color-hospital-surface, #fff);
    direction: rtl;
    transition: border-color 0.15s;
}
:deep(.fg input:focus),
.fg input:focus {
    outline: none;
    border-color: var(--color-hospital-primary, #0A4FA6);
    box-shadow: 0 0 0 3px rgba(10, 79, 166, 0.08);
}

.col-span-2 { grid-column: span 2; }

.verify-alert {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 12px;
    background: #FFFBEB;
    border: 1px solid #FDE68A;
    border-radius: 7px;
    font-size: 12px;
    color: #92400E;
}

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
}
.settings-btn-primary:hover { background: #0B4A98; }
.settings-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
</style>
