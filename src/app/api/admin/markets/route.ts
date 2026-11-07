import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { markets } from "@/db/schema";
import { eq } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 403 });
    }

    const body = await req.json();
    const { name, openTime, closeTime, category, tagline } = body;

    if (!name || !openTime || !closeTime) {
      return NextResponse.json({ error: "Market name, open time, and close time are required" }, { status: 400 });
    }

    const slug = name.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");

    const [newMarket] = await db
      .insert(markets)
      .values({
        name,
        slug,
        openTime,
        closeTime,
        category: category || "REGULAR",
        tagline: tagline || "Live Indian Matka Game",
        status: "OPEN",
        isActive: true,
      })
      .returning();

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(user.id, "MARKET_CREATED", `Created new market: ${name}`, "INFO", clientIp);

    return NextResponse.json({ success: true, market: newMarket });
  } catch (error: any) {
    console.error("Create market error:", error);
    return NextResponse.json({ error: "Failed to create market" }, { status: 500 });
  }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 403 });
    }

    const body = await req.json();
    const { marketId, status, isActive, openTime, closeTime } = body;

    if (!marketId) {
      return NextResponse.json({ error: "Market ID required" }, { status: 400 });
    }

    const updates: any = { updatedAt: new Date() };
    if (status) updates.status = status;
    if (isActive !== undefined) updates.isActive = isActive;
    if (openTime) updates.openTime = openTime;
    if (closeTime) updates.closeTime = closeTime;

    const [updated] = await db
      .update(markets)
      .set(updates)
      .where(eq(markets.id, Number(marketId)))
      .returning();

    return NextResponse.json({ success: true, market: updated });
  } catch (error: any) {
    console.error("Update market error:", error);
    return NextResponse.json({ error: "Failed to update market" }, { status: 500 });
  }
}
