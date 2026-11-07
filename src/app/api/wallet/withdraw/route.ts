import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { users, withdrawalRequests, transactions } from "@/db/schema";
import { eq } from "drizzle-orm";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await req.json();
    const { amount, payoutMethod, payoutDetails } = body;

    const numAmount = parseFloat(amount);
    if (isNaN(numAmount) || numAmount < 500) {
      return NextResponse.json({ error: "Minimum withdrawal amount is ₹500" }, { status: 400 });
    }

    if (!payoutDetails || payoutDetails.trim().length < 4) {
      return NextResponse.json({ error: "Please enter your UPI ID or Bank Account with IFSC code." }, { status: 400 });
    }

    // Check user balance
    const [dbUser] = await db
      .select()
      .from(users)
      .where(eq(users.id, user.id))
      .limit(1);

    const currentBalance = parseFloat(dbUser.walletBalance);
    if (currentBalance < numAmount) {
      return NextResponse.json(
        { error: `Insufficient balance. Available: ₹${currentBalance.toFixed(2)}, Requested: ₹${numAmount.toFixed(2)}` },
        { status: 400 }
      );
    }

    const newBalance = (currentBalance - numAmount).toFixed(2);

    // Deduct balance to escrow immediately
    await db
      .update(users)
      .set({
        walletBalance: newBalance,
        updatedAt: new Date(),
      })
      .where(eq(users.id, user.id));

    // Create withdrawal request
    const [reqRecord] = await db
      .insert(withdrawalRequests)
      .values({
        userId: user.id,
        amount: numAmount.toFixed(2),
        payoutMethod: payoutMethod || "UPI",
        payoutDetails: payoutDetails.trim(),
        status: "PENDING",
      })
      .returning();

    // Create transaction ledger
    await db.insert(transactions).values({
      userId: user.id,
      type: "WITHDRAWAL",
      amount: `-${numAmount.toFixed(2)}`,
      balanceAfter: newBalance,
      description: `Withdrawal request to ${payoutDetails.trim()} (Pending)`,
      referenceId: `WDR-${reqRecord.id}`,
    });

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(
      user.id,
      "WITHDRAWAL_REQUEST_SUBMITTED",
      `Amount: ₹${numAmount}, Target: ${payoutDetails.trim()}`,
      "INFO",
      clientIp
    );

    return NextResponse.json({
      success: true,
      message: "Withdrawal request submitted! Payout will be processed to your account.",
      requestId: reqRecord.id,
      newBalance,
    });
  } catch (error: any) {
    console.error("Withdrawal error:", error);
    return NextResponse.json({ error: "Failed to submit withdrawal request" }, { status: 500 });
  }
}
