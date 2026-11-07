import { pgTable, serial, text, varchar, numeric, boolean, timestamp, integer, date, index } from "drizzle-orm/pg-core";
import { relations } from "drizzle-orm";

// 1. Users table
export const users = pgTable("users", {
  id: serial("id").primaryKey(),
  username: varchar("username", { length: 50 }).notNull().unique(),
  email: varchar("email", { length: 120 }).unique(),
  phone: varchar("phone", { length: 20 }).unique(),
  passwordHash: text("password_hash").notNull(),
  role: varchar("role", { length: 20 }).notNull().default("USER"), // 'ADMIN', 'STAFF', 'USER'
  status: varchar("status", { length: 20 }).notNull().default("ACTIVE"), // 'ACTIVE', 'BANNED', 'SUSPENDED'
  walletBalance: numeric("wallet_balance", { precision: 12, scale: 2 }).notNull().default("0.00"),
  bonusBalance: numeric("bonus_balance", { precision: 12, scale: 2 }).notNull().default("0.00"),
  bankName: text("bank_name"),
  accountNumber: text("account_number"),
  ifscCode: text("ifsc_code"),
  upiId: text("upi_id"),
  failedLoginAttempts: integer("failed_login_attempts").default(0),
  lockedUntil: timestamp("locked_until", { withTimezone: true }),
  lastActiveAt: timestamp("last_active_at", { withTimezone: true }).defaultNow(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow(),
}, (table) => [
  index("idx_users_username").on(table.username),
  index("idx_users_role").on(table.role),
  index("idx_users_phone").on(table.phone),
  index("idx_users_last_active").on(table.lastActiveAt),
]);

// 2. Sessions table
export const sessions = pgTable("sessions", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id, { onDelete: "cascade" }),
  sessionToken: varchar("session_token", { length: 128 }).notNull().unique(),
  ipAddress: varchar("ip_address", { length: 64 }),
  userAgent: text("user_agent"),
  lastSeenAt: timestamp("last_seen_at", { withTimezone: true }).defaultNow(),
  expiresAt: timestamp("expires_at", { withTimezone: true }).notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
}, (table) => [
  index("idx_sessions_token").on(table.sessionToken),
  index("idx_sessions_user_id").on(table.userId),
  index("idx_sessions_last_seen").on(table.lastSeenAt),
]);

// 3. Markets table
export const markets = pgTable("markets", {
  id: serial("id").primaryKey(),
  name: varchar("name", { length: 100 }).notNull(),
  slug: varchar("slug", { length: 100 }).notNull().unique(),
  tagline: text("tagline"),
  openTime: varchar("open_time", { length: 10 }).notNull(),
  closeTime: varchar("close_time", { length: 10 }).notNull(),
  isActive: boolean("is_active").default(true),
  status: varchar("status", { length: 20 }).default("OPEN"), // 'OPEN', 'CLOSED', 'RUNNING'
  currentOpenPana: varchar("current_open_pana", { length: 10 }),
  currentOpenDigit: varchar("current_open_digit", { length: 5 }),
  currentCloseDigit: varchar("current_close_digit", { length: 5 }),
  currentClosePana: varchar("current_close_pana", { length: 10 }),
  fullResult: varchar("full_result", { length: 30 }).default("***-**-***"),
  category: varchar("category", { length: 30 }).default("REGULAR"), // 'REGULAR', 'KING_SPECIAL', 'STARLINE', 'GALI_DISAWAR'
  sortOrder: integer("sort_order").default(0),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow(),
}, (table) => [
  index("idx_markets_slug").on(table.slug),
  index("idx_markets_status").on(table.status),
  index("idx_markets_category").on(table.category),
]);

