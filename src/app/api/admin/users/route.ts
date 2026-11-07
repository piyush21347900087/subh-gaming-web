import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { users, transactions } from "@/db/schema";
import { eq, desc, ilike, or } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Super Admin privileges required" }, { status: 403 });
    }

    const { searchParams } = new URL(req.url);
    const search = searchParams.get("search");

    let query = db
      .select({
        id: users.id,
        username: users.username,
        email: users.email,
        phone: users.phone,
        role: users.role,
        status: users.status,
        walletBalance: users.walletBalance,
        bonusBalance: users.bonusBalance,
        createdAt: users.createdAt,
      })
      .from(users);

    const allUsers = await query.orderBy(desc(users.createdAt)).limit(100);

    return NextResponse.json({ success: true, users: allUsers });
  } catch (error: any) {
    console.error("Fetch users error:", error);
    return NextResponse.json({ error: "Failed to load users" }, { status: 500 });
  }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Super Admin privileges required" }, { status: 403 });
    }

    const body = await req.json();
    const { targetUserId, action, value, reason } = body;

    if (!targetUserId || !action) {
      return NextResponse.json({ error: "Target user and action required" }, { status: 400 });
    }

    const [target] = await db.select().from(users).where(eq(users.id, Number(targetUserId))).limit(1);
    if (!target) {
      return NextResponse.json({ error: "User not found" }, { status: 404 });
    }

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";

    if (action === "CHANGE_ROLE") {
      if (!["ADMIN", "STAFF", "USER"].includes(value)) {
        return NextResponse.json({ error: "Invalid role specified" }, { status: 400 });
      }
      await db.update(users).set({ role: value, updatedAt: new Date() }).where(eq(users.id, target.id));
      await logSecurityEvent(user.id, "USER_ROLE_CHANGED", `Changed ${target.username} role to ${value}`, "WARNING", clientIp);
      return NextResponse.json({ success: true, message: `Role updated to ${value}` });
    }

    if (action === "TOGGLE_STATUS") {
      const newStatus = target.status === "ACTIVE" ? "BANNED" : "ACTIVE";
      await db.update(users).set({ status: newStatus, updatedAt: new Date() }).where(eq(users.id, target.id));
      await logSecurityEvent(user.id, "USER_STATUS_TOGGLED", `User ${target.username} set to ${newStatus}`, "WARNING", clientIp);
      return NextResponse.json({ success: true, message: `User status changed to ${newStatus}` });
    }

    if (action === "ADJUST_BALANCE") {
      const adjustAmount = parseFloat(value);
      if (isNaN(adjustAmount) || adjustAmount === 0) {
        return NextResponse.json({ error: "Valid non-zero adjustment amount required" }, { status: 400 });
      }

      const current = parseFloat(target.walletBalance);
      const newBalance = (current + adjustAmount).toFixed(2);
      if (parseFloat(newBalance) < 0) {
        return NextResponse.json({ error: "Adjustment would result in negative balance" }, { status: 400 });
      }

      await db.update(users).set({ walletBalance: newBalance, updatedAt: new Date() }).where(eq(users.id, target.id));

      await db.insert(transactions).values({
        userId: target.id,
        type: "ADMIN_ADJUSTMENT",
        amount: adjustAmount > 0 ? `+${adjustAmount.toFixed(2)}` : adjustAmount.toFixed(2),
        balanceAfter: newBalance,
        description: `Manual admin balance adjustment: ${reason || "No reason specified"}`,
        referenceId: `ADMIN-ADJ-${Date.now()}`,
      });

      await logSecurityEvent(
        user.id,
        "MANUAL_BALANCE_ADJUSTMENT",
        `Adjusted ${target.username} balance by ${adjustAmount}. New Balance: ₹${newBalance}. Reason: ${reason}`,
        "WARNING",
        clientIp
      );

      return NextResponse.json({ success: true, message: `Balance adjusted by ₹${adjustAmount}. New Balance: ₹${newBalance}` });
    }

    return NextResponse.json({ error: "Unknown action" }, { status: 400 });
  } catch (error: any) {
    console.error("User update error:", error);
    return NextResponse.json({ error: "Failed to update user" }, { status: 500 });
  }
}

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Super Admin privileges required" }, { status: 403 });
    }

    const body = await req.json();
    const { username, password, role, phone, email } = body;

    if (!username || !password || !role) {
      return NextResponse.json({ error: "Username, password and role are required" }, { status: 400 });
    }

    const cleanUser = username.trim().toLowerCase();
    const existing = await db.select().from(users).where(eq(users.username, cleanUser)).limit(1);
    if (existing.length > 0) {
      return NextResponse.json({ error: "Username is already taken" }, { status: 409 });
    }

    const bcrypt = await import("bcryptjs");
    const passwordHash = await bcrypt.default.hash(password, 10);

    const [newUser] = await db
      .insert(users)
      .values({
        username: cleanUser,
        phone: phone ? phone.trim() : null,
        email: email ? email.trim().toLowerCase() : null,
        passwordHash,
        role: role as "ADMIN" | "STAFF" | "USER",
        status: "ACTIVE",
        walletBalance: "0.00",
        bonusBalance: "0.00",
      })
      .returning();

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(
      user.id,
      "ADMIN_CREATED_NEW_USER",
      `Created ${newUser.role} account for '${cleanUser}'`,
      "INFO",
      clientIp
    );

    return NextResponse.json({
      success: true,
      message: `New ${role} account created successfully for '${cleanUser}'!`,
      user: {
        id: newUser.id,
        username: newUser.username,
        role: newUser.role,
        status: newUser.status,
      },
    });
  } catch (error: any) {
    console.error("Create user error:", error);
    return NextResponse.json({ error: error.message || "Failed to create user" }, { status: 500 });
  }
}
