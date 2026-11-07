import { NextRequest, NextResponse } from "next/server";
import { getAuthUser } from "@/lib/auth";
import { db } from "@/db";
import { bets, users } from "@/db/schema";
import { eq, and } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user) {
      return NextResponse.json({ authenticated: false, user: null }, { status: 401 });
    }

    // Update lastActiveAt timestamp to keep online tracking accurate
    await db
      .update(users)
      .set({ lastActiveAt: new Date() })
      .where(eq(users.id, user.id));

    // Get active bets count
    const activeBets = await db
      .select({ id: bets.id })
      .from(bets)
      .where(and(eq(bets.userId, user.id), eq(bets.status, "PENDING")));

    return NextResponse.json({
      authenticated: true,
      user: {
        ...user,
        activeBetsCount: activeBets.length,
      },
    });
  } catch (error: any) {
    console.error("Auth check error:", error);
    return NextResponse.json({ authenticated: false, error: "Internal error" }, { status: 500 });
  }
}
