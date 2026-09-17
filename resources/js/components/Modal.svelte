<script lang="ts">
    import type { Snippet } from 'svelte';
    import { X } from '@lucide/svelte';

    let {
        show = false,
        title = '',
        maxWidth = '2xl',
        onclose,
        children,
    }: {
        show?: boolean;
        title?: string;
        maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl';
        onclose?: () => void;
        children?: Snippet;
    } = $props();

    const maxWidthClasses = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
        '2xl': 'max-w-2xl',
        '3xl': 'max-w-3xl',
        '4xl': 'max-w-4xl',
        '5xl': 'max-w-5xl',
    };

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape' && show) {
            onclose?.();
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

{#if show}
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div 
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            onclick={() => onclose?.()}
            role="button"
            tabindex="-1"
        ></div>

        <!-- Modal Container -->
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-6">
            <div 
                class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 text-left align-middle shadow-2xl transition-all w-full border border-slate-100 dark:border-slate-800 {maxWidthClasses[maxWidth]}"
            >
                {#if title}
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-6 py-4">
                        <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">{title}</h3>
                        <button 
                            type="button"
                            onclick={() => onclose?.()}
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition-colors"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>
                {/if}

                <div class="p-6">
                    {@render children?.()}
                </div>
            </div>
        </div>
    </div>
{/if}
