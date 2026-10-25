# Adatempat — Brand Identity & UI Design System

**Version:** 0.1  
**Status:** Approved high-level direction — final HEX tokens, logo, and selected component details remain open for confirmation  
**Scope:** Brand direction, visual identity, design tokens, UI principles, and experience patterns  
**Product baseline:** Adatempat PRD Consolidation v1.5  
**Working language:** Bahasa Indonesia for customer-facing product UI

---

## 1. Purpose and decision status

This document establishes a new visual identity for Adatempat rather than reusing another product's design system as a template. It is a working proposal intended to help the product owner choose a coherent direction before implementation.

This is a design reference, not an implementation task. It does not change routes, business rules, data models, booking behavior, pricing, payment handling, or permissions.

### Relationship to the PRD

The PRD v1.5 remains authoritative for product scope and business behavior. This document proposes a replacement for the borrowed visual direction in PRD Section 14.0–14.8: visual philosophy, palette, typography, layout principles, and general component styling. The Adatempat-specific functional UI requirements in Section 14.9 remain in force.

**Decisions confirmed by the product owner:** the brand direction is Citrus Lime, and Plus Jakarta Sans is the preferred single typeface with a friendly, readable weight hierarchy. The exact shades in the palette below are provisional samples and must not be treated as final HEX tokens yet. Logo artwork and some component details also remain open. The previous Ailene-inspired style and the recent UI implementation experiment must not be treated as the approved identity for future implementation.

## 2. Product understanding: what Adatempat must communicate

Adatempat is a marketplace for discovering and booking places or venues in Indonesia. It serves three connected groups:

- **Customers and visitors:** discover options, compare relevant details, check availability, contact an owner when needed, and complete a booking.
- **Business and venue owners:** present their business clearly, publish venue information, manage listings and schedules, and handle bookings.
- **Super Admin:** verify businesses, moderate content, support operational workflows, and oversee platform activity.

The visual identity must work across more than a marketing homepage. It must support photo-rich discovery, filters and comparison, venue information, transactional booking and payment states, chat, owner operations, and administration.

### Brand positioning proposal

> Adatempat helps people find and book the right place for the plan they have in mind, with clearer information and a dependable booking journey.

The core benefit is not inspiration alone. It is moving from “I need a place for this” to “I understand this option and know what to do next.”

### Proposed brand promise

**Tempat yang pas untuk setiap rencana.**

Supporting message: find a place based on what matters—purpose, location, budget, capacity, facilities, and schedule—then continue into a clear booking process.

The tagline is a working recommendation, not a locked marketing claim.

## 3. Design direction exploration

Three possible directions were considered against Adatempat's breadth of use cases.

| Direction | Visual character | Strength | Risk |
|---|---|---|---|
| **A. Citrus Lime — selected direction** | Bright citrus lime, deep forest green, warm cream, and ink-dark contrast; practical typography and confident photography | Distinctive, energetic, modern, and approachable across meetings, celebrations, studios, restaurants, and outdoor spaces | Requires careful contrast and restraint so the bright lime does not overwhelm content or look playful in transactional screens |
| B. Peta Kota | Ink blue, chalk, map-like linework, bright yellow-green accents, highly utility-led interface | Strong sense of navigation and search | Can feel generic, technical, or too close to a mapping product |
| C. Ruang Perayaan | Deep berry, peach, cream, expressive shapes and emotional imagery | Memorable for weddings and social events | Could make business and meeting venues feel secondary; may over-index on celebrations |

### Selected direction: Citrus Lime

Use a **modern, clear, energetic marketplace** visual language: direct, human, and useful. Citrus Lime should make Adatempat recognizable and optimistic without turning every page into a bright yellow-green surface. Pair the lime signature with deep green and ink-dark elements for clarity, plus a warm cream or neutral base that lets real venue photography remain the focus. Transactional and administrative areas should stay calm and high-contrast.

The design should be recognizable through a consistent combination of color, wordmark, typography, photo treatment, spacing, and interaction—not through a decorative landing-page illustration. The high-level direction is approved; the exact palette values below remain exploratory.

### Five design principles

