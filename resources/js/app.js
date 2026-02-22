import './bootstrap'
import '../css/app.css'

import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h } from 'vue'

    ;(async () => {
    await createInertiaApp({
        resolve: (name) => {
            return resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob('./Pages/**/*.vue'),
            )
        },

        setup({ el, App, props, plugin }) {
            createApp({
                render: () => h(App, props),
            })
                .use(plugin)
                .mount(el)
        },

        progress: {
            color: '#0f172a',
        },
    })
})()
