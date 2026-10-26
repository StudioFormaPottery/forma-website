/* Bilder und Videos nicht per langem Drücken, Rechtsklick oder Ziehen speicherbar (Vivien, 26.10.2026).
   Ganz verhindern lässt sich das im Browser nie (z. B. Bildschirmfoto), aber der normale Weg zum Speichern ist zu. */
(function () {
  var css = 'img,video,picture,svg image{-webkit-touch-callout:none!important;-webkit-user-select:none!important;user-select:none!important;-webkit-user-drag:none!important}' +
    'video[autoplay][muted]:not([controls]),.art img,.offer img,.vsolo video,.vsolo img{pointer-events:none}';
  var s = document.createElement('style'); s.textContent = css; (document.head || document.documentElement).appendChild(s);
  var isMedia = function (t) { return t && t.closest && t.closest('img,video,picture,.art,.vsolo,.vf,.pic,figure'); };
  document.addEventListener('contextmenu', function (e) { if (isMedia(e.target)) e.preventDefault(); }, true);
  document.addEventListener('dragstart', function (e) { if (isMedia(e.target)) e.preventDefault(); }, true);
  var prep = function (root) {
    (root.querySelectorAll ? root : document).querySelectorAll('img,video').forEach(function (m) {
      m.setAttribute('draggable', 'false');
      if (m.tagName === 'VIDEO') {
        m.setAttribute('controlslist', 'nodownload noplaybackrate noremoteplayback');
        m.setAttribute('disablepictureinpicture', '');
        m.setAttribute('disableremoteplayback', '');
        try { m.disablePictureInPicture = true; } catch (e) {}
      }
    });
  };
  var go = function () {
    prep(document);
    if ('MutationObserver' in window) new MutationObserver(function (ms) { ms.forEach(function (r) { r.addedNodes.forEach(function (n) { if (n.nodeType === 1) { if (/^(IMG|VIDEO)$/.test(n.tagName)) prep(n.parentNode || document); else prep(n); } }); }); }).observe(document.documentElement, { childList: true, subtree: true });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();
