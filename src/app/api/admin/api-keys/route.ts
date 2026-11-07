import { NextRequest, NextResponse } from "next/server";
import { getAuthUser, logSecurityEvent } from "@/lib/auth";
import { db } from "@/db";
import { apiKeys } from "@/db/schema";
import { eq, desc } from "drizzle-orm";
import crypto from "crypto";
import bcrypt from "bcryptjs";

export const dynamic = "force-dynamic";

// GET: List all generated API keys (shows only hidden prefix, never full key)
export async function GET(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Access denied" }, { status: 403 });
    }

    const keys = await db
      .select({
        id: apiKeys.id,
        name: apiKeys.name,
        keyPrefix: apiKeys.keyPrefix,
        permissions: apiKeys.permissions,
        status: apiKeys.status,
        lastUsedAt: apiKeys.lastUsedAt,
        createdAt: apiKeys.createdAt,
      })
      .from(apiKeys)
      .orderBy(desc(apiKeys.createdAt));

    return NextResponse.json({ success: true, keys });
  } catch (error: any) {
    console.error("Fetch API keys error:", error);
    return NextResponse.json({ error: "Failed to load API keys" }, { status: 500 });
  }
}

// POST: Generate a new secret API Key (returned ONCE to the admin, then strictly hashed in DB)
export async function POST(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Access denied" }, { status: 403 });
    }

    const body = await req.json();
    const { name, permissions } = body;

    if (!name || name.trim().length < 3) {
      return NextResponse.json({ error: "Key name must be at least 3 characters" }, { status: 400 });
    }

    // Generate high-entropy 48-char hex secret
    const rawSecret = `mk_live_${crypto.randomBytes(24).toString("hex")}`;
    const keyPrefix = `${rawSecret.slice(0, 12)}...`;
    const keyHash = await bcrypt.hash(rawSecret, 10);

    const [newKey] = await db
      .insert(apiKeys)
      .values({
        name: name.trim(),
        keyHash,
        keyPrefix,
        permissions: permissions || "READ_WRITE",
        status: "ACTIVE",
        createdById: user.id,
      })
      .returning();

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(user.id, "API_KEY_GENERATED", `Generated API Key '${name}' (Prefix: ${keyPrefix})`, "INFO", clientIp);

    // Return rawSecret only once in response
    return NextResponse.json({
      success: true,
      message: "API Key generated successfully! Store this key securely; it will not be displayed again.",
      key: {
        id: newKey.id,
        name: newKey.name,
        keyPrefix: newKey.keyPrefix,
        secret: rawSecret, // Full secret provided once
        permissions: newKey.permissions,
        createdAt: newKey.createdAt,
      },
    });
  } catch (error: any) {
    console.error("Create API key error:", error);
    return NextResponse.json({ error: "Failed to generate API key" }, { status: 500 });
  }
}

// PATCH: Revoke or re-activate an API key
export async function PATCH(req: NextRequest) {
  try {
    const user = await getAuthUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Access denied" }, { status: 403 });
    }

    const body = await req.json();
    const { keyId, action } = body;

    if (!keyId || !action) {
      return NextResponse.json({ error: "Key ID and action required" }, { status: 400 });
    }

    const newStatus = action === "REVOKE" ? "REVOKED" : "ACTIVE";

    await db
      .update(apiKeys)
      .set({ status: newStatus })
      .where(eq(apiKeys.id, Number(keyId)));

    const clientIp = req.headers.get("x-forwarded-for") || "127.0.0.1";
    await logSecurityEvent(user.id, "API_KEY_STATUS_CHANGED", `API Key ID ${keyId} set to ${newStatus}`, "WARNING", clientIp);

    return NextResponse.json({ success: true, message: `Key status set to ${newStatus}` });
  } catch (error: any) {
    console.error("Update API key error:", error);
    return NextResponse.json({ error: "Failed to update key status" }, { status: 500 });
  }
}
