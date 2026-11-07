import "dotenv/config";
import { pool, db } from "./index";
import { sql } from "drizzle-orm";
import { seedDatabase } from "./seed";
import { users } from "./schema";

export async function initDatabase() {
  console.log("Connecting to PostgreSQL and verifying tables...");

  const createTablesSql = `
    CREATE TABLE IF NOT EXISTS "users" (
      "id" serial PRIMARY KEY NOT NULL,
      "username" varchar(50) NOT NULL UNIQUE,
      "email" varchar(120) UNIQUE,
      "phone" varchar(20) UNIQUE,
      "password_hash" text NOT NULL,
      "role" varchar(20) DEFAULT 'USER' NOT NULL,
      "status" varchar(20) DEFAULT 'ACTIVE' NOT NULL,
      "wallet_balance" numeric(12, 2) DEFAULT '0.00' NOT NULL,
      "bonus_balance" numeric(12, 2) DEFAULT '0.00' NOT NULL,
      "bank_name" text,
      "account_number" text,
      "ifsc_code" text,
      "upi_id" text,
      "failed_login_attempts" integer DEFAULT 0,
      "locked_until" timestamp with time zone,
      "last_active_at" timestamp with time zone DEFAULT now(),
      "created_at" timestamp with time zone DEFAULT now(),
      "updated_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "sessions" (
      "id" serial PRIMARY KEY NOT NULL,
      "user_id" integer NOT NULL REFERENCES "users"("id") ON DELETE CASCADE,
      "session_token" varchar(128) NOT NULL UNIQUE,
      "ip_address" varchar(64),
      "user_agent" text,
      "last_seen_at" timestamp with time zone DEFAULT now(),
      "expires_at" timestamp with time zone NOT NULL,
      "created_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "markets" (
      "id" serial PRIMARY KEY NOT NULL,
      "name" varchar(100) NOT NULL,
      "slug" varchar(100) NOT NULL UNIQUE,
      "tagline" text,
      "open_time" varchar(10) NOT NULL,
      "close_time" varchar(10) NOT NULL,
      "is_active" boolean DEFAULT true,
      "status" varchar(20) DEFAULT 'OPEN',
      "current_open_pana" varchar(10),
      "current_open_digit" varchar(5),
      "current_close_digit" varchar(5),
      "current_close_pana" varchar(10),
      "full_result" varchar(30) DEFAULT '***-**-***',
      "category" varchar(30) DEFAULT 'REGULAR',
      "sort_order" integer DEFAULT 0,
      "created_at" timestamp with time zone DEFAULT now(),
      "updated_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "market_results" (
      "id" serial PRIMARY KEY NOT NULL,
      "market_id" integer NOT NULL REFERENCES "markets"("id") ON DELETE CASCADE,
      "result_date" date NOT NULL,
      "open_pana" varchar(10),
      "open_digit" varchar(5),
      "close_digit" varchar(5),
      "close_pana" varchar(10),
      "full_result" varchar(30) NOT NULL,
      "declared_at" timestamp with time zone DEFAULT now(),
      "declared_by" integer REFERENCES "users"("id")
    );

    CREATE TABLE IF NOT EXISTS "bets" (
      "id" serial PRIMARY KEY NOT NULL,
      "user_id" integer NOT NULL REFERENCES "users"("id") ON DELETE CASCADE,
      "market_id" integer NOT NULL REFERENCES "markets"("id") ON DELETE CASCADE,
      "game_type" varchar(30) NOT NULL,
      "session_type" varchar(10) NOT NULL,
      "selected_number" varchar(20) NOT NULL,
      "amount" numeric(10, 2) NOT NULL,
      "potential_win" numeric(10, 2) NOT NULL,
      "rate_multiplier" numeric(6, 2) NOT NULL,
      "status" varchar(20) DEFAULT 'PENDING' NOT NULL,
      "won_amount" numeric(10, 2) DEFAULT '0.00',
      "bet_date" date NOT NULL,
      "created_at" timestamp with time zone DEFAULT now(),
      "settled_at" timestamp with time zone
    );

    CREATE TABLE IF NOT EXISTS "transactions" (
      "id" serial PRIMARY KEY NOT NULL,
      "user_id" integer NOT NULL REFERENCES "users"("id") ON DELETE CASCADE,
      "type" varchar(30) NOT NULL,
      "amount" numeric(12, 2) NOT NULL,
      "balance_after" numeric(12, 2) NOT NULL,
      "description" text NOT NULL,
      "reference_id" varchar(100),
      "created_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "deposit_requests" (
      "id" serial PRIMARY KEY NOT NULL,
      "user_id" integer NOT NULL REFERENCES "users"("id") ON DELETE CASCADE,
      "amount" numeric(12, 2) NOT NULL,
      "payment_method" varchar(50) NOT NULL,
      "utr_number" varchar(100) NOT NULL,
      "status" varchar(20) DEFAULT 'PENDING' NOT NULL,
      "admin_notes" text,
      "processed_by" integer REFERENCES "users"("id"),
      "processed_at" timestamp with time zone,
      "created_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "withdrawal_requests" (
      "id" serial PRIMARY KEY NOT NULL,
      "user_id" integer NOT NULL REFERENCES "users"("id") ON DELETE CASCADE,
      "amount" numeric(12, 2) NOT NULL,
      "payout_method" varchar(50) NOT NULL,
      "payout_details" text NOT NULL,
      "status" varchar(20) DEFAULT 'PENDING' NOT NULL,
      "admin_notes" text,
      "processed_by" integer REFERENCES "users"("id"),
      "processed_at" timestamp with time zone,
      "created_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "game_settings" (
      "id" serial PRIMARY KEY NOT NULL,
      "key" varchar(100) NOT NULL UNIQUE,
      "value" text NOT NULL,
      "description" text,
      "updated_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "notice_board" (
      "id" serial PRIMARY KEY NOT NULL,
      "title" varchar(255) NOT NULL,
      "content" text NOT NULL,
      "is_active" boolean DEFAULT true,
      "type" varchar(30) DEFAULT 'ANNOUNCEMENT',
      "created_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "security_logs" (
      "id" serial PRIMARY KEY NOT NULL,
      "user_id" integer REFERENCES "users"("id"),
      "action" varchar(100) NOT NULL,
      "ip_address" varchar(64),
      "details" text,
      "severity" varchar(20) DEFAULT 'INFO',
      "created_at" timestamp with time zone DEFAULT now()
    );

    CREATE TABLE IF NOT EXISTS "api_keys" (
      "id" serial PRIMARY KEY NOT NULL,
      "name" varchar(100) NOT NULL,
      "key_hash" text NOT NULL,
      "key_prefix" varchar(16) NOT NULL,
      "permissions" text DEFAULT 'READ_WRITE' NOT NULL,
      "status" varchar(20) DEFAULT 'ACTIVE' NOT NULL,
      "last_used_at" timestamp with time zone,
      "created_by_id" integer REFERENCES "users"("id"),
      "created_at" timestamp with time zone DEFAULT now()
    );

    CREATE INDEX IF NOT EXISTS "idx_users_username" ON "users" ("username");
    CREATE INDEX IF NOT EXISTS "idx_users_role" ON "users" ("role");
    CREATE INDEX IF NOT EXISTS "idx_sessions_token" ON "sessions" ("session_token");
    CREATE INDEX IF NOT EXISTS "idx_markets_slug" ON "markets" ("slug");
    CREATE INDEX IF NOT EXISTS "idx_bets_user_id" ON "bets" ("user_id");
    CREATE INDEX IF NOT EXISTS "idx_bets_status" ON "bets" ("status");
  `;

  await pool.query(createTablesSql);
  console.log("All tables verified and created successfully.");

  // Check if admin user already exists
  const existingUsers = await db.select().from(users).limit(1);
  if (existingUsers.length === 0) {
    console.log("Database is empty. Seeding initial accounts, default markets, and settings...");
    await seedDatabase();
  } else {
    console.log(`Database already contains data (${existingUsers.length} users checked). Skipping seed.`);
  }

  console.log("Database initialization finished.");
}

if (process.argv[1]?.endsWith("init.ts")) {
  initDatabase()
    .then(() => {
      console.log("Database init complete.");
      process.exit(0);
    })
    .catch((err) => {
      console.error("Database init failed:", err);
      process.exit(1);
    });
}
