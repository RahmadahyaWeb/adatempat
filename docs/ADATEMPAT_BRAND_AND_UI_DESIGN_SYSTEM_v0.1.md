# Adatempat — Brand Identity & UI Design System

**Version:** 0.1  
**Status:** Draft proposal for review — not yet approved as the final visual source of truth  
**Scope:** Brand direction, visual identity, design tokens, UI principles, and experience patterns  
**Product baseline:** Adatempat PRD Consolidation v1.5  
**Working language:** Bahasa Indonesia for customer-facing product UI

---

## 1. Purpose and decision status

This document establishes a new visual identity for Adatempat rather than reusing another product's design system as a template. It is a working proposal intended to help the product owner choose a coherent direction before implementation.

This is a design reference, not an implementation task. It does not change routes, business rules, data models, booking behavior, pricing, payment handling, or permissions.

### Relationship to the PRD

The PRD v1.5 remains authoritative for product scope and business behavior. This document proposes a replacement for the borrowed visual direction in PRD Section 14.0–14.8: visual philosophy, palette, typography, layout principles, and general component styling. The Adatempat-specific functional UI requirements in Section 14.9 remain in force.

**Until this document is approved, all visual values and identity decisions below are proposals.** The previous Ailene-inspired style and the recent UI implementation experiment must not be treated as the approved identity for future implementation.

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
| **A. Ruang Terbuka — recommended** | Evergreen green, soft neutral canvas, restrained coral accents, practical typography, confident photography | Trustworthy and approachable across meetings, celebrations, studios, restaurants, outdoor spaces, and more | Needs disciplined photography and spacing so green does not make the product feel like an environmental brand |
| B. Peta Kota | Ink blue, chalk, map-like linework, bright yellow-green accents, highly utility-led interface | Strong sense of navigation and search | Can feel generic, technical, or too close to a mapping product |
| C. Ruang Perayaan | Deep berry, peach, cream, expressive shapes and emotional imagery | Memorable for weddings and social events | Could make business and meeting venues feel secondary; may over-index on celebrations |

### Recommended direction: Ruang Terbuka

Use a **modern local marketplace** visual language: direct, human, and useful. Let real venue photography and clear information create desire and confidence to book. The identity should feel warm without becoming overly editorial, playful without becoming childish, and polished without feeling luxury-only.

The design should be recognizable through a consistent combination of color, wordmark, typography, photo treatment, spacing, and interaction—not through a decorative landing-page illustration.

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

The recommended palette makes **evergreen the primary brand color** and **coral a secondary expression accent**. This deliberately moves away from a terracotta-first identity. The warm, slightly green-tinted canvas supports photography without making the interface feel clinical.

### 6.1 Core light palette

| Token | Value | Intended use |
|---|---|---|
| Canvas | **#F6F7F2** | Main page background |
| Surface | **#FFFFFF** | Cards, forms, dropdowns, elevated content |
| Surface subtle | **#EAF0E9** | Soft sections, selected filters, secondary surfaces |
| Surface warm | **#FBECE5** | Occasional coral-tinted highlight backgrounds |
| Text primary | **#1D2A24** | Headings and primary body text |
| Text secondary | **#53625A** | Supporting copy and descriptions |
| Text muted | **#6B786F** | Metadata, helper text, low-priority labels |
| Border | **#DDE5DD** | Default dividers and card borders |
| Border strong | **#B8C8BC** | Input borders and higher-contrast boundaries |
| Brand primary | **#24594A** | Primary buttons, selected controls, links where appropriate |
| Brand primary hover | **#194538** | Hover and pressed emphasis |
| Brand accent | **#EE7656** | Small decorative accents, highlights, illustration details |
| Brand accent strong | **#B94E32** | Text links or accent text requiring stronger contrast |
| Brand tint | **#E2EEE5** | Soft brand background and selected states |

### Color usage rules

- The primary action should normally be evergreen with white text.
- Coral creates recognition and energy; it is not the default fill for every button or badge.
- Never use color alone to communicate a status.
- Keep large areas relatively neutral so venue photos remain the main visual content.
- Use semantic status colors only for their meanings. Do not repurpose success green as a generic brand highlight in places where it could be mistaken for a booking state.
- Do not introduce new accent colors on a page without a specific semantic or functional reason.

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