1. **Discovery before decoration.** Search, location, categories, filters, and venue inventory must be easy to find.
2. **Trust through detail.** Show accurate venue information, prices, availability, booking states, and ownership or verification signals when available.
3. **Photography is product content.** A real venue should be represented by real, relevant images—not a generic illustration.
4. **Warm and capable.** Friendly enough for personal events, clear enough for professional meetings and business use.
5. **Calm, not empty.** Use hierarchy and whitespace to reduce cognitive load, but do not leave product areas looking decorative or unfinished.

## 4. Brand personality and language

### Brand attributes

- **Helpful:** makes the next step clear.
- **Grounded:** speaks plainly and avoids inflated promises.
- **Trustworthy:** distinguishes verified information from unknown information.
- **Inclusive:** does not make the product feel reserved for luxury weddings or large corporate events.
- **Capable:** booking, payments, and operational states feel precise and dependable.

### What Adatempat is not

- Not a wedding-only inspiration site.
- Not a luxury-only venue directory.
- Not a generic SaaS dashboard template.
- Not an event-planning agency promising outcomes it cannot control.
- Not a social network where engagement matters more than booking clarity.

### Tone of voice

Use clear, natural Bahasa Indonesia. Keep copy friendly and direct, but avoid slang that may feel dated or undermine trust in a financial or booking flow. Use the same terms consistently within a flow.

**Prefer**
- “Cari tempat”
- “Lihat detail tempat”
- “Pilih tanggal dan waktu”
- “Periksa rincian pembayaran”
- “Pembayaran sedang diverifikasi”
- “Tempat ini belum memiliki foto”
- “Jadwal tersebut tidak lagi tersedia. Pilih jadwal lain.”

**Avoid**
- Vague button labels such as “Gas!”, “Yuk!”, or “Lanjut” when the action is consequential.
- Generic error text such as “Terjadi kesalahan” without an explanation or next step.
- Unsubstantiated superlatives such as “venue terbaik se-Indonesia”.
- Fake urgency, invented testimonials, fabricated ratings, or unsupported claims of scarcity.

Use “kamu” for public and customer-facing conversational copy by default. Use concise, neutral wording for high-stakes transactions and administrative controls. Legal and contractual wording must follow approved legal documents, not the marketing voice.

## 5. Logo and graphic identity direction

The final logo has **not** been designed or approved. The following is a brief for a future logo exploration.

### Wordmark

Use the brand name **Adatempat**, written as one word. Preserve the spelling consistently across the product, metadata, documents, and social profiles. Do not casually vary between “Ada Tempat”, “AdaTempat”, and “adatempat” in the same context.

### Symbol concept: an open doorway

Explore a simple geometric symbol inspired by an open frame or doorway: a visual metaphor for finding an appropriate space and opening a route toward a plan. A subtle path or opening can be explored in negative space, but the mark should remain legible at small sizes.

This is a concept, not a finished logo.

### Logo constraints

- Must work in one color, in reverse, and at favicon scale.
- Must remain legible beside the wordmark on desktop and mobile.
- Avoid relying on the generic map-pin or house outline as the whole identity.
- Avoid gradients, glows, 3D treatments, tiny decorative details, or overly literal venue illustrations.
- Do not use the accent color as the only means of recognizing the mark.
- Establish clear space and minimum size after the final mark is chosen.

## 6. Color system proposal

The **Citrus Lime direction is approved**, but the precise color tokens are not yet locked. The swatches below record the palette explored in the concept preview; treat them as working references, not approved production HEX values. The final palette should be checked for contrast, large-surface comfort, and clarity in booking/payment states before implementation.

### 6.1 Working palette references — provisional

| Role | Reference swatch | Intended role | Decision status |
|---|---|---|---|
| Citrus lime | **#C9FF3A** | Signature brand color, selected states, small highlights, and potentially primary-action surfaces when paired with dark text | Direction approved; exact shade provisional |
| Deep forest | **#2F6B3E** | Dark brand companion, secondary actions, supporting accents | Provisional |
| Warm cream | **#FFF7D6** | Optional warm canvas or soft section surface | Provisional |
| Ink dark | **#1F1F1F** | Primary text and high-contrast element color | Provisional |
| White surface | **#FFFFFF** | Cards and form surfaces where useful | Provisional |
| Neutral border | **#D9DED2** | Dividers and input boundaries; needs contrast review | Provisional |

