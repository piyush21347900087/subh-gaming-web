import { NextRequest, NextResponse } from "next/server";
import { getAuthUser } from "@/lib/auth";

export const dynamic = "force-dynamic";

// In-memory support chat repository for live admin desk
let supportChats = [
  {
    id: 1,
    username: "player1",
    phone: "9876543210",
    message: "Namaste Sir, Kalyan Open draw result kitne baje aayega?",
    reply: "Kalyan Open result exactly 04:15 PM ko declare hota hai Sir.",
    status: "RESOLVED",
    time: "10 mins ago",
  },
  {
    id: 2,
    username: "rahul_matka",
    phone: "9812345678",
    message: "Sir UPI deposit kiya tha UTR 439810291039, please check.",
    reply: "Aapka deposit verify ho gaya hai aur wallet me add ho chuka hai.",
    status: "RESOLVED",
    time: "25 mins ago",
  },
  {
    id: 3,
    username: "vikram_king",
    phone: "9871122334",
    message: "Jodi Digit ka payout rate kitna hai?",
    reply: "Jodi Digit ka rate 95x hai (10 ka 950).",
    status: "PENDING",
    time: "Just now",
  },
];

export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || (user.role !== "ADMIN" && user.role !== "STAFF")) {
      return NextResponse.json({ error: "Access denied" }, { status: 403 });
    }

    return NextResponse.json({
      success: true,
      chats: supportChats,
      unreadCount: supportChats.filter((c) => c.status === "PENDING").length,
    });
  } catch (error: any) {
    console.error("Chat error:", error);
    return NextResponse.json({ error: "Failed to load chats" }, { status: 500 });
  }
}

export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || (user.role !== "ADMIN" && user.role !== "STAFF")) {
      return NextResponse.json({ error: "Access denied" }, { status: 403 });
    }

    const body = await req.json();
    const { chatId, replyText } = body;

    const chat = supportChats.find((c) => c.id === Number(chatId));
    if (chat) {
      chat.reply = replyText;
      chat.status = "RESOLVED";
    }

    return NextResponse.json({
      success: true,
      message: "Reply sent to player successfully!",
      chats: supportChats,
    });
  } catch (error: any) {
    console.error("Chat reply error:", error);
    return NextResponse.json({ error: "Failed to send reply" }, { status: 500 });
  }
}
