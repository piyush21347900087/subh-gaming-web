import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { sessions } from "@/db/schema";
import { eq } from "drizzle-orm";

export async function POST(req: NextRequest) {
  try {
    const cookieToken = req.cookies.get("mk_session")?.value;
    const authHeader = req.headers.get("authorization");
    let token = cookieToken;
    if (authHeader && authHeader.startsWith("Bearer ")) {
      token = authHeader.substring(7).trim();
    }

    if (token) {
      await db.delete(sessions).where(eq(sessions.sessionToken, token));
    }

    const response = NextResponse.json({ success: true, message: "Logged out successfully" });
    response.cookies.delete("mk_session");
    return response;
  } catch (error: any) {
    console.error("Logout error:", error);
    return NextResponse.json({ error: "Logout failed" }, { status: 500 });
  }
}
