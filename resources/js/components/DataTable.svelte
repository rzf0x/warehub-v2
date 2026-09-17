<script lang="ts">
    import type { Snippet } from 'svelte';

    let {
        headers = [],
        emptyText = 'Tidak ada data ditemukan.',
        children,
    }: {
        headers?: string[];
        emptyText?: string;
        children?: Snippet;
    } = $props();
</script>

<div class="overflow-x-auto rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs">
    <table class="w-full text-left text-sm text-slate-600 dark:text-slate-400">
        <thead class="bg-slate-50/80 dark:bg-slate-800/50 text-xs uppercase text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 font-semibold tracking-wider">
            <tr>
                {#each headers as header}
                    <th scope="col" class="px-6 py-4">{header}</th>
                {/each}
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            {@render children?.()}
        </tbody>
    </table>
</div>
