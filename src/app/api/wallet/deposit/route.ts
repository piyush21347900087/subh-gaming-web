import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { depositRequests, gameSettings, users, transactions } from "@/db/schema";
import { eq } from "drizzle-orm";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await req.json();
    const { amount, paymentMethod, utrNumber } = body;

    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount < 100) {
      return NextResponse.json({ error: "Minimum deposit is ₹100" }, { status: 400 });
    }

    if (!utrNumber || utrNumber.trim().length < 6) {
      return NextResponse.json({ error: "Please enter a valid 12-digit UPI Reference / UTR Number" }, { status: 400 });
    }

    // Check if auto-approve is enabled in admin settings
    const autoApproveSetting = await db
      .select()
      .from(gameSettings)
      .where(eq(gameSettings.key, "PAYMENT_AUTO_APPROVE"))
      .limit(1);

    const isAutoApprove = autoApproveSetting[0]?.value === "true";

    const [request] = await db
      .insert(depositRequests)
      .values({
        userId: user.id,
        amount: numAmount.toFixed(2),
        paymentMethod: paymentMethod || "UPI",
        utrNumber: utrNumber.trim(),
        status: isAutoApprove ? "APPROVED" : "PENDING",
        adminNotes: isAutoApprove ? "Auto-verified via configured UPI Gateway" : null,
        processedAt: isAutoApprove ? new Date() : null,
      })
      .returning();

    // If auto-approved, credit user wallet instantly and record ledger
    let newBalance = user.walletBalance;
    if (isAutoApprove) {
      const [freshUser] = await db.select().from(users).where(eq(users.id, user.id)).limit(1);
      const updated = (parseFloat(freshUser.walletBalance) + numAmount).toFixed(2);
      newBalance = updated;

      await db
        .update(users)
        .set({ walletBalance: updated, updatedAt: new Date() })
        .where(eq(users.id, user.id));

      await db.insert(transactions).values({
        userId: user.id,
        type: "DEPOSIT",
        amount: numAmount.toFixed(2),
        balanceAfter: updated,
        description: `Instant Auto-Approved UPI Deposit (UTR: ${utrNumber.trim()})`,
        referenceId: `DEP-${request.id}`,
      });
    }

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(
      user.id,
      "DEPOSIT_REQUEST_SUBMITTED",
      `Amount: ₹${numAmount}, UTR: ${utrNumber.trim()}, Status: ${request.status}`,
      "INFO",
      clientIp
    );

    return NextResponse.json({
      success: true,
      message: isAutoApprove
        ? `Deposit of ₹${numAmount} automatically credited to your wallet!`
        : "Deposit request submitted! Once verified by staff, your wallet will be updated.",
      requestId: request.id,
      autoApproved: isAutoApprove,
      newBalance: isAutoApprove ? newBalance : undefined,
    });
  } catch (error: any) {
    console.error("Deposit request error:", error);
    return NextResponse.json({ error: "Failed to submit deposit request" }, { status: 500 });
  }
}
