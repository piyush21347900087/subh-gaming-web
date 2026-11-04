const fs = require('fs');

let ud = fs.readFileSync('user_dashboard.php', 'utf8');
let pc = fs.readFileSync('price_chart.php', 'utf8');

// Get top PHP from price_chart (from beginning up to <!DOCTYPE html>)
let topPhp = pc.split('<!DOCTYPE html>')[0];

// Get HTML from user_dashboard (from <!DOCTYPE html> to end)
let htmlPart = '<!DOCTYPE html>' + ud.split('<!DOCTYPE html>')[1];

let newContentHtml = `
  <div class="content">
      <!-- Section 1: Game Rates Chart -->
      <div class="rate-section">
          <div class="rate-header">
              <span>🪙 Game Rates Chart 🪙</span>
          </div>
          
          <div class="rate-row" style="background-color: #bbf3d5;">
              <div class="rate-left">
                  <img src="icons/single-digit.svg" alt="Single Digit">
                  <span class="rate-name">Single Digit</span>
              </div>
              <div class="rate-right">1 Ka 10</div>
          </div>
          <div class="rate-row" style="background-color: #ffe8d4;">
              <div class="rate-left">
                  <img src="icons/jodi-digit.svg" alt="Jodi Digits">
                  <span class="rate-name">Jodi Digits</span>
              </div>
              <div class="rate-right">1 Ka 100</div>
          </div>
          <div class="rate-row" style="background-color: #ffb199;">
              <div class="rate-left">
                  <img src="icons/single-pana.svg" alt="Single Panna">
                  <span class="rate-name">Single Panna</span>
              </div>
              <div class="rate-right">1 Ka 150</div>
          </div>
          <div class="rate-row" style="background-color: #d1addb;">
              <div class="rate-left">
                  <img src="icons/double-pana.svg" alt="Double Panna">
                  <span class="rate-name">Double Panna</span>
              </div>
              <div class="rate-right">1 Ka 300</div>
          </div>
          <div class="rate-row" style="background-color: #fff6d9;">
              <div class="rate-left">
                  <img src="icons/triple-pana.svg" alt="Triple Panna">
                  <span class="rate-name">Triple Panna</span>
              </div>
              <div class="rate-right">1 Ka 1000</div>
          </div>
          <div class="rate-row" style="background-color: #d5eaf2;">
              <div class="rate-left">
                  <img src="icons/half-sangam.svg" alt="Half Sangam">
                  <span class="rate-name">Half Sangam</span>
              </div>
              <div class="rate-right">1 Ka 1000</div>
          </div>
          <div class="rate-row" style="background-color: #dacfff;">
              <div class="rate-left">
                  <img src="icons/full-sangam.svg" alt="Full Sangam">
                  <span class="rate-name">Full Sangam</span>
              </div>
              <div class="rate-right">1 Ka 10000</div>
          </div>
      </div>

      <!-- Section 2: Starline Rates Chart -->
      <div class="rate-section">
          <div class="rate-header">
              <span>🪙 Starline Rates Chart 🪙</span>
          </div>
          
          <div class="rate-row" style="background-color: #bbf3d5;">
              <div class="rate-left">
                  <img src="icons/single-digit.svg" alt="Single Digit">
                  <span class="rate-name">Single Digit</span>
              </div>
              <div class="rate-right">1 Ka 10</div>
          </div>
          <div class="rate-row" style="background-color: #ffe8d4;">
              <div class="rate-left">
                  <img src="icons/single-pana.svg" alt="Single Panna">
                  <span class="rate-name">Single Panna</span>
              </div>
              <div class="rate-right">1 Ka 150</div>
          </div>
          <div class="rate-row" style="background-color: #ffb199;">
              <div class="rate-left">
                  <img src="icons/double-pana.svg" alt="Double Panna">
                  <span class="rate-name">Double Panna</span>
              </div>
              <div class="rate-right">1 Ka 300</div>
          </div>
          <div class="rate-row" style="background-color: #d1addb;">
              <div class="rate-left">
                  <img src="icons/triple-pana.svg" alt="Triple Panna">
                  <span class="rate-name">Triple Panna</span>
              </div>
              <div class="rate-right">1 Ka 1000</div>
          </div>
      </div>

      <!-- Section 3: Jackpot Rates Chart -->
      <div class="rate-section">
          <div class="rate-header">
              <span>🪙 Jackpot Rates Chart 🪙</span>
          </div>
          
          <div class="rate-row" style="background-color: #bbf3d5;">
              <div class="rate-left">
                  <img src="icons/jodi-digit.svg" alt="Jodi Digits">
                  <span class="rate-name">Jodi Digits</span>
              </div>
              <div class="rate-right">1 Ka 100</div>
          </div>
      </div>
  </div>
`;

let newCss = `
        /* Game Rates New Styles */
        .rate-section {
            margin-bottom: 25px;
        }
        .rate-header {
            background-color: #8b0000;
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            text-align: center;
            padding: 12px 20px;
            margin-bottom: 15px;
            position: relative;
            background: radial-gradient(circle at top left, transparent 8px, #9b0000 0) top left,
                        radial-gradient(circle at top right, transparent 8px, #9b0000 0) top right,
                        radial-gradient(circle at bottom right, transparent 8px, #9b0000 0) bottom right,
                        radial-gradient(circle at bottom left, transparent 8px, #9b0000 0) bottom left;
            background-size: 51% 51%;
            background-repeat: no-repeat;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }
        .rate-header::before {
            content: '';
            position: absolute;
            top: 4px; left: 4px; right: 4px; bottom: 4px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            pointer-events: none;
        }
        .rate-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .rate-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .rate-left img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }
        .rate-name {
            font-size: 14px;
            font-weight: 700;
            color: #8b0000;
        }
        .rate-right {
            font-size: 14px;
            font-weight: 800;
            color: #222222;
        }
`;

// Replace content in HTML part
htmlPart = htmlPart.replace(/<div class="content">[\s\S]*?(?=<!-- Bottom Menu -->|<!-- Popup Overlay -->|<nav class="bottom-menu">)/, newContentHtml + '\n\n  ');

// Insert CSS before </style>
htmlPart = htmlPart.replace(/<\/style>/, newCss + '\n    </style>');

// Fix active item in bottom menu.
htmlPart = htmlPart.replace(/class="menu-item home-item active"/, 'class="menu-item home-item"');
htmlPart = htmlPart.replace(/href="price_chart.php" class="menu-item"/, 'href="price_chart.php" class="menu-item active"');

// Fix header title for price chart just in case it had something different (user_dashboard has "🪙 SUBH GAMING 🪙")
// We will keep "🪙 SUBH GAMING 🪙"

// Write back to price_chart.php
fs.writeFileSync('price_chart.php', topPhp + htmlPart, 'utf8');
console.log("Restored and updated price_chart.php!");
