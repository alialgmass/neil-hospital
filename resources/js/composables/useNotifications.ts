import { ref } from 'vue';

type NotificationType = 'success' | 'error' | 'warning' | 'info';

interface Notification {
    id: number;
    type: NotificationType;
    message: string;
}

let counter = 0;

const notifications = ref<Notification[]>([]);

const colorMap: Record<NotificationType, string> = {
    success: 'bg-hospital-success text-white',
    error:   'bg-hospital-danger text-white',
    warning: 'bg-hospital-warning text-white',
    info:    'bg-hospital-primary text-white',
};

export function useNotifications() {
    function show(message: string, type: NotificationType = 'info', duration = 3500) {
        const id = ++counter;
        notifications.value.push({ id, type, message });
        setTimeout(() => dismiss(id), duration);
    }

    function dismiss(id: number) {
        notifications.value = notifications.value.filter((n) => n.id !== id);
    }

    function success(message: string) { show(message, 'success'); }
    function error(message: string)   { show(message, 'error'); }
    function warning(message: string) { show(message, 'warning'); }
    function info(message: string)    { show(message, 'info'); }

    return { notifications, show, dismiss, success, error, warning, info, colorMap };
}
