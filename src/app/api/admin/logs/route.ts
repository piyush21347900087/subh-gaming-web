import { NextRequest, NextResponse } from "next/server";
import { getAuthUser } from "@/lib/auth";
import { db } from "@/db";
import { securityLogs, users } from "@/db/schema";
import { desc, eq } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 403 });
    }

    const logs = await db
      .select({
        id: securityLogs.id,
        userId: securityLogs.userId,
        username: users.username,
        action: securityLogs.action,
        details: securityLogs.details,
        severity: securityLogs.severity,
        ipAddress: securityLogs.ipAddress,
        createdAt: securityLogs.createdAt,
      })
      .from(securityLogs)
      .leftJoin(users, eq(securityLogs.userId, users.id))
      .orderBy(desc(securityLogs.createdAt))
      .limit(100);

    return NextResponse.json({ success: true, logs });
  } catch (error: any) {
    console.error("Fetch logs error:", error);
    return NextResponse.json({ error: "Failed to load security logs" }, { status: 500 });
  }
}