// 4. Market Results History
export const marketResults = pgTable("market_results", {
  id: serial("id").primaryKey(),
  marketId: integer("market_id").notNull().references(() => markets.id, { onDelete: "cascade" }),
  resultDate: date("result_date").notNull(),
  openPana: varchar("open_pana", { length: 10 }),
  openDigit: varchar("open_digit", { length: 5 }),
  closeDigit: varchar("close_digit", { length: 5 }),
  closePana: varchar("close_pana", { length: 10 }),
  fullResult: varchar("full_result", { length: 30 }).notNull(),
  declaredAt: timestamp("declared_at", { withTimezone: true }).defaultNow(),
  declaredBy: integer("declared_by").references(() => users.id),
}, (table) => [
  index("idx_market_results_market_id").on(table.marketId),
  index("idx_market_results_date").on(table.resultDate),
]);

// 5. Bets table
export const bets = pgTable("bets", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id, { onDelete: "cascade" }),
  marketId: integer("market_id").notNull().references(() => markets.id, { onDelete: "cascade" }),
  gameType: varchar("game_type", { length: 30 }).notNull(), // 'SINGLE_DIGIT', 'JODI_DIGIT', 'SINGLE_PANA', 'DOUBLE_PANA', 'TRIPLE_PANA', 'HALF_SANGAM', 'FULL_SANGAM'
  sessionType: varchar("session_type", { length: 10 }).notNull(), // 'OPEN', 'CLOSE'
  selectedNumber: varchar("selected_number", { length: 20 }).notNull(),
  amount: numeric("amount", { precision: 10, scale: 2 }).notNull(),
  potentialWin: numeric("potential_win", { precision: 10, scale: 2 }).notNull(),
  rateMultiplier: numeric("rate_multiplier", { precision: 6, scale: 2 }).notNull(),
  status: varchar("status", { length: 20 }).notNull().default("PENDING"), // 'PENDING', 'WON', 'LOST', 'REFUNDED'
  wonAmount: numeric("won_amount", { precision: 10, scale: 2 }).default("0.00"),
  betDate: date("bet_date").notNull(),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
  settledAt: timestamp("settled_at", { withTimezone: true }),
}, (table) => [
  index("idx_bets_user_id").on(table.userId),
  index("idx_bets_market_id").on(table.marketId),
  index("idx_bets_status").on(table.status),
  index("idx_bets_date").on(table.betDate),
]);

// 6. Transactions table
export const transactions = pgTable("transactions", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id, { onDelete: "cascade" }),
  type: varchar("type", { length: 30 }).notNull(), // 'DEPOSIT', 'WITHDRAWAL', 'BET_PLACED', 'BET_WIN', 'BET_REFUND', 'BONUS', 'ADMIN_ADJUSTMENT'
  amount: numeric("amount", { precision: 12, scale: 2 }).notNull(),
  balanceAfter: numeric("balance_after", { precision: 12, scale: 2 }).notNull(),
  description: text("description").notNull(),
  referenceId: varchar("reference_id", { length: 100 }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
}, (table) => [
  index("idx_transactions_user_id").on(table.userId),
  index("idx_transactions_type").on(table.type),
  index("idx_transactions_created_at").on(table.createdAt),
]);

// 7. Deposit Requests table
export const depositRequests = pgTable("deposit_requests", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id, { onDelete: "cascade" }),
  amount: numeric("amount", { precision: 12, scale: 2 }).notNull(),
  paymentMethod: varchar("payment_method", { length: 50 }).notNull(),
  utrNumber: varchar("utr_number", { length: 100 }).notNull(),
  status: varchar("status", { length: 20 }).notNull().default("PENDING"), // 'PENDING', 'APPROVED', 'REJECTED'
  adminNotes: text("admin_notes"),
  processedBy: integer("processed_by").references(() => users.id),
  processedAt: timestamp("processed_at", { withTimezone: true }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
}, (table) => [
  index("idx_deposit_requests_user_id").on(table.userId),
  index("idx_deposit_requests_status").on(table.status),
  index("idx_deposit_requests_utr").on(table.utrNumber),
]);

