<script setup lang="ts">
interface Props {
    modelValue: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const eyeSides = [
    { v: 'OD', l: 'OD — يمين' },
    { v: 'OS', l: 'OS — يسار' },
    { v: 'OU', l: 'OU — كلاهما' },
];

function selectSide(value: string) {
    emit('update:modelValue', props.modelValue === value ? '' : value);
}
</script>

<template>
    <div>
        <label class="bk-label">جهة العين</label>
        <div class="eye-side-row">
            <button
                v-for="side in eyeSides"
                :key="side.v"
                type="button"
                :class="['eye-btn', modelValue === side.v ? 'eye-btn-selected' : '']"
                @click="selectSide(side.v)"
            >
                {{ side.l }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.bk-label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: #4a5878;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}

.eye-side-row {
    display: flex;
    gap: 6px;
}

.eye-btn {
    flex: 1;
    padding: 7px 4px;
    border-radius: 7px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    color: #4a5878;
    transition: all 0.15s;
}

.eye-btn:hover {
    border-color: #1a8c5b;
    background: #e4f5ee;
    color: #1a8c5b;
}

.eye-btn-selected {
    border-color: #1a8c5b;
    background: #1a8c5b;
    color: #fff;
}
</style>