const fs = require('fs');
const path = require('path');

function processDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        const stat = fs.statSync(fullPath);
        if (stat.isFile() && file.endsWith('.php')) {
            let content = fs.readFileSync(fullPath, 'utf8');
            let modified = false;
            content = content.replace(/<script>([\s\S]*?)<\/script>/gi, (match, p1) => {
                let inner = p1;
                let newInner = inner.replace(/\b(let|const)\s+/g, 'var ');
                
                // Very basic arrow function replacement: () => { ... } to function() { ... }
                newInner = newInner.replace(/\(\)\s*=>\s*\{/g, 'function() {');
                newInner = newInner.replace(/\(([a-zA-Z0-9_, ]+)\)\s*=>\s*\{/g, 'function($1) {');
                newInner = newInner.replace(/\b([a-zA-Z0-9_]+)\s*=>\s*\{/g, 'function($1) {');
                
                // replace () => to function()
                newInner = newInner.replace(/\(\)\s*=>\s*/g, 'function() ');
                
                if (newInner !== inner) {
                    modified = true;
                }
                return '<script>' + newInner + '</script>';
            });
            if (modified) {
                fs.writeFileSync(fullPath, content, 'utf8');
                console.log('Modified:', file);
            }
        }
    }
}
processDir(__dirname);
