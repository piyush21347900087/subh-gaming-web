const fs = require('fs');
const text = fs.readFileSync('deposit.php', 'utf8');
const lines = text.split('\n');
const issues = [];

// Extract only JS script blocks (skip PHP sections)
let inScript = false;
lines.forEach(function(l, i) {
    const lineNo = i + 1;
    if (l.includes('<script') && !l.includes('src=')) inScript = true;
    if (l.includes('</script>')) { inScript = false; return; }
    if (!inScript) return;

    const trimmed = l.trim();
    if (trimmed.startsWith('//') || trimmed.startsWith('*')) return;

    // Arrow function check (JS only, skip PHP array =>)
    if (/[a-zA-Z0-9_\)]\s*=>\s*[a-zA-Z\({`]/.test(l) && !l.includes('<?')) {
        issues.push('ARROW L' + lineNo + ': ' + trimmed.substring(0, 90));
    }
    // setTimeout without braces
    if (/setTimeout\(function\(\)\s+[^{]/.test(l)) {
        issues.push('TIMEOUT-NO-BRACE L' + lineNo + ': ' + trimmed.substring(0, 90));
    }
    // broken return ();
    if (l.includes('return ();')) {
        issues.push('BROKEN-RETURN L' + lineNo + ': ' + trimmed.substring(0, 90));
    }
    // broken toLocaleString
    if (/toLocaleString\(''\)/.test(l)) {
        issues.push('LOCALE-EMPTY L' + lineNo + ': ' + trimmed.substring(0, 90));
    }
    // classList.remove('') 
    if (/classList\.(remove|add)\(''\)/.test(l)) {
        issues.push('CLASSLIST-EMPTY L' + lineNo + ': ' + trimmed.substring(0, 90));
    }
});

if (issues.length === 0) {
    console.log('No JS issues found in deposit.php');
} else {
    issues.forEach(function(x) { console.log(x); });
}
