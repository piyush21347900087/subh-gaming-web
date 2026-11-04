<?php
$f = file_get_contents("live_chat.php");

$pattern = '/async function fetchMessages\(\) \{.*?\n            \}/s';

$new_func = <<<EOF
async function fetchMessages() {
                try {
                    var r = await fetch(`live_chat.php?action=fetch&after_id=\${lastId}`);
                    var j = await r.json();
                    if (j.remaining_uploads !== undefined) { updateUploadLimitDisplay(j.remaining_uploads); }
                    
                    if (j.ok) {
                        // UPDATE TICKS REGARDLESS OF NEW MESSAGES
                        var userAdminSeen = j.admin_seen || 0;
                        var userAdminOnline = j.admin_online || false;
                        
                        document.querySelectorAll('.row.user').forEach(function(r) {
                            var msgId = parseInt(r.getAttribute('data-msg-id') || 0);
                            var tickImg = r.querySelector('.chat-tick');
                            if (tickImg) {
                                if (msgId <= userAdminSeen) {
                                    if (tickImg.src.indexOf('green_tick.svg') === -1) {
                                        tickImg.src = 'icons/green_tick.svg';
                                    }
                                } else if (userAdminOnline) {
                                    if (tickImg.src.indexOf('double_tick.svg') === -1) {
                                        tickImg.src = 'icons/double_tick.svg';
                                    }
                                } else {
                                    if (tickImg.src.indexOf('single_tick.svg') === -1) {
                                        tickImg.src = 'icons/single_tick.svg';
                                    }
                                }
                            }
                        });

                        if (j.messages && j.messages.length > 0) {
                            var wasAtBottom = nearBottom();
                            var hasAdminMsg = false;
                            
                            j.messages.forEach(function(m) {
                                renderMessage(m);
                                var mid = parseInt(m.id);
                                if (mid > lastId) lastId = mid;
                                if (m.sender === 'admin' && mid > lastNotifiedAdminId) {
                                    hasAdminMsg = true;
                                    lastNotifiedAdminId = mid;
                                }
                            });

                            if (hasAdminMsg && !pageVisible()) {
                                notifySound.play().catch(function() { });
                                localStorage.setItem('ll_chat_last_notified_admin_id', lastNotifiedAdminId);
                                if ('Notification' in window && Notification.permission === 'granted') {
                                    new Notification('New Message', { body: 'You have a new message from Support.' });
                                }
                            }

                            if (firstLoad) {
                                var hint = msgsEl.querySelector('.hint');
                                if (hint) hint.remove();
                            }
                            if (firstLoad || wasAtBottom || shouldScrollAfterSend) scrollToBottom(!firstLoad);
                            shouldScrollAfterSend = false;
                            firstLoad = false;
                        } else if (firstLoad) {
                            msgsEl.innerHTML = '<div class="hint">' + '<?= __("No messages yet. Start the conversation!") ?>' + '</div>';
                            firstLoad = false;
                        }
                    }
                } catch (e) {
                }
            }
EOF;

$f = preg_replace($pattern, $new_func, $f);
file_put_contents("live_chat.php", $f);
echo "DONE";
?>
