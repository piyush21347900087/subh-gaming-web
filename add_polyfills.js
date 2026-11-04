const fs = require('fs');
const path = require('path');

const polyfills = `
<!-- Polyfills for older Android WebViews -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/core-js/3.31.0/minified.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fetch/3.6.2/fetch.min.js"></script>
<link rel="stylesheet" href="assets/css/subh_gaming_theme.css?v=2.0">
</head>`;

function processDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);
        if (stat.isFile() && file.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            if (content.includes('</head>') && !content.includes('core-js/3.31.0')) {
                content = content.replace('</head>', polyfills);
                fs.writeFileSync(fullPath, content, 'utf8');
                console.log('Added polyfills to:', file);
            }
        }
    }
}
processDir(__dirname);
