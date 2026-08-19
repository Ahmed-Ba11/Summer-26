<script lang="ts">
	import { Dialog as SheetPrimitive } from "bits-ui";
	import type { Snippet } from "svelte";
	import { type WithoutChildrenOrChild } from "@/lib/utils.js";

	type TriggerProps = {
		onclick?: any;
		"aria-expanded"?: boolean;
		[key: string]: any;
	};

	let {
		ref = $bindable(null),
		asChild = false,
		children,
		...restProps
	}: WithoutChildrenOrChild<SheetPrimitive.TriggerProps> & {
		asChild?: boolean;
		children?: Snippet<[TriggerProps]>;
	} = $props();
</script>

<SheetPrimitive.Trigger bind:ref data-slot="sheet-trigger" {...restProps}>
	{#if asChild}
		{#snippet child({ props })}
			{@render children?.(props)}
		{/snippet}
	{:else}
		{@render children?.({})}
	{/if}
</SheetPrimitive.Trigger>
