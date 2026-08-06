import type { SharedData } from '@/types';
import type { Config, RouteParams } from 'ziggy-js';

declare global {
    function route(): Config;
    function route(name: string, params?: RouteParams<typeof name> | undefined, absolute?: boolean): string;
}

declare module '@inertiajs/core' {
    // Module augmentation requires an interface declaration.
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends SharedData {}
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}
