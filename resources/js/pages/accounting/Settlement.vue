<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { CheckCircle2, AlertTriangle, Lock, Calendar, TrendingUp, TrendingDown, DollarSign } from 'lucide-vue-next'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    month: string
    preview: {
        month: string
        total_revenue: number
        total_expenses: number
        total_doctor_claims: number
        net_surplus_deficit: number
        is_locked: boolean
    }
}>()

const form = useForm({
    month: props.month,
    notes: '',
})

function changeMonth(e: Event) {
    const target = e.target as HTMLInputElement
    router.get('/accounting/settlement', { month: target.value }, { preserveState: true })
}

function lockMonth() {
    form.post('/accounting/settlement/lock', {
        onSuccess: () => {
            // Success handled by flash messages or internal state
        },
    })
}

const fmt = (val: number) => new Intl.NumberFormat('ar-EG').format(val)
</script>

<template>
    <Head title="إغلاق الفترة المالية" />

    <div class="max-w-5xl mx-auto space-y-6 pb-12">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-[15px] font-bold text-hospital-text">إغلاق الفترة (التسوية الشهرية)</h2>
                <p class="text-[10px] text-hospital-text-3">مراجعة وتثبيت الأرقام المالية النهائية للشهر</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative">
                    <Calendar class="absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-hospital-text-3" />
                    <input 
                        type="month" 
                        :value="month"
                        @change="changeMonth"
                        class="h-9 rounded-[8px] border border-hospital-border bg-white pr-9 pl-3 text-[12px] font-bold text-hospital-text focus:border-hospital-primary focus:ring-0"
                    />
                </div>
            </div>
        </div>

        <!-- Locking Status Alert -->
        <div 
            v-if="preview.is_locked"
            class="flex items-center gap-3 rounded-[var(--rl)] border border-hospital-success/20 bg-hospital-success-pale p-4 text-hospital-success"
        >
            <CheckCircle2 class="h-5 w-5 shrink-0" />
            <div>
                <p class="text-[12px] font-bold">هذه الفترة مغلقة بالفعل</p>
                <p class="text-[10px] opacity-80">تم تثبيت كافة القيود ولا يمكن إجراء أي تعديلات مالية على هذا الشهر.</p>
            </div>
        </div>

        <div v-else class="flex items-center gap-3 rounded-[var(--rl)] border border-hospital-primary/20 bg-hospital-primary-pale p-4 text-hospital-primary">
            <Lock class="h-5 w-5 shrink-0" />
            <div>
                <p class="text-[12px] font-bold">الفترة مفتوحة للمراجعة</p>
                <p class="text-[10px] opacity-80">يمكنك مراجعة الأرقام أدناه قبل الضغط على زر "إعتماد وإغلاق الشهر".</p>
            </div>
        </div>

        <!-- Summary Grid -->
        <div class="grid grid-cols-1 gap-5 md:grid-cols-4">
            <!-- Revenue -->
            <div class="card rounded-[var(--rl)] border border-hospital-border bg-white p-5 [box-shadow:var(--sh)]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-hospital-text-3 uppercase">إجمالي الإيرادات</span>
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-hospital-success-pale text-hospital-success">
                        <TrendingUp class="h-3.5 w-3.5" />
                    </div>
                </div>
                <p class="text-[18px] font-bold text-hospital-text">{{ fmt(preview.total_revenue) }} <span class="text-[10px] font-medium mr-1">ج.م</span></p>
            </div>

            <!-- Expenses -->
            <div class="card rounded-[var(--rl)] border border-hospital-border bg-white p-5 [box-shadow:var(--sh)]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-hospital-text-3 uppercase">المصروفات التشغيلية</span>
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-hospital-danger-pale text-hospital-danger">
                        <TrendingDown class="h-3.5 w-3.5" />
                    </div>
                </div>
                <p class="text-[18px] font-bold text-hospital-text">{{ fmt(preview.total_expenses) }} <span class="text-[10px] font-medium mr-1">ج.م</span></p>
            </div>

            <!-- Dr Claims -->
            <div class="card rounded-[var(--rl)] border border-hospital-border bg-white p-5 [box-shadow:var(--sh)]">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-hospital-text-3 uppercase">مستحقات الأطباء</span>
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                        <DollarSign class="h-3.5 w-3.5" />
                    </div>
                </div>
                <p class="text-[18px] font-bold text-hospital-text">{{ fmt(preview.total_doctor_claims) }} <span class="text-[10px] font-medium mr-1">ج.م</span></p>
            </div>

            <!-- Net Result -->
            <div 
                class="card rounded-[var(--rl)] border p-5 [box-shadow:var(--sh)]"
                :class="preview.net_surplus_deficit >= 0 ? 'bg-hospital-primary border-hospital-primary text-white' : 'bg-hospital-danger border-hospital-danger text-white'"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold opacity-80 uppercase">صافي (فائض/عجز)</span>
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-white/20">
                        <CheckCircle2 class="h-3.5 w-3.5" />
                    </div>
                </div>
                <p class="text-[18px] font-bold">{{ fmt(preview.net_surplus_deficit) }} <span class="text-[10px] font-medium mr-1">ج.م</span></p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left: Confirmation Form -->
            <div class="lg:col-span-2">
                <div class="card rounded-[var(--rl)] border border-hospital-border bg-white overflow-hidden [box-shadow:var(--sh)]">
                    <div class="card-hd border-b border-hospital-border bg-hospital-surface-2 px-5 py-4">
                        <h3 class="text-[13px] font-bold text-hospital-text">إجراء الإغلاق المحاسبي</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <p class="text-[12px] text-hospital-text-2 leading-relaxed">
                            عند إغلاق الشهر، سيقوم النظام بتسجيل ملخص مالي للفترة وتثبيت الأرصدة. 
                            <span class="font-bold text-hospital-danger">تنبيه:</span> لا يمكن التراجع عن هذا الإجراء بسهولة، وسيمنع النظام أي تعديل على القيود المحاسبية التابعة لهذا الشهر.
                        </p>
                        
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-hospital-text-3">ملاحظات الإغلاق (اختياري)</label>
                            <textarea 
                                v-model="form.notes"
                                class="w-full rounded-[8px] border border-hospital-border p-3 text-[12px] focus:border-hospital-primary focus:ring-0 min-h-[100px]"
                                placeholder="مثال: تم مطابقة كافة الحسابات والترصيد بنجاح..."
                                :disabled="preview.is_locked"
                            ></textarea>
                        </div>

                        <button 
                            v-if="!preview.is_locked"
                            @click="lockMonth"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-hospital-primary py-3 text-[13px] font-bold text-white transition-all hover:bg-hospital-primary-dark active:scale-[0.98] disabled:opacity-50 shadow-lg shadow-hospital-primary/20"
                        >
                            <Lock class="h-4 w-4" />
                            <span>{{ form.processing ? 'جارٍ المعالجة...' : 'إعتماد وإغلاق الفترة' }}</span>
                        </button>

                        <div v-else class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-hospital-border py-3 text-[13px] font-bold text-hospital-text-3 cursor-not-allowed">
                            <CheckCircle2 class="h-4 w-4" />
                            <span>تم الإغلاق مسبقاً</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Checklist -->
            <div class="lg:col-span-1">
                <div class="card rounded-[var(--rl)] border border-hospital-border bg-white overflow-hidden [box-shadow:var(--sh)]">
                    <div class="card-hd border-b border-hospital-border bg-hospital-surface-2 px-5 py-4">
                        <h3 class="text-[13px] font-bold text-hospital-text">قائمة التحقق</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5"><CheckCircle2 class="h-3.5 w-3.5 text-hospital-success" /></div>
                            <p class="text-[11px] text-hospital-text-2">مراجعة كافة الفواتير والتأكد من تحصيلها.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5"><CheckCircle2 class="h-3.5 w-3.5 text-hospital-success" /></div>
                            <p class="text-[11px] text-hospital-text-2">صرف مستحقات الأطباء المتعلقة بهذا الشهر.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5"><CheckCircle2 class="h-3.5 w-3.5 text-hospital-success" /></div>
                            <p class="text-[11px] text-hospital-text-2">تسجيل كافة المصروفات التشغيلية والرواتب.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5"><AlertTriangle class="h-3.5 w-3.5 text-hospital-warning" /></div>
                            <p class="text-[11px] text-hospital-text-2 font-bold">مطابقة رصيد الخزينة الفعلي مع رصيد النظام.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
