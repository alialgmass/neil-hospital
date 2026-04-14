/**
 * Thin wrapper around window.print().
 * Adds `.printing` class to <body> before printing and removes it after,
 * so CSS `@media print` rules can further refine the output.
 */
export function usePrint() {
    function print() {
        document.body.classList.add('printing');
        window.print();
        // afterprint fires when the print dialog closes
        window.addEventListener(
            'afterprint',
            () => document.body.classList.remove('printing'),
            { once: true },
        );
    }

    return { print };
}
