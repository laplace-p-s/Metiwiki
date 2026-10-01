import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { Icon } from '@tabler/icons-vue';

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: Icon;
    isActive?: boolean;
};
