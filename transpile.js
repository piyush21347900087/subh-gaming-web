const fs = require('fs');
const path = require('path');
const babel = require('@babel/core');

function processDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        if (!file.endsWith('.php') || file.includes('adminer')) continue;
        const fullPath = path.join(dir, file);
        if (!fs.statSync(fullPath).isFile()) continue;

        let content = fs.readFileSync(fullPath, 'utf8');
        let oldContent = content;

        content = content.replace(/<script\b[^>]*>([\s\S]*?)<\/script>/gi, (match, scriptBody) => {
            if (match.includes('type="application/json"') || scriptBody.trim() === '') return match;
            if (!scriptBody.includes('=>') && !scriptBody.includes('const ') && !scriptBody.includes('let ')) return match; 

            let phpTags = [];
            let maskedScript = scriptBody.replace(/<\?(?:php|=)?[\s\S]*?\?>/gi, (phpMatch) => {
                const id = '___PHP_TAG_' + phpTags.length + '___';
                phpTags.push({ id, code: phpMatch });
                return id;
            });

            try {
                const result = babel.transformSync(maskedScript, {
                    presets: [['@babel/preset-env', { targets: 'defaults, ie 11' }]],
                    sourceType: 'script',
                    compact: false
                });

                let finalScript = result.code;
                
                for (let i = phpTags.length - 1; i >= 0; i--) {
                    finalScript = finalScript.replace(phpTags[i].id, phpTags[i].code);
                }

                return match.replace(scriptBody, '\n' + finalScript + '\n');
            } catch (e) {
                console.error('Babel error in ' + file + ':', e.message);
                return match;
            }
        });

        if (content !== oldContent) {
            fs.writeFileSync(fullPath, content, 'utf8');
            console.log('Transpiled scripts in', file);
        }
    }
}

processDir(__dirname);