### Color usage rules

- Treat Citrus Lime as the identity signature, but apply it selectively rather than filling every page or every control with lime.
- If a lime-filled button is used, pair it with dark text; do not assume white text will be readable on a bright lime background.
- Use deep forest and ink-dark colors to anchor navigation, text, and secondary actions when contrast testing supports the pairing.
- Never use color alone to communicate a status. Booking/payment states need visible labels and other cues.
- Keep large areas calm so venue photography and factual listing details remain the main content.
- Do not introduce extra accent colors without a specific semantic or functional reason.
- Exact primary-action colors, hover states, and neutral surfaces must be confirmed after contrast checks.

### 6.2 Semantic states

| State | Foreground | Background | Typical use |
|---|---|---|---|
| Success | **#276747** | **#E6F2E8** | Successful verification, completed action |
| Warning | **#915E10** | **#FBF0D8** | Upcoming deadline, caution, pending attention |
| Danger | **#B42318** | **#FDE8E7** | Failed action, destructive action, critical issue |
| Information | **#235D8C** | **#E6F0FA** | Neutral explanatory message |

Each state must also have a visible text label and, where appropriate, a clear icon. Verify contrast for each actual foreground/background pairing before implementation.

### 6.3 Dark mode

Dark mode is **not a launch requirement in this proposal**. Do not create and maintain a second theme until the light design is validated across discovery, booking, owner workflows, and admin screens. If dark mode becomes a requirement later, define a separate tested token set rather than mechanically inverting the light palette.

## 7. Typography

### Proposed font family: Plus Jakarta Sans

Use **Plus Jakarta Sans as the single typeface across the entire product**, with system sans-serif fallbacks. Avoid introducing a separate serif or monospace family into the UI by default.

Why this choice:
- Its geometric forms feel modern and friendly without becoming decorative or childish.
- The font's relatively tall x-height and open counters help letters remain distinguishable across a range of interface sizes, according to its typeface documentation. <https://github.com/google/fonts/blob/main/ofl/plusjakartasans/DESCRIPTION.en_us.html>
- A single family makes the product easier to maintain and keeps headings, venue details, forms, tables, and transactional states visually consistent.
- The Citrus Lime identity can supply distinctiveness through color, photography, layout, and graphic identity instead of relying on elaborate typography.
- Prices, dates, and dashboard metrics can use tabular numerals where supported; a separate monospace font is not required.

This is the recommended balance between readability and a warmer, less rigid personality. The family was designed by Gumpita Rahayu of Tokotype and commissioned originally for Jakarta's City of Collaboration identity.

### Type scale proposal

| Style | Desktop guidance | Mobile guidance | Use |
|---|---|---|---|
| Display | 48–56 px, weight 700 | 36–40 px, weight 700 | One primary heading on a marketing page; use 800 only when stronger emphasis is genuinely needed |
| H1 | 36–42 px, weight 700 | 30–34 px, weight 700 | Page title |
| H2 | 28–32 px, weight 700 | 24–28 px, weight 700 | Section title |
| H3 | 20–24 px, weight 600 | 20–22 px, weight 600 | Card and panel title |
| Body | 16 px, weight 400, line-height 1.5–1.65 | 16 px, weight 400, line-height 1.5–1.65 | Main content |
| Supporting | 14 px, weight 400, line-height 1.5 | 14 px, weight 400, line-height 1.5 | Descriptions and supporting labels |
| Metadata | 12–13 px | 12–13 px | Timestamps, IDs, small metadata |
| Form label | 14 px, weight 600 | 14 px, weight 600 | Form labels |

Use regular and medium weights for most body content; reserve semibold and bold for hierarchy rather than making everything heavy. Avoid long uppercase text, excessive letter spacing, and paragraphs set too small. Use tabular numerals for prices, durations, booking references, and dashboard metrics.

## 8. Photography and imagery

Venue imagery is one of the main ways users judge whether a place suits their plan. Image treatment is therefore part of product quality, not merely decoration.

### Listing imagery

