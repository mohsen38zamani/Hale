# معماری Domain در Hale

Hale یک Modular Monolith است. هر Domain منطق و Request و Service مرتبط خود را در `app/Domains` نگه می‌دارد و concerns مشترک در `app/Support` قرار می‌گیرند.

## مرزهای Phase 1
`Auth`, `Users`, `Organizations`, `Products`, `Media`, `Creative`, `Generations`, `AI`, `Credits`, `Billing`, `Notifications`, `Admin`.

AI Providerها نباید به Business Logic نشت کنند، Generation باید async باشد و تغییر Credit فقط از ledger انجام شود.

## ERD اولیه Foundation
```text
users 1 ─── * personal_access_tokens
users 1 ─── * sessions
users 1 ─── * organizations (owner_id)
```
