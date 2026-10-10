<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { PlusCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';

interface Role { id: number; name: string; label?: string }
interface User {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    created_at: string;
}

const props = defineProps<{
    users: { data: User[]; current_page: number; last_page: number; total: number };
    roles: Role[];
}>();

const columns = [
    { key: 'name',      label: 'الاسم',     sortable: true },
    { key: 'email',     label: 'البريد' },
    { key: 'role',      label: 'الدور' },
    { key: 'created_at', label: 'تاريخ الإنشاء' },
];

function goToPage(page: number) {
 router.get('/users', { page }, { preserveState: true }); 
}

const showAdd = ref(false);
const form = useForm({ name: '', email: '', password: '', role: '' });
function submit() {
 form.post('/users', { onSuccess: () => {
 showAdd.value = false; form.reset(); 
} }); 
}

/** Translated role label (served by PermissionLabelService), falling back to the key. */
function roleLabel(name: string): string {
    return props.roles.find((role) => role.name === name)?.label ?? name;
}

function getRoleName(user: User): string {
    return user.roles[0]?.name ?? '—';
}
</script>

<template>
    <Head title="إدارة المستخدمين" />

    <div class="mb-5 flex items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">إدارة المستخدمين</h2>
        <button class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90" @click="showAdd = true">
            <PlusCircle class="h-4 w-4" /> مستخدم جديد
        </button>
    </div>

    <DataTable :columns="columns" :rows="users.data" :current-page="users.current_page" :last-page="users.last_page" :total="users.total" empty-text="لا يوجد مستخدمون" @page="goToPage">
        <template #cell-role="{ row }">
            <span class="rounded-full bg-hospital-primary/10 px-2 py-0.5 text-xs font-medium text-hospital-primary">
                {{ roleLabel(getRoleName(row as User)) }}
            </span>
        </template>
        <template #cell-created_at="{ value }">
            {{ (value as string)?.slice(0, 10) }}
        </template>
    </DataTable>

    <Modal v-model="showAdd" title="إضافة مستخدم جديد" size="md">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium">الاسم</label>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-hospital-danger">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">البريد الإلكتروني</label>
                <input v-model="form.email" type="email" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                <p v-if="form.errors.email" class="mt-1 text-xs text-hospital-danger">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">كلمة المرور</label>
                <input v-model="form.password" type="password" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                <p v-if="form.errors.password" class="mt-1 text-xs text-hospital-danger">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">الدور الوظيفي</label>
                <select v-model="form.role" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                    <option value="">— اختر الدور —</option>
                    <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.label ?? role.name }}</option>
                </select>
                <p v-if="form.errors.role" class="mt-1 text-xs text-hospital-danger">{{ form.errors.role }}</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">إنشاء</button>
            </div>
        </form>
    </Modal>
</template>
