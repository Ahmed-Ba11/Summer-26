import type { LinkComponentBaseProps } from '@inertiajs/core';
import type { Component, SvelteComponent } from 'svelte';

export type IconComponent =
    | Component<{ class?: string }>
    | (new (...args: any[]) => SvelteComponent<{ class?: string }>);

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<LinkComponentBaseProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<LinkComponentBaseProps['href']>;
    icon?: IconComponent;
    isActive?: boolean;
    badge?: number;
    tag?: string;
};
