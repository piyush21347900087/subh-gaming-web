const fs = require('fs');

function fixGlobalFunctions(filename) {
    let content = fs.readFileSync(filename, 'utf8');

    // Make openM and closeM explicit window properties
    const windowFunctions = `
window.openM = function() {
    var sideMenu = document.getElementById('sideMenu');
    var sideOverlay = document.getElementById('sideOverlay');
    if (sideMenu) sideMenu.classList.add('open');
    if (sideOverlay) sideOverlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
};

window.closeM = function() {
    var sideMenu = document.getElementById('sideMenu');
    var sideOverlay = document.getElementById('sideOverlay');
    if (sideMenu) sideMenu.classList.remove('open');
    if (sideOverlay) sideOverlay.style.display = 'none';
    document.body.style.overflow = '';
};
function openM() { window.openM(); }
function closeM() { window.closeM(); }
`;

    // Replace the inner openM/closeM definition with window-scoped ones
    content = content.replace(
        /function openM\(\)\s*\{[\s\S]*?function closeM\(\)\s*\{[\s\S]*?\}\s*\}/,
        windowFunctions
    );

    // Also add script tag at head or top if missing
    fs.writeFileSync(filename, content, 'utf8');
}

fixGlobalFunctions('price_chart.php');
fixGlobalFunctions('user_dashboard.php');

console.log('Successfully made openM and closeM globally accessible via window.openM!');
