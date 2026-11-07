import { NextResponse } from "next/server";
import { db } from "@/db";
import { markets } from "@/db/schema";
import { asc } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET() {
  try {
    const allMarkets = await db
      .select()
      .from(markets)
      .orderBy(asc(markets.sortOrder), asc(markets.name));

    return NextResponse.json({
      success: true,
      markets: allMarkets,
    });
  } catch (error: any) {
    console.error("Fetch markets error:", error);
    return NextResponse.json({ error: "Failed to fetch markets" }, { status: 500 });
  }
}
