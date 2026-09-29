# Notice Board — External / Member-Facing Display Design

This is a design proposal only — nothing here is built yet. It covers a **new, separate screen** from the back-office Notice Board you already have. That back-office screen (the admin CRUD table under Secretarial Management) keeps its existing rules untouched: no scrolling, one standard font, one standard colour, Prev/Next paging. This document is about a different destination entirely — what a **member or visitor** sees when they open the notice board to read what's happening at their temple, NGO, club, or company.

Because the audience is different (reading on a phone, a lobby TV screen, or a public page — not an office worker doing data entry), this screen is allowed to look and behave differently: it can scroll, it can be visually rich, and it can change its background theme by community type.

## 1. Who sees this, and where

- A member opens "View Notice Board" from their own portal (mobile-friendly, scrollable).
- Optionally, the same screen could run full-screen on a lobby TV/tablet at the temple or office entrance, auto-rotating through notices.
- Read-only. Posting/editing stays exclusively in the back-office screen, restricted to officers/Secretary as already built.

## 2. Theme system — one background per community type

Every CBE entity already has an admin-editable "Faith/Practice Type" on its Group Name screen (Temple, Church, Mosque, Cooperative, Club, SME, etc. — never hardcoded). This design reuses that same field to pick a **visual theme** for the notice board background, so a temple and a company don't look identical, but nothing about the underlying data or layout changes between them — only the background art direction.

Proposed starter themes (all abstract/geometric, not tied to any single religion's specific iconography, since "Temple" alone covers many different faiths):

| Theme | Palette | Motif | Feel |
|---|---|---|---|
| **Sacred Space** (temple/church/mosque/any faith community) | Deep maroon + gold accents, warm ivory card background | Soft radiating lotus/mandala-inspired linework, very abstract, low-opacity | Calm, respectful, warm |
| **Community Hall** (NGO/club/cooperative/Rotary-style) | Teal + coral accent, clean white cards | Overlapping circles / interlocking rings, subtle | Friendly, civic, energetic |
| **Corporate** (SME/company) | Navy + slate blue, crisp white cards | Fine geometric grid / subtle hexagon lattice | Professional, modern |
| **Default / Unthemed** | Same neutral palette used in the back office | No motif — flat colour | Safe fallback for any entity that hasn't set a Faith/Practice Type yet |

Each theme is just a background image + two accent colours behind an otherwise identical card layout — the actual notice content, fonts for reading, and card structure stay the same across all themes, so nothing about legibility or consistency is lost.

## 3. Layout

```
┌─────────────────────────────────────────────┐
│  [Entity name + logo]     "What's Happening" │   ← full-width themed header band
├─────────────────────────────────────────────┤
│  📌 PINNED / FEATURED NOTICE (large card)     │
│     Title · category tag · short excerpt      │
├───────────────┬───────────────┬───────────────┤
│  Notice card  │  Notice card  │  Notice card  │   ← responsive grid, 1 column on
│  category tag │  category tag │  category tag │     phone, 2–3 on tablet/TV
│  title        │  title        │  title        │
│  excerpt      │  excerpt      │  excerpt      │
│  expires: ... │  expires: ... │  expires: ... │
├───────────────┴───────────────┴───────────────┤
│         (scrolls for more notices)            │
└─────────────────────────────────────────────┘
```

- **Header band**: entity name/logo on the theme's background art, full width.
- **Pinned notice**: the newest or officer-flagged "important" notice gets one large featured card at the top.
- **Grid of cards**: one card per notice — category tag (Announcement / Event / General, using your existing admin-editable categories), title, a short excerpt of the body, and an expiry note if one is set. Tapping a card opens the full notice (and its attachment, if any) in a simple detail view.
- **Category colour tags**: small pill badges, colour-coded by category, consistent across every theme so members always recognise "Event" vs "Announcement" at a glance regardless of which entity they're viewing.
- **Empty state**: a friendly "No notices right now — check back soon" message on the themed background, never a blank white screen.

## 4. Typography & accessibility

- One font family throughout (matching your brand font), just larger sizes than the back office — this is meant to be read at a glance on a phone or from across a room on a lobby screen, not scanned as a dense data table.
- High-contrast text over every themed background (light card surfaces on top of the themed backdrop, never text directly on a busy motif).
- Works with your existing 3-language support (EN/MS/ZH) — card layout doesn't change per language, only the text.

## 5. What stays exactly as-is

- The back-office admin screen (create/edit/delete, officer/Secretary-only access, no-scroll, standard font/colour) — completely untouched.
- Underlying data: same `cbe_temple_notices` table, same categories, same attachment handling. This is purely a second, read-only *view* of the same data — no new business data or workflow.

## 6. Open questions for you

1. Should the theme be fully automatic (based on the entity's existing Faith/Practice Type), or should each entity's officer be able to override/pick their own theme regardless of that field?
2. Do you want the lobby-TV auto-rotate mode as part of this, or just the phone/portal view for now?
3. Any logo/branding element you want in the header band, or keep it text-only for now?

---
*This is a design document for your review — no code has been written yet. Once you confirm the direction (and answer the open questions above), I'll build it.*
