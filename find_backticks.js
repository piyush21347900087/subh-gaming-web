const fs = require('fs');
['deposit.php', 'user_bank.php', 'withdrawal.php'].forEach(file => {
    console.log('--- ' + file + ' ---');
    const lines = fs.readFileSync(file, 'utf8').split('\n');
    lines.forEach((l, i) => {
        if (l.includes('`')) {
            console.log('L' + (i+1) + ': ' + l.trim());
        }
    });
});
