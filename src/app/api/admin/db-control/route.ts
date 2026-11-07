import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { sql } from "drizzle-orm";
import { 
  users, 
  markets, 
  marketResults, 
  bets, 
  transactions, 
  depositRequests, 
  withdrawalRequests, 
  gameSettings, 
  noticeBoard, 
  securityLogs,
  apiKeys 
} from "@/db/schema";
import bcrypt from "bcryptjs";
import { seedDatabase } from "@/db/seed";

export const dynamic = "force-dynamic";

// GET: Fetch Complete Database Overview, Statistics, Tables Info, and Diagnostic Health
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Access denied. Super Admin required." }, { status: 403 });
    }

    // Get count & metadata of all tables
    const [usersCount] = await db.select({ count: sql<number>`count(*)::int` }).from(users);
    const [marketsCount] = await db.select({ count: sql<number>`count(*)::int` }).from(markets);
    const [betsCount] = await db.select({ count: sql<number>`count(*)::int` }).from(bets);
    const [transactionsCount] = await db.select({ count: sql<number>`count(*)::int` }).from(transactions);
    const [resultsCount] = await db.select({ count: sql<number>`count(*)::int` }).from(marketResults);
    const [depositsCount] = await db.select({ count: sql<number>`count(*)::int` }).from(depositRequests);
    const [withdrawalsCount] = await db.select({ count: sql<number>`count(*)::int` }).from(withdrawalRequests);
    const [logsCount] = await db.select({ count: sql<number>`count(*)::int` }).from(securityLogs);
    const [apiKeysCount] = await db.select({ count: sql<number>`count(*)::int` }).from(apiKeys);

    // Database size & connection info from Postgres catalog
    const dbSizeResult = await db.execute(sql`
      SELECT pg_size_pretty(pg_database_size(current_database())) as db_size,
             current_database() as db_name,
             version() as pg_version;
    `);

    const tableStats = [
      { name: "users", label: "Users & Accounts", count: usersCount?.count || 0 },
      { name: "markets", label: "Markets & Draw Schedules", count: marketsCount?.count || 0 },
      { name: "bets", label: "Wagers & Game Slips", count: betsCount?.count || 0 },
      { name: "transactions", label: "Ledger Passbook", count: transactionsCount?.count || 0 },
      { name: "market_results", label: "Historical Declared Results", count: resultsCount?.count || 0 },
      { name: "deposit_requests", label: "UPI & Bank Deposits", count: depositsCount?.count || 0 },
      { name: "withdrawal_requests", label: "Payout Requests", count: withdrawalsCount?.count || 0 },
      { name: "security_logs", label: "Security Audit Trail", count: logsCount?.count || 0 },
      { name: "api_keys", label: "Secret API Keys", count: apiKeysCount?.count || 0 },
    ];

    return NextResponse.json({
      success: true,
      database: {
        status: "ONLINE",
        name: dbSizeResult.rows[0]?.db_name || "app_db",
        size: dbSizeResult.rows[0]?.db_size || "16 MB",
        version: String(dbSizeResult.rows[0]?.pg_version || "").slice(0, 40),
        tableStats,
      },
    });
  } catch (error: any) {
    console.error("DB Control Error:", error);
    return NextResponse.json({ error: "Failed to fetch database information" }, { status: 500 });
  }
}

// POST: Execute Admin Controlled Actions (Safe Reset & Reseed, Truncate Specific Table, Run Safe SQL)
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Access denied. Super Admin required." }, { status: 403 });
    }

    const body = await req.json();
    const { action, tableName, customSql } = body;
    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";

    if (action === "RESEED_DATABASE") {
      // Cleanly reseed markets, default rates, and sample data safely
      await seedDatabase();
      await logSecurityEvent(user.id, "DB_RESET_AND_RESEEDED", "Full database reset and fresh seed executed via Admin Desk", "CRITICAL", clientIp);

      return NextResponse.json({
        success: true,
        message: "Database successfully reset and reseeded with fresh official markets and default accounts!",
      });
    }

    if (action === "TRUNCATE_TABLE") {
      if (!tableName) {
        return NextResponse.json({ error: "Table name is required" }, { status: 400 });
      }

      const allowedTables = ["bets", "transactions", "deposit_requests", "withdrawal_requests", "security_logs", "market_results"];
      if (!allowedTables.includes(tableName)) {
        return NextResponse.json({ error: `Table '${tableName}' cannot be truncated for security reasons.` }, { status: 400 });
      }

      await db.execute(sql.raw(`TRUNCATE TABLE "${tableName}" RESTART IDENTITY CASCADE`));
      await logSecurityEvent(user.id, "TABLE_TRUNCATED", `Admin truncated table ${tableName}`, "WARNING", clientIp);

      return NextResponse.json({
        success: true,
        message: `Table '${tableName}' data cleared successfully.`,
      });
    }

    if (action === "VACUUM_ANALYZE") {
      await db.execute(sql`VACUUM ANALYZE`);
      await logSecurityEvent(user.id, "DB_OPTIMIZED", "Executed VACUUM ANALYZE query optimization", "INFO", clientIp);
      return NextResponse.json({
        success: true,
        message: "Database optimized and table indexes updated successfully.",
      });
    }

    if (action === "EXECUTE_SAFE_QUERY") {
      if (!customSql || typeof customSql !== "string") {
        return NextResponse.json({ error: "SQL query string required" }, { status: 400 });
      }

      const trimmed = customSql.trim();
      const forbidden = ["DROP DATABASE", "DROP ROLE", "SHUTDOWN", "ALTER SYSTEM"];
      if (forbidden.some((f) => trimmed.toUpperCase().includes(f))) {
        return NextResponse.json({ error: "Unsafe DDL operation prohibited." }, { status: 400 });
      }

      const result = await db.execute(sql.raw(trimmed));
      await logSecurityEvent(user.id, "ADMIN_SQL_EXECUTED", `Executed custom query: ${trimmed.slice(0, 100)}`, "WARNING", clientIp);

      return NextResponse.json({
        success: true,
        rowCount: result.rowCount,
        rows: (result.rows || []).slice(0, 50),
      });
    }

    return NextResponse.json({ error: "Invalid action" }, { status: 400 });
  } catch (error: any) {
    console.error("DB Control Action Error:", error);
    return NextResponse.json({ error: error.message || "Database action failed" }, { status: 500 });
  }
}
