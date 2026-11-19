/* PC1PM – media op de Software-pagina
 *  1. Korte clips   <video data-clip muted loop playsinline preload="none" poster="…">  spelen alleen als ze in beeld zijn.
 *  2. Lightbox      <a href="groot.webp" data-lightbox="groep"><img src="thumb.webp" alt="…"></a>
 *  3. YouTube-gevel <button class="yt" data-yt="VIDEO_ID" data-title="…"><img src="poster.jpg" alt=""></button>
 *                   Laadt de (cookievrije) YouTube-speler pas na een klik.
 */
(function () {
  "use strict";
  var nl = (document.documentElement.lang || "nl").slice(0, 2) !== "en";
  var L = nl
    ? { close: "Sluiten", prev: "Vorige", next: "Volgende", play: "Video afspelen", of: "van" }
    : { close: "Close", prev: "Previous", next: "Next", play: "Play video", of: "of" };
  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- 1. clips ---------- */
  function initClips() {
    var clips = document.querySelectorAll("video[data-clip]");
    if (!clips.length) return;
    if (reduceMotion || !("IntersectionObserver" in window)) {
      clips.forEach(function (v) { v.controls = true; });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        var v = e.target;
        if (e.isIntersecting) {
          if (v.preload === "none") v.preload = "auto";
          var p = v.play();
          if (p && p.catch) p.catch(function () { v.controls = true; });
        } else {
          v.pause();
        }
      });
    }, { threshold: 0.4 });
    clips.forEach(function (v) { io.observe(v); });
  }

  /* ---------- 2. lightbox ---------- */
  function initLightbox() {
    var links = Array.prototype.slice.call(document.querySelectorAll("a[data-lightbox]"));
    if (!links.length || typeof HTMLDialogElement === "undefined") return;

    var dlg = document.createElement("dialog");
    dlg.className = "lb";
    dlg.innerHTML =
      '<figure class="lb__fig"><img class="lb__img" alt=""><figcaption class="lb__cap"></figcaption></figure>' +
      '<button class="lb__btn lb__close" type="button" aria-label="' + L.close + '">×</button>' +
      '<button class="lb__btn lb__prev" type="button" aria-label="' + L.prev + '">‹</button>' +
      '<button class="lb__btn lb__next" type="button" aria-label="' + L.next + '">›</button>';
    document.body.appendChild(dlg);
    var img = dlg.querySelector(".lb__img"), cap = dlg.querySelector(".lb__cap");
    var group = [], idx = 0;

    function show(i) {
      idx = (i + group.length) % group.length;
      var a = group[idx], t = a.querySelector("img");
      img.src = a.getAttribute("href");
      img.alt = t ? t.alt : "";
      cap.textContent = (t && t.alt ? t.alt : "") + (group.length > 1 ? "  ·  " + (idx + 1) + " " + L.of + " " + group.length : "");
      dlg.classList.toggle("lb--single", group.length < 2);
    }
    links.forEach(function (a) {
      a.addEventListener("click", function (ev) {
        ev.preventDefault();
        var name = a.getAttribute("data-lightbox");
        group = links.filter(function (x) { return x.getAttribute("data-lightbox") === name; });
        show(group.indexOf(a));
        dlg.showModal();
      });
    });
    dlg.querySelector(".lb__close").addEventListener("click", function () { dlg.close(); });
    dlg.querySelector(".lb__prev").addEventListener("click", function () { show(idx - 1); });
    dlg.querySelector(".lb__next").addEventListener("click", function () { show(idx + 1); });
    dlg.addEventListener("click", function (ev) { if (ev.target === dlg) dlg.close(); });
    dlg.addEventListener("keydown", function (ev) {
      if (ev.key === "ArrowLeft") show(idx - 1);
      if (ev.key === "ArrowRight") show(idx + 1);
    });
    dlg.addEventListener("close", function () { img.removeAttribute("src"); });
  }

  /* ---------- 3. YouTube-gevel ---------- */
  function initYouTube() {
    document.querySelectorAll(".yt[data-yt]").forEach(function (btn) {
      btn.setAttribute("aria-label", L.play + ": " + (btn.getAttribute("data-title") || "YouTube"));
      btn.addEventListener("click", function () {
        var id = btn.getAttribute("data-yt").replace(/[^\w-]/g, "");
        var f = document.createElement("iframe");
        f.className = "yt__frame";
        f.src = "https://www.youtube-nocookie.com/embed/" + id + "?autoplay=1&rel=0&modestbranding=1";
        f.title = btn.getAttribute("data-title") || "YouTube";
        f.allow = "autoplay; encrypted-media; picture-in-picture; fullscreen";
        f.allowFullscreen = true;
        f.referrerPolicy = "strict-origin-when-cross-origin";
        btn.replaceWith(f);
      });
    });
  }

  function init() { initClips(); initLightbox(); initYouTube(); }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
