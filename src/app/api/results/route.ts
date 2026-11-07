import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { markets, marketResults } from "@/db/schema";
import { desc, eq } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const { searchParams } = new URL(req.url);
    const marketId = searchParams.get("marketId");

    // Live today's results for all active markets
    const activeMarkets = await db
      .select({
        id: markets.id,
        name: markets.name,
        slug: markets.slug,
        openTime: markets.openTime,
        closeTime: markets.closeTime,
        status: markets.status,
        fullResult: markets.fullResult,
        currentOpenPana: markets.currentOpenPana,
        currentOpenDigit: markets.currentOpenDigit,
        currentCloseDigit: markets.currentCloseDigit,
        currentClosePana: markets.currentClosePana,
      })
      .from(markets)
      .where(eq(markets.isActive, true));

    // If specific market historical requested
    let history: any[] = [];
    if (marketId) {
      history = await db
        .select()
        .from(marketResults)
        .where(eq(marketResults.marketId, Number(marketId)))
        .orderBy(desc(marketResults.resultDate))
        .limit(30);
    }

    return NextResponse.json({
      success: true,
      liveResults: activeMarkets,
      history,
    });
  } catch (error: any) {
    console.error("Results fetch error:", error);
    return NextResponse.json({ error: "Failed to fetch results" }, { status: 500 });
  }
}
