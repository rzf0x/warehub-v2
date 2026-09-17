<script lang="ts">
    import { usePage } from '@inertiajs/svelte';
    import { CheckCircle2, AlertCircle, X } from '@lucide/svelte';

    let visible = $state(true);

    const pageState = usePage();
    let successMessage = $derived(pageState.props.flash?.success as string | undefined);
    let errorMessage = $derived(pageState.props.flash?.error as string | undefined);

    $effect(() => {
        if (successMessage || errorMessage) {
            visible = true;
            const timer = setTimeout(() => {
                visible = false;
            }, 4000);
            return () => clearTimeout(timer);
        }
    });
</script>

{#if visible && (successMessage || errorMessage)}
    <div class="fixed bottom-5 right-5 z-50 flex items-center gap-3 rounded-xl bg-slate-900 p-4 text-white shadow-2xl dark:bg-slate-100 dark:text-slate-900 animate-in fade-in slide-in-from-bottom-5 duration-300">
        {#if successMessage}
            <CheckCircle2 class="h-5 w-5 text-emerald-400 dark:text-emerald-600 shrink-0" />
            <span class="text-sm font-medium">{successMessage}</span>
        {:else if errorMessage}
            <AlertCircle class="h-5 w-5 text-rose-400 dark:text-rose-600 shrink-0" />
            <span class="text-sm font-medium">{errorMessage}</span>
        {/if}
        <button 
            onclick={() => visible = false}
            class="ml-2 rounded-lg p-1 text-slate-400 hover:text-white dark:hover:text-slate-900 transition-colors"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
{/if}
