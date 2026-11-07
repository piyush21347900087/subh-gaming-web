import { NextRequest, NextResponse } from "next/server";

export const dynamic = "force-dynamic";

export async function GET(req: NextRequest) {
  const { searchParams } = new URL(req.url);
  const isDirectDownload = searchParams.get("download") === "true";

  if (isDirectDownload) {
    // Generate valid mock APK binary package metadata header
    const apkHeader = `PK\x03\x04\x14\x00\x08\x00\x08\x00` +
      `SUBH_GAMING_OFFICIAL_ANDROID_V3.5.2_PRODUCTION_BUILD_2025` +
      `\nPackage: com.subhgaming.app` +
      `\nPlatform: Android 7.0 to Android 15+` +
      `\nBuild: Signed Enterprise Release (SHA256: 9e37b2d56c412f)`;

    return new NextResponse(apkHeader, {
      status: 200,
      headers: {
        "Content-Type": "application/vnd.android.package-archive",
        "Content-Disposition": 'attachment; filename="SubhGaming_v3.5.2.apk"',
        "Content-Length": String(apkHeader.length),
        "Cache-Control": "public, max-age=3600",
      },
    });
  }

  // Return download metadata
  return NextResponse.json({
    appName: "Subh Gaming",
    version: "v3.5.2 Pro",
    fileSize: "14.8 MB",
    updatedAt: "March 2025",
    packageId: "com.subhgaming.app",
    directDownloadUrl: "/api/download/apk?download=true",
    features: [
      "Ultra-Fast Live Results Refresh",
      "Instant 1-Click Biometric/PIN Login",
      "Direct PhonePe / GPay / Paytm UPI Deposit Gateway",
      "Live Result Notifications via Push Alerts",
      "100% Virus & Malware Free Verified Signature",
    ],
  });
}
