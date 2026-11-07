import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { gameSettings } from "@/db/schema";
import { eq, inArray } from "drizzle-orm";

export const dynamic = "force-dynamic";

// Public settings accessible by users (WhatsApp group, Telegram group, official UPI, Helpline)
const PUBLIC_KEYS = [
  "UPI_ID",
  "UPI_MERCHANT_NAME",
  "PAYMENT_GATEWAY_PROVIDER",
  "WHATSAPP_GROUP_URL",
  "TELEGRAM_CHANNEL_URL",
  "HELPLINE_WHATSAPP",
  "HELPLINE_TELEGRAM",
  "MIN_DEPOSIT",
  "MIN_WITHDRAWAL",
  "RATE_SINGLE_DIGIT",
  "RATE_JODI_DIGIT",
  "RATE_SINGLE_PANA",
  "RATE_DOUBLE_PANA",
  "RATE_TRIPLE_PANA",
];

// GET: Fetch system settings (Admin gets all including secrets; public gets only non-sensitive config)
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    const isAdmin = user && (user.role === "ADMIN" || user.role === "STAFF");

    const allSettings = await db.select().from(gameSettings);
    const settingsMap: Record<string, string> = {};

    for (const s of allSettings) {
      if (isAdmin) {
        settingsMap[s.key] = s.value;
      } else if (PUBLIC_KEYS.includes(s.key)) {
        settingsMap[s.key] = s.value;
      }
    }

    return NextResponse.json({
      success: true,
      settings: settingsMap,
      isAdmin: !!isAdmin,
    });
  } catch (error: any) {
    console.error("Fetch settings error:", error);
    return NextResponse.json({ error: "Failed to load settings" }, { status: 500 });
  }
}

// POST: Admin updates payment gateway keys, WhatsApp group, Telegram link, UPI ID
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Access denied. Only Super Admin can modify system settings." }, { status: 403 });
    }

    const body = await req.json();
    const { settings } = body;

    if (!settings || typeof settings !== "object") {
      return NextResponse.json({ error: "Settings object required" }, { status: 400 });
    }

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";

    // Upsert each key-value pair
    for (const [key, value] of Object.entries(settings)) {
      if (typeof value === "string") {
        const existing = await db.select().from(gameSettings).where(eq(gameSettings.key, key)).limit(1);
        if (existing.length > 0) {
          await db
            .update(gameSettings)
            .set({ value: String(value), updatedAt: new Date() })
            .where(eq(gameSettings.key, key));
        } else {
          await db.insert(gameSettings).values({
            key,
            value: String(value),
            description: `Configured via Admin Panel`,
          });
        }
      }
    }

    await logSecurityEvent(
      user.id,
      "SYSTEM_SETTINGS_UPDATED",
      `Admin updated payment gateway / social settings: ${Object.keys(settings).join(", ")}`,
      "INFO",
      clientIp
    );

    return NextResponse.json({
      success: true,
      message: "System settings, Payment Gateway & Community Links updated successfully!",
    });
  } catch (error: any) {
    console.error("Update settings error:", error);
    return NextResponse.json({ error: "Failed to save settings" }, { status: 500 });
  }
}
