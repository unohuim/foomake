import "./bootstrap";

import { createInertiaApp } from "@inertiajs/vue3";
import { createApp, h } from "vue";

import.meta.glob(["../img/**"]);

createInertiaApp({
    title: (title) => (title ? `${title} | FooMake` : "FooMake"),
    resolve: (name) => {
        const pages = import.meta.glob("./pages/**/*.vue", { eager: true });
        const page = pages[`./pages/${name}.vue`];

        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
