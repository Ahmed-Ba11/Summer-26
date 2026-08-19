import type { LinkComponentBaseProps } from '@inertiajs/core';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export type WithoutChild<T> = Omit<T, 'child'>;

export type WithoutChildren<T> = Omit<T, 'children'>;

export type WithoutChildrenOrChild<T> = WithoutChild<WithoutChildren<T>>;

export type WithElementRef<
    T,
    E extends HTMLElement = HTMLElement,
> = T & {
    ref?: E | null;
};

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(
    href: NonNullable<LinkComponentBaseProps['href']>,
): string {
    return typeof href === 'string' ? href : href.url;
}
