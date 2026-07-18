<script setup lang="ts">
interface Props {
    patientName: string;
    deptLabel: string;
    serviceName: string;
    doctorName: string;
    visitDate: string;
    visitTime: string;
    netAmount: number;
}

defineProps<Props>();
</script>

<template>
    <div class="bk-section">
        <span class="bk-title bk-title-blue">ملخص الحجز</span>
        <div class="pay-summary">
            <div class="pay-row">
                <span class="pay-lbl">اسم المريض</span>
                <span class="pay-val">{{ patientName || '—' }}</span>
            </div>
            <div class="pay-row">
                <span class="pay-lbl">القسم</span>
                <span class="pay-val">{{ deptLabel }}</span>
            </div>
            <div class="pay-row">
                <span class="pay-lbl">الخدمة</span>
                <span class="pay-val">{{ serviceName || '—' }}</span>
            </div>
            <div class="pay-row">
                <span class="pay-lbl">الطبيب</span>
                <span class="pay-val">{{ doctorName }}</span>
            </div>
            <div class="pay-row">
                <span class="pay-lbl">التاريخ</span>
                <span class="pay-val">{{ visitDate }} {{ visitTime }}</span>
            </div>
            <div class="pay-row">
                <span class="pay-lbl">إجمالي المستحق</span>
                <span class="pay-val pay-val-total">{{ netAmount.toLocaleString('ar-EG') }} ج</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bk-section {
    background: var(--color-hospital-bg, #f3f6fa);
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 14px;
}

.bk-title {
    display: inline-block;
    border-radius: 6px;
    padding: 4px 14px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 12px;
    letter-spacing: 0.3px;
}

.bk-title-blue {
    background: #0a4fa6;
}

.pay-summary {
    background: #0d1f3c;
    border-radius: 8px;
    overflow: hidden;
    font-size: 12px;
}

.pay-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.pay-row:last-child {
    border-bottom: none;
}

.pay-lbl {
    color: rgba(255, 255, 255, 0.5);
    font-size: 11px;
}

.pay-val {
    font-weight: 600;
    color: #fff;
    font-size: 12px;
}

.pay-val-total {
    font-size: 16px;
    font-weight: 900;
    color: #7fffd4;
}
</style>