### Proposed font families

- **Primary UI and body:** Manrope, with a system sans-serif fallback.
- **Metadata and technical identifiers:** IBM Plex Mono, used sparingly for booking references, compact metrics, timestamps, and some labels.
- **Serif display font:** not part of the default system. The product should get its distinctiveness from clear hierarchy and real imagery rather than relying on oversized editorial serif headlines.

This is a departure from the earlier Ailene-inspired combination. It aims to make Adatempat feel like a dependable discovery and booking product.

### Type scale proposal

| Style | Desktop guidance | Mobile guidance | Use |
|---|---|---|---|
| Display | 48–60 px, weight 750–800 | 36–42 px, weight 750–800 | One primary heading on a marketing page |
| H1 | 36–44 px, weight 700–800 | 30–36 px, weight 700–800 | Page title |
| H2 | 28–32 px, weight 700 | 24–28 px, weight 700 | Section title |
| H3 | 20–24 px, weight 650–750 | 20–22 px, weight 650–750 | Card and panel title |
| Body | 16 px, line-height 1.5–1.65 | 16 px, line-height 1.5–1.65 | Main content |
| Supporting | 14 px, line-height 1.5 | 14 px, line-height 1.5 | Descriptions and supporting labels |
| Metadata | 12–13 px | 12–13 px | Timestamps, IDs, small metadata |
| Form label | 14 px, weight 600–700 | 14 px, weight 600–700 | Form labels |

Avoid long uppercase text, excessive letter spacing, and paragraphs set too small. Use tabular numerals for prices, durations, booking references, and dashboard metrics.

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

## 13. Proposed design tokens

This table is a starting point for implementation after approval. It is not intended to be applied to the application until the proposal is accepted.

| Token | Value |
|---|---|
| Brand primary | #24594A |
| Brand primary hover | #194538 |
| Brand accent | #EE7656 |
| Brand accent strong | #B94E32 |
| Brand tint | #E2EEE5 |
| Canvas | #F6F7F2 |
| Surface | #FFFFFF |
| Surface subtle | #EAF0E9 |
| Surface warm | #FBECE5 |
| Text primary | #1D2A24 |
| Text secondary | #53625A |
| Text muted | #6B786F |
| Border subtle | #DDE5DD |
| Border strong | #B8C8BC |
| Success / background | #276747 / #E6F2E8 |
| Warning / background | #915E10 / #FBF0D8 |
| Danger / background | #B42318 / #FDE8E7 |
| Information / background | #235D8C / #E6F0FA |
| Primary font | Manrope |
| Metadata font | IBM Plex Mono |
| Card shadow | 0 2px 8px rgba(29, 42, 36, 0.05) |
| Overlay shadow | 0 12px 32px rgba(29, 42, 36, 0.16) |

Tailwind aliases and actual component tokens should be defined only after this direction is approved. The implementation must not create a second competing set of values.

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

- [ ] Is **Ruang Terbuka** the right brand direction for Adatempat?
- [ ] Does evergreen green as the primary color feel distinct and appropriate, with coral used as a supporting accent?
- [ ] Is the typography direction sufficiently original and practical?
- [ ] Does the proposed doorway/open-frame logo concept deserve exploration?
- [ ] Does the photo-first marketplace approach represent the full range of venue types, not just weddings or luxury events?
- [ ] Are the public, owner, booking, and Super Admin patterns consistent with the intended product?
- [ ] Is the visual identity proposal approved to replace the Ailene-inspired visual requirements in PRD v1.5 Section 14.0–14.8?

### Recommended sequence after approval

1. Lock the brand position, palette, typography, and logo direction.
2. Produce a compact visual board and core component examples before building pages.
3. Define responsive layouts for homepage, search results, venue detail, booking, owner workspace, and Super Admin.
4. Update the PRD's design-system reference and identify this document as the visual source of truth.
5. Implement the approved design in the repository, one product area at a time.

**Do not begin a second broad UI implementation until the identity direction is approved.**
