<script lang="ts">
    import Modal from './Modal.svelte';
    import { AlertTriangle } from '@lucide/svelte';

    let {
        show = false,
        title = 'Konfirmasi Hapus',
        message = 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.',
        confirmText = 'Hapus',
        cancelText = 'Batal',
        onconfirm,
        oncancel,
    }: {
        show?: boolean;
        title?: string;
        message?: string;
        confirmText?: string;
        cancelText?: string;
        onconfirm?: () => void;
        oncancel?: () => void;
    } = $props();
</script>

<Modal {show} {title} maxWidth="md" onclose={oncancel}>
    <div class="flex items-start gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400">
            <AlertTriangle class="h-6 w-6" />
        </div>
        <div>
            <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                {message}
            </p>
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <button
            type="button"
            onclick={oncancel}
            class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
        >
            {cancelText}
        </button>
        <button
            type="button"
            onclick={onconfirm}
            class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 transition-colors shadow-xs"
        >
            {confirmText}
        </button>
    </div>
</Modal>
