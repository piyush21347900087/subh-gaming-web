const fs = require('fs');

// Read source of truth user_dashboard.php and price_chart.php
let ud = fs.readFileSync('user_dashboard.php', 'utf8');
let pc = fs.readFileSync('price_chart.php', 'utf8');

// 1. Keep price_chart.php's top PHP logic (everything before <!DOCTYPE html>)
let topPhp = pc.split('<!DOCTYPE html>')[0];

// 2. Extract content area from price_chart.php (<div class="content"> ... </div>)
let contentAreaMatch = pc.match(/<div class="content">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/);
let contentArea = '';
if (contentAreaMatch) {
    contentArea = contentAreaMatch[0];
} else {
    // Fallback search for <div class="content"> to </div> before bottom-menu
    let startIdx = pc.indexOf('<div class="content">');
    let endIdx = pc.indexOf('<nav class="bottom-menu">');
    contentArea = pc.substring(startIdx, endIdx).trim();
}

// 3. Take user_dashboard.php from <!DOCTYPE html> to end
let udHtml = '<!DOCTYPE html>' + ud.split('<!DOCTYPE html>')[1];

// Replace user_dashboard's <div class="content">...</div> area with price_chart's content area!
// In user_dashboard.php, content area goes from <div class="content"> to <!-- Closed Popup HTML --> or <!-- SIDE MENU -->
let udContentStart = udHtml.indexOf('<div class="content">');
let udContentEnd = udHtml.indexOf('<!-- Closed Popup HTML -->');
if (udContentEnd === -1) {
    udContentEnd = udHtml.indexOf('<!-- Market Timetable Modal Popup -->');
}

let beforeContent = udHtml.substring(0, udContentStart);
let afterContent = udHtml.substring(udContentEnd);

// In afterContent, update the bottom menu to set Game Rates as active and remove active from Home
afterContent = afterContent.replace(
    /href="user_dashboard.php" class="menu-item home-item active"/g,
    'href="user_dashboard.php" class="menu-item home-item"'
);
afterContent = afterContent.replace(
    /href="price_chart.php" class="menu-item"/g,
    'href="price_chart.php" class="menu-item active"'
);

// Combine into final price_chart.php
let finalPc = topPhp + beforeContent + contentArea + '\n\n    ' + afterContent;

fs.writeFileSync('price_chart.php', finalPc, 'utf8');
console.log('Successfully synchronized price_chart.php 1:1 with user_dashboard.php structure!');
