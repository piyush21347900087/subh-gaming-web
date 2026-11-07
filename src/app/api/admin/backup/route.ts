import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { users, markets, marketResults, bets, transactions, gameSettings } from "@/db/schema";
import { sql } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 403 });
    }

    const { searchParams } = new URL(req.url);
    const download = searchParams.get("download") === "true";

    // Query high-level stats
    const allUsers = await db.select({
      id: users.id,
      username: users.username,
      role: users.role,
      status: users.status,
      walletBalance: users.walletBalance,
      createdAt: users.createdAt,
    }).from(users);

    const allMarkets = await db.select().from(markets);
    const allResults = await db.select().from(marketResults).limit(100);
    const allSettings = await db.select().from(gameSettings);
    const [betsTotal] = await db.select({ count: sql<number>`count(*)::int` }).from(bets);
    const [txTotal] = await db.select({ count: sql<number>`count(*)::int` }).from(transactions);

    const snapshot = {
      timestamp: new Date().toISOString(),
      platform: "SUBH GAMING ENTERPRISE",
      databaseVersion: "PostgreSQL 15+",
      summary: {
        totalUsers: allUsers.length,
        totalMarkets: allMarkets.length,
        totalHistoricalResults: allResults.length,
        totalBets: betsTotal?.count || 0,
        totalTransactions: txTotal?.count || 0,
      },
      markets: allMarkets,
      settings: allSettings,
      recentResults: allResults,
      usersSanitized: allUsers,
    };

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(user.id, "DB_BACKUP_GENERATED", `Exported system snapshot`, "INFO", clientIp);

    if (download) {
      return new NextResponse(JSON.stringify(snapshot, null, 2), {
        status: 200,
        headers: {
          "Content-Type": "application/json",
          "Content-Disposition": `attachment; filename="mahadev_king_backup_${Date.now()}.json"`,
        },
      });
    }

    return NextResponse.json({
      success: true,
      backupStatus: "HEALTHY",
      snapshot,
    });
  } catch (error: any) {
    console.error("Backup error:", error);
    return NextResponse.json({ error: "Failed to generate backup" }, { status: 500 });
  }
}
