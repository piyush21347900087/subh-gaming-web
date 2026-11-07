import { db } from "@/db";
import { users, sessions, securityLogs } from "@/db/schema";
import { eq, and, gt } from "drizzle-orm";
import bcrypt from "bcryptjs";
import crypto from "crypto";
import { NextRequest } from "next/server";

export interface AuthenticatedUser {
  id: number;
  username: string;
  email: string | null;
  phone: string | null;
  role: "ADMIN" | "STAFF" | "USER";
  status: string;
  walletBalance: string;
  bonusBalance: string;
  bankName?: string | null;
  accountNumber?: string | null;
  ifscCode?: string | null;
  upiId?: string | null;
}

export async function hashPassword(plain: string): Promise<string> {
  return bcrypt.hash(plain, 10);
}

export async function verifyPassword(plain: string, hash: string): Promise<boolean> {
  return bcrypt.compare(plain, hash);
}

export function generateSessionToken(): string {
  return crypto.randomBytes(32).toString("hex");
}

export async function createSession(
  userId: number,
  ipAddress?: string,
  userAgent?: string
): Promise<string> {
  const token = generateSessionToken();
  const expiresAt = new Date();
  expiresAt.setDate(expiresAt.getDate() + 7); // 7-day session

  await db.insert(sessions).values({
    userId,
    sessionToken: token,
    ipAddress: ipAddress || "unknown",
    userAgent: userAgent?.slice(0, 500) || "unknown",
    expiresAt,
  });

  return token;
}

export async function validateSessionToken(token: string): Promise<AuthenticatedUser | null> {
  if (!token) return null;

  const [sessionRecord] = await db
    .select({
      session: sessions,
      user: users,
    })
    .from(sessions)
    .innerJoin(users, eq(sessions.userId, users.id))
    .where(and(eq(sessions.sessionToken, token), gt(sessions.expiresAt, new Date())))
    .limit(1);

  if (!sessionRecord || sessionRecord.user.status !== "ACTIVE") {
    return null;
  }

  const u = sessionRecord.user;
  return {
    id: u.id,
    username: u.username,
    email: u.email,
    phone: u.phone,
    role: u.role as "ADMIN" | "STAFF" | "USER",
    status: u.status,
    walletBalance: u.walletBalance,
    bonusBalance: u.bonusBalance,
    bankName: u.bankName,
    accountNumber: u.accountNumber,
    ifscCode: u.ifscCode,
    upiId: u.upiId,
  };
}

export async function getAuthUser(req: NextRequest): Promise<AuthenticatedUser | null> {
  // Check Authorization header first
  const authHeader = req.headers.get("authorization");
  let token = "";

  if (authHeader && authHeader.startsWith("Bearer ")) {
    token = authHeader.substring(7).trim();
  } else {
    // Check cookie
    const cookieToken = req.cookies.get("mk_session")?.value;
    if (cookieToken) {
      token = cookieToken;
    }
  }

  if (!token) return null;
  return validateSessionToken(token);
}

export async function logSecurityEvent(
  userId: number | null,
  action: string,
  details: string,
  severity: "INFO" | "WARNING" | "CRITICAL" = "INFO",
  ipAddress = "127.0.0.1"
) {
  try {
    await db.insert(securityLogs).values({
      userId: userId ?? undefined,
      action,
      details,
      severity,
      ipAddress,
    });
  } catch (err) {
    console.error("Failed to log security event:", err);
  }
}
