<script lang="ts">
	import { DropdownMenu as DropdownMenuPrimitive } from "bits-ui";
	import type { Snippet } from "svelte";

	let {
		open = $bindable(false),
		class: className,
		...restProps
	}: Omit<DropdownMenuPrimitive.RootProps, "class"> & {
		class?: string;
		children?: Snippet;
	} = $props();
</script>

<div class={className}>
	<DropdownMenuPrimitive.Root bind:open {...restProps} />
</div>
