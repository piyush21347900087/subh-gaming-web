import { NextResponse } from "next/server";
import { db } from "@/db";
import { noticeBoard } from "@/db/schema";
import { desc, eq } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET() {
  try {
    const notices = await db
      .select()
      .from(noticeBoard)
      .where(eq(noticeBoard.isActive, true))
      .orderBy(desc(noticeBoard.createdAt))
      .limit(5);

    return NextResponse.json({
      success: true,
      notices,
    });
  } catch (error: any) {
    console.error("Notice error:", error);
    return NextResponse.json({ error: "Failed to fetch notices" }, { status: 500 });
  }
}
