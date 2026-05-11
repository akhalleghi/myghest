/**
 * کپی SweetAlert2 از node_modules به public/vendor (بدون وابستگی به اینترنت در runtime)
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');
const srcDir = path.join(root, 'node_modules', 'sweetalert2', 'dist');
const destDir = path.join(root, 'public', 'vendor', 'sweetalert2');

const files = [
    ['sweetalert2.min.js', 'sweetalert2.min.js'],
    ['sweetalert2.min.css', 'sweetalert2.min.css'],
];

if (!fs.existsSync(srcDir)) {
    console.warn('[copy-vendor-sweetalert2] node_modules/sweetalert2 یافت نشد؛ npm install را اجرا کنید.');
    process.exit(0);
}

fs.mkdirSync(destDir, { recursive: true });

for (const [name, destName] of files) {
    const from = path.join(srcDir, name);
    const to = path.join(destDir, destName);
    if (!fs.existsSync(from)) {
        console.error('[copy-vendor-sweetalert2] فایل نیست:', from);
        process.exit(1);
    }
    fs.copyFileSync(from, to);
    console.log('[copy-vendor-sweetalert2]', destName, '→ public/vendor/sweetalert2/');
}
