# 🧾 Ledger System – Skill Repo
> Sales, Purchase & Ledger System (Laravel + MySQL)

This repo contains **prompt skills** — structured guidance files you paste into AI tools (Claude, Copilot, Cursor, etc.) to get accurate, consistent code generation for each layer of the app.

---

## 🎨 UI/UX & Mobile Optimizations (Recent Updates)

A comprehensive frontend overhaul was integrated directly into the `resources/views` and `public/css` layers to vastly improve the application's mobile density and ergonomic usability:

- **Viewport Zoom Fix:** Configured the global `<meta name="viewport">` tag (`maximum-scale=1, user-scalable=0`) inside `app.blade.php` to completely prevent iOS Safari auto-zooming when interacting with forms, allowing us to enforce a high-density environment.
- **Global Form Standardization:** Overhauled all 32 Blade views across the entire platform, forcefully stripping bloated `text-base` sizes or overly generous padding, bringing every single input and select tightly into line with a clean `text-sm py-1.5` tailwind constraint.
- **Select2 Density:** Shrunk the absolute CSS height constraints for `.select2` inputs in `layout.css` down to `min-height: 36px` and applied inline `0.875rem` font sizes to organically match the native input styles without layout breaking.
- **Date Picker Bottom Sheet:** Overhauled the `bs-date-selector` UI. On screens `<640px`, it transforms into a beautifully anchored fixed bottom sheet with a smooth `bg-gray-900/40` Alpine.js-triggered dimming overlay, prioritizing the calendar over noisy background forms.
- **Navigation Automation:** The desktop and mobile sidebars now natively identify the current URL path as an `.is-active` route. `layout.js` waits precisely on page load to invoke native geometry logic (`getBoundingClientRect`) to determine nested depth, safely and gracefully triggering `scrollTo({ behavior: 'smooth' })` so active components seamlessly slide center-screen without jumping the window. Desktop scrolling is perfectly constrained using `lg:sticky lg:h-screen lg:top-0`.

---

## 📁 Skill Files

| Skill | File | Use When |
|---|---|---|
| 🏗️ Laravel Setup | `laravel-setup/SKILL.md` | Scaffolding migrations, models, normal auto-increment IDs |
| 🔥 Ledger Engine | `ledger-engine/SKILL.md` | Writing ledger entries (the core) |
| 💸 Transactions | `transactions/SKILL.md` | Sale, Purchase, Payment flows |
| 🌐 API Design | `api-design/SKILL.md` | Building REST controllers & resources |
| 🎨 Frontend (Blade) | `frontend-blade/SKILL.md` | UI with Blade + Tailwind |

---

## 🧠 Core Principle (Always Keep in Mind)

> ✅ **Ledger is the ONLY source of truth.**
> ❌ Never store or manually update a `balance` column.
> ✅ All balances are calculated: `SUM(dr_amount) - SUM(cr_amount)`

---

## 🔄 How to Use These Skills

1. Open any skill file
2. Copy the content
3. Paste it at the **top of your AI prompt** before asking for code
4. Then describe what you want to build

**Example:**
```
[Paste ledger-engine/SKILL.md here]

Now write a LedgerService that records a Sale transaction for party_id X with total 1000.
```
