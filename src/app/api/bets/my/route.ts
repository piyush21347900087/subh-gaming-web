import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { bets, markets } from "@/db/schema";
import { eq, desc, and } from "drizzle-orm";
import { getAuthUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const { searchParams } = new URL(req.url);
    const statusFilter = searchParams.get("status");

    let query = db
      .select({
        id: bets.id,
        marketId: bets.marketId,
        marketName: markets.name,
        gameType: bets.gameType,
        sessionType: bets.sessionType,
        selectedNumber: bets.selectedNumber,
        amount: bets.amount,
        potentialWin: bets.potentialWin,
        rateMultiplier: bets.rateMultiplier,
        status: bets.status,
        wonAmount: bets.wonAmount,
        betDate: bets.betDate,
        createdAt: bets.createdAt,
      })
      .from(bets)
      .innerJoin(markets, eq(bets.marketId, markets.id))
      .where(
        statusFilter && statusFilter !== "ALL"
          ? and(eq(bets.userId, user.id), eq(bets.status, statusFilter))
          : eq(bets.userId, user.id)
      )
      .orderBy(desc(bets.createdAt))
      .limit(100);

    const userBets = await query;

    return NextResponse.json({
      success: true,
      bets: userBets,
    });
  } catch (error: any) {
    console.error("Fetch user bets error:", error);
    return NextResponse.json({ error: "Failed to fetch bets" }, { status: 500 });
  }
}
