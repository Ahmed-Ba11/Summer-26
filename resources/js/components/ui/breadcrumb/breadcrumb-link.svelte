<script lang="ts">
	import type { HTMLAnchorAttributes } from "svelte/elements";
	import type { Snippet } from "svelte";
	import { cn, type WithElementRef } from "@/lib/utils.js";

	let {
		ref = $bindable(null),
		class: className,
		href = undefined,
		child,
		asChild = false,
		children,
		...restProps
	}: Omit<WithElementRef<HTMLAnchorAttributes>, "children"> & {
		child?: Snippet<[{ props: HTMLAnchorAttributes }]>;
		asChild?: boolean;
		children?: Snippet<[HTMLAnchorAttributes]>;
	} = $props();

	const attrs = $derived({
		"data-slot": "breadcrumb-link",
		class: cn("hover:text-foreground transition-colors", className),
		href,
		...restProps,
	});
</script>

	{#if child}
		{@render child({ props: attrs })}
	{:else if asChild}
		{@render children?.(attrs)}
	{:else}
		<a bind:this={ref} {...attrs}>
			{@render children?.({})}
	</a>
{/if}
