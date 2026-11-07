import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { depositRequests, users, transactions } from "@/db/schema";
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
        id: depositRequests.id,
        userId: depositRequests.userId,
        username: users.username,
        phone: users.phone,
        amount: depositRequests.amount,
        paymentMethod: depositRequests.paymentMethod,
        utrNumber: depositRequests.utrNumber,
        status: depositRequests.status,
        adminNotes: depositRequests.adminNotes,
        createdAt: depositRequests.createdAt,
      })
      .from(depositRequests)
      .innerJoin(users, eq(depositRequests.userId, users.id))
      .orderBy(desc(depositRequests.createdAt))
      .limit(50);

    return NextResponse.json({ success: true, requests });
  } catch (error: any) {
    console.error("Fetch deposits error:", error);
    return NextResponse.json({ error: "Failed to load deposits" }, { status: 500 });
  }
}

export async function PATCH(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || (user.role !== "ADMIN" && user.role !== "STAFF")) {
      return NextResponse.json({ error: "Access denied" }, { status: 403 });
    }

    const body = await req.json();
    const { requestId, action, notes } = body; // action: 'APPROVE' | 'REJECT'

    if (!requestId || !action) {
      return NextResponse.json({ error: "Request ID and action are required" }, { status: 400 });
    }

    const [request] = await db
      .select()
      .from(depositRequests)
      .where(eq(depositRequests.id, Number(requestId)))
      .limit(1);

    if (!request) {
      return NextResponse.json({ error: "Deposit request not found" }, { status: 404 });
    }

    if (request.status !== "PENDING") {
      return NextResponse.json({ error: `Request already marked as ${request.status}` }, { status: 400 });
    }

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";

    if (action === "APPROVE") {
      // Fetch target user
      const [targetUser] = await db
        .select()
        .from(users)
        .where(eq(users.id, request.userId))
        .limit(1);

      if (!targetUser) {
        return NextResponse.json({ error: "Target user not found" }, { status: 404 });
      }

      const depositAmount = parseFloat(request.amount);
      const newBal = (parseFloat(targetUser.walletBalance) + depositAmount).toFixed(2);

      // Update user wallet
      await db
        .update(users)
        .set({
          walletBalance: newBal,
          updatedAt: new Date(),
        })
        .where(eq(users.id, targetUser.id));

      // Update deposit request
      await db
        .update(depositRequests)
        .set({
          status: "APPROVED",
          adminNotes: notes || "Approved by staff",
          processedBy: user.id,
          processedAt: new Date(),
        })
        .where(eq(depositRequests.id, request.id));

      // Add to transaction ledger
      await db.insert(transactions).values({
        userId: targetUser.id,
        type: "DEPOSIT",
        amount: request.amount,
        balanceAfter: newBal,
        description: `Deposit Approved via ${request.paymentMethod} (UTR: ${request.utrNumber})`,
        referenceId: `DEP-${request.id}`,
      });

      await logSecurityEvent(
        user.id,
        "DEPOSIT_APPROVED",
        `Deposit #${request.id} for ₹${request.amount} approved for user ${targetUser.username}`,
        "INFO",
        clientIp
      );

      return NextResponse.json({
        success: true,
        message: `Deposit of ₹${request.amount} approved for ${targetUser.username}. Balance updated.`,
      });
    } else if (action === "REJECT") {
      await db
        .update(depositRequests)
        .set({
          status: "REJECTED",
          adminNotes: notes || "Rejected - Invalid UTR or proof",
          processedBy: user.id,
          processedAt: new Date(),
        })
        .where(eq(depositRequests.id, request.id));

      await logSecurityEvent(
        user.id,
        "DEPOSIT_REJECTED",
        `Deposit #${request.id} rejected. Reason: ${notes || "None"}`,
        "WARNING",
        clientIp
      );

      return NextResponse.json({ success: true, message: "Deposit request rejected." });
    }

    return NextResponse.json({ error: "Invalid action" }, { status: 400 });
  } catch (error: any) {
    console.error("Deposit approval error:", error);
    return NextResponse.json({ error: "Failed to process deposit" }, { status: 500 });
  }
}