- Use photos of the actual listed venue, supplied by the owner or otherwise authorized.
- Do not use generic stock or AI-generated images as though they depict a real listed venue.
- Do not invent or imply a venue's rating, facilities, capacity, pricing, location, or availability.
- If a listing lacks photos, show a consistent, branded no-photo placeholder with an honest label.
- Require appropriate image rights and moderation before publishing owner-submitted media.

### Composition

- **Search/listing card:** use a landscape image, preferably around 4:3. Keep the crop consistent across a result grid.
- **Venue detail hero/gallery:** a wide landscape image can lead, followed by a gallery that shows different angles and practical details.
- **Category navigation:** use representative imagery only as a category illustration. Do not let a generic photo appear to be a specific venue.
- **Owner onboarding:** give short, actionable guidance about showing the actual space, multiple angles, light, layout, and relevant setups.

### Photo behavior

- Reserve image dimensions to prevent layout shifts.
- Lazy-load below-the-fold images; prioritize the main image only when it is truly the page's lead content.
- Provide meaningful alt text when an image conveys information. Use empty alt text for purely decorative images.
- Preserve the subject on small screens; do not force a center crop if it removes key venue features.

## 9. Layout, spacing, and shape

### Layout principles

- Use a consistent content container, approximately **1200–1280 px maximum width** on large screens.
- Build on a 4 px spacing base, using a small, repeatable scale rather than arbitrary values.
- Organize sections by function: search, filters, results, venue detail, booking summary, and support actions.
- Use whitespace to group related information; do not rely on decorative shapes to communicate structure.
- Keep the primary action visible at the moment the user needs it, especially on mobile booking flows.
- Avoid intentional horizontal overflow at 320 px and above.

### Spacing scale

| Token | Value |
|---|---:|
| Space 1 | 4 px |
| Space 2 | 8 px |
| Space 3 | 12 px |
| Space 4 | 16 px |
| Space 5 | 20 px |
| Space 6 | 24 px |
| Space 8 | 32 px |
| Space 10 | 40 px |
| Space 12 | 48 px |
| Space 16 | 64 px |
| Space 20 | 80 px |

### Corners and elevation

| Token | Value | Use |
|---|---:|---|
| Radius small | 8 px | Small controls, badges |
| Radius medium | 12 px | Inputs, buttons, menus |
| Radius large | 16 px | Cards and grouped panels |
| Radius extra large | 20 px | Feature panels and major surfaces |
| Radius pill | 9999 px | Chips, compact filters, status pills only |

Prefer subtle borders over heavy shadows. Use elevation to express layering—such as a dropdown above a page—not as a default decoration on every card. Buttons should be comfortably rounded, but not every interface element needs to be a pill.

## 10. Iconography and graphic language

- Use one consistent outlined icon family, such as Lucide, with a consistent stroke weight.
- Typical icon sizes: 16 px for inline support, 20 px for standard controls, 24 px for primary navigation.
- Use an icon when it improves recognition or scanning; do not add icons to every label.
- A standalone icon button must have an accessible name and a clear focus state.
- Do not mix emoji, filled cartoon icons, outline icons, and unrelated illustration styles in the same functional area.
- Keep decoration restrained. If the interface needs a graphic motif, use the open-frame/doorway concept from the identity brief rather than a library of unrelated shapes.

## 11. Core UI patterns

### 11.1 Public navigation

- Brand mark and wordmark are always easy to find.
- The primary journey is **Jelajahi / Cari tempat**.
- Secondary navigation may include categories, how it works, and owner information.
- Authentication and owner calls to action must be distinguishable without competing with search.
- Mobile navigation must be keyboard accessible and must not conceal critical booking actions.

### 11.2 Search and filters

Search is a core interaction, not a decorative field placed in a hero. Present the criteria that matter to the user's task, progressively exposing advanced filters when needed.

Depending on available data, search may include purpose/category, location, date and time, capacity, price range, and relevant facilities. Never render a filter as if it works unless it is connected to a real query. Make active filters visible and easy to remove.

### 11.3 Venue listing card

A listing card should help users decide whether to open the detail page. When the data exists, prioritize:

1. Actual venue photo.
2. Venue name and category.
3. Location or distance.
4. Price or pricing basis.
5. Capacity or other decision-critical facts.
6. Rating and review count only if real, approved data exists.
7. Availability only if the system has reliable availability data.

