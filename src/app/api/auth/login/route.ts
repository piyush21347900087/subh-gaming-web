import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { users } from "@/db/schema";
import { eq, or } from "drizzle-orm";
import { verifyPassword, createSession, logSecurityEvent } from "@/lib/auth";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const { username, password } = body;

    if (!username || !password) {
      return NextResponse.json({ error: "Username and password are required" }, { status: 400 });
    }

    const trimmedUser = username.trim().toLowerCase();

    // Find user by username or phone
    const [user] = await db
      .select()
      .from(users)
      .where(or(eq(users.username, trimmedUser), eq(users.phone, trimmedUser)))
      .limit(1);

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    const userAgent = req.headers.get("user-agent") || "";

    if (!user) {
      await logSecurityEvent(null, "LOGIN_FAILED_NOT_FOUND", `Attempted username: ${trimmedUser}`, "WARNING", clientIp);
      return NextResponse.json({ error: "Invalid username or password" }, { status: 401 });
    }

    // Check if account is suspended/banned
    if (user.status !== "ACTIVE") {
      await logSecurityEvent(user.id, "LOGIN_BLOCKED_ACCOUNT_INACTIVE", `Status: ${user.status}`, "WARNING", clientIp);
      return NextResponse.json({ error: `Account is ${user.status.toLowerCase()}. Please contact helpline.` }, { status: 403 });
    }

    // Check lockout
    if (user.lockedUntil && new Date(user.lockedUntil) > new Date()) {
      return NextResponse.json(
        { error: "Account temporarily locked due to repeated failed attempts. Try again in 15 minutes." },
        { status: 429 }
      );
    }

    // Verify password
    const isValid = await verifyPassword(password, user.passwordHash);
    if (!isValid) {
      const attempts = (user.failedLoginAttempts || 0) + 1;
      let lockDate: Date | null = null;
      if (attempts >= 5) {
        lockDate = new Date(Date.now() + 15 * 60 * 1000); // 15 mins
      }

      await db
        .update(users)
        .set({
          failedLoginAttempts: attempts,
          lockedUntil: lockDate,
          updatedAt: new Date(),
        })
        .where(eq(users.id, user.id));

      await logSecurityEvent(user.id, "LOGIN_FAILED_PASSWORD", `Failed attempts: ${attempts}`, "WARNING", clientIp);
      return NextResponse.json({ error: "Invalid username or password" }, { status: 401 });
    }

    // Reset failed login counter on success
    await db
      .update(users)
      .set({
        failedLoginAttempts: 0,
        lockedUntil: null,
        updatedAt: new Date(),
      })
      .where(eq(users.id, user.id));

    // Create session token
    const token = await createSession(user.id, clientIp, userAgent);

    await logSecurityEvent(user.id, "LOGIN_SUCCESS", `Role: ${user.role}`, "INFO", clientIp);

    const response = NextResponse.json({
      success: true,
      token,
      user: {
        id: user.id,
        username: user.username,
        email: user.email,
        phone: user.phone,
        role: user.role,
        walletBalance: user.walletBalance,
        bonusBalance: user.bonusBalance,
      },
    });

    // Set secure HTTP-only cookie
    response.cookies.set("mk_session", token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      maxAge: 7 * 24 * 60 * 60, // 7 days
      path: "/",
    });

    return response;
  } catch (error: any) {
    console.error("Login error:", error);
    return NextResponse.json({ error: "Internal server error during login" }, { status: 500 });
  }
}
