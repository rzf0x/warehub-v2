<script lang="ts">
    import { usePage } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const pageState = usePage();
    const name = $derived(pageState.props.name ?? 'Warehub v2');
</script>

<div
    class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
>
    <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
</div>
<div class="ml-1 grid flex-1 text-left text-sm leading-tight">
    <span class="mb-0.5 truncate font-semibold">{name}</span>
</div>
