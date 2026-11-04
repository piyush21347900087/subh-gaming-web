
(function () {
  if (window.SUBHGAMING_NOTICE_TRACKER_STARTED) return;
  window.SUBHGAMING_NOTICE_TRACKER_STARTED = true;
  var KEY_SEEN = 'last_notice_seen';
  var KEY_SOUND = 'last_notice_sound_id';
  var CHANNEL_KEY = 'notice_seen_broadcast';
  var DOT_SELECTOR = '#notifyDot, #sideNotifyDot';
  var LINK_SELECTOR = '#notifyLink, #sideNotifyItem, a[href*="notice_board.php"]';
  var latestNoticeId = 0;
  var lastSound = +(localStorage.getItem(KEY_SOUND) || 0);
  var audioReady = false;
  var sound = new Audio('sound/notification.mp3');
  sound.preload = 'auto';
  function setDots(show) {
    document.querySelectorAll(DOT_SELECTOR).forEach(function (dot) {
      dot.style.display = show ? 'block' : 'none';
    });
  }
  function getSeen() {
    return +(localStorage.getItem(KEY_SEEN) || 0);
  }
  function markSeen(id) {
    var seenId = Math.max(getSeen(), +(id || latestNoticeId || 0));
    if (seenId > 0) {
      localStorage.setItem(KEY_SEEN, String(seenId));
      localStorage.setItem(CHANNEL_KEY, String(Date.now()));
    }
    setDots(false);
  }
  function playNoticeSound() {
    try {
      sound.pause();
      sound.currentTime = 0;
      sound.muted = false;
      sound.play().catch(function () {});
    } catch (e) {}
  }
  function unlockAudio() {
    if (audioReady) return;
    audioReady = true;
    try {
      sound.muted = true;
      sound.play().then(function () {
        sound.pause();
        sound.currentTime = 0;
        sound.muted = false;
      }).catch(function () {
        sound.muted = false;
      });
    } catch (e) {
      sound.muted = false;
    }
  }
  function pollNotice() {
    fetch('notice_status.php?_=' + Date.now(), {
      cache: 'no-store'
    }).then(function (r) {
      return r.json();
    }).then(function (d) {
      if (!d || typeof d.id === 'undefined') return;
      var id = +(d.id || 0);
      latestNoticeId = Math.max(latestNoticeId, id);
      var hasNew = id > getSeen();
      setDots(hasNew);
      if (hasNew && id > lastSound) {
        playNoticeSound();
        lastSound = id;
        localStorage.setItem(KEY_SOUND, String(id));
      }
    }).catch(function () {});
  }
  document.addEventListener('click', function (event) {
    unlockAudio();
    var link = event.target.closest(LINK_SELECTOR);
    if (link) markSeen(latestNoticeId);
  }, true);
  document.addEventListener('touchstart', unlockAudio, true);
  document.addEventListener('pointerdown', unlockAudio, true);
  window.addEventListener('storage', function (event) {
    if (event.key === KEY_SEEN || event.key === CHANNEL_KEY) {
      if (latestNoticeId <= getSeen()) setDots(false);
    }
  });
  pollNotice();
  setInterval(pollNotice, 15000);
})();