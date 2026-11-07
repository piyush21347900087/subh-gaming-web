# SUBH GAMING & MAHADEV KING
### Enterprise Online Matka & High-Roller Casino Gaming Platform

A modern, production-grade, highly secure Matka and Casino gaming platform engineered with Next.js 16 (App Router), PostgreSQL via Drizzle ORM, and Tailwind CSS.

---

## 🌟 Key Features

1. **Brand Identity & Luxury UI/UX**:
   - Official luxury black & gold diamond aesthetic ("Subh Gaming - King of online world").
   - Responsive mobile-first interface designed for mobile phones, tablets, and wide monitors.
   - Real-time animated ticker for today's market outcomes and urgent announcements.

2. **Market Engine & Game Types**:
   - 10 premier markets: *Kalyan, Milan Day/Night, Rajdhani Day/Night, Subh Gaming Special, Mahadev King Super, Time Bazar, Main Bazar, Gali Disawar*.
   - Multiple game formats:
     * **Single Digit (Ank 0-9)**: Multiplier 9.5x (₹10 ka ₹95)
     * **Jodi Digit (00-99)**: Multiplier 95x (₹10 ka ₹950)
     * **Single Pana (120)**: Multiplier 140x (₹10 ka ₹1,400)
     * **Double Pana (90)**: Multiplier 280x (₹10 ka ₹2,800)
     * **Triple Pana (10)**: Multiplier 600x (₹10 ka ₹6,000)
     * **Half Sangam & Full Sangam**: Multiplier 1000x - 10000x

3. **Automated Result Declaration & Payout Settlement**:
   - Enter 3-digit Open Pana or Close Pana; system auto-computes Ank digit sum modulo 10.
   - Evaluates all pending wagers for that market session.
   - Automatically marks winning bets as `WON` and credits user wallets with transparent double-entry ledger entries (`BET_WIN`).

4. **Financial Ledger & Wallet System**:
   - Real wallet balance + Bonus balances.
   - Instant UPI Deposit system with UTR tracking and staff verification queue.
   - Escrow-backed withdrawal request system with automatic refunds upon rejection.
   - Complete transaction passbook.

5. **Advanced Security & RBAC**:
   - **Password Security**: Bcryptjs salt hashing with cost factor 10.
   - **Session Management**: Cryptographic 64-character bearer & cookie session tokens.
   - **Role-Based Access Control**: `ADMIN`, `STAFF`, `USER` role isolation.
   - **Anti-Brute Force**: 5 failed login attempts trigger a 15-minute lockout.
   - **SQL Injection Prevention**: Parameterized queries via Drizzle ORM.
   - **Security Audit Logs**: Real-time event logging with IP, timestamp, action, and severity.

6. **Admin Command Hub**:
   - Executive KPI metrics: Turnover, Payouts, Active Bets, Pending Queues.
   - Live Bets stream.
   - Result declaration desk.
   - Deposit and withdrawal approval desks.
   - User account management with manual balance adjustments and ban toggles.
   - Market scheduling and status controls.
   - Full JSON database backup snapshot export.

---

## 🔑 Default Test Accounts

| Role | Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `admin` | `MahadevKing@2025!` | Full control, result declaration, financial desks |
| **Staff Officer** | `staff` | `Staff@2025!` | Deposit & withdrawal review |
| **VIP Player** | `player1` | `Player@12345` | Pre-loaded with ₹15,450.00 wallet balance |
| **Player 2** | `rahul_matka` | `Player@12345` | Active wagers and test balance |

---

## 🗄️ Database Architecture (PostgreSQL)

- `users`: Identity, hashed credentials, roles, balances, lockout counters.
- `sessions`: Token storage with user foreign keys, expiration dates, and IP logs.
- `markets`: Operating schedules, active state, and live results.
- `market_results`: Historical date-wise outcomes for panel and jodi charts.
- `bets`: High-concurrency wagers, game types, sessions, win status.
- `transactions`: Double-entry accounting ledger with balance snapshots.
- `deposit_requests`: Verification queue for user UPI payments.
- `withdrawal_requests`: Payout queue with escrow management.
- `game_settings`: Dynamic operational rates and payout multipliers.
- `security_logs`: Tamper-evident administrative audit trail.
- `notice_board`: Active announcements and urgent broadcasts.
