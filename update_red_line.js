const fs = require('fs');

let pc = fs.readFileSync('price_chart.php', 'utf8');

const oldHeaderBeforeRegex = /\.rate-header::before\s*\{[\s\S]*?\}/g;

const newHeaderBeforeCss = `.rate-header::before {
            content: '';
            position: absolute;
            top: 4px; left: 4px; right: 4px; bottom: 4px;
            border: 1.5px solid #ff2a2a;
            -webkit-mask: radial-gradient(circle at top left, transparent 6px, #000 0) top left,
                          radial-gradient(circle at top right, transparent 6px, #000 0) top right,
                          radial-gradient(circle at bottom right, transparent 6px, #000 0) bottom right,
                          radial-gradient(circle at bottom left, transparent 6px, #000 0) bottom left;
            -webkit-mask-size: 51% 51%;
            -webkit-mask-repeat: no-repeat;
            mask: radial-gradient(circle at top left, transparent 6px, #000 0) top left,
                  radial-gradient(circle at top right, transparent 6px, #000 0) top right,
                  radial-gradient(circle at bottom right, transparent 6px, #000 0) bottom right,
                  radial-gradient(circle at bottom left, transparent 6px, #000 0) bottom left;
            mask-size: 51% 51%;
            mask-repeat: no-repeat;
            pointer-events: none;
        }`;

pc = pc.replace(oldHeaderBeforeRegex, newHeaderBeforeCss);

fs.writeFileSync('price_chart.php', pc, 'utf8');
console.log('Successfully updated inner border line to bright red with ticket cutout mask!');
