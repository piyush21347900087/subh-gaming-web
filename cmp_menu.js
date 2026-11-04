const fs = require('fs');
const ud = fs.readFileSync('user_dashboard.php', 'utf8');
const pc = fs.readFileSync('price_chart.php', 'utf8');

const udMenu = ud.substring(ud.indexOf('id="sideOverlay"'), ud.indexOf('<!-- Market Timetable Modal'));
const pcMenu = pc.substring(pc.indexOf('id="sideOverlay"'), pc.indexOf('<!-- Section 1'));

console.log('UD MENU snippet:\n', udMenu.substring(0, 200));
console.log('PC MENU snippet:\n', pcMenu.substring(0, 200));
console.log('Lengths:', udMenu.length, pcMenu.length);
