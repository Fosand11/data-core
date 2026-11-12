import path from 'path';
import { defineConfig } from 'vitest/config';

// Separate from vite.config.ts so tests do not boot the Laravel and Vue plugins.
export default defineConfig({
    resolve: {
        alias: { '@': path.resolve(__dirname, './resources/js') },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
});
