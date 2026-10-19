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
.who{position:fixed;right:16px;bottom:16px;width:min(390px,calc(100% - 32px));max-height:calc(100% - 90px);overflow:auto;z-index:20;background:#F6F3EA;color:#2F2B22;border-radius:26px;box-shadow:0 22px 50px rgba(20,18,12,.38);padding:22px 22px 18px;font-family:"Outfit",system-ui,sans-serif;font-size:16px;line-height:1.55;transform:translateY(24px);opacity:0;transition:transform .45s cubic-bezier(.2,.8,.2,1),opacity .45s}
.who.in{transform:none;opacity:1}
.who .hi{font-family:"Hand","Bradley Hand",cursive;color:#5E6B35;font-size:17px;transform:rotate(-2deg);display:inline-block}
.who h3{font-family:"Italiana",Georgia,serif;font-weight:400;font-size:32px;line-height:1.08;color:#3F4A26;margin:6px 0 8px}
.who p{margin:0;color:#6B5D4C;font-size:15.5px}
.who .pr{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin-top:14px}
.who .reg{color:#9A9282;text-decoration:line-through;font-size:17px}
.who .sp{font-size:30px;color:#3F4A26;font-weight:500}
.who .only{font-size:13px;color:#6B5D4C;width:100%}
.who .cd{display:inline-flex;gap:8px;align-items:center;margin-top:10px;padding:7px 14px;border-radius:999px;background:#DCE0D2;color:#3F4A26;font-size:14.5px;font-weight:500}
.who .cd b{font-variant-numeric:tabular-nums;font-weight:600}
.who .go{display:block;width:100%;margin-top:14px;font:inherit;font-weight:500;font-size:17px;padding:14px 20px;border:0;border-radius:999px;background:#5E6B35;color:#F6F3EA;cursor:pointer}
.who .go:disabled{opacity:.6;cursor:default}
.who .sm{display:block;text-align:center;font-size:12.5px;color:#9A9282;margin-top:8px}
.who .x{position:absolute;right:12px;top:10px;border:0;background:transparent;color:#9A9282;font-size:22px;cursor:pointer;line-height:1;padding:6px}
.who .err{color:#8A3B2E;font-size:14px;margin-top:8px}
.whp{position:fixed;right:16px;bottom:16px;z-index:20;border:0;border-radius:999px;padding:11px 18px;background:#F6F3EA;color:#3F4A26;font:500 15px "Outfit",system-ui,sans-serif;box-shadow:0 10px 30px rgba(20,18,12,.35);cursor:pointer}
.whp b{font-variant-numeric:tabular-nums}`;
  document.head.appendChild(css);

  const card = document.createElement('div'); card.className = 'who'; card.hidden = true;
  const pill = document.createElement('button'); pill.className = 'whp'; pill.type = 'button'; pill.hidden = true;
  document.body.append(card, pill);

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
    card.innerHTML = '<button class="x" type="button" aria-label="Minimise">–</button>'
      + '<span class="hi">' + (live ? 'just for you tonight ♡' : 'when you are ready ♡') + '</span>'
      + '<h3>' + esc(cur.title) + '</h3><p>' + esc(cur.text) + '</p>'
      + '<div class="pr">' + (live ? '<span class="reg">' + esc(cur.regular) + '</span><span class="sp">' + esc(cur.special) + '</span><span class="only">Gathering price, only for women who joined tonight</span>' : '<span class="sp">' + esc(cur.regular) + '</span>') + '</div>'
      + (live ? '<div class="cd">Gathering price ends in <b>' + mmss(left()) + '</b></div>' : '')
      + '<button class="go" type="button"' + (preview ? ' disabled' : '') + '>' + (preview ? 'Vorschau: so sehen es die Frauen' : 'Book now · ' + esc(live ? cur.special : cur.regular)) + '</button>'
      + '<span class="sm">Secure payment with Stripe · opens in a new tab, you stay in the room</span><div class="err" hidden></div>';
    card.hidden = false;
    requestAnimationFrame(() => card.classList.add('in'));
    card.querySelector('.x').onclick = () => { small = true; render(); };
    const go = card.querySelector('.go');
    if (!preview) go.onclick = async () => {
      go.disabled = true;
      const w = window.open('', '_blank');
      try {
        const r = await fetch('/api/wh-offer.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({action: 'buy', id: cur.id, k: opts.k || '', t: opts.t || '', g: opts.g || 0, gast: opts.gast ? 1 : 0})});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || '');
        if (w) w.location = j.redirect; else location.href = j.redirect;
      } catch (e) {
        if (w) w.close();
        const er = card.querySelector('.err'); er.hidden = false; er.textContent = e.message || 'That did not work just now. Please try again.';
      }
      go.disabled = false;
    };
  };
  pill.onclick = () => { small = false; render(); };

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