The card should not become a dashboard of badges. Keep metadata concise and use a consistent order across results.

### 11.4 Venue detail and business profile

Structure information around booking decisions:
- Photo gallery.
- Venue and business identity.
- Exact location and useful directions when available.
- Description, capacity, facilities, and restrictions.
- Price basis, additional fees, and package details when configured.
- Date/time availability.
- Cancellation/refund terms.
- Verification and support signals.
- Primary action to select a schedule or start a booking.

Do not hide material fees or limitations behind decorative accordions. Use progressive disclosure for secondary details, not essential booking terms.

### 11.5 Booking and payment

A booking flow should prioritize clarity and error prevention over brand decoration.

- Show the selected venue, schedule, price breakdown, and applicable policies before confirmation.
- Present the hold duration and its effect clearly when a slot is temporarily reserved.
- Distinguish waiting for payment, payment verification, confirmed booking, cancellation, refund, dispute, and completion using both labels and appropriate visual states.
- Keep financial amounts aligned and use tabular numerals.
- Confirmation dialogs must describe the actual consequence of the action.
- Never suggest a booking is paid or confirmed solely because a customer returned from a payment redirect; reflect the verified system state.

Existing PRD booking and payment rules remain authoritative. This document only specifies how those states should be communicated.

### 11.6 Chat and notifications

- Make the other party, venue, and booking context clear.
- Keep message timestamps legible without overpowering message content.
- Distinguish system notices from messages written by people.
- Use explicit delivery, error, and retry states where supported.
- Do not use color alone to distinguish the sender or the type of notice.

### 11.7 Owner workspace

The owner area should feel like an operational tool, not like the public marketing site with a new color. Use a clear sidebar or responsive navigation, a predictable page title/action area, and consistent table, form, and status patterns.

Prioritize onboarding progress, business verification status, venue publication, schedule, booking actions, and relevant financial summaries. Use plain-language status and next steps instead of abstract decorative metrics.

### 11.8 Super Admin

Optimize for safe decisions, traceability, and data density:
- Clear page titles and filters.
- Readable tables with stable column alignment.
- Explicit status labels and timestamps.
- Strong visual distinction for destructive actions.
- Confirmation and step-up flows for sensitive actions as required by the PRD.
- Audit-relevant context visible before an admin approves, rejects, suspends, refunds, or changes sensitive records.

Do not use marketing-style hero treatments in operational admin screens.

### 11.9 Ads and promoted listings

Paid placements must be identified with the explicit label **“Iklan”**. Promoted placement must not visually masquerade as an organic ranking. Ensure the label remains readable on top of imagery and is not communicated only by a badge color.

The PRD's ad-slot count, rotation, and other operational rules remain in force.

## 12. Responsive behavior and accessibility

Responsive behavior is a functional requirement, not a final polish step.

- Target widths include 320, 360, 390, 768, 1024, 1280, and 1440 px.
- On small screens, move filters into a clear sheet or expandable region when a permanent sidebar no longer fits.
- Use touch targets around 44 × 44 px where practical.
- Avoid horizontal scrolling for primary page content; tables may use a deliberate responsive treatment where necessary.
- Every form field has a programmatic label, a visible focus state, and a nearby error/helper message.
- Keep keyboard order logical; do not make hover the only way to reveal actions.
- Use semantic headings and landmarks.
- Meet WCAG AA contrast as a baseline; verify actual text, control, disabled, hover, and status pairs rather than assuming the palette alone guarantees compliance.
- Respect reduced-motion preferences; use motion only when it explains a state change or supports orientation.
- Use visible confirmation and progress feedback for slow actions.
- Do not rely on color alone for payment, verification, moderation, or booking statuses.

## 13. Design tokens — provisional baseline

This is a direction record, not an implementation contract. Only the typeface family and the Citrus Lime direction are confirmed at this stage. Color values, semantic pairings, hover states, and component tokens below must be validated and approved before implementation.

