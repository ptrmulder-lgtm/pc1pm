/* PC1PM – Ruimteweer & propagatie-widget
 * Live data van NOAA Space Weather Prediction Center (services.swpc.noaa.gov, CORS open).
 * Gebruik: <div class="sw" data-sw data-map="img/web/world-land.svg"></div>
 * Taal volgt <html lang="nl|en">. Ververst elke 5 minuten; de dag/nachtkaart elke minuut.
 */
(function () {
  "use strict";

  var API = "https://services.swpc.noaa.gov/";
  var HOME = { lat: 52.1, lon: 5.3 }; // midden van Nederland (geen exacte QTH)
  var REFRESH_MS = 5 * 60 * 1000;

  var T = {
    nl: {
      title: "Ruimteweer & propagatie",
      live: "live",
      updated: "Bijgewerkt",
      loading: "Gegevens ophalen…",
      error: "Kon de gegevens nu niet ophalen. Probeer het later opnieuw.",
      map: "Dag, nacht en grijze lijn",
      mapNote: "Oranje band: de grijze lijn (schemering), vaak goed voor DX.",
      sfi: "Zonneflux (SFI)",
      ssn: "Zonnevlekken",
      kp: "Kp-index",
      a: "A-index",
      xray: "Röntgen",
      wind: "Zonnewind",
      bz: "Bz / Bt",
      scales: "NOAA-schalen",
      days30: "30 dagen",
      days7: "Kp afgelopen 7 dagen",
      hours6: "6 uur",
      now: "nu",
      bands: "HF-banden (schatting)",
      band: "Band",
      day: "Dag",
      night: "Nacht",
      good: "Goed",
      fair: "Matig",
      poor: "Slecht",
      bandNote: "Schatting op basis van zonneflux en Kp-index, voor Europa. Een kolom is gemarkeerd als het daar in Nederland nu dag of nacht is.",
      source: "Bron: NOAA Space Weather Prediction Center",
      kpText: ["rustig", "rustig", "rustig", "licht onrustig", "onrustig", "storm (G1)", "storm (G2)", "sterke storm (G3)", "zware storm (G4)", "extreme storm (G5)"],
      sfiText: function (v) { return v < 80 ? "laag" : v < 110 ? "gemiddeld" : v < 150 ? "goed" : v < 200 ? "zeer goed" : "uitstekend"; },
      summary: function (s) {
        var hi = s.sfi >= 150 ? "Hoge banden (15–10 m) staan wijd open" : s.sfi >= 110 ? "Hoge banden gaan overdag open" : s.sfi >= 85 ? "Vooral 40–20 m is goed bruikbaar" : "Lage zonneactiviteit: houd het bij 80–20 m";
        var geo = s.kp >= 5 ? ", maar een geomagnetische storm verstoort de verbindingen." : s.kp >= 4 ? ", al is het aardmagnetisch veld wat onrustig." : " en het aardmagnetisch veld is rustig.";
        return hi + geo;
      },
      dayHere: "In Nederland is het nu dag",
      nightHere: "In Nederland is het nu nacht",
      sun: "Zon in het zenit",
      kmS: "km/s"
    },
    en: {
      title: "Space weather & propagation",
      live: "live",
      updated: "Updated",
      loading: "Fetching data…",
      error: "Could not fetch the data right now. Please try again later.",
      map: "Day, night and grey line",
      mapNote: "Orange band: the grey line (twilight), often good for DX.",
      sfi: "Solar flux (SFI)",
      ssn: "Sunspot number",
      kp: "Kp index",
      a: "A index",
      xray: "X-ray",
      wind: "Solar wind",
      bz: "Bz / Bt",
      scales: "NOAA scales",
      days30: "30 days",
      days7: "Kp last 7 days",
      hours6: "6 hours",
      now: "now",
      bands: "HF bands (estimate)",
      band: "Band",
      day: "Day",
      night: "Night",
      good: "Good",
      fair: "Fair",
      poor: "Poor",
      bandNote: "Estimate based on solar flux and Kp index, for Europe. A column is highlighted if it is currently day or night in the Netherlands.",
      source: "Source: NOAA Space Weather Prediction Center",
      kpText: ["quiet", "quiet", "quiet", "unsettled", "active", "storm (G1)", "storm (G2)", "strong storm (G3)", "severe storm (G4)", "extreme storm (G5)"],
      sfiText: function (v) { return v < 80 ? "low" : v < 110 ? "moderate" : v < 150 ? "good" : v < 200 ? "very good" : "excellent"; },
      summary: function (s) {
        var hi = s.sfi >= 150 ? "The high bands (15–10 m) are wide open" : s.sfi >= 110 ? "The high bands open up during the day" : s.sfi >= 85 ? "40–20 m is the place to be" : "Low solar activity: stick to 80–20 m";
        var geo = s.kp >= 5 ? ", but a geomagnetic storm is disturbing propagation." : s.kp >= 4 ? ", although the geomagnetic field is somewhat unsettled." : " and the geomagnetic field is quiet.";
        return hi + geo;
      },
      dayHere: "It is daytime in the Netherlands",
      nightHere: "It is night-time in the Netherlands",
      sun: "Subsolar point",
      kmS: "km/s"
    }
  };

  /* ---------- helpers ---------- */
  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }
  function getJSON(path) {
    return fetch(API + path, { cache: "no-store" }).then(function (r) {
      if (!r.ok) throw new Error(r.status);
      return r.json();
    });
  }
  function getText(path) {
    return fetch(API + path, { cache: "no-store" }).then(function (r) {
      if (!r.ok) throw new Error(r.status);
      return r.text();
    });
  }
  function settle(p) {
    return p.then(function (v) { return v; }, function () { return null; });
  }
  function last(arr) { return arr && arr.length ? arr[arr.length - 1] : null; }
  function fmtTime(d, lang) {
    return d.toLocaleString(lang === "nl" ? "nl-NL" : "en-GB", { hour: "2-digit", minute: "2-digit", day: "numeric", month: "short" });
  }
  function utc(s) { return new Date(/Z$/.test(s) ? s : s + "Z"); }

  function xrayClass(flux) {
    if (!(flux > 0)) return "–";
    var classes = [["X", 1e-4], ["M", 1e-5], ["C", 1e-6], ["B", 1e-7], ["A", 1e-8]];
    for (var i = 0; i < classes.length; i++) {
      if (flux >= classes[i][1]) return classes[i][0] + (flux / classes[i][1]).toFixed(1);
    }
    return "A" + (flux / 1e-8).toFixed(1);
  }
  function kpLevel(kp) { return kp >= 5 ? "bad" : kp >= 4 ? "warn" : "ok"; }

  /* ---------- sparkline / bars (inline SVG) ---------- */
  function sparkline(values, opts) {
    opts = opts || {};
    var w = 200, h = 44, pad = 3;
    var vals = values.filter(function (v) { return v != null && isFinite(v); });
    if (vals.length < 2) return "";
    var tx = opts.log ? function (v) { return Math.log10(v); } : function (v) { return v; };
    var tv = vals.map(tx);
    var min = Math.min.apply(null, tv), max = Math.max.apply(null, tv);
    if (max === min) { max += 1; min -= 1; }
    var pts = tv.map(function (v, i) {
      return [pad + (i * (w - 2 * pad)) / (tv.length - 1), h - pad - ((v - min) * (h - 2 * pad)) / (max - min)];
    });
    var line = pts.map(function (p, i) { return (i ? "L" : "M") + p[0].toFixed(1) + " " + p[1].toFixed(1); }).join("");
    var area = line + "L" + pts[pts.length - 1][0].toFixed(1) + " " + h + "L" + pts[0][0].toFixed(1) + " " + h + "Z";
    var lp = pts[pts.length - 1];
    return '<svg class="sw-spark" viewBox="0 0 ' + w + " " + h + '" preserveAspectRatio="none" aria-hidden="true">' +
      '<path d="' + area + '" class="sw-spark__area"/><path d="' + line + '" class="sw-spark__line" vector-effect="non-scaling-stroke"/>' +
      '<circle cx="' + lp[0].toFixed(1) + '" cy="' + lp[1].toFixed(1) + '" r="2.6" class="sw-spark__dot"/></svg>';
  }

  function kpBars(rows) {
    var w = 560, h = 90, gap = 1.5;
    var n = rows.length;
    if (!n) return "";
    var bw = (w - gap * (n - 1)) / n;
    var s = '<svg class="sw-kpbars" viewBox="0 0 ' + w + " " + (h + 14) + '" preserveAspectRatio="none" role="img" aria-label="Kp">';
    [3, 5, 7].forEach(function (k) {
      var y = h - (k / 9) * h;
      s += '<line x1="0" x2="' + w + '" y1="' + y + '" y2="' + y + '" class="sw-kpbars__grid"/>';
    });
    var lastDay = null;
    rows.forEach(function (r, i) {
      var kp = Math.max(r.Kp, 0.15);
      var bh = (kp / 9) * h;
      var x = i * (bw + gap);
      s += '<rect x="' + x.toFixed(1) + '" y="' + (h - bh).toFixed(1) + '" width="' + bw.toFixed(1) + '" height="' + bh.toFixed(1) + '" class="sw-kp--' + kpLevel(r.Kp) + '"><title>' + r.time_tag.replace("T", " ").slice(0, 16) + " UTC · Kp " + r.Kp.toFixed(2) + "</title></rect>";
      var day = r.time_tag.slice(8, 10);
      if (day !== lastDay && r.time_tag.slice(11, 13) === "00") {
        s += '<text x="' + (x + 1).toFixed(1) + '" y="' + (h + 12) + '" class="sw-kpbars__label">' + Number(day) + "</text>";
      }
      lastDay = day;
    });
    return s + "</svg>";
  }

  /* ---------- zonnestand & kaart ---------- */
  function sunPosition(date) {
    var rad = Math.PI / 180;
    var d = date.getTime() / 86400000 - 10957.5; // dagen sinds J2000.0
    var g = (357.529 + 0.98560028 * d) * rad;
    var q = 280.459 + 0.98564736 * d;
    var L = (q + 1.915 * Math.sin(g) + 0.02 * Math.sin(2 * g)) * rad;
    var e = (23.439 - 0.00000036 * d) * rad;
    var ra = Math.atan2(Math.cos(e) * Math.sin(L), Math.cos(L)) / rad;
    var dec = Math.asin(Math.sin(e) * Math.sin(L)) / rad;
    var gmst = (18.697374558 + 24.06570982441908 * d) % 24;
    var lon = ra - gmst * 15;
    lon = ((lon + 540) % 360) - 180;
    return { lat: dec, lon: lon };
  }
  function sunAltitude(lat, lon, sun) {
    var rad = Math.PI / 180;
    return Math.asin(
      Math.sin(lat * rad) * Math.sin(sun.lat * rad) +
      Math.cos(lat * rad) * Math.cos(sun.lat * rad) * Math.cos((lon - sun.lon) * rad)
    ) / rad;
  }

  function drawMap(canvas, sun) {
    var W = canvas.width, H = canvas.height;
    var ctx = canvas.getContext("2d");
    var img = ctx.createImageData(W, H);
    var data = img.data;
    for (var y = 0; y < H; y++) {
      var lat = 90 - ((y + 0.5) * 180) / H;
      for (var x = 0; x < W; x++) {
        var lon = ((x + 0.5) * 360) / W - 180;
        var alt = sunAltitude(lat, lon, sun);
        var i = (y * W + x) * 4;
        var a = 0, r = 6, g = 10, b = 20;
        if (alt < 0) a = Math.min(1, (-alt + 0.3) / 12) * 0.7;
        var gl = Math.abs(alt) < 4 ? 1 - Math.abs(alt) / 4 : 0; // grijze lijn
        if (gl > 0) {
          var ga = gl * gl * 0.42;
          r = (r * a + 245 * ga) / (a + ga || 1);
          g = (g * a + 165 * ga) / (a + ga || 1);
          b = (b * a + 36 * ga) / (a + ga || 1);
          a = Math.min(1, a + ga);
        }
        data[i] = r; data[i + 1] = g; data[i + 2] = b; data[i + 3] = a * 255;
      }
    }
    ctx.putImageData(img, 0, 0);
  }

  function placeMarker(node, lat, lon) {
    node.style.left = ((lon + 180) / 360) * 100 + "%";
    node.style.top = ((90 - lat) / 180) * 100 + "%";
  }

  /* ---------- bandcondities (heuristiek) ---------- */
  function bandConditions(sfi, kp) {
    function lvl(n) { return Math.max(0, Math.min(2, n)); } // 2 goed, 1 matig, 0 slecht
    var storm = kp >= 7 ? 2 : kp >= 5 ? 1 : 0;
    var unsettled = kp >= 4 ? 1 : 0;
    var rows = [
      { band: "160 m", day: 0, night: kp <= 2 ? 2 : kp <= 4 ? 1 : 0 },
      { band: "80 m", day: 1, night: 2 },
      { band: "40 m", day: 2, night: 2 },
      { band: "30 m", day: 2, night: sfi >= 100 ? 2 : 1 },
      { band: "20 m", day: sfi >= 85 ? 2 : 1, night: sfi >= 130 ? 2 : sfi >= 95 ? 1 : 0 },
      { band: "17 m", day: sfi >= 110 ? 2 : sfi >= 85 ? 1 : 0, night: sfi >= 170 ? 1 : 0 },
      { band: "15 m", day: sfi >= 125 ? 2 : sfi >= 95 ? 1 : 0, night: sfi >= 190 ? 1 : 0 },
      { band: "12 m", day: sfi >= 145 ? 2 : sfi >= 110 ? 1 : 0, night: 0 },
      { band: "10 m", day: sfi >= 160 ? 2 : sfi >= 120 ? 1 : 0, night: 0 }
    ];
    rows.forEach(function (r) {
      // geomagnetische onrust treft vooral de hogere en de nachtelijke paden
      r.day = lvl(r.day - storm);
      r.night = lvl(r.night - storm - unsettled);
    });
    return rows;
  }

  /* ---------- opbouw ---------- */
  function build(root) {
    var lang = (document.documentElement.lang || "nl").slice(0, 2) === "en" ? "en" : "nl";
    var t = T[lang];
    var mapSrc = root.getAttribute("data-map") || "img/web/world-land.svg";

    root.innerHTML =
      '<div class="sw-head">' +
        '<div><h3 class="sw-title">' + t.title + '</h3><p class="sw-summary" data-f="summary">' + t.loading + "</p></div>" +
        '<p class="sw-live"><span class="sw-live__dot"></span>' + t.live + ' · <span data-f="updated">–</span></p>' +
      "</div>" +
      '<div class="sw-grid">' +
        '<figure class="sw-map">' +
          '<figcaption><span>' + t.map + '</span><span class="sw-map__here" data-f="here"></span></figcaption>' +
          '<div class="sw-map__frame">' +
            '<img src="' + mapSrc + '" alt="" width="720" height="360">' +
            '<canvas width="720" height="360" aria-hidden="true"></canvas>' +
            '<span class="sw-sun" title="' + t.sun + '"></span>' +
            '<span class="sw-home" title="PC1PM"><span>PC1PM</span></span>' +
          "</div>" +
          '<p class="sw-note">' + t.mapNote + "</p>" +
        "</figure>" +
        '<div class="sw-tiles">' +
          tile("sfi", t.sfi, t.days30) + tile("ssn", t.ssn) + tile("kp", t.kp) + tile("a", t.a) +
          tile("xray", t.xray, t.hours6) + tile("wind", t.wind) + tile("bz", t.bz) + tile("scales", t.scales) +
        "</div>" +
        '<div class="sw-kp">' +
          '<p class="sw-label">' + t.days7 + "</p><div data-f=\"kpbars\"></div>" +
        "</div>" +
        '<div class="sw-bands">' +
          '<p class="sw-label">' + t.bands + "</p>" +
          '<table><thead><tr><th>' + t.band + '</th><th data-col="day">' + t.day + '</th><th data-col="night">' + t.night + "</th></tr></thead><tbody data-f=\"bands\"></tbody></table>" +
          '<p class="sw-note">' + t.bandNote + "</p>" +
        "</div>" +
      "</div>" +
      '<p class="sw-source"><a href="https://www.swpc.noaa.gov/" rel="noopener">' + t.source + "</a></p>";

    function tile(key, label, sub) {
      return '<div class="sw-tile" data-tile="' + key + '"><p class="sw-label">' + label + (sub ? " <span>· " + sub + "</span>" : "") +
        '</p><p class="sw-value" data-f="' + key + '">–</p><p class="sw-sub" data-f="' + key + 'Sub"></p><div data-f="' + key + 'Chart"></div></div>';
    }
    function f(name) { return root.querySelector('[data-f="' + name + '"]'); }
    function set(name, html) { var n = f(name); if (n) n.innerHTML = html; }

    var canvas = root.querySelector("canvas");
    var sunEl = root.querySelector(".sw-sun");
    placeMarker(root.querySelector(".sw-home"), HOME.lat, HOME.lon);

    var state = { sfi: null, kp: null, isDay: true };

    function updateMap() {
      var sun = sunPosition(new Date());
      drawMap(canvas, sun);
      placeMarker(sunEl, sun.lat, sun.lon);
      state.isDay = sunAltitude(HOME.lat, HOME.lon, sun) > -0.8;
      set("here", state.isDay ? t.dayHere : t.nightHere);
      root.querySelectorAll("[data-col]").forEach(function (th) {
        th.classList.toggle("is-now", th.getAttribute("data-col") === (state.isDay ? "day" : "night"));
      });
      renderBands();
    }

    function renderBands() {
      if (state.sfi == null || state.kp == null) return;
      var names = [t.poor, t.fair, t.good], cls = ["poor", "fair", "good"];
      var col = state.isDay ? "day" : "night";
      set("bands", bandConditions(state.sfi, state.kp).map(function (r) {
        return "<tr><td>" + r.band + "</td>" +
          ["day", "night"].map(function (c) {
            return '<td class="' + (c === col ? "is-now" : "") + '"><span class="sw-pill sw-pill--' + cls[r[c]] + '">' + names[r[c]] + "</span></td>";
          }).join("") + "</tr>";
      }).join(""));
    }

    function load() {
      Promise.all([
        settle(getJSON("products/10cm-flux-30-day.json")),
        settle(getText("text/daily-solar-indices.txt")),
        settle(getJSON("products/noaa-planetary-k-index.json")),
        settle(getJSON("json/planetary_k_index_1m.json")),
        settle(getJSON("json/goes/primary/xrays-6-hour.json")),
        settle(getJSON("products/summary/solar-wind-speed.json")),
        settle(getJSON("products/summary/solar-wind-mag-field.json")),
        settle(getJSON("products/noaa-scales.json"))
      ]).then(function (res) {
        var sfi30 = res[0], dsd = res[1], kp3h = res[2], kp1m = res[3], xr = res[4], wind = res[5], mag = res[6], scales = res[7];
        var got = 0;

        if (sfi30 && sfi30.length) {
          got++;
          var sfiNow = last(sfi30).flux;
          state.sfi = sfiNow;
          set("sfi", sfiNow);
          set("sfiSub", t.sfiText(sfiNow));
          set("sfiChart", sparkline(sfi30.map(function (r) { return r.flux; })));
        }

        if (dsd) {
          var lines = dsd.split("\n").filter(function (l) { return /^\d{4} \d\d \d\d/.test(l); });
          var ssnSeries = lines.map(function (l) { var p = l.trim().split(/\s+/); return Number(p[4]); }).filter(function (v) { return v >= 0; });
          if (ssnSeries.length) {
            got++;
            set("ssn", last(ssnSeries));
            set("ssnChart", sparkline(ssnSeries));
            var dp = last(lines).trim().split(/\s+/);
            set("ssnSub", new Date(Date.UTC(+dp[0], +dp[1] - 1, +dp[2])).toLocaleDateString(lang === "nl" ? "nl-NL" : "en-GB", { day: "numeric", month: "short" }));
          }
        }

        if (kp1m && kp1m.length) {
          got++;
          var k = last(kp1m);
          var kpNow = k.estimated_kp != null ? k.estimated_kp : k.kp_index;
          state.kp = kpNow;
          set("kp", '<span class="sw-kp--' + kpLevel(kpNow) + '-text">' + kpNow.toFixed(1) + "</span>");
          set("kpSub", t.kpText[Math.min(9, Math.round(kpNow))]);
        }
        if (kp3h && kp3h.length) {
          got++;
          var rows = kp3h.map(function (r) { return { time_tag: r.time_tag, Kp: Number(r.Kp), a: Number(r.a_running) }; })
            .filter(function (r) { return isFinite(r.Kp); }).slice(-56);
          set("kpbars", kpBars(rows));
          var aNow = last(rows).a;
          set("a", isFinite(aNow) ? aNow : "–");
          set("aSub", aNow < 8 ? t.kpText[0] : aNow < 16 ? t.kpText[3] : aNow < 30 ? t.kpText[4] : t.kpText[5]);
          if (state.kp == null) { state.kp = last(rows).Kp; }
        }

        if (xr && xr.length) {
          var longCh = xr.filter(function (r) { return r.energy === "0.1-0.8nm"; });
          if (longCh.length) {
            got++;
            var fl = last(longCh).flux;
            var cls = xrayClass(fl);
            set("xray", '<span class="sw-xray sw-xray--' + cls.charAt(0) + '">' + cls + "</span>");
            set("xraySub", cls.charAt(0) === "M" || cls.charAt(0) === "X" ? (lang === "nl" ? "zonnevlam!" : "solar flare!") : (lang === "nl" ? "achtergrond" : "background"));
            set("xrayChart", sparkline(longCh.filter(function (r, i) { return i % 3 === 0; }).map(function (r) { return r.flux; }), { log: true }));
          }
        }

        if (wind && wind.length) {
          got++;
          var v = last(wind).proton_speed;
          set("wind", v + ' <small>' + t.kmS + "</small>");
          set("windSub", v > 600 ? (lang === "nl" ? "snel" : "fast") : v > 450 ? (lang === "nl" ? "verhoogd" : "elevated") : (lang === "nl" ? "normaal" : "normal"));
        }
        if (mag && mag.length) {
          got++;
          var m = last(mag);
          set("bz", '<span class="' + (m.bz_gsm <= -10 ? "sw-kp--bad-text" : m.bz_gsm < 0 ? "sw-kp--warn-text" : "") + '">' + (m.bz_gsm > 0 ? "+" : "") + m.bz_gsm + "</span> / " + m.bt + " <small>nT</small>");
          set("bzSub", m.bz_gsm < -5 ? (lang === "nl" ? "zuidwaarts: storingsgevoelig" : "southward: storm-prone") : (lang === "nl" ? "gunstig" : "favourable"));
        }

        if (scales && scales["0"]) {
          got++;
          var c = scales["0"];
          set("scales", ["R", "S", "G"].map(function (key) {
            var n = Number(c[key].Scale) || 0;
            return '<span class="sw-scale' + (n > 0 ? " is-active" : "") + '">' + key + n + "</span>";
          }).join(" "));
          set("scalesSub", lang === "nl" ? "radio · straling · geomagnetisch" : "radio · radiation · geomagnetic");
        }

        if (!got) {
          set("summary", t.error);
          root.classList.add("is-error");
          return;
        }
        root.classList.remove("is-error");
        if (state.sfi != null && state.kp != null) set("summary", t.summary(state));
        set("updated", fmtTime(new Date(), lang));
        renderBands();
      });
    }

    updateMap();
    load();
    setInterval(updateMap, 60 * 1000);
    setInterval(load, REFRESH_MS);
  }

  function init() {
    document.querySelectorAll("[data-sw]").forEach(build);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
