<script lang="ts">
    import type { Snippet } from 'svelte';

    let {
        variant = 'primary',
        children,
    }: {
        variant?: 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info';
        children?: Snippet;
    } = $props();

    const variants = {
        primary: 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600/10 dark:bg-indigo-950/50 dark:text-indigo-300',
        secondary: 'bg-slate-50 text-slate-600 ring-1 ring-slate-500/10 dark:bg-slate-800 dark:text-slate-300',
        success: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/10 dark:bg-emerald-950/50 dark:text-emerald-300',
        danger: 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/10 dark:bg-rose-950/50 dark:text-rose-300',
        warning: 'bg-amber-50 text-amber-700 ring-1 ring-amber-600/10 dark:bg-amber-950/50 dark:text-amber-300',
        info: 'bg-sky-50 text-sky-700 ring-1 ring-sky-600/10 dark:bg-sky-950/50 dark:text-sky-300',
    };
</script>

<span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium transition-colors {variants[variant]}">
    {@render children?.()}
</span>
