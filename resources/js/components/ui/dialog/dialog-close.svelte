<script lang="ts">
	import { Dialog as DialogPrimitive } from "bits-ui";
	import type { Snippet } from "svelte";
	import { type WithoutChildrenOrChild } from "@/lib/utils.js";

	type CloseProps = {
		onclick?: () => void;
		onClick?: () => void;
		[key: string]: any;
	};

	let {
		ref = $bindable(null),
		type = "button",
		asChild = false,
		children,
		...restProps
	}: WithoutChildrenOrChild<DialogPrimitive.CloseProps> & {
		asChild?: boolean;
		children?: Snippet<[CloseProps]>;
	} = $props();
</script>

<DialogPrimitive.Close bind:ref data-slot="dialog-close" {type} {...restProps}>
	{#if asChild}
		{#snippet child({ props })}
			{@render children?.({
				...props,
				onClick: props.onclick ?? props.onClick,
			})}
		{/snippet}
	{:else}
		{@render children?.({})}
	{/if}
</DialogPrimitive.Close>
