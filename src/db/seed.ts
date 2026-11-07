import "dotenv/config";
import { db } from "./index";
import { 
  users, 
  markets, 
  marketResults, 
  bets, 
  transactions, 
  gameSettings, 
  noticeBoard, 
  securityLogs,
  depositRequests,
  withdrawalRequests,
  sessions,
  apiKeys
} from "./schema";
import bcrypt from "bcryptjs";
import { sql } from "drizzle-orm";

export async function seedDatabase() {
  console.log("Starting complete database rebuild & fresh generation...");

  // Safely truncate and restart identities for fresh new database state
  await db.execute(sql`
    TRUNCATE TABLE 
      api_keys, 
      security_logs, 
      notice_board, 
      game_settings, 
      withdrawal_requests, 
      deposit_requests, 
      transactions, 
      bets, 
      market_results, 
      sessions, 
      markets, 
      users 
    RESTART IDENTITY CASCADE;
  `);

  console.log("All tables cleared. Generating brand new clean database structure...");

  // 1. Hash Passwords
  const adminPassword = await bcrypt.hash("MahadevKing@2025!", 10);
  const staffPassword = await bcrypt.hash("Staff@2025!", 10);
  const playerPassword = await bcrypt.hash("Player@12345", 10);

  const now = new Date();

  // 2. Insert Official Default Users (No fake demo amounts, real clean balances)
  const [adminUser] = await db.insert(users).values({
    username: "admin",
    email: "admin@subhgaming.com",
    phone: "9999900001",
    passwordHash: adminPassword,
    role: "ADMIN",
    status: "ACTIVE",
    walletBalance: "0.00",
    bonusBalance: "0.00",
    bankName: "HDFC Bank",
    accountNumber: "5010023498112",
    ifscCode: "HDFC0001234",
    upiId: "admin@hdfcbank",
    lastActiveAt: now,
  }).returning();

  const [staffUser] = await db.insert(users).values({
    username: "staff",
    email: "staff@subhgaming.com",
    phone: "9999900002",
    passwordHash: staffPassword,
    role: "STAFF",
    status: "ACTIVE",
    walletBalance: "0.00",
    bonusBalance: "0.00",
    lastActiveAt: now,
  }).returning();

  const [player1] = await db.insert(users).values({
    username: "player1",
    email: "player1@subhgaming.com",
    phone: "9876543210",
    passwordHash: playerPassword,
    role: "USER",
    status: "ACTIVE",
    walletBalance: "0.00",
    bonusBalance: "0.00",
    lastActiveAt: now,
  }).returning();

  const [player2] = await db.insert(users).values({
    username: "rahul_matka",
    email: "rahul@subhgaming.com",
    phone: "9812345678",
    passwordHash: playerPassword,
    role: "USER",
    status: "ACTIVE",
    walletBalance: "0.00",
    bonusBalance: "0.00",
    lastActiveAt: now,
  }).returning();

  const [player3] = await db.insert(users).values({
    username: "vikram_king",
    email: "vikram@subhgaming.com",
    phone: "9871122334",
    passwordHash: playerPassword,
    role: "USER",
    status: "ACTIVE",
    walletBalance: "0.00",
    bonusBalance: "0.00",
    lastActiveAt: now,
  }).returning();

  console.log("Users created successfully.");

  // 3. System Configuration & Payment Gateway API settings
  await db.insert(gameSettings).values([
    // Payment Gateway API Settings (Configurable directly from Admin Panel)
    { key: "PAYMENT_GATEWAY_PROVIDER", value: "UPI_DIRECT", description: "Payment mode: UPI_DIRECT, RAZORPAY, CASHFREE, PAYTM, or CUSTOM_QR" },
    { key: "UPI_ID", value: "mahadevking@icici", description: "Default Official Receiving UPI ID for deposits" },
    { key: "UPI_MERCHANT_NAME", value: "Mahadev King Official", description: "Payee Name shown on UPI payment apps" },
    { key: "PAYMENT_API_KEY", value: "rzp_live_9a8bc76d54ef1234", description: "Payment Gateway Public API Key" },
    { key: "PAYMENT_API_SECRET", value: "sec_live_9988776655443322", description: "Payment Gateway Secret Key (Hidden)" },
    { key: "PAYMENT_WEBHOOK_SECRET", value: "whsec_live_alpha_omega_99", description: "Webhook Verification Secret" },
    { key: "PAYMENT_AUTO_APPROVE", value: "true", description: "Enable instant automated balance credit on valid UPI UTR" },
    
    // Community & Social Groups (Configurable from Admin Panel)
    { key: "WHATSAPP_GROUP_URL", value: "https://chat.whatsapp.com/MahadevKingVIPClub2025", description: "Official Player WhatsApp Community Group" },
    { key: "TELEGRAM_CHANNEL_URL", value: "https://t.me/MahadevKingOfficial555", description: "Official Telegram Channel for Fast Leaks & Results" },
    { key: "HELPLINE_WHATSAPP", value: "+91 99999 88888", description: "Official 24x7 WhatsApp support number" },
    { key: "HELPLINE_TELEGRAM", value: "@MahadevSupport24x7", description: "24x7 Telegram Support Handle" },
    
    // Game Rates & Limits
    { key: "RATE_SINGLE_DIGIT", value: "9.5", description: "Multiplier for Single Digit (Ank)" },
    { key: "RATE_JODI_DIGIT", value: "95", description: "Multiplier for Jodi Digit" },
    { key: "RATE_SINGLE_PANA", value: "140", description: "Multiplier for Single Pana" },
    { key: "RATE_DOUBLE_PANA", value: "280", description: "Multiplier for Double Pana" },
    { key: "RATE_TRIPLE_PANA", value: "600", description: "Multiplier for Triple Pana" },
    { key: "RATE_HALF_SANGAM", value: "1000", description: "Multiplier for Half Sangam" },
    { key: "RATE_FULL_SANGAM", value: "10000", description: "Multiplier for Full Sangam" },
    { key: "MIN_DEPOSIT", value: "100", description: "Minimum deposit allowed in INR" },
    { key: "MIN_WITHDRAWAL", value: "500", description: "Minimum withdrawal allowed in INR" },
  ]);

  // 4. Notice Board
  await db.insert(noticeBoard).values([
    {
      title: "👑 Welcome to Subh Gaming & Mahadev King!",
      content: "Join our Official WhatsApp Group and Telegram Channel for daily VIP Super Jodi leaks, instant results, and live payment support!",
      type: "ANNOUNCEMENT",
      isActive: true,
    },
    {
      title: "⚡ Instant Automatic UPI Deposits & Fast Withdrawals",
      content: "Deposit through any UPI App (PhonePe, GPay, Paytm) with zero deduction. Automated 24x7 withdrawals processed in 10 minutes.",
      type: "OFFER",
      isActive: true,
    },
  ]);

  // 5. Markets Configuration
  const marketData = [
    {
      name: "Kalyan",
      slug: "kalyan",
      tagline: "India's #1 Most Popular Matka",
      openTime: "04:15 PM",
      closeTime: "06:15 PM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "138",
      currentOpenDigit: "2",
      currentCloseDigit: "8",
      currentClosePana: "468",
      fullResult: "138-28-468",
      category: "REGULAR",
      sortOrder: 1,
    },
    {
      name: "Milan Day",
      slug: "milan-day",
      tagline: "High Stakes Daily Matka",
      openTime: "03:00 PM",
      closeTime: "05:00 PM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "246",
      currentOpenDigit: "2",
      currentCloseDigit: "4",
      currentClosePana: "158",
      fullResult: "246-24-158",
      category: "REGULAR",
      sortOrder: 2,
    },
    {
      name: "Rajdhani Day",
      slug: "rajdhani-day",
      tagline: "Capital Special Draw",
      openTime: "03:15 PM",
      closeTime: "05:15 PM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "129",
      currentOpenDigit: "2",
      currentCloseDigit: "0",
      currentClosePana: "479",
      fullResult: "129-20-479",
      category: "REGULAR",
      sortOrder: 3,
    },
    {
      name: "Subh Gaming Special",
      slug: "subh-gaming-special",
      tagline: "Exclusive Super Jackpot Market",
      openTime: "01:30 PM",
      closeTime: "03:30 PM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "555",
      currentOpenDigit: "5",
      currentCloseDigit: "5",
      currentClosePana: "555",
      fullResult: "555-55-555",
      category: "KING_SPECIAL",
      sortOrder: 0,
    },
    {
      name: "Mahadev King Super",
      slug: "mahadev-king-super",
      tagline: "Royal High Roller Market",
      openTime: "04:00 PM",
      closeTime: "06:00 PM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "789",
      currentOpenDigit: "4",
      currentCloseDigit: "6",
      currentClosePana: "178",
      fullResult: "789-46-178",
      category: "KING_SPECIAL",
      sortOrder: 0,
    },
    {
      name: "Time Bazar",
      slug: "time-bazar",
      tagline: "Early Afternoon Express",
      openTime: "01:00 PM",
      closeTime: "02:00 PM",
      isActive: true,
      status: "CLOSED",
      currentOpenPana: "389",
      currentOpenDigit: "0",
      currentCloseDigit: "7",
      currentClosePana: "269",
      fullResult: "389-07-269",
      category: "REGULAR",
      sortOrder: 4,
    },
    {
      name: "Main Bazar",
      slug: "main-bazar",
      tagline: "The Midnight Grand Finale",
      openTime: "09:35 PM",
      closeTime: "12:05 AM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "450",
      currentOpenDigit: "9",
      currentCloseDigit: "*",
      currentClosePana: "***",
      fullResult: "450-9*-***",
      category: "REGULAR",
      sortOrder: 5,
    },
    {
      name: "Milan Night",
      slug: "milan-night",
      tagline: "Prime Night Market",
      openTime: "09:00 PM",
      closeTime: "11:00 PM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "238",
      currentOpenDigit: "3",
      currentCloseDigit: "*",
      currentClosePana: "***",
      fullResult: "238-3*-***",
      category: "REGULAR",
      sortOrder: 6,
    },
    {
      name: "Rajdhani Night",
      slug: "rajdhani-night",
      tagline: "Late Night Premier Draw",
      openTime: "09:25 PM",
      closeTime: "11:35 PM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "157",
      currentOpenDigit: "3",
      currentCloseDigit: "9",
      currentClosePana: "289",
      fullResult: "157-39-289",
      category: "REGULAR",
      sortOrder: 7,
    },
    {
      name: "Gali Disawar Special",
      slug: "gali-disawar",
      tagline: "Northern Express Fast Jodi",
      openTime: "11:00 PM",
      closeTime: "11:30 PM",
      isActive: true,
      status: "OPEN",
      currentOpenPana: "---",
      currentOpenDigit: "5",
      currentCloseDigit: "8",
      currentClosePana: "---",
      fullResult: "58",
      category: "GALI_DISAWAR",
      sortOrder: 8,
    },
  ];

  const insertedMarkets = await db.insert(markets).values(marketData).returning();
  console.log(`Created ${insertedMarkets.length} markets.`);

  // 6. Active Live Bets (Showing users playing the game right now)
  const todayStr = now.toISOString().split("T")[0];
  const kalyan = insertedMarkets.find(m => m.slug === "kalyan") || insertedMarkets[0];
  const subhMarket = insertedMarkets.find(m => m.slug === "subh-gaming-special") || insertedMarkets[3];

  await db.insert(bets).values([
    {
      userId: player1.id,
      marketId: kalyan.id,
      gameType: "SINGLE_DIGIT",
      sessionType: "OPEN",
      selectedNumber: "2",
      amount: "500.00",
      potentialWin: "4750.00",
      rateMultiplier: "9.50",
      status: "PENDING",
      wonAmount: "0.00",
      betDate: todayStr,
    },
    {
      userId: player1.id,
      marketId: kalyan.id,
      gameType: "JODI_DIGIT",
      sessionType: "OPEN",
      selectedNumber: "28",
      amount: "250.00",
      potentialWin: "23750.00",
      rateMultiplier: "95.00",
      status: "PENDING",
      wonAmount: "0.00",
      betDate: todayStr,
    },
    {
      userId: player2.id,
      marketId: subhMarket.id,
      gameType: "SINGLE_PANA",
      sessionType: "OPEN",
      selectedNumber: "555",
      amount: "100.00",
      potentialWin: "60000.00",
      rateMultiplier: "600.00",
      status: "PENDING",
      wonAmount: "0.00",
      betDate: todayStr,
    },
    {
      userId: player3.id,
      marketId: kalyan.id,
      gameType: "SINGLE_DIGIT",
      sessionType: "CLOSE",
      selectedNumber: "8",
      amount: "400.00",
      potentialWin: "3800.00",
      rateMultiplier: "9.50",
      status: "PENDING",
      wonAmount: "0.00",
      betDate: todayStr,
    },
  ]);

  // 7. Real Transactions Ledger starts clean without fake demo entries

  // 8. Historical Results
  for (let i = 1; i <= 7; i++) {
    const d = new Date(now);
    d.setDate(d.getDate() - i);
    const dateStr = d.toISOString().split("T")[0];

    await db.insert(marketResults).values({
      marketId: kalyan.id,
      resultDate: dateStr,
      openPana: `${(100 + i * 12).toString().slice(0, 3)}`,
      openDigit: `${(i * 3) % 10}`,
      closeDigit: `${(i * 7) % 10}`,
      closePana: `${(200 + i * 15).toString().slice(0, 3)}`,
      fullResult: `${(100 + i * 12).toString().slice(0, 3)}-${(i * 3) % 10}${(i * 7) % 10}-${(200 + i * 15).toString().slice(0, 3)}`,
      declaredBy: adminUser.id,
    });
  }

  // 9. Initial Security Log
  await db.insert(securityLogs).values([
    {
      userId: adminUser.id,
      action: "DATABASE_COMPLETELY_RESET_AND_GENERATED",
      ipAddress: "127.0.0.1",
      details: "Database reset cleanly. Fresh tables, live playing sessions, and payment API configurations initialized.",
      severity: "INFO",
    },
  ]);

  console.log("Brand new database initialized and seeded successfully!");
}

if (process.argv[1]?.endsWith("seed.ts")) {
  seedDatabase()
    .then(() => {
      console.log("Database generation finished.");
      process.exit(0);
    })
    .catch((err) => {
      console.error("Seed error:", err);
      process.exit(1);
    });
}
