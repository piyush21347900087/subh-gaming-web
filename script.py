
import sys
with open('live_chat.php', 'r', encoding='utf-8') as f:
    c = f.read()

s = '''                var meta = document.createElement('div');
                meta.className = 'meta';
                meta.innerText = m.t;
                buLLle.appendChild(meta);'''
                
r = '''                var meta = document.createElement('div');
                meta.className = 'meta';
                
                var tSpan = document.createElement('span');
                tSpan.innerText = m.t;
                meta.appendChild(tSpan);
                
                if (m.sender === 'user') {
                    var tw = document.createElement('span');
                    tw.className = 'tick-wrap';
                    tw.style.marginLeft = '4px';
                    tw.style.display = 'inline-flex';
                    tw.style.alignItems = 'center';
                    tw.style.verticalAlign = 'middle';
                    
                    var tImg = document.createElement('img');
                    tImg.className = 'chat-tick';
                    tImg.src = 'icons/single_tick.svg';
                    tImg.style.height = '12px';
                    tImg.style.width = 'auto';
                    
                    tw.appendChild(tImg);
                    meta.appendChild(tw);
                }
                
                buLLle.appendChild(meta);'''
                
c = c.replace(s, r)

fetch_logic_old = '''                            j.messages.forEach(function(m) {
                                renderMessage(m);
                                var mid = parseInt(m.id);
                                if (mid > lastId) lastId = mid;
                                if (m.sender === 'admin' && mid > lastNotifiedAdminId) {
                                    hasAdminMsg = true;
                                    lastNotifiedAdminId = mid;
                                }
                            });'''
                            
fetch_logic_new = '''                            j.messages.forEach(function(m) {
                                renderMessage(m);
                                var mid = parseInt(m.id);
                                if (mid > lastId) lastId = mid;
                                if (m.sender === 'admin' && mid > lastNotifiedAdminId) {
                                    hasAdminMsg = true;
                                    lastNotifiedAdminId = mid;
                                }
                            });
                            
                            // UPDATE TICKS
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
                            });'''

c = c.replace(fetch_logic_old, fetch_logic_new)

with open('live_chat.php', 'w', encoding='utf-8') as f:
    f.write(c)

