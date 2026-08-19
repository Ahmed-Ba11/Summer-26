<script lang="ts">
	import { DropdownMenu as DropdownMenuPrimitive } from "bits-ui";
	import type { Snippet } from "svelte";
	import { type WithoutChildrenOrChild } from "@/lib/utils.js";

	type TriggerProps = {
		onclick?: any;
		"aria-expanded"?: boolean;
		"data-state"?: "open" | "closed";
		[key: string]: any;
	};

	let {
		ref = $bindable(null),
		asChild = false,
		children,
		...restProps
	}: WithoutChildrenOrChild<DropdownMenuPrimitive.TriggerProps> & {
		asChild?: boolean;
		children?: Snippet<[TriggerProps]>;
	} = $props();
</script>

<DropdownMenuPrimitive.Trigger bind:ref data-slot="dropdown-menu-trigger" {...restProps}>
	{#if asChild}
		{#snippet child({ props })}
			{@render children?.(props)}
		{/snippet}
	{:else}
		{@render children?.({})}
	{/if}
</DropdownMenuPrimitive.Trigger>
