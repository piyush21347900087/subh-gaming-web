<?php
if (isset($_GET['mode']) && $_GET['mode'] === 'json') {
  // your existing JSON endpoint
  require 'notify_api.php';
  exit;
}
header('Content-Type: application/javascript; charset=utf-8');
?>
function _toConsumableArray(r) { return _arrayWithoutHoles(r) || _iterableToArray(r) || _unsupportedIterableToArray(r) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _iterableToArray(r) { if ("undefined" != typeof Symbol && null != r[Symbol.iterator] || null != r["@@iterator"]) return Array.from(r); }
function _arrayWithoutHoles(r) { if (Array.isArray(r)) return _arrayLikeToArray(r); }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
/* =============== NOTIFY BUNDLE JS (auto‑loaded) =============== */

(function () {
  var KEY_B = 'last_bid_id',
    KEY_W = 'last_win_id';
  var lastBid = +localStorage.getItem(KEY_B) || 0;
  var lastWin = +localStorage.getItem(KEY_W) || 0;

  // simple in‑page toast
  window.showPopup = window.showPopup || function (msg) {
    var pop = document.createElement('div');
    pop.style.cssText = "position:fixed;top:42px;left:50%;transform:translateX(-50%);" + "background:#2e7d32;color:#fff;padding:16px 26px;border-radius:12px;" + "font-weight:600;z-index:9999;box-shadow:0 3px 18px rgba(0,0,0,.2);" + "white-space:pre-line;text-align:center;";
    pop.textContent = msg;
    document.body.appendChild(pop);
    setTimeout(function () {
      return pop.remove();
    }, 3000);
  };
  window.showMarketResultPopup = window.showMarketResultPopup || function (o) {
    var market = String(o && o.market || '');
    var result = String(o && o.result || '');
    var pop = document.createElement('div');
    pop.style.cssText = "position:fixed;top:42px;left:50%;transform:translateX(-50%);" + "background:#ffffff;color:#1f2735;padding:16px 26px;border-radius:12px;" + "font-weight:700;z-index:9999;box-shadow:0 3px 18px rgba(0,0,0,.2);" + "white-space:pre-line;text-align:center;border:1px solid rgba(0,102,204,.18);";
    pop.innerHTML = "<span style=\"color:#0066cc;\">".concat(market.replace(/[&<>"']/g, function (ch) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[ch];
    }), "</span>").concat(result ? '<br>' + result.replace(/[&<>"']/g, function (ch) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[ch];
    }) : '');
    document.body.appendChild(pop);
    setTimeout(function () {
      return pop.remove();
    }, 3000);
  };

  // fancy win modal
  window.animatedWinPopup = window.animatedWinPopup || function (o) {
    var overlay = document.createElement('div');
    overlay.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;";
    var box = document.createElement('div');
    box.style.cssText = "position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);" + "background:#fff;border-radius:16px;padding:22px 28px;box-shadow:0 8px 32px rgba(0,0,0,.35);" + "text-align:center;animation:pop .35s ease";
    box.innerHTML = "\n<h3 style=\"margin:0 0 8px;color:#0066cc;font-size:20px;\">".concat(o.market || '', "</h3>\n<div style=\"font-size:16px;margin-bottom:6px;\">\n  <strong>").concat(o.bet_type || '', "</strong> | ").concat(o.session || '', "\n</div>\n<div style=\"margin-bottom:6px;\">Bid: \u20B9").concat((+o.amount || 0).toFixed(2), "</div>\n<div style=\"font-weight:700;color:#2e7d32;font-size:18px;\">\n  WIN: \u20B9").concat((+o.win || 0).toFixed(2), "\n</div>\n<button style=\"margin-top:14px;padding:8px 20px;border:none;background:#c62828;\"\n  + \"color:#fff;border-radius:8px;font-weight:600;cursor:pointer;\">\n  OK\n</button>\n");
    overlay.appendChild(box);
    box.querySelector('button').onclick = function () {
      return overlay.remove();
    };
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.remove();
    });
    // Dynamic CSS for blinking dot
    var style = document.createElement('style');
    style.textContent = "\n@keyframes pop{0%{transform:translate(-50%,-50%) scale(.7);opacity:0}100%{transform:translate(-50%,-50%)\nscale(1);opacity:1}}\n@keyframes blink-yellow {\n0% { opacity: 1; transform: scale(1); background-color: #ffee58; }\n50% { opacity: 0.5; transform: scale(1.3); background-color: #fff176; }\n100% { opacity: 1; transform: scale(1); background-color: #ffee58; }\n}\n.notify-blink {\nanimation: blink-yellow 0.8s infinite ease-in-out !important;\nbox-shadow: 0 0 8px #ffee58;\n}\n";
    document.head.appendChild(style);
    document.body.appendChild(overlay);
  };
  function poll() {
    fetch('notify_bundle.php?mode=json&last_bid_id=' + lastBid + '&last_win_id=' + lastWin, {
      cache: 'no-store'
    }).then(function (r) {
      return r.json();
    }).then(function (j) {
      // 1) new bids → show to everyone
      if (j.bid && j.bid.length) {
        j.bid.slice().reverse().forEach(function (b) {
          var title = "Market Open";
          var message = "Market: ".concat(b.market || '', "\nType: ").concat(b.bet_type || '', "\nSession: ").concat(b.session || '', "\nNumber:\n").concat(b.declared_no || '');
          if (window.AndroidNotify && AndroidNotify.notify) {
            AndroidNotify.notify(title, message);
          } else {
            showMarketResultPopup({
              market: b.market || '',
              result: "Type: ".concat(b.bet_type || '', "\nSession: ").concat(b.session || '', "\nNumber:\n").concat(b.declared_no || '')
            });
          }
        });
        lastBid = Math.max.apply(Math, _toConsumableArray(j.bid.map(function (x) {
          return +x.id;
        })));
        localStorage.setItem(KEY_B, lastBid);
      }

      // 2) new wins → only for winners
      if (j.win && j.win.length) {
        j.win.slice().reverse().forEach(function (w) {
          var title = "You WON \u20B9".concat((+w.wining_balance || 0).toFixed(2));
          var message = "".concat(w.market || '', " | ").concat(w.bet_type || '', " | ").concat(w.session || '');
          if (window.AndroidNotify && AndroidNotify.notify) {
            AndroidNotify.notify(title, message);
          } else {
            animatedWinPopup({
              market: w.market,
              bet_type: w.bet_type,
              session: w.session,
              amount: w.amount,
              win: w.wining_balance
            });
          }
        });
        lastWin = Math.max.apply(Math, _toConsumableArray(j.win.map(function (x) {
          return +x.id;
        })));
        localStorage.setItem(KEY_W, lastWin);
      }
    }).catch(function () {/* silent */});
  }

  // Admin Notice polling
  function pollNotice() {
    fetch('notice_status.php', {
      cache: 'no-store'
    }).then(function (r) {
      return r.json();
    }).then(function (d) {
      if (!d || typeof d.id === 'undefined') return;
      var latestId = +d.id || 0;
      var KEY_SEEN = 'last_notice_seen';
      var lastSeen = +localStorage.getItem(KEY_SEEN) || 0;
      var hasNew = latestId > lastSeen;
      var headerDot = document.getElementById('notifyDot');
      var sideDot = document.getElementById('sideNotifyDot');
      var bell = document.querySelector('.notify-icon');
      var setDots = function setDots(show) {
        if (headerDot) {
          headerDot.style.display = show ? 'block' : 'none';
          if (show) headerDot.classList.add('notify-blink');else headerDot.classList.remove('notify-blink');
        }
        if (sideDot) {
          sideDot.style.display = show ? 'block' : 'none';
          if (show) sideDot.classList.add('notify-blink');else sideDot.classList.remove('notify-blink');
        }
        if (bell) {
          if (show) bell.classList.add('pulse');else bell.classList.remove('pulse');
        }
      };
      setDots(hasNew);
    }).catch(function () {});
  }

  // kick off
  setTimeout(poll, 1500);
  setInterval(poll, 120000);

  // start Admin Notice polling too
  setTimeout(pollNotice, 2000);
  setInterval(pollNotice, 45000);

  /* ✅ ENHANCED: App Downloading Popup Interceptor */
  function initDownloaderUI() {
    document.body.addEventListener('click', function (e) {
      var target = e.target.closest('a[href="download.php"]');
      if (target) {
        e.preventDefault();

        // Create Popup Container
        var overlay = document.createElement('div');
        overlay.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,0.8);backdrop-filter:blur(8px);z-index:10000;display:flex;justify-content:center;align-items:center;font-family:'Poppins',sans-serif;opacity:0;transition:opacity 0.3s ease;";
        var modal = document.createElement('div');
        modal.style.cssText = "background:#1a1a1a;color:#fff;border:2px solid #39ff14;border-radius:20px;padding:30px 20px;width:85%;max-width:360px;text-align:center;box-shadow:0 0 25px rgba(57,255,20,0.4);transform:scale(0.8);transition:transform 0.3s ease;";
        modal.innerHTML = "\n        <div style=\"position:relative;width:80px;height:80px;margin:0 auto 20px;\">\n          <div style=\"position:absolute;inset:0;border:4px solid rgba(57,255,20,0.2);border-top-color:#39ff14;border-radius:50%;animation:spin 1s linear infinite;\"></div>\n          <i class=\"fas fa-cloud-arrow-down\" style=\"position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:30px;color:#39ff14;animation:bounce 1s infinite;\"></i>\n        </div>\n        <h3 style=\"margin:0 0 10px;font-size:20px;font-weight:600;letter-spacing:0.5px;\">Downloading App</h3>\n        <p style=\"margin:0 0 15px;color:#aaa;font-size:13px;line-height:1.5;\">Please wait while your application downloads in the background.</p>\n        <div style=\"width:100%;height:6px;background:#333;border-radius:10px;overflow:hidden;\">\n          <div style=\"width:0%;height:100%;background:#39ff14;box-shadow:0 0:10px #39ff14;border-radius:10px;animation:fillBar 3.5s ease-in-out forwards;\"></div>\n        </div>\n        <div style=\"margin-top:10px;color:#39ff14;font-size:11px;font-weight:700;\" id=\"dlStatus\">Preparing Files...</div>\n      ";
        var style = document.createElement('style');
        style.textContent = "\n        @keyframes spin { 100% { transform: rotate(360deg); } }\n        @keyframes bounce { 0%, 100% { transform: translate(-50%, -45%); } 50% { transform: translate(-50%, -60%); } }\n        @keyframes fillBar { 0% { width: 0%; } 30% { width: 40%; } 70% { width: 75%; } 100% { width: 100%; } }\n      ";
        document.head.appendChild(style);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Animate In
        setTimeout(function () {
          overlay.style.opacity = '1';
          modal.style.transform = 'scale(1)';
        }, 50);

        // Update Status phrases
        setTimeout(function () {
          var statusEl = document.getElementById('dlStatus');
          if (statusEl) statusEl.textContent = 'Downloading APK...';
        }, 1000);
        setTimeout(function () {
          var statusEl = document.getElementById('dlStatus');
          if (statusEl) statusEl.textContent = 'Finishing Download...';
        }, 2500);

        // Trigger Actual Download
        var downloadIframe = document.createElement('iframe');
        downloadIframe.style.display = 'none';
        downloadIframe.src = 'download.php';
        document.body.appendChild(downloadIframe);

        // Auto Close after 4 seconds
        setTimeout(function () {
          overlay.style.opacity = '0';
          modal.style.transform = 'scale(0.8)';
          setTimeout(function () {
            overlay.remove();
            downloadIframe.remove();
          }, 300);
        }, 4000);
      }
    });
  }
  if (document.readyState !== 'loading') {
    initDownloaderUI();
  } else {
    document.addEventListener('DOMContentLoaded', initDownloaderUI);
  }
})();
