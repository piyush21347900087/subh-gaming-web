const fs = require('fs');
let ud = fs.readFileSync('user_dashboard.php', 'utf8');
let pc = fs.readFileSync('price_chart.php', 'utf8');

// HTML Replacements
let headerHtmlMatch = ud.match(/<header>[\s\S]*?<\/header>/);
let headerHtml = headerHtmlMatch ? headerHtmlMatch[0] : '';

let smHtmlMatch = ud.match(/<!-- SIDE MENU -->[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/);
let smHtml = smHtmlMatch ? smHtmlMatch[0] : '';

let bmHtmlMatch = ud.match(/<nav class="bottom-menu">[\s\S]*?<\/nav>/);
let bmHtml = bmHtmlMatch ? bmHtmlMatch[0] : '';

if (headerHtml && smHtml && bmHtml) {
    pc = pc.replace(/<header>[\s\S]*?<\/header>/, headerHtml);
    pc = pc.replace(/<!-- SIDE MENU -->[\s\S]*?<\/div>\s*<\/div>\s*<header>/, smHtml + '\n  <header>');
    
    bmHtml = bmHtml.replace('class="menu-item home-item active"', 'class="menu-item home-item"');
    bmHtml = bmHtml.replace('<a href="price_chart.php" class="menu-item">', '<a href="price_chart.php" class="menu-item active">');
    pc = pc.replace(/<nav class="bottom-menu">[\s\S]*?<\/nav>/, bmHtml);
} else {
    console.error("HTML matches failed.");
    process.exit(1);
}

// CSS Replacements
let smCssMatch = ud.match(/\.side-menu-overlay\s*\{[\s\S]*?(?=\/\* ── Market Timetable Modal ── \*\/)/);
if (smCssMatch) {
    let smCss = smCssMatch[0].trim();
    pc = pc.replace(/\.side-menu-overlay\s*\{[\s\S]*?\.share-btn\s*\{.*?\}/, smCss);
}

let bmCssMatch = ud.match(/nav\.bottom-menu\s*\{[\s\S]*?\.menu-item\.active span\s*\{[\s\S]*?\}/);
if (bmCssMatch) {
    let bmCss = bmCssMatch[0];
    pc = pc.replace(/nav\.bottom-menu\s*\{[\s\S]*?\.menu-item\.home-item span\s*\{\s*display: none;\s*\}/, bmCss);
}

let headerCssMatch = ud.match(/header\s*\{[\s\S]*?#menuToggle\s*\{[\s\S]*?\}/);
if (headerCssMatch) {
    let headerCss = headerCssMatch[0];
    pc = pc.replace(/header\s*\{[\s\S]*?#menuToggle\s*\{[\s\S]*?\}/, headerCss);
}

fs.writeFileSync('price_chart.php', pc, 'utf8');
console.log('Script executed successfully');
