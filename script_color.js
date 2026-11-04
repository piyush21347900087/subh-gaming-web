const fs = require('fs');

function updateColors(file) {
    let content = fs.readFileSync(file, 'utf8');

    // 1. Replace the active nav-icon filter
    // Old filter: filter: brightness(0) saturate(100%) invert(85%) sepia(80%) saturate(400%) hue-rotate(5deg) brightness(105%);
    let redFilter = 'filter: brightness(0) saturate(100%) invert(29%) sepia(85%) saturate(7483%) hue-rotate(353deg) brightness(101%) contrast(105%);';
    content = content.replace(/filter:\s*brightness\(0\)\s*saturate\(100%\)\s*invert\(85%\)\s*sepia\(80%\)\s*saturate\(400%\)\s*hue-rotate\(5deg\)\s*brightness\(105%\);/g, redFilter);

    // 2. Replace the active span color
    // Old color: color: #ffd700 !important;
    content = content.replace(/color:\s*#ffd700\s*!important;/g, 'color: #ffffff !important;');

    fs.writeFileSync(file, content, 'utf8');
}

updateColors('user_dashboard.php');
updateColors('price_chart.php');
console.log('Colors updated');
