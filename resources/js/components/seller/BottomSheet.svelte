<script lang="ts">
    import type { Snippet } from 'svelte';
    import { X } from '@lucide/svelte';

    let {
        show = false,
        title = '',
        onclose,
        children,
    }: {
        show?: boolean;
        title?: string;
        onclose?: () => void;
        children?: Snippet;
    } = $props();

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape' && show) {
            onclose?.();
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

{#if show}
    <div class="fixed inset-0 z-50 flex flex-col justify-end" role="dialog" aria-modal="true">
        <!-- Backdrop Overlay -->
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity animate-in fade-in duration-200"
            onclick={() => onclose?.()}
            role="button"
            tabindex="-1"
        ></div>

        <!-- Bottom Sheet Drawer Container -->
        <div
            class="relative z-50 w-full max-w-md mx-auto rounded-t-3xl bg-white dark:bg-slate-900 text-left shadow-2xl transition-all border-t border-slate-200 dark:border-slate-800 max-h-[85vh] flex flex-col animate-in slide-in-from-bottom duration-300"
        >
            <!-- Drag Handle Bar -->
            <div class="pt-3 pb-1 flex justify-center">
                <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700"></div>
            </div>

            <!-- Header -->
            {#if title}
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-6 py-3">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 tracking-tight">{title}</h3>
                    <button
                        type="button"
                        onclick={() => onclose?.()}
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition-colors"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            {/if}

            <!-- Sheet Content Area -->
            <div class="p-6 overflow-y-auto flex-1">
                {@render children?.()}
            </div>
        </div>
    </div>
{/if}
