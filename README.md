# 📦 DevCas – SEO & Admin Toolkit

**Auteur:** [Castaar – Alec Meganck & Robbe Cooman](https://castaar.com)
**Versie:** 1.1.1
**Plugin URL:** [GitHub Repository](https://github.com/NeZotVanCastaar/devcas)

---

## 🔍 Overzicht

**DevCas** is een krachtige WordPress plugin ontwikkeld door [Castaar](https://castaar.com), die handige SEO-tools, bulk bewerkers en extra functionaliteit toevoegt voor webdevelopers & digital marketeers.

---

## ⚙️ Functionaliteit

### ✅ SEO Meta & Score Checker

- Extra metabox bij elk bericht/pagina
- Invoer voor:

  - Meta Title
  - Meta Description
  - Hoofd Keyword + 4 extra keywords

- Live SEO-score met checklist op 20 punten
- Keyword-analyse op basis van content
- Automatische injectie van meta tags in `<head>`
- Structured data (`JSON-LD`) wordt gegenereerd

### 🧠 ALT-tag Generator

- Automatisch gegenereerde alt-tags bij upload
- Bulkpagina voor retroactieve alt-tag generatie
- Slimme opschoning op basis van bestandsnamen

### 🧰 Bulk SEO Editor

- Pagina in Media-sectie voor bewerken van titels en alt-tags van afbeeldingen in bulk

### 🔒 Noindex Toggle

- Side metabox om “noindex” toe te voegen per post/pagina
- Kolomweergave in admin + quick edit ondersteuning

### 🎛️ SEO Score Kolom

- SEO Score badge in de WordPress admin lijstweergave
- Toont keyword, aantal interne/externe/media links

### 🎨 Admin Branding

- Gouden Castaar-styling in admin dashboard
- Aangepaste loginpagina
- Aangepaste footer en adminbar met logo
- Dashboard-widget met contactgegevens
- Minimalistische UI (verbergt standaardwidgets)

### 🌐 Sitemapbeheer

- Selecteerbare post types en taxonomieën
- Compatibel met WPML
- Respecteert handmatige noindex-instellingen

### 🤠 MHSM Snippets

- Custom post type om code in te voegen (HTML, CSS, JS, PHP)
- Injectie in header, body of footer
- Conditieveld per snippet (bijv. alleen op specifieke pagina)
- Safe eval voor PHP met debug logging

### 🤝 Extra’s

- SVG-upload ondersteuning + veilige preview
- Automatische `AOS` animatie scripts/styles
- Shortcode `[year]` ➔ huidig jaar
- `pagina_editor` gebruikersrol met uitgebreide rechten
- Drag & drop ordering van alle post types (menu_order)
- Drafts automatisch verborgen uit navigatie
- "Dupliceren" link in de lijstweergave voor snelle contentduplicatie

---

## 🚀 Installatie

1. Download of clone deze repository:

   ```bash
   git clone https://github.com/NeZotVanCastaar/devcas.git
   ```

2. Plaats de map `devcas/` in de map `wp-content/plugins/`
3. Activeer de plugin via het WordPress dashboard

---

## 🧪 Updater (GitHub)

Deze plugin maakt gebruik van [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) voor automatische updates via GitHub.

- Zorg ervoor dat de branch `main` actief is
- Er is **geen token** vereist voor publieke toegang

Voorbeeld implementatie:

```php
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
  'https://example.com/devcas-update.json',
  __FILE__,
  'devcas'
);
```

---

## 👋 Contact

- 🌐 [castaar.com](https://castaar.com)
- 📧 [info@castaar.com](mailto:info@castaar.com)
- 📞 +32 (0)54 255 178
