const fs = require('fs');

function fixPage(filename) {
    let content = fs.readFileSync(filename, 'utf8');

    // 1. Add inline onclick handler to menuToggle, sideCloseBtn, sideOverlay to guarantee clicking works
    content = content.replace(
        /<div id="menuToggle"/g,
        '<div id="menuToggle" onclick="openM()"'
    );
    content = content.replace(
        /<button type="button" class="side-close-btn" id="sideCloseBtn"/g,
        '<button type="button" class="side-close-btn" id="sideCloseBtn" onclick="closeM()"'
    );
    content = content.replace(
        /<div class="side-menu-overlay" id="sideOverlay"/g,
        '<div class="side-menu-overlay" id="sideOverlay" onclick="closeM()"'
    );

    // Make sure openM and closeM are defined globally so onclick="openM()" works
    const globalMenuFunctions = `
function openM() {
    var sideMenu = document.getElementById('sideMenu');
    var sideOverlay = document.getElementById('sideOverlay');
    if (sideMenu) sideMenu.classList.add('open');
    if (sideOverlay) sideOverlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
}
function closeM() {
    var sideMenu = document.getElementById('sideMenu');
    var sideOverlay = document.getElementById('sideOverlay');
    if (sideMenu) sideMenu.classList.remove('open');
    if (sideOverlay) sideOverlay.style.display = 'none';
    document.body.style.overflow = '';
}
`;

    // Ensure globalMenuFunctions exist in script block
    if (!content.includes('function openM()')) {
        content = content.replace('<script>', '<script>\n' + globalMenuFunctions);
    } else {
        // Replace existing openM/closeM if scoped inside DOMContentLoaded
        content = content.replace(/function openM\(\)[\s\S]*?function closeM\(\)[\s\S]*?\}/, globalMenuFunctions);
    }

    // 2. Lite Red CSS Filter for active bottom menu item (#ff5252 / #ff4d4d)
    const liteRedFilter = 'filter: brightness(0) saturate(100%) invert(48%) sepia(79%) saturate(2476%) hue-rotate(328deg) brightness(102%) contrast(101%);';

    // Replace any dark red active filters or yellow filters in .menu-item.active img.nav-icon
    content = content.replace(
        /\.menu-item\.active img\.nav-icon\s*\{[\s\S]*?\}/g,
        `.menu-item.active img.nav-icon {\n            ${liteRedFilter}\n        }`
    );

    fs.writeFileSync(filename, content, 'utf8');
}

fixPage('price_chart.php');
fixPage('user_dashboard.php');

console.log('Successfully updated side menu handlers and lite red icon filter!');
