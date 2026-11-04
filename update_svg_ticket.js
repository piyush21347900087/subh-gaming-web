const fs = require('fs');

let pc = fs.readFileSync('price_chart.php', 'utf8');

// Replace .rate-header and .rate-header::before CSS block
const oldCssRegex = /\.rate-header\s*\{[\s\S]*?\.rate-header::before\s*\{[\s\S]*?\}/g;

const svgDataUri = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 50' preserveAspectRatio='none'%3E%3Cpath d='M 15 0 A 15 15 0 0 1 0 15 L 0 35 A 15 15 0 0 1 15 50 L 385 50 A 15 15 0 0 1 400 35 L 400 15 A 15 15 0 0 1 385 0 Z' fill='%238b0000'/%3E%3Cpath d='M 18 4 A 14 14 0 0 1 4 18 L 4 32 A 14 14 0 0 1 18 46 L 382 46 A 14 14 0 0 1 396 32 L 396 18 A 14 14 0 0 1 382 4 Z' fill='none' stroke='%23ff2a2a' stroke-width='1.5'/%3E%3C/svg%3E";

const newCss = `.rate-header {
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            text-align: center;
            padding: 12px 20px;
            margin-bottom: 15px;
            position: relative;
            background-image: url("${svgDataUri}");
            background-size: 100% 100%;
            background-repeat: no-repeat;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15));
        }`;

pc = pc.replace(oldCssRegex, newCss);

// Fallback in case regex didn't match both together
if (pc.includes('.rate-header::before')) {
    pc = pc.replace(/\.rate-header::before\s*\{[\s\S]*?\}/g, '');
}

fs.writeFileSync('price_chart.php', pc, 'utf8');
console.log('Successfully applied SVG ticket header background with smooth continuous red border line!');