| Token | Working reference |
|---|---|
| Brand direction | Citrus Lime |
| Citrus lime swatch | #C9FF3A — provisional |
| Deep forest companion | #2F6B3E — provisional |
| Warm cream | #FFF7D6 — provisional |
| Ink dark | #1F1F1F — provisional |
| Primary UI and heading font | Plus Jakarta Sans (single family; approved) |
| Body weight | 400 |
| Labels and secondary emphasis | 500–600 |
| Section/card headings | 600–700 |
| Main display heading | 700; reserve heavier weights for rare emphasis |
| Card shadow | 0 2px 8px rgba(31, 31, 31, 0.05) — provisional |
| Overlay shadow | 0 12px 32px rgba(31, 31, 31, 0.16) — provisional |

After palette contrast review, define the production CSS/Tailwind aliases once. Do not create a second competing set of values.

## 14. Content truth and trust rules

These are non-negotiable UI principles:

- Never invent venue records, prices, reviews, ratings, capacity, availability, business verification, or trust statistics to fill a design.
- If information is missing, show a clear empty state, omit the field, or state that the information has not been provided.
- Use realistic sample content only in clearly identified design prototypes or seed data that is not represented as real customer-facing data.
- A verification badge must map to a real platform state.
- Ads must remain labelled as ads.
- Price, fees, dates, schedules, refund terms, and booking status must match the system of record.
- Use neutral loading and empty states; do not make a page look populated with irrelevant stock photos or invented metrics.

## 15. Reference research and observations

The PRD defines the product scope. The public references below were reviewed to understand common needs in venue discovery and listing quality. They are used for product-pattern observations, not as visual templates to copy.

1. **Venuerific Indonesia** — its discovery interface surfaces event type, venue type, guest count, and location, and organizes venue discovery around common use cases. This supports prioritizing actionable search criteria and category pathways.  
   https://www.venuerific.com/id

2. **Event By Indonesia — Find Venue** — frames discovery around venue and hotel types such as ballrooms, convention centers, exhibition halls, and meeting rooms. This reinforces that categories should help users translate an intent into a kind of place.  
   https://event.kemenpar.go.id/find-venue

3. **Event By Indonesia — Register Venue** — asks owners to provide valid business information, venue specifications, facilities, capacity, and quality photos before a listing is published. This supports treating listing completeness and imagery as trust requirements.  
   https://event.kemenpar.go.id/daftar-venue

4. **Peerspace photo guidance** — recommends accurate, high-quality listing images that show the actual space from useful angles and make activity types understandable. This supports the photo-quality rules in Section 8.  
   https://support.peerspace.com/en/articles/10119356-photo-tips-and-requirements-for-listings

5. **Bridestory project guidelines** — emphasizes relevant portfolio imagery, quality, and permission to use visual work. Although wedding-focused, it reinforces that marketplace imagery needs both editorial relevance and rights awareness.  
   https://helpdesk.bridestory.com/hc/id/articles/360046546334-Bridestory-s-Project-Guidelines

These observations describe patterns and operational expectations found in the references; they do not prove that any single color palette or layout will perform best for Adatempat. The recommended identity direction is a design judgment that still needs owner review and later product validation.

## 16. Review and approval checklist

Before this document becomes the visual source of truth, review these decisions:

- [x] Is **Citrus Lime** the right brand direction for Adatempat? — selected by the product owner.
- [x] Is **Plus Jakarta Sans** the preferred readable single-family typography direction? — selected by the product owner.
- [ ] Which exact Citrus Lime HEX value and neutral/companion values should be locked for production?
- [ ] Is the typography scale comfortable across venue discovery, transactions, and admin screens?
- [ ] Does the proposed doorway/open-frame logo concept deserve exploration?
- [ ] Does the photo-first marketplace approach represent the full range of venue types, not just weddings or luxury events?
- [ ] Are the public, owner, booking, and Super Admin patterns consistent with the intended product?
- [ ] After the remaining color and identity decisions are confirmed, approve this document to replace the Ailene-inspired visual requirements in PRD v1.5 Section 14.0–14.8.

### Recommended sequence after approval

1. Lock the brand position, palette, typography, and logo direction.
2. Produce a compact visual board and core component examples before building pages.
3. Define responsive layouts for homepage, search results, venue detail, booking, owner workspace, and Super Admin.
4. Update the PRD's design-system reference and identify this document as the visual source of truth.
5. Implement the approved design in the repository, one product area at a time.

**Do not begin a second broad UI implementation until the identity direction is approved.**
