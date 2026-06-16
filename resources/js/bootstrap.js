import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Fix: Redirigir al login cuando la sesión expire (419 Page Expired)
window.axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 419) {
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);