// 8. Withdrawal Requests table
export const withdrawalRequests = pgTable("withdrawal_requests", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().references(() => users.id, { onDelete: "cascade" }),
  amount: numeric("amount", { precision: 12, scale: 2 }).notNull(),
  payoutMethod: varchar("payout_method", { length: 50 }).notNull(),
  payoutDetails: text("payout_details").notNull(),
  status: varchar("status", { length: 20 }).notNull().default("PENDING"), // 'PENDING', 'APPROVED', 'REJECTED'
  adminNotes: text("admin_notes"),
  processedBy: integer("processed_by").references(() => users.id),
  processedAt: timestamp("processed_at", { withTimezone: true }),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
}, (table) => [
  index("idx_withdrawal_requests_user_id").on(table.userId),
  index("idx_withdrawal_requests_status").on(table.status),
]);

// 9. Game Settings & System Configuration table
export const gameSettings = pgTable("game_settings", {
  id: serial("id").primaryKey(),
  key: varchar("key", { length: 100 }).notNull().unique(),
  value: text("value").notNull(),
  description: text("description"),
  updatedAt: timestamp("updated_at", { withTimezone: true }).defaultNow(),
});

// 10. Security Audit Logs table
export const securityLogs = pgTable("security_logs", {
  id: serial("id").primaryKey(),
  userId: integer("user_id"),
  action: varchar("action", { length: 100 }).notNull(),
  ipAddress: varchar("ip_address", { length: 64 }),
  details: text("details"),
  severity: varchar("severity", { length: 20 }).default("INFO"), // 'INFO', 'WARNING', 'CRITICAL'
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
}, (table) => [
  index("idx_security_logs_action").on(table.action),
  index("idx_security_logs_severity").on(table.severity),
  index("idx_security_logs_created_at").on(table.createdAt),
]);

// 11. Notice Board table
export const noticeBoard = pgTable("notice_board", {
  id: serial("id").primaryKey(),
  title: varchar("title", { length: 255 }).notNull(),
  content: text("content").notNull(),
  isActive: boolean("is_active").default(true),
  type: varchar("type", { length: 30 }).default("ANNOUNCEMENT"), // 'ANNOUNCEMENT', 'OFFER', 'MAINTENANCE', 'ALERT'
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
});

// 12. API Keys table (for hidden, generated API security)
export const apiKeys = pgTable("api_keys", {
  id: serial("id").primaryKey(),
  name: varchar("name", { length: 100 }).notNull(),
  keyHash: text("key_hash").notNull(),
  keyPrefix: varchar("key_prefix", { length: 16 }).notNull(), // shows only first chars e.g. "mk_live_..."
  permissions: text("permissions").notNull().default("READ_WRITE"), // READ, WRITE, ADMIN
  status: varchar("status", { length: 20 }).notNull().default("ACTIVE"), // ACTIVE, REVOKED
  lastUsedAt: timestamp("last_used_at", { withTimezone: true }),
  createdById: integer("created_by_id").references(() => users.id),
  createdAt: timestamp("created_at", { withTimezone: true }).defaultNow(),
}, (table) => [
  index("idx_api_keys_status").on(table.status),
  index("idx_api_keys_prefix").on(table.keyPrefix),
]);

// Relations definitions
export const usersRelations = relations(users, ({ many }) => ({
  sessions: many(sessions),
  bets: many(bets),
  transactions: many(transactions),
  depositRequests: many(depositRequests),
  withdrawalRequests: many(withdrawalRequests),
}));

export const marketsRelations = relations(markets, ({ many }) => ({
  results: many(marketResults),
  bets: many(bets),
}));

export const betsRelations = relations(bets, ({ one }) => ({
  user: one(users, {
    fields: [bets.userId],
    references: [users.id],
  }),
  market: one(markets, {
    fields: [bets.marketId],
    references: [markets.id],
  }),
}));

export const transactionsRelations = relations(transactions, ({ one }) => ({
  user: one(users, {
    fields: [transactions.userId],
    references: [users.id],
  }),
}));
