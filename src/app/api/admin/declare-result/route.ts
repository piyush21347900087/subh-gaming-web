import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { markets, marketResults, bets, users, transactions } from "@/db/schema";
import { eq, and, sql } from "drizzle-orm";
import { calculateAnkFromPana, evaluateBetWin } from "@/lib/matka";

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Access denied. Only Super Admin can declare market results." }, { status: 403 });
    }

    const body = await req.json();
    const { marketId, openPana, closePana } = body;

    if (!marketId) {
      return NextResponse.json({ error: "Market ID is required" }, { status: 400 });
    }

    const [market] = await db
      .select()
      .from(markets)
      .where(eq(markets.id, Number(marketId)))
      .limit(1);

    if (!market) {
      return NextResponse.json({ error: "Market not found" }, { status: 404 });
    }

    // Determine Open Pana & Ank
    let computedOpenPana = openPana ? String(openPana).trim() : market.currentOpenPana;
    let computedOpenDigit = computedOpenPana && computedOpenPana.length === 3 ? calculateAnkFromPana(computedOpenPana) : market.currentOpenDigit;

    // Determine Close Pana & Ank
    let computedClosePana = closePana ? String(closePana).trim() : market.currentClosePana;
    let computedCloseDigit = computedClosePana && computedClosePana.length === 3 ? calculateAnkFromPana(computedClosePana) : market.currentCloseDigit;

    // Format full result string
    const p1 = computedOpenPana || "***";
    const d1 = computedOpenDigit || "*";
    const d2 = computedCloseDigit || "*";
    const p2 = computedClosePana || "***";
    const formattedResult = `${p1}-${d1}${d2}-${p2}`;

    // Update Market
    await db
      .update(markets)
      .set({
        currentOpenPana: computedOpenPana,
        currentOpenDigit: computedOpenDigit,
        currentCloseDigit: computedCloseDigit,
        currentClosePana: computedClosePana,
        fullResult: formattedResult,
        updatedAt: new Date(),
      })
      .where(eq(markets.id, market.id));

    // Save to historical records
    const todayStr = new Date().toISOString().split("T")[0];
    await db.insert(marketResults).values({
      marketId: market.id,
      resultDate: todayStr,
      openPana: computedOpenPana,
      openDigit: computedOpenDigit,
      closeDigit: computedCloseDigit,
      closePana: computedClosePana,
      fullResult: formattedResult,
      declaredBy: user.id,
    });

    // Evaluate Pending Bets
    const pendingBets = await db
      .select()
      .from(bets)
      .where(and(eq(bets.marketId, market.id), eq(bets.status, "PENDING")));

    let settledCount = 0;
    let winCount = 0;
    let totalWinPayout = 0;

    for (const b of pendingBets) {
      // Check if this bet can be settled
      let canSettle = false;

      if (b.sessionType === "OPEN" && computedOpenDigit) {
        canSettle = true;
      } else if (b.sessionType === "CLOSE" && computedCloseDigit) {
        canSettle = true;
      } else if (b.gameType === "JODI_DIGIT" && computedOpenDigit && computedCloseDigit) {
        canSettle = true;
      }

      if (!canSettle) continue;

      const { isWin, wonAmountMultiplier } = evaluateBetWin(
        b.gameType,
        b.sessionType,
        b.selectedNumber,
        computedOpenPana,
        computedOpenDigit,
        computedCloseDigit,
        computedClosePana
      );

      settledCount++;

      if (isWin) {
        winCount++;
        const betAmt = parseFloat(b.amount);
        const winAmount = (betAmt * wonAmountMultiplier).toFixed(2);
        totalWinPayout += parseFloat(winAmount);

        // Update bet
        await db
          .update(bets)
          .set({
            status: "WON",
            wonAmount: winAmount,
            settledAt: new Date(),
          })
          .where(eq(bets.id, b.id));

        // Credit user wallet atomically
        const [targetUser] = await db
          .select()
          .from(users)
          .where(eq(users.id, b.userId))
          .limit(1);

        if (targetUser) {
          const updatedBal = (parseFloat(targetUser.walletBalance) + parseFloat(winAmount)).toFixed(2);

          await db
            .update(users)
            .set({
              walletBalance: updatedBal,
              updatedAt: new Date(),
            })
            .where(eq(users.id, targetUser.id));

          // Log winning transaction
          await db.insert(transactions).values({
            userId: targetUser.id,
            type: "BET_WIN",
            amount: winAmount,
            balanceAfter: updatedBal,
            description: `Winning payout on ${market.name} (${b.gameType}: ${b.selectedNumber})`,
            referenceId: `WIN-BET-${b.id}`,
          });
        }
      } else {
        // Mark as Lost
        await db
          .update(bets)
          .set({
            status: "LOST",
            wonAmount: "0.00",
            settledAt: new Date(),
          })
          .where(eq(bets.id, b.id));
      }
    }

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(
      user.id,
      "RESULT_DECLARED",
      `Market: ${market.name}, Result: ${formattedResult}, Bets Settled: ${settledCount}, Winners: ${winCount}, Payout: ₹${totalWinPayout.toFixed(2)}`,
      "INFO",
      clientIp
    );

    return NextResponse.json({
      success: true,
      message: `Result declared for ${market.name}! Settled ${settledCount} bets (${winCount} winners, Total Payout: ₹${totalWinPayout.toFixed(2)})`,
      marketResult: formattedResult,
      settledCount,
      winCount,
      totalWinPayout,
    });
  } catch (error: any) {
    console.error("Declare result error:", error);
    return NextResponse.json({ error: "Failed to declare result" }, { status: 500 });
  }
}
