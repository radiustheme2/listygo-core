# Listygo Core Plugin — Translation Reference

## Overview

- **Text domain**: `listygo-core`
- **Type**: WordPress Plugin
- **Languages directory**: `languages/`
- **POT file**: `languages/listygo-core.pot`
- **Total translatable strings**: ~1446
- **Supported languages**: 15

## Supported Languages

| Code    | Language   | PO File                  | MO File                  | L10n PHP File                  |
|---------|------------|--------------------------|--------------------------|--------------------------------|
| es_ES   | Spanish    | listygo-core-es_ES.po    | listygo-core-es_ES.mo    | listygo-core-es_ES.l10n.php    |
| fr_FR   | French     | listygo-core-fr_FR.po    | listygo-core-fr_FR.mo    | listygo-core-fr_FR.l10n.php    |
| de_DE   | German     | listygo-core-de_DE.po    | listygo-core-de_DE.mo    | listygo-core-de_DE.l10n.php    |
| pt_BR   | Portuguese | listygo-core-pt_BR.po    | listygo-core-pt_BR.mo    | listygo-core-pt_BR.l10n.php    |
| it_IT   | Italian    | listygo-core-it_IT.po    | listygo-core-it_IT.mo    | listygo-core-it_IT.l10n.php    |
| nl_NL   | Dutch      | listygo-core-nl_NL.po    | listygo-core-nl_NL.mo    | listygo-core-nl_NL.l10n.php    |
| ru_RU   | Russian    | listygo-core-ru_RU.po    | listygo-core-ru_RU.mo    | listygo-core-ru_RU.l10n.php    |
| ja      | Japanese   | listygo-core-ja.po       | listygo-core-ja.mo       | listygo-core-ja.l10n.php       |
| zh_CN   | Chinese    | listygo-core-zh_CN.po    | listygo-core-zh_CN.mo    | listygo-core-zh_CN.l10n.php    |
| ar      | Arabic     | listygo-core-ar.po       | listygo-core-ar.mo       | listygo-core-ar.l10n.php       |
| fa_IR   | Persian    | listygo-core-fa_IR.po    | listygo-core-fa_IR.mo    | listygo-core-fa_IR.l10n.php    |
| ur      | Urdu       | listygo-core-ur.po       | listygo-core-ur.mo       | listygo-core-ur.l10n.php       |
| he_IL   | Hebrew     | listygo-core-he_IL.po    | listygo-core-he_IL.mo    | listygo-core-he_IL.l10n.php    |
| hi_IN   | Hindi      | listygo-core-hi_IN.po    | listygo-core-hi_IN.mo    | listygo-core-hi_IN.l10n.php    |
| bn_BD   | Bengali    | listygo-core-bn_BD.po    | listygo-core-bn_BD.mo    | listygo-core-bn_BD.l10n.php    |

## Translation File Types

| Extension   | Purpose                                         |
|-------------|------------------------------------------------|
| `.pot`      | Template — master list of all translatable strings |
| `.po`       | Portable Object — human-readable translations    |
| `.mo`       | Machine Object — compiled binary for WordPress   |
| `.l10n.php` | PHP array — WordPress 6.5+ fast loading via OPcache |

## Domain-Specific Terminology

| English Term     | Context                                              |
|-----------------|------------------------------------------------------|
| Listing          | A classified ad or directory post                    |
| Store            | A seller's or agent's shop/profile page              |
| Booking          | Reservation or appointment for a listing             |
| Agent            | Real estate agent or service provider                |
| Category         | Listing classification group                         |
| Location         | Geographic area for a listing                        |
| Custom Field     | Additional data field on a listing                   |
| Membership       | Subscription plan for sellers                        |
| Badge            | Visual indicator (e.g., "Featured", "New")           |
| Elementor        | Page builder plugin (keep as-is, never translate)    |
| Widget           | Elementor or WordPress widget component              |

## PO File Rules

- **Escape sequences**: Use `\"` for quotes inside `msgstr`, `\\n` for literal newline display
- **Placeholders**: Keep `%s`, `%d`, `%1$s`, `%2$s` exactly as in `msgid`
- **HTML**: Preserve all HTML tags and attributes
- **Punctuation**: Match trailing periods, colons, ellipsis from source string
- **Context**: `msgctxt` lines must NOT be translated — they are lookup keys
- **Plurals**: Use `msgstr[0]` for singular, `msgstr[1]` for plural (varies by language)

## RTL Languages

Arabic (`ar`), Persian (`fa_IR`), Urdu (`ur`), and Hebrew (`he_IL`) are RTL languages. WordPress handles text direction automatically.

## Plural Forms by Language

| Language(s)                                                          | Plural-Forms |
|----------------------------------------------------------------------|--------------|
| Spanish, French, Italian, Portuguese, German, Dutch, Persian, Urdu, Hindi, Bengali, Hebrew | `nplurals=2; plural=(n != 1);` |
| Russian                                                                | `nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 \|\| n%100>14) ? 1 : 2);` |
| Japanese, Chinese                                                      | `nplurals=1; plural=0;` |
| Arabic                                                                 | `nplurals=2; plural=(n != 1);` |