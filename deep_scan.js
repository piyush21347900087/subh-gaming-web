const fs = require('fs');
const text = fs.readFileSync('deposit.php', 'utf8');
const lines = text.split('\\n');
let inScript = false;
let backtickIssues = [];
let constLetIssues = [];
let arrowIssues = [];

lines.forEach(function(l, i) {
    const lineNo = i + 1;
    
    // Track script blocks
    if (/<script(?:\s[^>]*)?>/.test(l) && !l.includes('src=')) { inScript = true; }
    if (l.includes('</script>')) { inScript = false; return; }
    if (!inScript) return;
    
    const t = l.trim();
    if (t.startsWith('//') || t.startsWith('*') || t === '') return;
    // Skip lines with PHP
    if (l.includes('<?') || l.includes('?>')) return;
    
    // Backtick template literals
    if (l.includes('`')) {
        backtickIssues.push('L' + lineNo + ': ' + t.substring(0, 70));
    }
    // const/let (not supported in very old Android WebViews)
    if (/^\s*(const|let)\s/.test(l)) {
        constLetIssues.push('L' + lineNo + ': ' + t.substring(0, 70));
    }
    // Arrow functions  
    if (/[a-zA-Z0-9_)]\s*=>\s*[a-zA-Z({`]/.test(l)) {
        arrowIssues.push('L' + lineNo + ': ' + t.substring(0, 70));
    }
});

console.log('=== BACKTICK template literals: ' + backtickIssues.length);
backtickIssues.slice(0,5).forEach(function(x) { console.log('  ' + x); });
console.log('=== const/let: ' + constLetIssues.length);
constLetIssues.slice(0,5).forEach(function(x) { console.log('  ' + x); });
console.log('=== Arrow functions: ' + arrowIssues.length);
arrowIssues.slice(0,5).forEach(function(x) { console.log('  ' + x); });
