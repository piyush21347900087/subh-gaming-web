import { NextRequest, NextResponse } from "next/server";
import { db } from "@/db";
import { users, markets, bets, transactions } from "@/db/schema";
import { eq, sql } from "drizzle-orm";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { GAME_RATES } from "@/lib/matka";

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user) {
      return NextResponse.json({ error: "Please log in to place bets" }, { status: 401 });
    }

    const body = await req.json();
    const { marketId, gameType, sessionType, betsList } = body;

    if (!marketId || !gameType || !betsList || !Array.isArray(betsList) || betsList.length === 0) {
      return NextResponse.json({ error: "Invalid bet request parameters" }, { status: 400 });
    }

    const rateInfo = GAME_RATES[gameType];
    if (!rateInfo) {
      return NextResponse.json({ error: "Invalid game type selected" }, { status: 400 });
    }

    // Verify Market
    const [market] = await db
      .select()
      .from(markets)
      .where(eq(markets.id, Number(marketId)))
      .limit(1);

    if (!market || !market.isActive) {
      return NextResponse.json({ error: "This market is not active or not accepting bets." }, { status: 400 });
    }

    if (market.status === "CLOSED") {
      return NextResponse.json({ error: "This market is currently closed for betting." }, { status: 400 });
    }

    // Calculate total amount and validate numbers
    let totalBetAmount = 0;
    const validatedBets: Array<{ number: string; amount: number; potentialWin: number }> = [];

    for (const b of betsList) {
      const numStr = String(b.number).trim();
      const amt = Number(b.amount);

      if (isNaN(amt) || amt < 10) {
        return NextResponse.json({ error: `Minimum bet amount is ₹10. Found ₹${amt} on number ${numStr}` }, { status: 400 });
      }

      if (amt > 100000) {
        return NextResponse.json({ error: `Maximum bet amount is ₹1,00,000 per digit.` }, { status: 400 });
      }

      // Format check
      if (gameType === "SINGLE_DIGIT" && !/^\d$/.test(numStr)) {
        return NextResponse.json({ error: `Single digit must be 0-9. Found: ${numStr}` }, { status: 400 });
      }
      if (gameType === "JODI_DIGIT" && !/^\d{2}$/.test(numStr)) {
        return NextResponse.json({ error: `Jodi must be 2 digits (00-99). Found: ${numStr}` }, { status: 400 });
      }
      if ((gameType === "SINGLE_PANA" || gameType === "DOUBLE_PANA" || gameType === "TRIPLE_PANA") && !/^\d{3}$/.test(numStr)) {
        return NextResponse.json({ error: `Pana must be 3 digits. Found: ${numStr}` }, { status: 400 });
      }

      const potWin = amt * rateInfo.rate;
      totalBetAmount += amt;
      validatedBets.push({
        number: numStr,
        amount: amt,
        potentialWin: potWin,
      });
    }

    // Check user balance from fresh DB query
    const [dbUser] = await db
      .select()
      .from(users)
      .where(eq(users.id, user.id))
      .limit(1);

    const currentBalance = parseFloat(dbUser.walletBalance);
    if (currentBalance < totalBetAmount) {
      return NextResponse.json(
        { error: `Insufficient wallet balance. You have ₹${currentBalance.toFixed(2)}, required ₹${totalBetAmount.toFixed(2)}.` },
        { status: 400 }
      );
    }

    const newBalance = (currentBalance - totalBetAmount).toFixed(2);
    const todayStr = new Date().toISOString().split("T")[0];

    // Deduct balance
    await db
      .update(users)
      .set({
        walletBalance: newBalance,
        updatedAt: new Date(),
      })
      .where(eq(users.id, user.id));

    // Insert bets
    const betInsertRecords = validatedBets.map((b) => ({
      userId: user.id,
      marketId: market.id,
      gameType,
      sessionType: sessionType || "OPEN",
      selectedNumber: b.number,
      amount: b.amount.toFixed(2),
      potentialWin: b.potentialWin.toFixed(2),
      rateMultiplier: rateInfo.rate.toFixed(2),
      status: "PENDING",
      betDate: todayStr,
    }));

    const inserted = await db.insert(bets).values(betInsertRecords).returning();

    // Insert transaction
    await db.insert(transactions).values({
      userId: user.id,
      type: "BET_PLACED",
      amount: `-${totalBetAmount.toFixed(2)}`,
      balanceAfter: newBalance,
      description: `Wager on ${market.name} - ${rateInfo.label} (${validatedBets.length} bids)`,
      referenceId: `BETS-${inserted.map(b => b.id).join(",")}`.slice(0, 100),
    });

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(
      user.id,
      "BET_PLACED",
      `Placed ${validatedBets.length} bets on ${market.name} for ₹${totalBetAmount}`,
      "INFO",
      clientIp
    );

    return NextResponse.json({
      success: true,
      message: `Successfully placed ${validatedBets.length} bet(s)! Total: ₹${totalBetAmount}`,
      betsCount: validatedBets.length,
      totalAmount: totalBetAmount,
      newBalance,
    });
  } catch (error: any) {
    console.error("Place bet error:", error);
    return NextResponse.json({ error: "Failed to place bet. Please try again." }, { status: 500 });
  }
}
