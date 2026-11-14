import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // server: {
    //     watch: {
    //         ignored: ['**/storage/framework/views/**'],
    //     },
    // },
    server: {
       	host: '0.0.0.0', // Listen on all network interfaces
         port: 5173,
         cors: {
            origin: 'http://192.168.0.141:8000'  // allow Laravel's origin
        },
         hmr: {
             host: '192.168.0.141' // Replace with your machine's IP
         }
     },
});
