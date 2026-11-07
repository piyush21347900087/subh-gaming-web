"use client";

import React, { useState } from "react";
import Image from "next/image";
import { 
  X, 
  Download, 
  Smartphone, 
  ShieldCheck, 
  Zap, 
  CheckCircle2, 
  Sparkles, 
  ArrowRight,
  FileCheck
} from "lucide-react";

interface ApkDownloadModalProps {
  onClose: () => void;
}

export default function ApkDownloadModal({ onClose }: ApkDownloadModalProps) {
  const [downloading, setDownloading] = useState<boolean>(false);
  const [downloadComplete, setDownloadComplete] = useState<boolean>(false);

  const handleStartDownload = () => {
    setDownloading(true);
    // Trigger download
    const link = document.createElement("a");
    link.href = "/api/download/apk?download=true";
    link.setAttribute("download", "SubhGaming_v3.5.2.apk");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    setTimeout(() => {
      setDownloading(false);
      setDownloadComplete(true);
    }, 1500);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
      <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-6 my-auto">
        {/* Header */}
        <div className="flex items-start justify-between border-b border-neutral-800 pb-4">
          <div className="flex items-center gap-3">
            <div className="relative w-12 h-12 rounded-xl overflow-hidden border border-amber-500/40 bg-neutral-900">
              <Image
                src="/images/subh-gaming-logo.png"
                alt="Logo"
                fill
                className="object-cover"
              />
            </div>
            <div>
              <div className="flex items-center gap-1.5">
                <span className="text-lg font-black text-white">Subh Gaming App</span>
                <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800">
                  v3.5.2 Pro
                </span>
              </div>
              <p className="text-xs text-amber-400 font-semibold mt-0.5">
                Official Android APK (14.8 MB)
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className="p-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-white transition-colors border border-neutral-800"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Benefits Cards */}
        <div className="space-y-2.5">
          <div className="p-3.5 rounded-2xl bg-neutral-900/80 border border-neutral-800 flex items-center gap-3">
            <div className="p-2 rounded-xl bg-amber-500/20 text-amber-400">
              <Zap className="w-5 h-5" />
            </div>
            <div>
              <h4 className="text-xs font-bold text-white">Super Fast 1-Click Betting</h4>
              <p className="text-[11px] text-neutral-400">Zero lag, direct result notifications before anyone else</p>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl bg-neutral-900/80 border border-neutral-800 flex items-center gap-3">
            <div className="p-2 rounded-xl bg-emerald-500/20 text-emerald-400">
              <ShieldCheck className="w-5 h-5" />
            </div>
            <div>
              <h4 className="text-xs font-bold text-white">100% Secure & Virus Free</h4>
              <p className="text-[11px] text-neutral-400">Signed release verified with Google Play Protect standards</p>
            </div>
          </div>

          <div className="p-3.5 rounded-2xl bg-neutral-900/80 border border-neutral-800 flex items-center gap-3">
            <div className="p-2 rounded-xl bg-rose-500/20 text-rose-400">
              <Sparkles className="w-5 h-5" />
            </div>
            <div>
              <h4 className="text-xs font-bold text-white">Instant UPI Withdrawal Integration</h4>
              <p className="text-[11px] text-neutral-400">Direct wallet crediting via PhonePe, GPay, Paytm</p>
            </div>
          </div>
        </div>

        {/* Installation Steps */}
        <div className="p-4 rounded-2xl bg-black/60 border border-neutral-800 text-xs text-neutral-300 space-y-2">
          <span className="font-bold text-amber-400 uppercase tracking-wider block text-[11px]">
            Quick 3-Step Install Guide:
          </span>
          <ol className="list-decimal list-inside space-y-1 text-neutral-400 text-[11px]">
            <li>Click <strong>Download Android APK</strong> button below.</li>
            <li>If prompted, allow <em>"Install from Unknown Sources"</em> in settings.</li>
            <li>Tap on downloaded APK file to install and start playing!</li>
          </ol>
        </div>

        {/* Download Button */}
        <div>
          {downloadComplete ? (
            <div className="p-4 rounded-2xl bg-emerald-950/80 border border-emerald-800 text-center space-y-2">
              <CheckCircle2 className="w-8 h-8 text-emerald-400 mx-auto" />
              <p className="text-xs font-bold text-white">APK Download Started!</p>
              <p className="text-[11px] text-emerald-300">
                Check your browser downloads notification to open and install.
              </p>
            </div>
          ) : (
            <button
              onClick={handleStartDownload}
              disabled={downloading}
              className="w-full py-4 rounded-2xl gold-button text-sm font-black uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl shadow-amber-500/30"
            >
              <Download className="w-5 h-5" />
              {downloading ? "Preparing APK Package..." : "Download Android APK (14.8 MB)"}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
