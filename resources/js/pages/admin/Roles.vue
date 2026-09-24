<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import Modal from '@/components/shared/Modal.vue';

defineOptions({ layout: AppLayout });

interface Permission {
    name: string;
    label: string;
    group: string;
    group_label: string;
}

interface Role {
    id: number;
    name: string;
    label: string;
    permissions: Permission[];
}

const props = defineProps<{
    roles: Role[];
    allPermissions: Permission[];
}>();

// Group permissions by module prefix — labels come translated from the server
// (lang/ar/permissions.php via PermissionLabelService).
const permissionGroups = computed(() => {
    const groups: Record<string, { label: string; permissions: Permission[] }> = {};

    for (const p of props.allPermissions) {
        if (!groups[p.group]) {
            groups[p.group] = { label: p.group_label, permissions: [] };
        }

        groups[p.group].permissions.push(p);
    }

    return groups;
});

// Edit permissions for a role
const editingRole  = ref<Role | null>(null);
const editForm     = useForm({ permissions: [] as string[] });

function openEdit(role: Role) {
    editingRole.value    = role;
    editForm.permissions = role.permissions.map(p => p.name);
}

function togglePermission(perm: string) {
    const idx = editForm.permissions.indexOf(perm);

    if (idx === -1) {
        editForm.permissions.push(perm);
    } else {
        editForm.permissions.splice(idx, 1);
    }
}

function submitEdit() {
    if (!editingRole.value) {
 return; 
}

    editForm.put(`/roles/${editingRole.value.id}/permissions`, {
        onSuccess: () => {
 editingRole.value = null; 
},
    });
}
</script>

<template>
    <Head title="الأدوار والصلاحيات" />

    <h2 class="mb-5 text-lg font-bold text-hospital-text">الأدوار والصلاحيات</h2>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
            v-for="role in roles"
            :key="role.id"
            class="overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm"
        >
            <!-- Role Header -->
            <div class="flex items-center justify-between border-b border-hospital-border bg-hospital-bg px-4 py-3">
                <div class="flex items-center gap-2">
                    <Shield class="h-4 w-4 text-hospital-primary" />
                    <span class="font-semibold text-hospital-text">{{ role.label }}</span>
                </div>
                <span class="text-xs text-hospital-text-3">{{ role.permissions.length }} صلاحية</span>
            </div>

            <!-- Permissions list -->
            <div class="p-4">
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="perm in role.permissions"
                        :key="perm.name"
                        class="rounded-full bg-hospital-primary/10 px-2 py-0.5 text-xs text-hospital-primary"
                    >
                        {{ perm.label }}
                    </span>
                    <span v-if="role.permissions.length === 0" class="text-xs text-hospital-text-3">لا توجد صلاحيات</span>
                </div>
                <button
                    v-if="role.name !== 'admin'"
                    class="mt-3 w-full rounded-lg border border-hospital-border py-1.5 text-xs text-hospital-text hover:bg-hospital-bg transition-colors"
                    @click="openEdit(role)"
                >
                    تعديل الصلاحيات
                </button>
                <p v-else class="mt-3 text-center text-xs text-hospital-text-3">صلاحيات كاملة — لا يمكن تعديلها</p>
            </div>
        </div>
    </div>

    <!-- Edit Permissions Modal -->
    <Modal v-if="editingRole" :model-value="!!editingRole" title="تعديل صلاحيات الدور" size="lg" @update:model-value="editingRole = null">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <div class="flex items-center gap-2 rounded-lg border border-br bg-pp px-3 py-2">
                <Shield class="h-4 w-4 text-p" />
                <span class="text-sm font-medium text-pd">{{ editingRole.label }}</span>
                <span class="mr-auto text-xs text-t3">{{ editForm.permissions.length }} صلاحية محددة</span>
            </div>

            <div
                v-for="(groupData, group) in permissionGroups"
                :key="group"
                class="overflow-hidden rounded-lg border border-br"
            >
                <div class="border-b border-br bg-sf2 px-3 py-2">
                    <p class="text-xs font-bold text-t">{{ groupData.label }}</p>
                </div>
                <div class="flex flex-wrap gap-2 p-3">
                    <label
                        v-for="perm in groupData.permissions"
                        :key="perm.name"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-all select-none"
                        :class="editForm.permissions.includes(perm.name)
                            ? 'border-p bg-pp text-pd shadow-sm'
                            : 'border-br text-t2 hover:border-p/40 hover:bg-pp/50'"
                    >
                        <span
                            class="flex h-3.5 w-3.5 shrink-0 items-center justify-center rounded border transition-colors"
                            :class="editForm.permissions.includes(perm.name) ? 'border-p bg-p' : 'border-t3'"
                        >
                            <svg v-if="editForm.permissions.includes(perm.name)" class="h-2.5 w-2.5 text-white" fill="none" viewBox="0 0 10 10">
                                <path d="M2 5l2.5 2.5L8 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <input
                            type="checkbox"
                            :checked="editForm.permissions.includes(perm.name)"
                            class="sr-only"
                            @change="togglePermission(perm.name)"
                        />
                        <span :title="perm.name">{{ perm.label }}</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="editingRole = null">إلغاء</button>
                <button type="submit" :disabled="editForm.processing" class="btn-primary">
                    {{ editForm.processing ? 'جارٍ الحفظ...' : 'حفظ الصلاحيات' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
