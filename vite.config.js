import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    resolve: {
        preserveSymlinks: true,
    },
    plugins: [
        laravel({
            input: [
                'resources/css/site.scss',
                'resources/js/site.js',
                'vendor/pcteckserv/cms-core/resources/css/admin.scss',
                'vendor/pcteckserv/cms-core/resources/js/admin.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
