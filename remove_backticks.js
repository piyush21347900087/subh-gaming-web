const fs = require('fs');
const babel = require('@babel/core');

function processFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    
    // Regex to match <script>...</script> exactly
    const scriptRegex = /<script\b[^>]*>([\s\S]*?)<\/script>/gi;
    let modified = false;

    content = content.replace(scriptRegex, (match, scriptContent, offset, fullStr) => {
        // Skip empty scripts or src scripts
        if (!scriptContent || scriptContent.trim() === '') return match;

        // Skip scripts that have <?php or <?= inside them, as Babel will crash
        // Wait, template literals might contain <?= $var ?> !
        // Let's stub PHP tags before babel, and restore them after!
        
        let stubbedContent = scriptContent;
        const phpStubs = [];
        stubbedContent = stubbedContent.replace(/<\?php[\s\S]*?\?>/gi, (phpMatch) => {
            const stub = `__PHP_STUB_${phpStubs.length}__`;
            phpStubs.push(phpMatch);
            return stub;
        });
        stubbedContent = stubbedContent.replace(/<\?=[\s\S]*?\?>/gi, (phpMatch) => {
            const stub = `__PHP_STUB_${phpStubs.length}__`;
            phpStubs.push(phpMatch);
            return stub;
        });

        try {
            const result = babel.transformSync(stubbedContent, {
                plugins: [
                    "@babel/plugin-transform-template-literals"
                ],
                compact: false,
                retainLines: true
            });

            if (result && result.code) {
                let newScriptContent = result.code;
                
                // Restore PHP tags
                phpStubs.forEach((phpMatch, index) => {
                    newScriptContent = newScriptContent.replace(`__PHP_STUB_${index}__`, phpMatch);
                });

                if (newScriptContent !== scriptContent) {
                    modified = true;
                    return match.replace(scriptContent, newScriptContent);
                }
            }
        } catch (err) {
            console.error('Error transforming script at offset ' + offset + ' in ' + filePath);
            console.error(err.message);
        }

        return match;
    });

    if (modified) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log('Fixed backticks in ' + filePath);
    } else {
        console.log('No backticks to fix in ' + filePath);
    }
}

['user_crypto_kyc.php', 'user_bid_history.php', 'user_profile.php', 'user_result.php'].forEach(processFile);
