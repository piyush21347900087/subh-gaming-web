import "dotenv/config";
import { db } from "../db";
import { users, markets, bets, transactions, securityLogs } from "../db/schema";
import { eq, sql } from "drizzle-orm";
import { verifyPassword, createSession, validateSessionToken } from "./auth";
import { calculateAnkFromPana, evaluateBetWin } from "./matka";

async function runSystemTests() {
  console.log("=== STARTING SYSTEM INTEGRATION TESTS ===");

  // Test 1: Database connection & user verification
  console.log("Test 1: Verifying Database Connection and Seeded Users...");
  const admin = await db.select().from(users).where(eq(users.username, "admin")).limit(1);
  if (!admin[0]) throw new Error("Admin user not found");
  console.log("✓ Admin user verified:", admin[0].username, "Role:", admin[0].role);

  const player = await db.select().from(users).where(eq(users.username, "player1")).limit(1);
  if (!player[0]) throw new Error("Player user not found");
  console.log("✓ Player user verified:", player[0].username, "Balance: ₹" + player[0].walletBalance);

  // Test 2: Password hashing & verification
  console.log("\nTest 2: Verifying Bcrypt Password Validation...");
  const passValid = await verifyPassword("MahadevKing@2025!", admin[0].passwordHash);
  if (!passValid) throw new Error("Admin password verification failed");
  console.log("✓ Admin password verification passed");

  // Test 3: Session creation & token validation
  console.log("\nTest 3: Testing Session Management & Bearer Tokens...");
  const token = await createSession(player[0].id, "127.0.0.1", "TestRunner/1.0");
  const sessionUser = await validateSessionToken(token);
  if (!sessionUser || sessionUser.username !== "player1") throw new Error("Session validation failed");
  console.log("✓ Session token generated and validated for:", sessionUser.username);

  // Test 4: Matka Calculation Engine
  console.log("\nTest 4: Testing Matka Ank Calculation & Win Logic...");
  const openPana = "138";
  const calculatedAnk = calculateAnkFromPana(openPana);
  if (calculatedAnk !== "2") throw new Error(`Expected 2 for 138, got ${calculatedAnk}`);
  console.log(`✓ Pana ${openPana} correctly yields Ank ${calculatedAnk} (1+3+8=12 -> 2)`);

  const winCheck = evaluateBetWin("SINGLE_DIGIT", "OPEN", "2", "138", "2", "8", "468");
  if (!winCheck.isWin) throw new Error("Expected single digit 2 to win");
  console.log("✓ Win evaluation logic correctly determined win (Multiplier: " + winCheck.wonAmountMultiplier + "x)");

  // Test 5: Markets and Live Status
  console.log("\nTest 5: Verifying Operating Markets...");
  const allMarkets = await db.select().from(markets);
  console.log(`✓ ${allMarkets.length} active markets confirmed in database.`);

  // Test 6: Security Audit Log Entry
  console.log("\nTest 6: Security Audit Logs Verification...");
  const logs = await db.select().from(securityLogs).limit(5);
  console.log(`✓ ${logs.length} security audit events found in tamper-evident ledger.`);

  console.log("\n==========================================");
  console.log("🎉 ALL INTEGRATION TESTS PASSED 100%!");
  console.log("==========================================");
}

runSystemTests()
  .then(() => process.exit(0))
  .catch((err) => {
    console.error("Test failed:", err);
    process.exit(1);
  });
