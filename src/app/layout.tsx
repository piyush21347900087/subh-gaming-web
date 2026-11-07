import type { Metadata, Viewport } from "next";
import type { ReactNode } from "react";
import "./globals.css";

export const metadata: Metadata = {
  title: "Subh Gaming | King of Online World",
  description: "India's premier high-roller Matka & Casino gaming platform. Real-time fast results, 100% genuine automated payouts, 24x7 instant UPI withdrawals.",
  keywords: "Subh Gaming, Mahadev King, Matka, Kalyan Matka, Satta Matka, Online Matka Play, Fast Result",
};

export const viewport: Viewport = {
  themeColor: "#08080a",
  width: "device-width",
  initialScale: 1,
  maximumScale: 1,
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en" className="dark">
      <body className="bg-[#08080a] text-neutral-100 antialiased min-h-screen selection:bg-amber-500 selection:text-black">
        {children}
      </body>
    </html>
  );
}
