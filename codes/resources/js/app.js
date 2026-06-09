import { createApp } from 'vue';
import DashboardPage from './pages/DashboardPage.vue';
import LoginPage from './pages/LoginPage.vue';

const root = document.getElementById('app');

if (root) {
    const pages = {
        dashboard: DashboardPage,
        login: LoginPage,
    };

    const page = root.dataset.page;
    const props = JSON.parse(root.dataset.props || '{}');
    const component = pages[page];

    if (component) {
        createApp(component, props).mount(root);
    }
}
