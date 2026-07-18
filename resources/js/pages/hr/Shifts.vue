<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Clock, PlusCircle } from 'lucide-vue-next'
import { ref } from 'vue'
import Modal from '@/components/shared/Modal.vue'

interface Shift {
    id: string
    name: string
    start_time: string
    end_time: string
    is_active: boolean
}

interface Handover {
    id: string
    handover_date: string
    handover_time?: string
    status: string
    shift: { name: string }
    from_employee: { name: string }
    to_employee: { name: string }
}

defineProps<{
    shifts: Shift[]
    recent_handovers: Handover[]
}>()

const handoverStatusConfig: Record<string, { label: string; class: string }> = {
    pending: { label: 'معلق', class: 'bg-wp text-w' },
    accepted: { label: 'مقبول', class: 'bg-sp text-s' },
}

// Add
const showAdd = ref(false)
const addForm = useForm({ name: '', start_time: '', end_time: '', is_active: true })
function submitAdd() {
    addForm.post('/shifts', { onSuccess: () => { showAdd.value = false; addForm.reset() } })
}

// Edit
const showEdit = ref(false)
const editingId = ref('')
const editForm = useForm({ name: '', start_time: '', end_time: '', is_active: true })
function openEdit(s: Shift) {
    editingId.value = s.id
    editForm.name = s.name
    editForm.start_time = s.start_time
    editForm.end_time = s.end_time
    editForm.is_active = s.is_active
    showEdit.value = true
}
function submitEdit() {
    editForm.put(`/shifts/${editingId.value}`, { onSuccess: () => { showEdit.value = false } })
}
</script>

<template>
    <Head title="الورديات" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">الورديات</h1>
        <p class="mt-0.5 text-sm text-t3">إدارة ورديات العمل في المستشفى</p>
    </div>

    <div class="grid grid-cols-3 gap-6">
        <!-- Shifts List -->
        <div class="col-span-1">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-t">الورديات المعرفة</h2>
                <button class="btn-primary flex items-center gap-1 text-xs px-3 py-1.5" @click="showAdd = true">
                    <PlusCircle class="h-3.5 w-3.5" />
                    إضافة
                </button>
            </div>
            <div class="space-y-2">
                <div v-for="s in shifts" :key="s.id"
                    class="flex items-center justify-between rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="s.is_active ? 'bg-pp' : 'bg-sf2'">
                            <Clock class="h-5 w-5" :class="s.is_active ? 'text-p' : 'text-t3'" />
                        </div>
                        <div>
                            <p class="font-semibold text-t">{{ s.name }}</p>
                            <p class="text-xs font-mono text-t3">{{ s.start_time }} — {{ s.end_time }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2 py-0.5 text-xs" :class="s.is_active ? 'bg-sp text-s' : 'bg-sf2 text-t3'">
                            {{ s.is_active ? 'نشطة' : 'موقوفة' }}
                        </span>
                        <button class="rounded-lg border border-br px-2.5 py-1 text-xs text-t2 hover:border-p/40 hover:bg-pp hover:text-p transition-colors" @click="openEdit(s)">
                            تعديل
                        </button>
                    </div>
                </div>
                <div v-if="shifts.length === 0" class="rounded-xl border border-br bg-sf p-8 text-center text-t3">
                    لا توجد ورديات محددة
                </div>
            </div>
        </div>

        <!-- Recent Handovers -->
        <div class="col-span-2">
            <h2 class="mb-3 text-sm font-semibold text-t">آخر عمليات تسليم الوردية</h2>
            <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
                <table class="w-full text-sm">
                    <thead class="bg-sf2">
                        <tr>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">التاريخ</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">الوردية</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">المسلِّم</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">المستلِم</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-br/50">
                        <tr v-for="h in recent_handovers" :key="h.id" class="hover:bg-sf2 transition-colors">
                            <td class="px-4 py-2.5 text-xs text-t2">{{ h.handover_date }}</td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-full bg-pp px-2 py-0.5 text-xs text-p">{{ h.shift?.name }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-t2">{{ h.from_employee?.name }}</td>
                            <td class="px-4 py-2.5 font-medium text-t">{{ h.to_employee?.name }}</td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="handoverStatusConfig[h.status]?.class ?? 'bg-sf2 text-t3'">
                                    {{ handoverStatusConfig[h.status]?.label ?? h.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="recent_handovers.length === 0">
                            <td class="px-4 py-10 text-center text-t3" colspan="5">لا توجد عمليات تسليم</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="إضافة وردية جديدة">
        <form class="space-y-4" @submit.prevent="submitAdd">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label">اسم الوردية <span class="text-d">*</span></label>
                    <input v-model="addForm.name" type="text" placeholder="صباحي، مسائي، ليلي..." class="input-field" />
                    <p v-if="addForm.errors.name" class="form-error">{{ addForm.errors.name }}</p>
                </div>
                <div>
                    <label class="form-label">وقت البداية</label>
                    <input v-model="addForm.start_time" type="time" class="input-field font-mono" />
                </div>
                <div>
                    <label class="form-label">وقت النهاية</label>
                    <input v-model="addForm.end_time" type="time" class="input-field font-mono" />
                </div>
                <div class="col-span-2">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input v-model="addForm.is_active" type="checkbox" class="h-4 w-4 rounded border-br" />
                        <span class="text-sm font-medium text-t">وردية نشطة</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="addForm.processing" class="btn-primary">
                    {{ addForm.processing ? 'جارٍ الحفظ...' : 'إضافة الوردية' }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Edit Modal -->
    <Modal v-model="showEdit" title="تعديل الوردية">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label">اسم الوردية <span class="text-d">*</span></label>
                    <input v-model="editForm.name" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">وقت البداية</label>
                    <input v-model="editForm.start_time" type="time" class="input-field font-mono" />
                </div>
                <div>
                    <label class="form-label">وقت النهاية</label>
                    <input v-model="editForm.end_time" type="time" class="input-field font-mono" />
                </div>
                <div class="col-span-2">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input v-model="editForm.is_active" type="checkbox" class="h-4 w-4 rounded border-br" />
                        <span class="text-sm font-medium text-t">وردية نشطة</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showEdit = false">إلغاء</button>
                <button type="submit" :disabled="editForm.processing" class="btn-primary">
                    {{ editForm.processing ? 'جارٍ الحفظ...' : 'حفظ التعديلات' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
