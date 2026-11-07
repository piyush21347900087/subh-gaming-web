# Enterprise System Audit, Architecture & Security Specification
**Application:** Subh Gaming & Mahadev King Matka Gaming Platform  
**Architecture:** Next.js 16 (App Router) + PostgreSQL (Drizzle ORM) + Tailwind CSS + Node.js  
**Security Level:** Production Enterprise (RBAC, bcrypt hashing, session tokens, rate limiting, SQL injection-safe parameterized queries)

---

## 1. Phase 1: Complete Project Audit Report

### 1.1 Existing System State
- **Previous Schema**: The previous database was an uninitialized default starter template without any business logic, tables, or relations.
- **Frontend State**: Blank starter placeholder page displaying a generic text box.
- **Authentication**: Zero authentication mechanisms existed (no login, register, session management, or role authorization).
- **Security Vulnerabilities**:
  - No access control (RBAC).
  - No session validation or session expiration.
  - No rate-limiting or anti-brute force mechanisms.
  - No audit trails or security event logging.
- **Data Integrity**: No foreign keys, unique constraints, or primary keys for bets, markets, wallets, or ledger transactions.

---

## 2. Phase 2: Database Rebuild & Architecture

The PostgreSQL database has been completely designed with 11 relational tables:

1. **`users`**: User identities, role-based access (`ADMIN`, `STAFF`, `USER`), account status (`ACTIVE`, `BANNED`), wallet and bonus balances, bank and UPI details, brute-force tracking (`failed_login_attempts`, `locked_until`).
2. **`sessions`**: Secure bearer / cookie session tokens tied to user accounts with IP tracking, user-agent auditing, and expiration timestamps.
3. **`markets`**: Complete market registry with open/close schedules, active state, live pana and digit results, and market classification (`REGULAR`, `KING_SPECIAL`, `STARLINE`, `GALI_DISAWAR`).
4. **`market_results`**: Historical archive for panel charts and date-by-date outcome verification.
5. **`bets`**: High-concurrency wager tracking with game types (`SINGLE_DIGIT`, `JODI_DIGIT`, `SINGLE_PANA`, `DOUBLE_PANA`, `TRIPLE_PANA`, `HALF_SANGAM`, `FULL_SANGAM`), session type (`OPEN`/`CLOSE`), potential win calculations, and real-time status.
6. **`transactions`**: Double-entry financial ledger recording deposits, withdrawals, bet placements, winnings, bonuses, and administrative adjustments with balance snapshots.
7. **`deposit_requests`**: Approval queue for user UPI/Bank deposits with UTR numbers and staff review notes.
8. **`withdrawal_requests`**: Payout requests queue with automated wallet deduction, KYC details, and approval pipeline.
9. **`game_settings`**: Dynamic operational parameters and payout rate multipliers (e.g., 9.5x for Single Digit, 95x for Jodi, 140x for Single Pana, etc.).
10. **`security_logs`**: Tamper-evident security audit trail capturing user IP, action type, timestamp, and severity.
11. **`notice_board`**: Real-time broadcasts, offers, and urgent announcements.

---

## 3. Phase 3 & 4: UI/UX & Brand Aesthetics

- **Visual Identity**: High-roller luxury aesthetic inspired by the official **"Subh Gaming - King of online world"**.
- **Color Palette**: Deep Obsidian Black (`#0a0a0c`), Royal Crimson Red (`#8B0000` / `#DC2626`), Metallic Burnished Gold (`#D4AF37`, `#F59E0B`), and Emerald Green for wins and positive transactions.
- **Responsive Layout**: Mobile-first design optimized for handheld gaming, tablets, laptops, and wide monitors.
- **Component Architecture**: Modular navigation bar, live market cards with countdowns, dynamic bet slips, quick-select chip pickers, live Matka chart viewer, passbook ledger, and comprehensive Admin Command Hub.

---

## 4. Phase 5: Security Architecture & RBAC

- **Password Storage**: Salting and hashing via `bcryptjs` (cost factor 10).
- **Session Security**: Cryptographically random 64-character tokens stored in database sessions table with explicit expiration timestamps.
- **Authorization**:
  - `ADMIN`: Full system controls, market creation, result declaration, wallet adjustments, staff management, database tools.
  - `STAFF`: Deposit & withdrawal request processing, market monitoring.
  - `USER`: Bet placement, wallet deposits, withdrawals, bet history, live charts.
- **Protection**:
  - IDOR prevention: All balance and bet lookups are authenticated via the validated session user ID.
  - Parameterized queries via Drizzle ORM prevent SQL injection.
  - Input validation on all numbers, pana digits, and bet amounts.

---

## 5. Default Test Accounts

- **Administrator**:
  - Username: `admin`
  - Password: `MahadevKing@2025!`
  - Role: `ADMIN`
- **Staff Officer**:
  - Username: `staff`
  - Password: `Staff@2025!`
  - Role: `STAFF`
- **VIP Player**:
  - Username: `player1`
  - Password: `Player@12345`
  - Role: `USER`
  - Balance: ₹15,450.00
