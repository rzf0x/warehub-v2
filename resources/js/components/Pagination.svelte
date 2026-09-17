<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    interface PaginationLink {
        url: string | null;
        label: string;
        active: boolean;
    }

    let { links = [] }: { links?: PaginationLink[] } = $props();
</script>

{#if links && links.length > 3}
    <div class="flex items-center justify-between border-t border-slate-100 dark:border-slate-800 px-4 py-3 sm:px-6">
        <div class="flex flex-1 justify-between sm:hidden">
            {#if links[0].url}
                <Link
                    href={links[0].url}
                    class="relative inline-flex items-center rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700"
                >
                    Previous
                </Link>
            {/if}
            {#if links[links.length - 1].url}
                <Link
                    href={links[links.length - 1].url}
                    class="relative ml-3 inline-flex items-center rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700"
                >
                    Next
                </Link>
            {/if}
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-center">
            <nav class="isolate inline-flex -space-x-px rounded-md shadow-xs" aria-label="Pagination">
                {#each links as link}
                    {#if link.url === null}
                        <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400 dark:text-slate-600 ring-1 ring-inset ring-slate-200 dark:ring-slate-800 focus:outline-offset-0">
                            {@html link.label}
                        </span>
                    {:else}
                        <Link
                            href={link.url}
                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold transition-colors focus:z-20 ring-1 ring-inset ring-slate-200 dark:ring-slate-800
                            {link.active 
                                ? 'z-10 bg-indigo-600 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 ring-indigo-600' 
                                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-offset-0'}"
                        >
                            {@html link.label}
                        </Link>
                    {/if}
                {/each}
            </nav>
        </div>
    </div>
{/if}
