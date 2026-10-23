// Angebotskarte im Gathering-Raum: erscheint, sobald Viv ein Angebot einblendet.
// Der Gathering-Preis läuft mit Countdown ab, danach gilt der reguläre Preis (wird am Server geprüft).
window.whOffer = function (opts) {
  const q = new URLSearchParams();
  if (opts.k) q.set('k', opts.k);
  if (opts.t) q.set('t', opts.t);
  if (opts.g) q.set('g', opts.g);
  if (opts.gast) q.set('gast', '1');
  const preview = !!opts.preview;

  const css = document.createElement('style');
  css.textContent = `
.who{position:fixed;right:16px;bottom:16px;width:min(390px,calc(100% - 32px));max-height:calc(100% - 90px);overflow:auto;z-index:20;background:#F2ECE4;color:#2E2529;border-radius:26px;box-shadow:0 22px 50px rgba(31,22,26,.38);padding:22px 22px 18px;font-family:"Outfit",system-ui,sans-serif;font-size:16px;line-height:1.55;transform:translateY(24px);opacity:0;transition:transform .45s cubic-bezier(.2,.8,.2,1),opacity .45s}
.who.in{transform:none;opacity:1}
.who .hi{font-family:"Hand","Bradley Hand",cursive;color:#7D5E68;font-size:17px;transform:rotate(-2deg);display:inline-block}
.who h3{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:32px;line-height:1.08;color:#6B5658;margin:6px 0 8px}
.who p{margin:0;color:#7D7076;font-size:15.5px}
.who .pr{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin-top:14px}
.who .reg{color:#A0959A;text-decoration:line-through;font-size:17px}
.who .sp{font-size:30px;color:#6B5658;font-weight:500}
.who .only{font-size:13px;color:#7D7076;width:100%}
.who .cd{display:inline-flex;gap:8px;align-items:center;margin-top:10px;padding:7px 14px;border-radius:999px;background:#EADFDE;color:#6B5658;font-size:14.5px;font-weight:500}
.who .cd b{font-variant-numeric:tabular-nums;font-weight:600}
.who .go{display:block;width:100%;margin-top:14px;font:inherit;font-weight:500;font-size:17px;padding:14px 20px;border:0;border-radius:999px;background:#7D5E68;color:#F2ECE4;cursor:pointer}
.who .go:disabled{opacity:.6;cursor:default}
.who .sm{display:block;text-align:center;font-size:12.5px;color:#A0959A;margin-top:8px}
.who .x{position:absolute;right:12px;top:10px;border:0;background:transparent;color:#A0959A;font-size:22px;cursor:pointer;line-height:1;padding:6px}
.who .err{color:#8A3B2E;font-size:14px;margin-top:8px}
.whp{position:fixed;right:16px;bottom:16px;z-index:20;border:0;border-radius:999px;padding:11px 18px;background:#F2ECE4;color:#6B5658;font:500 15px "Outfit",system-ui,sans-serif;box-shadow:0 10px 30px rgba(31,22,26,.35);cursor:pointer}
.whp b{font-variant-numeric:tabular-nums}
.who .it{border-top:1px solid #DDD2C6;padding:12px 0 2px}
.who .it:first-of-type{border-top:0}
.who .it h4{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:24px;color:#6B5658;margin:0}
.who .it p{font-size:14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.who .it .pr{margin-top:6px}.who .it .sp{font-size:22px}.who .it .reg{font-size:15px}
.who .it .go{margin-top:8px;padding:11px 18px;font-size:16px}
.who .go2{display:block;width:100%;margin-top:8px;font:inherit;font-size:15px;padding:10px 18px;border-radius:999px;border:1.5px solid #DDD2C6;background:transparent;color:#6B5658;cursor:pointer}
.who .cons{display:flex;gap:8px;align-items:flex-start;font-size:12.5px;line-height:1.45;color:#7D7076;margin-top:12px}
.who .cons input{margin-top:2px;width:16px;height:16px;flex:0 0 auto;accent-color:#7D5E68}
.who .cons a,.who .sm a{color:#7D5E68}
.who .note.start{color:#6B5658;background:#EADFDE;border-radius:10px;padding:6px 10px;font-size:13px}.who .note{font-size:12.5px;color:#7D7076;margin-top:8px}
.whq{position:fixed;left:50%;top:70px;transform:translate(-50%,-14px);opacity:0;transition:transform .45s cubic-bezier(.2,.8,.2,1),opacity .45s;z-index:19;width:min(640px,calc(100% - 32px));background:#F2ECE4;border-radius:22px;padding:16px 22px 18px;box-shadow:0 18px 40px rgba(31,22,26,.35);font-family:"Outfit",system-ui,sans-serif}
.whq.in{transform:translate(-50%,0);opacity:1}
.whq small{display:block;color:#7D5E68;font-size:14px;font-weight:500;margin-bottom:4px}
.whq div{color:#2E2529;font-size:21px;line-height:1.4}
.wha{position:fixed;left:16px;bottom:16px;z-index:18;border:0;border-radius:999px;padding:11px 18px;background:rgba(242,236,228,.94);color:#6B5658;font:500 15px "Outfit",system-ui,sans-serif;box-shadow:0 10px 30px rgba(31,22,26,.3);cursor:pointer}
.whf{position:fixed;left:16px;bottom:16px;z-index:22;width:min(380px,calc(100% - 32px));background:#F2ECE4;border-radius:22px;padding:18px;box-shadow:0 18px 44px rgba(31,22,26,.4);font-family:"Outfit",system-ui,sans-serif;color:#2E2529}
.whf b{display:block;font-weight:500;font-size:17px;color:#6B5658}
.whf p{font-size:14px;color:#7D7076;margin:4px 0 10px}
.whf textarea{width:100%;box-sizing:border-box;min-height:96px;font:inherit;font-size:16px;padding:10px 12px;border-radius:14px;border:1.5px solid #DDD2C6;background:#FBF8F4;resize:vertical}
.whf label{display:flex;gap:8px;align-items:center;font-size:14px;color:#7D7076;margin-top:8px}
.whf .r{display:flex;gap:8px;margin-top:12px}
.whf button{font:inherit;font-weight:500;font-size:15px;padding:10px 18px;border-radius:999px;border:0;background:#7D5E68;color:#F2ECE4;cursor:pointer}
.whf button.g{background:transparent;color:#6B5658;border:1.5px solid #DDD2C6}
.whf .m{font-size:14px;margin-top:8px;color:#7D5E68}
@media (max-width:600px){.who{left:12px;right:12px;width:auto;bottom:70px;padding:16px 18px 14px;max-height:calc(100% - 140px)}.who h3{font-size:25px;margin:4px 0 6px}.who p{font-size:14px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.who .pr{margin-top:10px}.who .sp{font-size:26px}.who .go{padding:12px 18px;margin-top:10px}.whp{bottom:70px;right:12px}.whq{top:60px}.whq div{font-size:18px}}`;
  document.head.appendChild(css);

  const card = document.createElement('div'); card.className = 'who'; card.hidden = true;
  const pill = document.createElement('button'); pill.className = 'whp'; pill.type = 'button'; pill.hidden = true;
  document.body.append(card, pill);

  // Eingeblendete Frage (oben) und „Ask anonymously“ (nur echte Teilnehmerinnen)
  const qb = document.createElement('div'); qb.className = 'whq'; qb.hidden = true; document.body.appendChild(qb);
  let qCur = 0;
  const showQ = qq => {
    if (!qq) { qCur = 0; qb.classList.remove('in'); setTimeout(() => { if (!qCur) qb.hidden = true; }, 450); return; }
    if (qq.id === qCur) return;
    qCur = qq.id; qb.innerHTML = '<small>' + esc(qq.label) + '</small><div>' + esc(qq.text) + '</div>';
    qb.hidden = false; requestAnimationFrame(() => qb.classList.add('in'));
  };
  if (!preview) {
    const ab = document.createElement('button'); ab.type = 'button'; ab.className = 'wha'; ab.textContent = '♡ Ask anonymously';
    const af = document.createElement('form'); af.className = 'whf'; af.hidden = true;
    af.innerHTML = '<b>Ask a question</b><p>Viv will see it without your name and can answer it for everyone. Nobody knows it was you.</p><textarea maxlength="500" required placeholder="Your question …"></textarea>'
      + (opts.k ? '<label><input type="checkbox"> I am happy to show my first name</label>' : '')
      + '<div class="r"><button type="submit">Send</button><button type="button" class="g">Close</button></div><div class="m" hidden></div>';
    document.body.append(ab, af);
    ab.onclick = () => { af.hidden = false; ab.hidden = true; af.querySelector('textarea').focus(); };
    af.querySelector('.g').onclick = () => { af.hidden = true; ab.hidden = false; };
    af.onsubmit = async e => {
      e.preventDefault();
      const ta = af.querySelector('textarea'), m = af.querySelector('.m'), cb = af.querySelector('input[type=checkbox]');
      try {
        const r = await fetch('/api/wh-offer.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'ask', text: ta.value, named: cb && cb.checked ? 1 : 0, k: opts.k || '', t: opts.t || '', gast: opts.gast ? 1 : 0})});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || '');
        ta.value = ''; m.hidden = false; m.textContent = 'Thank you ♡ Your question is with Viv.';
        setTimeout(() => { m.hidden = true; af.hidden = true; ab.hidden = false; }, 2200);
      } catch (err) { m.hidden = false; m.textContent = err.message || 'That did not work just now. Please try again.'; }
    };
  }

  let cur = null, skew = 0, small = false, shownId = 0, tick = 0;
  const esc = s => String(s).replace(/[&<>"]/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]));
  const left = () => cur ? Math.max(0, cur.until * 1000 - (Date.now() - skew)) : 0;
  const mmss = ms => { const s = Math.ceil(ms / 1000), m = Math.floor(s / 60); return m + ':' + String(s % 60).padStart(2, '0'); };

  const render = () => {
    if (!cur) { card.hidden = pill.hidden = true; card.classList.remove('in'); return; }
    const live = cur.live && left() > 0;
    if (small) {
      card.hidden = true; pill.hidden = false;
      pill.innerHTML = '✦ ' + esc(cur.title) + (live ? ' · <b>' + mmss(left()) + '</b>' : '');
      return;
    }
    pill.hidden = true;
    if (cur.items) {
      const anyLive = cur.live && left() > 0;
      card.innerHTML = '<button class="x" type="button" aria-label="Minimise">–</button>'
        + '<span class="hi">' + (anyLive ? 'just for you tonight ♡' : 'when you are ready ♡') + '</span><h3>Ways to walk with me</h3>'
        + (anyLive ? '<div class="cd">Gathering prices end in <b>' + mmss(left()) + '</b></div>' : '')
        + (cur.items.some(it => it.offer !== 'retreat') ? '<label class="cons"><input type="checkbox" class="cb"><span>' + (opts.k || opts.t ? '' : '') + 'For the Inner Circle and Year Circle: I would like my access to start right away. I know that if I withdraw within 14 days, I pay only for the part already used. <a href="/terms" target="_blank" rel="noopener">Terms &amp; cancellation</a></span></label>' + '<label class="cons"><input type="checkbox" class="cb2"><span>Optional: please unlock the course area and downloads right away. I understand that I lose my right of withdrawal for this digital content. Without this tick, I start with the starter section and everything unlocks after 14 days.</span></label>' : '')
        + cur.items.map(it => { const lv = anyLive && it.live; return '<div class="it"><h4>' + esc(it.title) + '</h4><p>' + esc(it.text) + '</p><div class="pr">' + (lv ? '<span class="reg">' + esc(it.regular) + '</span><span class="sp">' + esc(it.special) + '</span>' : '<span class="sp">' + esc(it.regular) + '</span>') + '</div>'
          + (lv && it.note ? '<p class="note" style="margin-top:2px">' + esc(it.note) + '</p>' : '')
          + (it.start ? '<p class="note start">' + esc(it.start) + '</p>' : '')
          + '<button class="go" type="button" data-item="' + esc(it.offer) + '"' + (preview ? ' disabled' : '') + '>' + (preview ? 'Vorschau' : (it.offer === 'year' ? 'Apply · ' : 'Book · ') + esc(lv ? it.special : it.regular)) + '</button>'
          + (it.inst || []).map(pl => '<button class="go2" type="button" data-item="' + esc(it.offer) + '" data-plan="' + esc(pl.plan) + '"' + (preview ? ' disabled' : '') + '>' + (pl.label ? esc(pl.label) : 'or ' + pl.count + ' monthly payments of ' + esc(lv && pl.special ? pl.special : pl.regular)) + '</button>').join('')
          + (it.offer === 'retreat' ? '<p class="note">Fixed dates, so no statutory right of withdrawal. <a href="/terms" target="_blank" rel="noopener">Cancellation policy</a></p>' : '') + '</div>'; }).join('')
        + '<span class="sm">Secure payment with Stripe · opens in a new tab, you stay in the room</span><div class="err" hidden></div>';
      card.hidden = false;
      requestAnimationFrame(() => card.classList.add('in'));
      card.querySelector('.x').onclick = () => { small = true; render(); };
      if (!preview) card.querySelectorAll('.go, .go2').forEach(go => go.onclick = () => buy(go, go.dataset.item, go.dataset.plan || 'full'));
      return;
    }
    card.innerHTML = '<button class="x" type="button" aria-label="Minimise">–</button>'
      + '<span class="hi">' + (live ? 'just for you tonight ♡' : 'when you are ready ♡') + '</span>'
      + '<h3>' + esc(cur.title) + '</h3><p>' + esc(cur.text) + '</p>'
      + '<div class="pr">' + (live ? '<span class="reg">' + esc(cur.regular) + '</span><span class="sp">' + esc(cur.special) + '</span><span class="only">Gathering price, only for women who joined tonight</span>' : '<span class="sp">' + esc(cur.regular) + '</span>') + '</div>'
      + (live && cur.note ? '<p class="note" style="margin-top:2px">' + esc(cur.note) + '</p>' : '')
      + (cur.start ? '<p class="note start">' + esc(cur.start) + '</p>' : '')
      + (live ? '<div class="cd">Gathering price ends in <b>' + mmss(left()) + '</b></div>' : '')
      + (cur.start ? '<label class="cons"><input type="checkbox" class="cb"><span>I book The Inner Circle with a binding commitment. It opens on ' + esc(cur.start.replace(/^Opens on /, '').split(' · ')[0]) + ', and my first payment is taken then. <a href="/terms" target="_blank" rel="noopener">Terms &amp; cancellation</a></span></label>' : '')
      + (cur.start ? '' : cur.offer !== 'retreat' ? '<label class="cons"><input type="checkbox" class="cb"><span>' + (opts.k || opts.t ? '' : '') + 'I would like my access to start right away. I know that if I withdraw within 14 days, I pay only for the part already used. <a href="/terms" target="_blank" rel="noopener">Terms &amp; cancellation</a></span></label>' + '<label class="cons"><input type="checkbox" class="cb2"><span>Optional: please unlock the course area and downloads right away. I understand that I lose my right of withdrawal for this digital content. Without this tick, I start with the starter section and everything unlocks after 14 days.</span></label>' : '<p class="note">Retreats take place on fixed dates, so there is no statutory right of withdrawal. <a href="/terms" target="_blank" rel="noopener">Cancellation policy</a></p>')
      + '<button class="go" type="button"' + (preview ? ' disabled' : '') + '>' + (preview ? 'Vorschau: so sehen es die Frauen' : (cur.offer === 'year' ? 'Apply · ' : 'Book now · ') + esc(live ? cur.special : cur.regular)) + '</button>'
      + (cur.inst || []).map(pl => '<button class="go2" type="button" data-plan="' + esc(pl.plan) + '"' + (preview ? ' disabled' : '') + '>' + (pl.label ? esc(pl.label) : 'or ' + pl.count + ' monthly payments of ' + esc(live && pl.special ? pl.special : pl.regular)) + '</button>').join('')
      + '<span class="sm">Secure payment with Stripe · opens in a new tab, you stay in the room</span><div class="err" hidden></div>';
    card.hidden = false;
    requestAnimationFrame(() => card.classList.add('in'));
    card.querySelector('.x').onclick = () => { small = true; render(); };
    const go = card.querySelector('.go');
    if (!preview) { go.onclick = () => buy(go, '', 'full'); card.querySelectorAll('.go2').forEach(g2 => g2.onclick = () => buy(g2, '', g2.dataset.plan)); }
  };
  const buy = async (go, item, plan) => {
      const which = item || cur.offer, cb = card.querySelector('.cb'), er = card.querySelector('.err');
      if (which !== 'retreat' && cb && !cb.checked) { er.hidden = false; er.textContent = 'Please tick the box above first.'; cb.focus(); return; }
      go.disabled = true;
      const w = window.open('', '_blank');
      try {
        const r = await fetch('/api/wh-offer.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'buy', id: cur.id, item, plan, consent: cb && cb.checked ? 1 : 0, digital: which !== 'retreat' && card.querySelector('.cb2') && card.querySelector('.cb2').checked ? 1 : 0, k: opts.k || '', t: opts.t || '', g: opts.g || 0, gast: opts.gast ? 1 : 0})});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || '');
        if (w) w.location = j.redirect; else location.href = j.redirect;
      } catch (e) {
        if (w) w.close();
        er.hidden = false; er.textContent = e.message || 'That did not work just now. Please try again.';
      }
      go.disabled = false;
  };
  pill.onclick = () => { small = false; render(); };
  card.addEventListener('change', e => { if (e.target.classList.contains('cb') && e.target.checked) { const er = card.querySelector('.err'); if (er) er.hidden = true; } });

  // Countdown nur neu zeichnen, wenn sich die Anzeige ändert
  setInterval(() => {
    if (!cur) return;
    const sec = Math.ceil(left() / 1000);
    if (sec !== tick) {
      tick = sec;
      const b = small ? pill.querySelector('b') : card.querySelector('.cd b');
      if (b) b.textContent = mmss(left());
      if (cur.live && sec <= 0) { cur.live = false; render(); }
    }
  }, 250);

  const poll = async () => {
    try {
      const r = await fetch('/api/wh-offer.php?' + q.toString(), {cache: 'no-store'});
      const j = await r.json();
      showQ(j.question || null);
      const o = j.offer || null;
      if (o) skew = Date.now() - o.now * 1000;
      if (!o) { cur = null; shownId = 0; render(); }
      else if (!cur || o.id !== cur.id || o.live !== cur.live) { if (o.id !== shownId) { small = false; shownId = o.id; } cur = o; render(); }
      else cur = o;
      if (opts.onStatus) opts.onStatus(j);
    } catch (e) {}
  };
  poll();
  setInterval(poll, opts.interval || 6000);
  return {refresh: poll};
};
