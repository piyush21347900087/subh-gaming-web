import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { bets, users, markets, transactions } from "@/db/schema";
import { eq, and, desc, sql, ilike, or } from "drizzle-orm";

export const dynamic = "force-dynamic";

// GET: Fetch comprehensive bets report and Number-Wise Exposure Sheet (Boli Book)
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || (user.role !== "ADMIN" && user.role !== "STAFF")) {
      return NextResponse.json({ error: "Access denied: Staff or Admin role required." }, { status: 403 });
    }

    const { searchParams } = new URL(req.url);
    const marketId = searchParams.get("marketId");
    const gameType = searchParams.get("gameType");
    const sessionType = searchParams.get("sessionType");
    const status = searchParams.get("status");
    const searchNumber = searchParams.get("number");
    const searchUser = searchParams.get("search");

    // Build filters array
    const conditions = [];

    if (marketId && marketId !== "ALL") {
      conditions.push(eq(bets.marketId, Number(marketId)));
    }
    if (gameType && gameType !== "ALL") {
      conditions.push(eq(bets.gameType, gameType));
    }
    if (sessionType && sessionType !== "ALL") {
      conditions.push(eq(bets.sessionType, sessionType));
    }
    if (status && status !== "ALL") {
      conditions.push(eq(bets.status, status));
    }
    if (searchNumber) {
      conditions.push(eq(bets.selectedNumber, searchNumber.trim()));
    }

    const whereClause = conditions.length > 0 ? and(...conditions) : undefined;

    // Fetch detailed bets with user & market joins
    const allBets = await db
      .select({
        id: bets.id,
        userId: bets.userId,
        username: users.username,
        userPhone: users.phone,
        marketId: bets.marketId,
        marketName: markets.name,
        gameType: bets.gameType,
        sessionType: bets.sessionType,
        selectedNumber: bets.selectedNumber,
        amount: bets.amount,
        potentialWin: bets.potentialWin,
        rateMultiplier: bets.rateMultiplier,
        status: bets.status,
        wonAmount: bets.wonAmount,
        betDate: bets.betDate,
        createdAt: bets.createdAt,
        settledAt: bets.settledAt,
      })
      .from(bets)
      .innerJoin(users, eq(bets.userId, users.id))
      .innerJoin(markets, eq(bets.marketId, markets.id))
      .where(whereClause)
      .orderBy(desc(bets.createdAt))
      .limit(200);

    // Filter by user search if provided
    const filteredBets = searchUser
      ? allBets.filter(
          (b) =>
            b.username.toLowerCase().includes(searchUser.toLowerCase()) ||
            (b.userPhone && b.userPhone.includes(searchUser))
        )
      : allBets;

    // Calculate Number-Wise Exposure Sheet (Total money placed on each number)
    // Grouping by selectedNumber for current filter
    const numberExposureMap: Record<
      string,
      { number: string; gameType: string; totalAmount: number; count: number; potentialPayout: number }
    > = {};

    let totalStaked = 0;
    let totalPotentialLiability = 0;

    for (const b of filteredBets) {
      const amt = parseFloat(b.amount) || 0;
      const win = parseFloat(b.potentialWin) || 0;
      totalStaked += amt;
      totalPotentialLiability += win;

      const key = `${b.gameType}:${b.selectedNumber}`;
      if (!numberExposureMap[key]) {
        numberExposureMap[key] = {
          number: b.selectedNumber,
          gameType: b.gameType,
          totalAmount: 0,
          count: 0,
          potentialPayout: 0,
        };
      }
      numberExposureMap[key].totalAmount += amt;
      numberExposureMap[key].count += 1;
      numberExposureMap[key].potentialPayout += win;
    }

    // Sort exposure numbers by highest stake first
    const numberExposureList = Object.values(numberExposureMap).sort(
      (a, b) => b.totalAmount - a.totalAmount
    );

    return NextResponse.json({
      success: true,
      totalCount: filteredBets.length,
      totalStaked: totalStaked.toFixed(2),
      totalPotentialLiability: totalPotentialLiability.toFixed(2),
      bets: filteredBets,
      numberExposure: numberExposureList,
    });
  } catch (error: any) {
    console.error("Admin fetch bets error:", error);
    return NextResponse.json({ error: "Failed to fetch bets ledger" }, { status: 500 });
  }
}

// PATCH: Admin can refund/cancel an erroneous bet
export async function PATCH(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Only Super Admin can refund or cancel bets." }, { status: 403 });
    }

    const body = await req.json();
    const { betId, action, reason } = body;

    if (!betId || action !== "REFUND") {
      return NextResponse.json({ error: "Bet ID and valid action required" }, { status: 400 });
    }

    const [targetBet] = await db
      .select()
      .from(bets)
      .where(eq(bets.id, Number(betId)))
      .limit(1);

    if (!targetBet) {
      return NextResponse.json({ error: "Bet record not found" }, { status: 404 });
    }

    if (targetBet.status !== "PENDING") {
      return NextResponse.json({ error: `Cannot refund bet in '${targetBet.status}' state.` }, { status: 400 });
    }

    // Mark bet as REFUNDED
    await db
      .update(bets)
      .set({
        status: "REFUNDED",
        settledAt: new Date(),
      })
      .where(eq(bets.id, targetBet.id));

    // Refund money back to user wallet
    const [targetUser] = await db
      .select()
      .from(users)
      .where(eq(users.id, targetBet.userId))
      .limit(1);

    let newBalance = "0.00";
    if (targetUser) {
      const refundAmt = parseFloat(targetBet.amount);
      newBalance = (parseFloat(targetUser.walletBalance) + refundAmt).toFixed(2);

      await db
        .update(users)
        .set({
          walletBalance: newBalance,
          updatedAt: new Date(),
        })
        .where(eq(users.id, targetUser.id));

      // Ledger entry
      await db.insert(transactions).values({
        userId: targetUser.id,
        type: "BET_REFUND",
        amount: targetBet.amount,
        balanceAfter: newBalance,
        description: `Bet #${targetBet.id} cancelled & refunded by Admin (${targetBet.gameType}: ${targetBet.selectedNumber}). Reason: ${reason || "Cancelled by admin"}`,
        referenceId: `REFUND-BET-${targetBet.id}`,
      });
    }

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(
      user.id,
      "BET_REFUNDED_BY_ADMIN",
      `Refunded Bet #${targetBet.id} of ₹${targetBet.amount} on number '${targetBet.selectedNumber}' to user ID ${targetBet.userId}. Reason: ${reason || "None"}`,
      "WARNING",
      clientIp
    );

    return NextResponse.json({
      success: true,
      message: `Bet #${targetBet.id} refunded successfully! ₹${targetBet.amount} credited back to user wallet.`,
      newBalance,
    });
  } catch (error: any) {
    console.error("Admin refund bet error:", error);
    return NextResponse.json({ error: "Failed to refund bet" }, { status: 500 });
  }
}
