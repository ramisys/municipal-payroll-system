# Administrator & Technical Operations Runbook

**Project:** Municipal Payroll Management System  
**Version:** 1.0 (Phase P5 Release Candidate)  
**Target Roles:** System Administrator (`ADMINISTRATOR`), Municipal Database Administrator, IT Operations Officer  
**Standard Reference:** Baseline B2 Specification and System Architecture §6 / §7 / §8  

---

## 1. Architecture & Component Inventory

The Municipal Payroll Management System operates as an on-premises, network-isolated municipal enterprise platform.

```
+-----------------------------------------------------------------------------------+
| APPLICATION HOST (Local Municipal LAN: 192.168.1.50)                             |
|  - Web / HTTP: Nginx 1.24+ / HTTPS Local CA                                       |
|  - Runtime: PHP 8.3 (bcmath, pdo_mysql, mbstring, intl, zip, gd, openssl)         |
|  - Framework: Laravel 11.x (Database Cache, Database Session, Sync/Database Queue)|
|  - Presentation: Blade + Compiled Tailwind CSS + Alpine.js (Static build assets)  |
|  - Document Generators: DomPDF (Payslips, Reports, Certificates), PhpSpreadsheet  |
+-----------------------------------------------------------------------------------+
                                   |
                                   | MySQL Protocol (Port 3306)
                                   v
+-----------------------------------------------------------------------------------+
| DATABASE HOST (Co-located or Managed Storage)                                     |
|  - Engine: MySQL 8.4 LTS / MariaDB 11.4                                           |
|  - Storage Engine: InnoDB, utf8mb4_unicode_ci                                     |
|  - Critical Field Types: DECIMAL(13,2) for all monetary values (No Floats)        |
|  - Database Constraints: 37 Tables, Triggers for Immutability & Hash Chaining     |
+-----------------------------------------------------------------------------------+
                                   |
                                   | JSON-RPC 2.0 over HTTP (Port 8545)
                                   v
+-----------------------------------------------------------------------------------+
| EXTERNAL INTEGRITY LEDGER (Independent Host / IT Validator Nodes)                |
|  - Platform: Hyperledger Besu 24.x (QBFT Consensus, 4 Validators)                 |
|  - Smart Contract: Solidity 0.8.x IntegrityAnchor.sol                             |
|  - Stored Payload: Deterministic SHA-256 Hashes & Block Metadata (ZERO PII/Wages) |
|  - Interaction Boundary: LedgerGateway (Asynchronous Outbox, Outage-Resilient)    |
+-----------------------------------------------------------------------------------+
```

---

## 2. Server Installation & Offline Initialization

### 2.1 Server Prerequisites
Ensure all required runtime extensions are compiled and active:
```bash
php -v # Verify PHP 8.3
php -m | grep -E "bcmath|pdo_mysql|mbstring|intl|zip|gd|openssl"
```
> **Non-Negotiable:** `bcmath` is mandatory. The application strictly forbids PHP native float casting in any monetary calculation path (`BR-01`, `BR-40`, `AD-07`).

### 2.2 Environment Configuration
Copy environment template and configure local database credentials:
```bash
cp .env.example .env
php artisan key:generate
```
Edit `.env` for production municipal network:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://payroll.municipality.gov.local

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=municipal_payroll
DB_USERNAME=payroll_app
DB_PASSWORD="<STRONG_RANDOMLY_GENERATED_PASSWORD>"

SESSION_DRIVER=database
SESSION_LIFETIME=120
BCRYPT_ROUNDS=12

LEDGER_DRIVER=besu
LEDGER_RPC_URL=http://127.0.0.1:8545
LEDGER_CONTRACT_ADDRESS="0x..."
LEDGER_TIMEOUT=3
LEDGER_RETRY_LIMIT=5
```

### 2.3 Database Initialization from Scratch
Execute all 45 versioned migrations and core seeders:
```bash
php artisan migrate:fresh --seed --force
```
Confirm the seeded state:
- **Roles:** 4 roles (`ADMINISTRATOR`, `PAYROLL_OFFICER`, `APPROVER`, `VIEWER`).
- **Users:** 4 baseline user accounts with password change flags.
- **Reference Data:** Earning types, deduction types, attendance classifications.
- **Statutory Tables:** SSS, PhilHealth, Pag-IBIG brackets effective 2026.
- **Employees:** 30 demo employees with compensation profiles (`E-1000` to `E-1029`).

### 2.4 Production Asset Compilation & Optimization
```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 3. User Administration & Security Controls (NFR-6.5)

### 3.1 Provisioning New Staff Accounts
*Route: `/users/create` (Administrator only)*

1. Navigate to **User Management** (`/users`). Click **Create User Account**.
2. Enter Full Name, Email, and Unique Username.
3. Select Role (`PAYROLL_OFFICER`, `APPROVER`, or `VIEWER`).
4. Enter temporary password (minimum 8 characters, requiring mixed case, numbers, and symbols).
5. The `must_change_password` flag is automatically checked. Upon first sign-in, the user will be forced to choose a confidential password (`UC-01`).

### 3.2 Account Lockout Management (BR-31)
- If an account exceeds `FAILED_LOGIN_LIMIT` (default: 5 attempts), the account locks automatically.
- **Unlocking:**
  1. Open `/users`. Locate the locked user (highlighted with a red badge).
  2. Click **Unlock Account**.
  3. The `is_locked` flag resets to `false`, `failed_attempt_count` resets to `0`, and an audit event (`UNLOCK`) is recorded.

