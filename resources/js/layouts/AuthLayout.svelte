<script lang="ts">
    import type { Snippet } from 'svelte';
    import AuthLayout from '@/layouts/auth/AuthSimpleLayout.svelte';

    let {
        title = '',
        description = '',
        showLogo = false,
        children,
    }: {
        title?: string;
        description?: string;
        showLogo?: boolean;
        children?: Snippet;
    } = $props();
</script>

<AuthLayout {title} {description} {showLogo}>
    {@render children?.()}
</AuthLayout>
