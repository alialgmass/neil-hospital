/**
 * Triggers an Excel export by navigating to the export endpoint with query params.
 * The server responds with an application/vnd.openxmlformats... attachment.
 */
export function useExport() {
    function exportReport(type: string, params: Record<string, string> = {}) {
        const query = new URLSearchParams(params).toString();
        const url = `/reports/${type}/export${query ? '?' + query : ''}`;
        // Open in same tab so the browser handles the file download naturally.
        window.location.href = url;
    }

    return { exportReport };
}
