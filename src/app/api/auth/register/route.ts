import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { users, transactions } from "@/db/schema";
import { eq, or } from "drizzle-orm";
import { hashPassword, createSession, logSecurityEvent } from "@/lib/auth";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const { username, phone, email, password } = body;

    if (!username || !phone || !password) {
      return NextResponse.json({ error: "Username, phone, and password are required" }, { status: 400 });
    }

    const cleanUser = username.trim().toLowerCase();
    const cleanPhone = phone.trim().replace(/\D/g, "");

    if (cleanUser.length < 3) {
      return NextResponse.json({ error: "Username must be at least 3 characters" }, { status: 400 });
    }

    if (cleanPhone.length < 10) {
      return NextResponse.json({ error: "Please enter a valid 10-digit phone number" }, { status: 400 });
    }

    if (password.length < 6) {
      return NextResponse.json({ error: "Password must be at least 6 characters" }, { status: 400 });
    }

    // Check if user already exists
    const existing = await db
      .select()
      .from(users)
      .where(or(eq(users.username, cleanUser), eq(users.phone, cleanPhone)))
      .limit(1);

    if (existing.length > 0) {
      return NextResponse.json({ error: "Username or phone number is already registered" }, { status: 409 });
    }

    const passwordHash = await hashPassword(password);

    const [newUser] = await db
      .insert(users)
      .values({
        username: cleanUser,
        phone: cleanPhone,
        email: email ? email.trim().toLowerCase() : null,
        passwordHash,
        role: "USER",
        status: "ACTIVE",
        walletBalance: "0.00",
        bonusBalance: "0.00",
      })
      .returning();

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    const userAgent = req.headers.get("user-agent") || "";
    const token = await createSession(newUser.id, clientIp, userAgent);

    await logSecurityEvent(newUser.id, "USER_REGISTERED", `Username: ${cleanUser}`, "INFO", clientIp);

    const response = NextResponse.json({
      success: true,
      token,
      user: {
        id: newUser.id,
        username: newUser.username,
        email: newUser.email,
        phone: newUser.phone,
        role: newUser.role,
        walletBalance: newUser.walletBalance,
        bonusBalance: newUser.bonusBalance,
      },
    });

    response.cookies.set("mk_session", token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      maxAge: 7 * 24 * 60 * 60,
      path: "/",
    });

    return response;
  } catch (error: any) {
    console.error("Register error:", error);
    return NextResponse.json({ error: "Registration failed. Please try again." }, { status: 500 });
  }
}
