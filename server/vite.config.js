import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// 纯 Vite 构建到 Laravel 的 public/build（manifest 与 @vite 指令兼容）
export default defineConfig({
    plugins: [react(), tailwindcss()],
    publicDir: false,
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: { main: 'resources/js/main.jsx' },
        },
    },
});
