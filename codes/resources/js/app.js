import { createApp } from 'vue';
import DashboardPage from './pages/DashboardPage.vue';
import IncidentCreatePage from './pages/IncidentCreatePage.vue';
import IncidentDetailPage from './pages/IncidentDetailPage.vue';
import IncidentListPage from './pages/IncidentListPage.vue';
import LoginPage from './pages/LoginPage.vue';
import PasswordChangePage from './pages/PasswordChangePage.vue';
import ProfilePage from './pages/ProfilePage.vue';
import UserCreatePage from './pages/UserCreatePage.vue';
import UserListPage from './pages/UserListPage.vue';

const root = document.getElementById('app');

if (root) {
    const pages = {
        dashboard: DashboardPage,
        'incident-create': IncidentCreatePage,
        'incident-detail': IncidentDetailPage,
        'incident-list': IncidentListPage,
        login: LoginPage,
        'password-change': PasswordChangePage,
        profile: ProfilePage,
        'user-create': UserCreatePage,
        'user-list': UserListPage,
    };

    const page = root.dataset.page;
    const props = JSON.parse(root.dataset.props || '{}');
    const component = pages[page];

    if (component) {
        createApp(component, props).mount(root);
    }
}
