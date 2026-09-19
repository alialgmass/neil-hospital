<script setup lang="ts">
import { ChevronDown, ChevronUp, Search } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/components/shared/Modal.vue';

interface Props {
    modelValue: boolean;
    permissionsByModule: Record<string, { name: string }[]>;
    /** Currently selected DIRECT permissions (not role-inherited). */
    selected: string[];
    /** Permissions inherited via the employee's current role — shown, not editable here. */
    rolePermissions: string[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'save', selected: string[]): void;
}>();

const localSelected = ref<Set<string>>(new Set());
const search = ref('');
const openModules = reactive<Record<string, boolean>>({});

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            localSelected.value = new Set(props.selected);
            search.value = '';

            // Expand every module by default when opening.
            for (const mod of Object.keys(props.permissionsByModule)) {
                openModules[mod] = true;
            }
        }
    },
);

function toggleModule(mod: string) {
    openModules[mod] = !openModules[mod];
}

function isRoleGranted(name: string): boolean {
    return props.rolePermissions.includes(name);
}

function isChecked(name: string): boolean {
    return localSelected.value.has(name) || isRoleGranted(name);
}

function toggle(name: string) {
    if (isRoleGranted(name)) {
        // Inherited via role — not editable from here.
        return;
    }

    if (localSelected.value.has(name)) {
        localSelected.value.delete(name);
    } else {
        localSelected.value.add(name);
    }
}

function selectAllInModule(mod: string) {
    for (const p of props.permissionsByModule[mod] ?? []) {
        if (!isRoleGranted(p.name)) {
            localSelected.value.add(p.name);
        }
    }
}

function clearAllInModule(mod: string) {
    for (const p of props.permissionsByModule[mod] ?? []) {
        localSelected.value.delete(p.name);
    }
}

const filteredModules = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) {
        return props.permissionsByModule;
    }

    const result: Record<string, { name: string }[]> = {};

    for (const [mod, perms] of Object.entries(props.permissionsByModule)) {
        const matches = perms.filter(
            (p) =>
                p.name.toLowerCase().includes(term) ||
                mod.toLowerCase().includes(term),
        );

        if (matches.length) {
            result[mod] = matches;
        }
    }

    return result;
});

const selectedCount = computed(() => localSelected.value.size);

function save() {
    emit('save', Array.from(localSelected.value));
}

function close() {
    emit('update:modelValue', false);
}
</script>

<template>
    <Modal
        :model-value="modelValue"
        title="إدارة الصلاحيات"
        size="lg"
        @update:model-value="(v) => emit('update:modelValue', v)"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 right-2.5 h-4 w-4 -translate-y-1/2 text-t3"
                />
                <input
                    v-model="search"
                    type="search"
                    placeholder="بحث عن صلاحية..."
                    class="input-field w-full pr-8"
                />
            </div>
            <span
                class="shrink-0 rounded-full bg-pp px-3 py-1 text-xs font-bold text-p"
            >
                Permissions: {{ selectedCount }} selected
            </span>
        </div>

        <div class="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
            <div
                v-for="(perms, mod) in filteredModules"
                :key="mod"
                class="overflow-hidden rounded-lg border border-br"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between bg-sf2 px-3 py-2 text-right"
                    @click="toggleModule(mod)"
                >
                    <span class="text-xs font-bold text-t">{{ mod }}</span>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-t3"
                            >{{ perms.length }} صلاحية</span
                        >
                        <ChevronUp
                            v-if="openModules[mod]"
                            class="h-3.5 w-3.5 text-t3"
                        />
                        <ChevronDown v-else class="h-3.5 w-3.5 text-t3" />
                    </div>
                </button>

                <div
                    v-if="openModules[mod]"
                    class="border-t border-br bg-sf p-3"
                >
                    <div class="mb-2 flex items-center gap-3">
                        <button
                            type="button"
                            class="text-[11px] font-medium text-p hover:underline"
                            @click="selectAllInModule(mod)"
                        >
                            تحديد الكل
                        </button>
                        <button
                            type="button"
                            class="text-[11px] font-medium text-t3 hover:underline"
                            @click="clearAllInModule(mod)"
                        >
                            إلغاء الكل
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5">
                        <label
                            v-for="p in perms"
                            :key="p.name"
                            class="flex items-center gap-2 text-xs"
                            :class="
                                isRoleGranted(p.name)
                                    ? 'cursor-not-allowed text-t3'
                                    : 'cursor-pointer text-t'
                            "
                        >
                            <input
                                type="checkbox"
                                :checked="isChecked(p.name)"
                                :disabled="isRoleGranted(p.name)"
                                @change="toggle(p.name)"
                            />
                            <span>{{ p.name }}</span>
                            <span
                                v-if="isRoleGranted(p.name)"
                                class="rounded bg-sf2 px-1 py-0.5 text-[9px] text-t3"
                                >عبر الدور</span
                            >
                        </label>
                    </div>
                </div>
            </div>

            <div
                v-if="Object.keys(filteredModules).length === 0"
                class="py-8 text-center text-xs text-t3"
            >
                لا توجد صلاحيات مطابقة
            </div>
        </div>

        <template #footer>
            <button type="button" class="btn-secondary" @click="close">
                إلغاء
            </button>
            <button type="button" class="btn-primary" @click="save">حفظ</button>
        </template>
    </Modal>
</template>
