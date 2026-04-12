# 🧾 Ledger System – Skill Repo
> Sales, Purchase & Ledger System (Laravel + MySQL)

This repo contains **prompt skills** — structured guidance files you paste into AI tools (Claude, Copilot, Cursor, etc.) to get accurate, consistent code generation for each layer of the app.

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
