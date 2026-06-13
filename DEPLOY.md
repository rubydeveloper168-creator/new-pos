# RubyShop POS — Deployment & Git Workflow

> ตั้งค่าเมื่อ 2026-06-13 หลังจากเก็บงานที่แก้บน server โดยตรง ~5 เดือนเข้า git

## โครงสร้าง

| | NEW POS | OLD POS |
| --- | --- | --- |
| Server path | `/var/www/shop.rubyshop.co.th` | `/var/www/sale.rubyshop.co.th` |
| ชนิด | Laravel (UltimatePOS) | CodeIgniter (SMA) |
| GitHub | `git@github.com:rubydeveloper168-creator/new-pos.git` | `git@github.com:rubydeveloper168-creator/sale-pos-old.git` |
| Branch | `main` | `main` |
| ไฟล์เป็นของ | `www-data` | `www-data` |

ทั้งสอง repo อยู่ใต้ account **`rubydeveloper168-creator`** (auth ผ่าน SSH key บน server: `/home/ruby168/.ssh/id_ed25519`)

## 🔑 กฎทอง: แก้ที่ local เท่านั้น — ห้ามแก้บน production

ปัญหาเดิมคือทีมแก้โค้ดบน server ตรงๆ ทำให้ git เก่า 5 เดือน + เสี่ยงงานหาย flow ใหม่:

```
แก้ที่เครื่อง dev  →  commit  →  push ขึ้น GitHub  →  รัน deploy.sh บน server
```

## วิธี deploy

บน server (รันได้เลย):

```bash
sudo bash /home/ruby168/deploy.sh new    # deploy NEW POS (pull + composer + migrate + clear cache)
sudo bash /home/ruby168/deploy.sh old    # deploy OLD POS (pull อย่างเดียว — ไม่ใช่ Laravel)
```

`deploy.sh` จะ:
1. **ปฏิเสธถ้า working tree มีการแก้ค้าง** (กันไม่ให้ทับงานที่แก้บน server) — ต้อง commit/stash ก่อน
2. `git pull --ff-only origin main` (เป็น www-data, ใช้ SSH key ผ่าน temp copy ที่ลบทิ้งอัตโนมัติ)
3. (NEW POS เท่านั้น) `composer install --no-dev` → `php artisan migrate --force` → `php artisan optimize:clear`

## 🔐 กฎเรื่อง secret: ห้าม hardcode key ในโค้ด

GitHub push protection จะบล็อกถ้ามี API key ในโค้ด (เคยเจอ OpenAI key ใน `config/services.php`)

- เก็บ key/password ใน `.env` เท่านั้น (gitignore อยู่แล้ว) แล้วอ่านด้วย `env('XXX')` ใน config
- อย่าใส่ค่าจริงใน `config/*.php`, `*.example`, หรือ commit ใดๆ

## ไฟล์ที่ไม่เข้า git (.gitignore)

- **NEW POS:** `vendor/`, `.env`, `*.sql`, `/backup`, `public/uploads/*`, `*.bak*`, `app.zip`, `__MACOSX`
- **OLD POS:** `vendor/`, `/files`, `/assets/uploads`, `*.zip`, `*.sql`, `*.xls`, `app/config/database.php` (DB creds), logs

> หลัง clone ใหม่ ต้องสร้าง `.env` (new) / `app/config/database.php` (old) เอง — มี `.example` ให้

## Migrations (NEW POS)

- migration อยู่ใน `database/migrations/` — `deploy.sh new` รัน `migrate --force` ให้อัตโนมัติ
- เขียน migration แบบ additive/idempotent เสมอ (ไม่ลบคอลัมน์ที่มีข้อมูล)

## Backups

ก่อนทำงานครั้งใหญ่ มี backup โค้ด + .env ที่ `/home/ruby168/deploy-backups-YYYYMMDD-HHMMSS/`

## สถานะ sync (เก่า ↔ ใหม่)

- `sync:bidirectional` รันทุกนาที (`app/Console/Kernel.php`) — sync บิลเก่า→ใหม่ ทั้งสร้างใหม่และ**แก้บิลเดิม** (cursor sweep)
- ดูรายละเอียดการ audit/แก้ที่ `SYNC_AUDIT.md`

## หมายเหตุ auth/permission

- ไฟล์เว็บเป็นของ `www-data` → git ต้องรันเป็น www-data (`sudo -u www-data`) ไม่งั้นสร้างไฟล์ root-owned ทำเว็บพัง
- push/pull ใช้ SSH key ของ ruby168 (account `rubydeveloper168-creator`) — `deploy.sh` จัดการ copy key ชั่วคราวให้ www-data แล้วลบทิ้งเอง
