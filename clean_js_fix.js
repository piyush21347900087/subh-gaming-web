const fs = require('fs');

function cleanJs(filename) {
    let content = fs.readFileSync(filename, 'utf8');

    const cleanBlock = `        /* SIDE MENU TOGGLE */
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
        window.openM = openM;
        window.closeM = closeM;

        document.addEventListener('DOMContentLoaded', function() {
            var menuBtn = document.getElementById('menuToggle');
            var sideMenu = document.getElementById('sideMenu');
            var sideOverlay = document.getElementById('sideOverlay');
            var sideClose = document.getElementById('sideCloseBtn');
            var liveSupportBtn = document.getElementById('liveSupportBtn');

            if (menuBtn) menuBtn.addEventListener('click', openM);
            if (sideOverlay) sideOverlay.addEventListener('click', closeM);
            if (sideClose) sideClose.addEventListener('click', closeM);

            function refreshSupportDot() {
                if (!liveSupportBtn) return;
                try {
                    fetch('user_dashboard.php?action=support_unread&_=' + Date.now(), {
                        cache: 'no-store',
                        credentials: 'same-origin'
                    }).then(function(res) {
                        return res.text();
                    }).then(function(text) {
                        var unread = parseInt(text, 10) || 0;
                        if (unread > 0) {
                            liveSupportBtn.classList.add('has-support-unread');
                        } else {
                            liveSupportBtn.classList.remove('has-support-unread');
                        }
                    }).catch(function(e) {});
                } catch (e) {}
            }

            refreshSupportDot();
            setInterval(refreshSupportDot, 5000);
        });`;

    // Regex replace from /* SIDE MENU TOGGLE */ down to /* SHARE */
    content = content.replace(/\/\* SIDE MENU TOGGLE \*\/[\s\S]*?(?=\/\* SHARE \*\/)/, cleanBlock + '\n\n            ');

    fs.writeFileSync(filename, content, 'utf8');
}

cleanJs('price_chart.php');
cleanJs('user_dashboard.php');

console.log('Successfully cleaned JS syntax and restored side menu toggle in price_chart.php & user_dashboard.php!');