### 3.3 Session Timeout Configuration (BR-32)
- Session idle timeout is configured via `SystemConfig` key `SESSION_TIMEOUT_MINUTES` (default: 120 minutes).
- Idle sessions are invalidated server-side by the scheduled cleaner:
```bash
php artisan session:gc
```

---

## 4. Column Mapping & Register Intake Maintenance (FR-0.4, FR-2.8)

*Route: `/admin/column-maps`*

When the Accounting Office alters their spreadsheet header layouts, the Administrator updates the column mapping without altering application source code (`AC-2.8.4`):

1. Navigate to **Column Mappings** (`/admin/column-maps`).
2. Click **Create New Mapping Version**.
3. Map canonical system fields to the Accounting Office's custom column headers:
   - `basic_pay` -> `"Basic Monthly Salary"`
   - `overtime_pay` -> `"Total Overtime Rendered"`
   - `sss_deduction` -> `"Social Security EE"`
   - `philhealth_deduction` -> `"PHIC EE Share"`
   - `pagibig_deduction` -> `"HDMF Contribution"`
   - `withholding_tax` -> `"BIR Withholding Tax"`
4. Set the mapping as **Active**.
5. Historical payroll runs retain their original mapping version references for auditability (`FR-2.10`).

---

## 5. Statutory Contribution Schedule Maintenance (FR-2.3)

*Route: `/admin/statutory-tables`*

When national statutory rates update (e.g., SSS contribution schedule revisions):

1. Navigate to **Statutory Schedules** (`/admin/statutory-tables`).
2. Click **Create Schedule Version**.
3. Select Agency: `SSS`, `PHILHEALTH`, or `PAGIBIG`.
4. Enter the **Effectivity Date** (e.g., `2026-07-01`).
5. Populate salary brackets, employee contribution rates, and employer municipal shares.
6. Click **Save & Activate**:
   - The system utilizes the schedule active on the payroll run's `pay_date`.
   - Historical payroll records remain strictly unaffected (`BR-24`).

---

## 6. Database Backup & Disaster Recovery (NFR-5.4, NFR-6.3)

*Route: `/admin/backup`*

### 6.1 Creating Logical Backups
Backups are triggered automatically via cron every midnight and can be created on-demand:
```bash
# Manual CLI Backup
php artisan backup:create
```
- **Archive Format:** Compressed SQL dump + attendance attachments + SHA-256 checksum manifest.
- **Storage Location:** Dedicated storage volume mounted at `storage/app/backups/`.
- **Retention:** Governed by `RECORD_RETENTION_YEARS` (default: 10 years per `OI-10`).

### 6.2 Restore Procedure with Double-Confirmation
> [!CAUTION]
> Restoring a database replaces the current database contents. Execute only during disaster recovery or authorized migration.

1. Navigate to **Backup & Restore** (`/admin/backup`).
2. Select the verified backup archive from the list.
3. Click **Restore Database**.
4. The system requires typing the exact file name into the confirmation field (`NFR-6.3`).
5. Click **Execute Restore**:
   - The database is locked and schema restored from the logical dump.
   - Post-restore table integrity and row counts are validated automatically.
6. **Mandatory Post-Restore Integrity Verification:**
   - Immediately navigate to `/integrity`.
   - Click **Batch Verify All Historical Periods**.
   - If the database was restored correctly from an authentic backup, all checks will return **`MATCH`**.
   - If an altered or stale backup was restored, the system flags **`MISMATCH`** against the external ledger (`Milestone P-D`).

---

## 7. Hyperledger Besu Ledger & Outbox Operations (FR-6.3, Milestone P-E)

### 7.1 Ledger Connectivity Architecture
- The system interacts with Hyperledger Besu via `App\Services\LedgerGateway`.
- **Zero PII Guarantee (`AC-6.3.6`):** The ledger receives only SHA-256 cryptographic fingerprints, chain positions, and municipal scope identifiers. No employee names, ID numbers, or financial figures are ever sent.
- **Outbox Processing:** Anchors are queued in MySQL with `PENDING` status. The scheduler sweeps and transmits them to the ledger every 5 minutes:
```bash
# Manual Outbox Processing
php artisan integrity:process-outbox --limit=50
```

### 7.2 Handling Network Outages & Stalled Anchors
- If the Besu validator node is offline or unreachable, payroll finalization continues normally (`AC-6.3.5`).
- The anchor increments `retry_count`.
- Once `retry_count >= ANCHOR_RETRY_LIMIT` (5), the anchor is flagged as **`STALLED`** on the Integrity dashboard.
- **Resolution:**
  1. Restore network connectivity to the Besu node (`systemctl start besu` on validator host).
  2. Click **Process Pending Anchors** or run:
     ```bash
     php artisan integrity:process-outbox --force-stalled
     ```
  3. Stalled anchors transmit successfully and transition to **`CONFIRMED`**.

---

## 8. Audit Trail & Cryptographic Verification (FR-6.1, UC-31)

### 8.1 Audit Chain Verification (UC-31 A1)
1. Navigate to **Audit Trail** (`/audit-logs`) or **Integrity Verification** (`/integrity`).
2. Click **Verify Cryptographic Chain**.
3. The engine verifies every entry's `prev_entry_hash` across the entire history.
4. If an unauthorized direct database change or deletion occurred, the engine locates the exact row index of the break.

### 8.2 Official Auditor Certificate Export (UC-31 A3)
1. Navigate to `/integrity/runs/{run_id}`.
2. Verify that outcome displays **`MATCH`**.
3. Click **Export Verification Certificate (PDF)**.
4. Generates an official signed verification document with SHA-256 payload hashes, Besu block transaction references, and timestamp for the Commission on Audit (COA).
