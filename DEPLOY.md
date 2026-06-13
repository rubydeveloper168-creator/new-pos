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

## Deploy จาก local working copy → production (sync ไฟล์ที่แก้)

> ใช้เมื่อโค้ด local อยู่ในโฟลเดอร์ทำงาน (เช่น MAMP) ที่ **ไม่ใช่ git clone** ของ repo
> หลักการ: sync เฉพาะไฟล์ที่แก้ขึ้น server → วางเป็น `www-data` → จัดการ DB → **commit/push บน server**
> ตัวแปรที่ใช้: `H=ruby168@<SERVER_IP>` , `PW=<SUDO_PW>` (อย่าเก็บรหัสจริงในไฟล์นี้/ใน repo)

### ขั้นที่ 0 — ตั้งค่า
```bash
H=ruby168@<SERVER_IP>; export SSHPASS='<SSH_PW>'        # ใช้ sshpass
# NEW POS: SRC=.../sale-pos-new-version  DST=/var/www/shop.rubyshop.co.th
# OLD POS: SRC=.../sale-pos-older-version DST=/var/www/sale.rubyshop.co.th
```

### ขั้นที่ 1 — หาว่าแก้ไฟล์อะไรบ้าง (local)
```bash
cd <SRC>
find . -type f -mtime -2 \
  -not -path "./vendor/*" -not -path "./files/*" -not -path "./assets/uploads/*" \
  \( -name "*.php" -o -name "*.js" -o -name "*.css" \)
```

### ขั้นที่ 2 — SAFETY: diff กับ server ก่อน (กันทับงานบน server)
ดึงไฟล์จาก server มาเทียบทีละไฟล์ — diff ควรมีแต่ "ส่วนที่เราตั้งใจแก้":
```bash
sshpass -e ssh -o StrictHostKeyChecking=no $H "cat $DST/<relpath>" > /tmp/srv
diff /tmp/srv <relpath>      # ถ้า diff ใหญ่/แปลก = local กับ server ต่างกันมาก ต้องระวัง/merge
```

### ขั้นที่ 3 — แพ็ก + อัปโหลด (เลี่ยง junk จาก macOS)
```bash
# COPYFILE_DISABLE=1 + --no-xattrs กันไฟล์ ._* (AppleDouble) ติดไปบน server
COPYFILE_DISABLE=1 tar --no-xattrs -czf /tmp/feature.tgz \
  app/controllers/admin/Foo.php themes/.../bar.php   # ใส่เฉพาะไฟล์ที่จะ deploy
sshpass -e scp -o StrictHostKeyChecking=no /tmp/feature.tgz $H:/tmp/feature.tgz
```

### ขั้นที่ 4 — วางไฟล์เป็น www-data + lint
```bash
sshpass -e ssh -o StrictHostKeyChecking=no $H '
  PW=<SUDO_PW>; DST=/var/www/...
  echo $PW | sudo -S -p "" -u www-data tar xzf /tmp/feature.tgz -C "$DST"
  php -l "$DST/app/controllers/admin/Foo.php"     # lint ไฟล์ PHP สำคัญ
  rm -f /tmp/feature.tgz'
```
> สำคัญ: ต้องวาง/commit เป็น **www-data** เสมอ ไม่งั้นเกิดไฟล์ root-owned ทำเว็บพัง

### ขั้นที่ 5 — DB / migrations
- **NEW POS (Laravel):** `sudo -u www-data php artisan migrate --force`
- **OLD POS (SMA/CI):** ระวัง! `migration->latest()` รัน migration **ทุกตัวตั้งแต่ recorded+1 ถึง target เรียงกัน**
  - เช็คก่อนเสมอว่า migration ที่ค้างมีตัวไหน **destructive** (`drop_column`, `drop_table`, `modify_column`):
    ```bash
    # ดู recorded version
    { echo $PW; } | sudo -S mysql <DB> -e "SELECT * FROM sma_migrations;"
    grep -E "drop_column|drop_table|modify_column" app/migrations/*.php
    ```
  - ถ้า migration ใหม่เป็นแค่ **เพิ่มตาราง** และมี migration อื่นค้างที่อันตราย → **สร้างตารางตรงๆ** ด้วย `CREATE TABLE IF NOT EXISTS ...` แทน แล้ว **อย่าเลื่อน `migration_version`** (กันรัน chain ที่ลบข้อมูล)
  - feed SQL + sudo พร้อมกัน: `{ echo $PW; cat file.sql; } | sudo -S mysql <DB>`
    (อย่าใช้ `echo $PW | sudo -S mysql < file` เพราะ `<` แย่ง stdin จาก sudo)

### ขั้นที่ 6 — commit + push บน server (เป็น www-data)
```bash
sshpass -e ssh -o StrictHostKeyChecking=no $H '
  cd $DST; PW=<SUDO_PW>
  R(){ echo $PW | sudo -S -p "" -u www-data env HOME=/var/www "$@"; }
  R git add <ไฟล์ที่ deploy ทีละตัว>
  R git -c user.name="rubyshop-deploy" -c user.email="deploy@rubyshop.local" commit -m "..."
  # push: copy SSH key ชั่วคราวให้ www-data อ่านได้ แล้วลบทิ้ง
  echo $PW | sudo -S cp /home/ruby168/.ssh/id_ed25519 /tmp/gh_key
  echo $PW | sudo -S chown www-data:www-data /tmp/gh_key; echo $PW | sudo -S chmod 600 /tmp/gh_key
  echo $PW | sudo -S -u www-data env HOME=/var/www \
    GIT_SSH_COMMAND="ssh -i /tmp/gh_key -o StrictHostKeyChecking=no -o IdentitiesOnly=yes" \
    git push origin main
  echo $PW | sudo -S rm -f /tmp/gh_key'
```

### ขั้นที่ 7 — เก็บกวาด
```bash
# ลบ junk AppleDouble ถ้าเผลอหลุดไป + ไฟล์ /tmp
sshpass -e ssh ... '$PW | sudo -S find <DST>/themes -name "._*" -delete'
rm -f /tmp/feature.tgz /tmp/srv
```

### ⚠️ Gotchas (เจอจริง)
- รหัส sudo/SSH **ห้ามเขียนลงไฟล์นี้/commit** — ใช้ placeholder
- git/ไฟล์ ต้องเป็น **www-data** เสมอ
- macOS tar → ไฟล์ `._*` ติดไปด้วย → ใช้ `COPYFILE_DISABLE=1 tar --no-xattrs` + ลบทีหลัง
- sudo + redirect stdin: ใช้ `{ echo $PW; cat file; } | sudo -S ...`
- OLD POS migration: เช็ค destructive migration ที่ค้างก่อนเลื่อน version เสมอ

---

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
