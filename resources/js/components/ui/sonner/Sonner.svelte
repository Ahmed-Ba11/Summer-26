<script lang="ts">
	import { Toaster as Sonner, type ToasterProps as SonnerProps } from "svelte-sonner";
	import { mode } from "mode-watcher";
	import Loader2Icon from "lucide-svelte/icons/loader-2";
	import CircleCheckIcon from "lucide-svelte/icons/circle-check";
	import OctagonXIcon from "lucide-svelte/icons/octagon-x";
	import InfoIcon from "lucide-svelte/icons/info";
	import TriangleAlertIcon from "lucide-svelte/icons/triangle-alert";

	let { ...restProps }: SonnerProps = $props();
</script>

<Sonner
	theme={mode.current}
	class="toaster group"
	style="--normal-bg: var(--color-popover); --normal-text: var(--color-popover-foreground); --normal-border: var(--color-border);"
	{...restProps}
	>
	<svelte:fragment slot="loading-icon">
		<Loader2Icon class="size-4 animate-spin" />
	</svelte:fragment>
	<svelte:fragment slot="success-icon">
		<CircleCheckIcon class="size-4" />
	</svelte:fragment>
	<svelte:fragment slot="error-icon">
		<OctagonXIcon class="size-4" />
	</svelte:fragment>
	<svelte:fragment slot="info-icon">
		<InfoIcon class="size-4" />
	</svelte:fragment>
	<svelte:fragment slot="warning-icon">
		<TriangleAlertIcon class="size-4" />
	</svelte:fragment>
</Sonner>
