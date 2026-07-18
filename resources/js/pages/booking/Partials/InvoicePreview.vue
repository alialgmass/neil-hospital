<script setup lang="ts">
interface Props {
    selectedServiceName: string;
    price: string;
    discount: string;
    insAmount: string;
    isInsurance: boolean;
    netAmount: number;
}

defineProps<Props>();
</script>

<template>
    <div class="inv-preview mt-3">
        <p class="inv-preview-title">🧾 فاتورة تلقائية — ستُنشأ عند الحفظ</p>
        <div class="inv-line">
            <span>الخدمة</span><span>{{ selectedServiceName || '—' }}</span>
        </div>
        <div class="inv-line">
            <span>السعر الأصلي</span
            ><span>{{ Number(price).toLocaleString('ar-EG') }} ج</span>
        </div>
        <div class="inv-line">
            <span>الخصم</span
            ><span>{{ Number(discount).toLocaleString('ar-EG') }} ج</span>
        </div>
        <div v-if="isInsurance" class="inv-line">
            <span>مبلغ التأمين</span
            ><span>{{ Number(insAmount).toLocaleString('ar-EG') }} ج</span>
        </div>
        <div class="inv-line font-bold">
            <span>💰 الإجمالي المستحق</span
            ><span>{{ netAmount.toLocaleString('ar-EG') }} ج</span>
        </div>
    </div>
</template>

<style scoped>
.inv-preview {
    background: #fffef5;
    border: 1.5px dashed #e07c10;
    border-radius: 8px;
    padding: 12px;
    font-size: 11px;
}

.inv-preview-title {
    font-weight: 700;
    color: #e07c10;
    margin-bottom: 8px;
    font-size: 12px;
}

.inv-line {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px solid #f3f6fa;
    color: #0d1f3c;
}

.inv-line:last-child {
    border-bottom: none;
}
</style>