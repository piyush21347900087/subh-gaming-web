import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { markets, marketResults } from "@/db/schema";
import { eq, desc } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET(
  req: NextRequest,
  { params }: { params: Promise<{ id: string }> }
) {
  try {
    const { id } = await params;
    const marketId = parseInt(id, 10);
    if (isNaN(marketId)) {
      return NextResponse.json({ error: "Invalid market ID" }, { status: 400 });
    }

    const [market] = await db
      .select()
      .from(markets)
      .where(eq(markets.id, marketId))
      .limit(1);

    if (!market) {
      return NextResponse.json({ error: "Market not found" }, { status: 404 });
    }

    const resultsHistory = await db
      .select()
      .from(marketResults)
      .where(eq(marketResults.marketId, marketId))
      .orderBy(desc(marketResults.resultDate))
      .limit(30);

    return NextResponse.json({
      success: true,
      market,
      history: resultsHistory,
    });
  } catch (error: any) {
    console.error("Fetch market details error:", error);
    return NextResponse.json({ error: "Internal error" }, { status: 500 });
  }
}
