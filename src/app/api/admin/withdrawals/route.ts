import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { withdrawalRequests, users, transactions } from "@/db/schema";
import { eq, desc } from "drizzle-orm";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || (user.role !== "ADMIN" && user.role !== "STAFF")) {
      return NextResponse.json({ error: "Access denied" }, { status: 403 });
    }

    const requests = await db
      .select({
        id: withdrawalRequests.id,
        userId: withdrawalRequests.userId,
        username: users.username,
        phone: users.phone,
        amount: withdrawalRequests.amount,
        payoutMethod: withdrawalRequests.payoutMethod,
        payoutDetails: withdrawalRequests.payoutDetails,
        status: withdrawalRequests.status,
        adminNotes: withdrawalRequests.adminNotes,
        createdAt: withdrawalRequests.createdAt,
      })
      .from(withdrawalRequests)
      .innerJoin(users, eq(withdrawalRequests.userId, users.id))
      .orderBy(desc(withdrawalRequests.createdAt))
      .limit(50);

    return NextResponse.json({ success: true, requests });
  } catch (error: any) {
    console.error("Fetch withdrawals error:", error);
    return NextResponse.json({ error: "Failed to load withdrawals" }, { status: 500 });
  }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || (user.role !== "ADMIN" && user.role !== "STAFF")) {
      return NextResponse.json({ error: "Access denied" }, { status: 403 });
    }

    const body = await req.json();
    const { requestId, action, notes } = body;

    if (!requestId || !action) {
      return NextResponse.json({ error: "Request ID and action are required" }, { status: 400 });
    }

    const [request] = await db
      .select()
      .from(withdrawalRequests)
      .where(eq(withdrawalRequests.id, Number(requestId)))
      .limit(1);

    if (!request) {
      return NextResponse.json({ error: "Withdrawal request not found" }, { status: 404 });
    }

    if (request.status !== "PENDING") {
      return NextResponse.json({ error: `Request already marked as ${request.status}` }, { status: 400 });
    }

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";

    if (action === "APPROVE") {
      await db
        .update(withdrawalRequests)
        .set({
          status: "APPROVED",
          adminNotes: notes || "Payout completed successfully.",
          processedBy: user.id,
          processedAt: new Date(),
        })
        .where(eq(withdrawalRequests.id, request.id));

      await logSecurityEvent(
        user.id,
        "WITHDRAWAL_APPROVED",
        `Withdrawal #${request.id} for ₹${request.amount} marked APPROVED`,
        "INFO",
        clientIp
      );

      return NextResponse.json({ success: true, message: `Withdrawal of ₹${request.amount} marked as paid!` });
    } else if (action === "REJECT") {
      // Refund wallet balance
      const [targetUser] = await db
        .select()
        .from(users)
        .where(eq(users.id, request.userId))
        .limit(1);

      if (targetUser) {
        const refundedBal = (parseFloat(targetUser.walletBalance) + parseFloat(request.amount)).toFixed(2);
        await db
          .update(users)
          .set({
            walletBalance: refundedBal,
            updatedAt: new Date(),
          })
          .where(eq(users.id, targetUser.id));

        // Ledger refund
        await db.insert(transactions).values({
          userId: targetUser.id,
          type: "ADMIN_ADJUSTMENT",
          amount: request.amount,
          balanceAfter: refundedBal,
          description: `Withdrawal #${request.id} Rejected - Escrow Refunded. Reason: ${notes || "None"}`,
          referenceId: `REFUND-WDR-${request.id}`,
        });
      }

      await db
        .update(withdrawalRequests)
        .set({
          status: "REJECTED",
          adminNotes: notes || "Rejected by administration",
          processedBy: user.id,
          processedAt: new Date(),
        })
        .where(eq(withdrawalRequests.id, request.id));

      await logSecurityEvent(
        user.id,
        "WITHDRAWAL_REJECTED",
        `Withdrawal #${request.id} rejected and ₹${request.amount} refunded to user.`,
        "WARNING",
        clientIp
      );

      return NextResponse.json({ success: true, message: "Withdrawal rejected and amount refunded to user wallet." });
    }

    return NextResponse.json({ error: "Invalid action" }, { status: 400 });
  } catch (error: any) {
    console.error("Withdrawal approval error:", error);
    return NextResponse.json({ error: "Failed to process withdrawal" }, { status: 500 });
  }
}
