# pc1pm.nl – website zendamateur-call PC1PM

Gestart 19 november 2026. Eigenaar: Peter (PC1PM). Taal van site en communicatie: Nederlands.

## Website
- Eenvoudige **statische site (HTML/CSS)**, geen CMS, geen build-stap nodig.
- Peter bouwt met Claude Code in deze map. **Publiceren gaat via GitHub:** Peter commit en pusht met
  **GitHub Desktop** naar de privé-repo `ptrmulder-lgtm/pc1pm` (branch `main`); de Plesk Git-extensie
  haalt die (via deploy key + webhook) automatisch op en zet hem in de servermap `pc1pm.nl`
  (`/var/www/vhosts/mulderz.nl/pc1pm.nl`). Claude commit/pusht niet zelf, tenzij Peter daarom vraagt.
- Omdat de hele repo in de webmap komt, schermt `.htaccess` werkbestanden af (`CLAUDE.md`, `tools/`,
  alles wat met een punt begint). Nieuwe niet-website-bestanden daar ook aan toevoegen.
- Alleen relatieve paden gebruiken, zodat de map 1-op-1 geüpload kan worden.
- Geen e-mail op dit domein: geen `mailto:@pc1pm.nl`-adressen of contactformulier dat mail verstuurt.
- Opbouw: `index.html` (home) en `hobby.html` (uitleg hobby, live condities, QRZ-logboek-iframe),
  `css/style.css`, `favicon.svg`, foto's in `img/web/`.
- **Tweetalig:** Nederlands in de root, Engels in `en/` (`en/index.html`, `en/hobby.html`, paden met `../`).
  Elke pagina heeft een NL- én EN-versie; wijzig ze altijd samen. De NL|EN-schakelaar linkt naar de
  tegenhanger van dezelfde pagina; ook `hreflang`-links in de `<head>` bijwerken bij nieuwe pagina's.
- Peter wil geen contest-tijdlijn en geen kerncijfers-blok (DXCC/2016/3×) op de homepage (bewust verwijderd).
- Hoofdfoto = `img/hoofdfoto1.png` (door Peter bewerkt aangeleverd); het script snijdt daar alleen hero,
  portret en OG-beeld uit, zonder extra kleurcorrectie.
- Originele foto's staan in `img/` (alleen-lezen). `python tools/process_photos.py` maakt daar de webversies
  in `img/web/` van: bijsnijden, kleur/contrast, geen EXIF/GPS. De originelen in `img/` staan in `.gitignore`
  en gaan dus niet naar GitHub/de server.
- Lokaal bekijken: `python -m http.server 8611` (staat in `.claude/launch.json`).
- Inhoud komt van de QRZ-pagina van PC1PM (bio, station, contesten) en de eigen foto's.

## Hosting
- Cloud86, zelfde Plesk-pakket als mulderz.nl; pc1pm.nl is een extra domein (map naast `httpdocs`).
- HTTP→HTTPS-redirect staat aan.
- **Nog doen:** Let's Encrypt-certificaat (incl. www) aanvragen zodra het domein in DNS zichtbaar is.

## Registratie & DNS
- Geregistreerd 19-11-2026 bij mijn.host (onafhankelijke registrar; mulderz.nl gaat hier later ook naartoe).
- Nameservers bij mijn.host (ns1/ns2/ns3.mijn.host), DNSSEC aan. TTL 15 min.
- Records:
  - `@` A `45.82.190.35` (Cloud86)
  - `www` CNAME `pc1pm.nl`
  - `@` TXT `v=spf1 -all` (geen mail)
  - `_dmarc` TXT `v=DMARC1; p=reject; sp=reject;`
  - Parkeerrecords van mijn.host (`*` A/AAAA, `@` AAAA, MX) verwijderd.
- 19-11 11:15: nog NXDOMAIN bij SIDN – normaal kort na registratie, afwachten.
