# Pattern: Global Brand Bar

A thin strip across the top of every page containing a small, centered logo (brand wordmark). Users always see which app/brand they're in, no matter how deep they navigate. Wired **once** at the navigation/layout level — never repeated per screen. Built for Alta Apps (React Native), but the principle drops into any app or website.

**Type:** reusable UI/layout pattern (shared chrome). Stack-agnostic — reference impls for React Native/Expo + PHP/HTML/CSS.

**Core rule:** put shared chrome in the layout, never in the page. One component + one wiring point = it appears app/site-wide, stays consistent, and every new page inherits it automatically.

---

## Integration Prompt

> Paste everything below this line into the target project. Swap the logo asset + brand colors.

---

You are given a task to add a **global brand bar** — a small centered logo shown on every page, wired once at the layout level.

### 1. The principle (works anywhere)

Render the brand bar once, at the navigation/layout level — NOT inside each page. Every page under that layout inherits it automatically, including pages built later. This is the whole trick:
- **Single source of truth** — change the logo/height in one file, it updates everywhere.
- **Zero per-page work** — new screens get the bar for free.
- **Safe-area aware** — the layout owns the top inset (notch / status bar), so pages don't double-pad.

**Why:** several screens (dashboard, lists) had zero indicator of which app the user was in — confusing for less technical users. A persistent brand mark fixes that with one shared component, instead of pasting a logo onto dozens of screens (which drifts out of sync and is easy to forget on new screens).

### 2. React Native / Expo implementation

Two pieces: a tiny presentational component + one line wiring it into the tab navigator's header.

**a) The component** — `src/components/shared/brand-bar.tsx`:
```tsx
import { View, Image, useColorScheme } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

// Slim, app-wide brand strip: a small centered logo below the status bar.
export function BrandBar() {
  const scheme = useColorScheme();
  const insets = useSafeAreaInsets();
  const tint   = scheme === 'dark' ? '#ffffff' : '#222e3c';   // logo recolors per theme
  const bg     = scheme === 'dark' ? '#0f172a' : '#ffffff';
  const border = scheme === 'dark' ? '#1e293b' : '#e2e8f0';
  return (
    <View style={{ paddingTop: insets.top, backgroundColor: bg,
                  borderBottomWidth: 1, borderBottomColor: border }}>
      <View style={{ height: 30, alignItems: 'center', justifyContent: 'center' }}>
        <Image source={require('../../../assets/images/parent-logo.png')}
               style={{ height: 16, width: 72, tintColor: tint }}
               resizeMode="contain" />
      </View>
    </View>
  );
}
```
The logo PNG is a transparent silhouette, so `tintColor` repaints it navy in light mode and white in dark mode — one asset, both themes.

**b) Wire it once** — `src/app/(tabs)/_layout.tsx`:
```tsx
import { BrandBar } from '@/components/shared/brand-bar';

<Tabs
  screenOptions={{
    headerShown: true,
    header: () => <BrandBar />,   // <-- shows on EVERY tab + every nested screen
    /* ...tab bar styling... */
  }}
>
```

**Why the navigator header (not a floating overlay):** React Navigation measures the header and pushes screen content down by exactly its height, and resets the safe-area top inset to 0 for content below. So pages never collide with the bar and never double-pad the notch. A floating `position:absolute` overlay would do neither — it would sit on top of content and clash with the Dynamic Island.

**Apply to other RN apps:**
1. Copy `brand-bar.tsx`. Swap the `require(...)` to that app's logo, and the tint/bg colors to its brand.
2. Add `header: () => <BrandBar />` + `headerShown: true` to the tab (or stack) navigator's `screenOptions`.
3. Done. Every current and future screen under that navigator shows the bar.

### 3. Website implementation (HTML / CSS / PHP)

Same principle — render once in the shared layout, every page inherits it. On a PHP site you already use this with includes (e.g. `menu.php`); the brand bar is one more include at the very top.

**a) The partial** — `brand-bar.php` (or `_brandbar.html`):
```html
<div class="brand-bar">
  <img src="/assets/parent-logo.svg" alt="Brand" class="brand-bar__logo">
</div>
```

**b) The CSS** (drop in your global stylesheet):
```css
.brand-bar{
  position: sticky; top: 0; z-index: 50;
  display: flex; align-items: center; justify-content: center;
  height: 38px; background:#fff; border-bottom:1px solid #e2e8f0;
}
.brand-bar__logo{ height: 18px; width:auto; }
@media (prefers-color-scheme: dark){
  .brand-bar{ background:#0f172a; border-bottom-color:#1e293b; }
}
```

**c) Include it once** in the shared header/layout:
```php
<body>
  <?php include __DIR__ . '/partials/brand-bar.php'; ?>   <!-- every page that includes the layout gets it -->
  <?php include __DIR__ . '/partials/menu.php'; ?>        <!-- existing left sidebar, below the brand bar -->
  <main> ... page content ... </main>
</body>
```

### 4. Left-sidebar variant

Want it on the LEFT instead of the top? Same component, different CSS. Make it a vertical strip:
```css
position: fixed; left: 0; top: 0; bottom: 0; width: 56px; flex-direction: column;
```
and add `margin-left: 56px` to your main content. The "render once in the layout" rule is identical — only the geometry changes.

### 5. Files (Alta reference)

| File | Role |
|------|------|
| `src/components/shared/brand-bar.tsx` | The component (logo, theme tint, safe-area top). |
| `src/app/(tabs)/_layout.tsx` | Wires it as the Tabs header — one line, applies app-wide. |
| `assets/images/parent-logo.png` | Transparent wordmark; recolored via `tintColor`. |

### The rule to remember

Put shared chrome in the layout, never in the page. One component + one wiring point in the navigator/layout = it appears on the whole app/site, stays consistent, and every new page gets it automatically. That is the entire pattern.

---

## Pattern Metadata

| Field | Value |
|-------|-------|
| Category | UI / layout chrome |
| Stacks | React Native / Expo + PHP / HTML / CSS |
| Key idea | Render shared chrome once at layout/navigator level; pages inherit |
| Theming | Single transparent logo asset, recolored via `tintColor` (RN) / `prefers-color-scheme` (web) |
| Safe-area | Layout owns top inset; navigator header pushes content down, resets inset to 0 |
| Variants | Top strip (default) or left vertical strip (geometry-only change) |
| Customize | Swap logo asset + brand colors |
