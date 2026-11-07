"use client";

import React, { useState, useEffect } from "react";
import Navbar from "@/components/Navbar";
import MarqueeTicker from "@/components/MarqueeTicker";
import LiveMarketsGrid from "@/components/LiveMarketsGrid";
import BettingModal from "@/components/BettingModal";
import ResultsAndChartModal from "@/components/ResultsAndChartModal";
import WalletModal from "@/components/WalletModal";
import MyBetsModal from "@/components/MyBetsModal";
import GameRatesModal from "@/components/GameRatesModal";
import AuthModal from "@/components/AuthModal";
import AdminPanel from "@/components/AdminPanel";
import ApkDownloadModal from "@/components/ApkDownloadModal";
import { MessageCircle, ShieldCheck, Download, Smartphone, Send } from "lucide-react";
import Image from "next/image";

export default function HomePage() {
  const [user, setUser] = useState<any>(null);
  const [markets, setMarkets] = useState<any[]>([]);
  const [notices, setNotices] = useState<any[]>([]);
  const [loading, setLoading] = useState<boolean>(true);

  // Dynamic system settings (WhatsApp group, Telegram link, UPI, Banner Text)
  const [systemSettings, setSystemSettings] = useState<Record<string, string>>({
    WHATSAPP_GROUP_URL: "https://chat.whatsapp.com/MahadevKingVIPClub2025",
    TELEGRAM_CHANNEL_URL: "https://t.me/MahadevKingOfficial555",
    HELPLINE_WHATSAPP: "+91 99999 88888",
    BANNER_TEXT: "Subh Gaming - King of Online World! 100% genuine automated results & instant withdrawals 24x7.",
    ADMIN_NOTICE: "Official VIP Daily Jodi Leaks available on Telegram. Contact helpline for bulk deposit bonus.",
  });

  // Active view: "games" or "admin"
  const [activeView, setActiveView] = useState<string>("games");

  // Modals state - Registration opened by default when app loads for visitors
  const [authModalOpen, setAuthModalOpen] = useState<boolean>(false);
  const [authMode, setAuthMode] = useState<"login" | "register">("register");
  const [selectedPlayMarket, setSelectedPlayMarket] = useState<any | null>(null);
  const [chartModalOpen, setChartModalOpen] = useState<boolean>(false);
  const [chartMarketId, setChartMarketId] = useState<number | undefined>(undefined);
  const [walletModalOpen, setWalletModalOpen] = useState<boolean>(false);
  const [walletTab, setWalletTab] = useState<"deposit" | "withdraw" | "ledger">("deposit");
  const [myBetsOpen, setMyBetsOpen] = useState<boolean>(false);
  const [ratesOpen, setRatesOpen] = useState<boolean>(false);
  const [apkModalOpen, setApkModalOpen] = useState<boolean>(false);

  // Fetch Current User
  const fetchCurrentUser = async () => {
    try {
      const res = await fetch("/api/auth/me");
      const data = await res.json();
      if (data.authenticated && data.user) {
        setUser(data.user);
        return true;
      } else {
        setUser(null);
        return false;
      }
    } catch (e) {
      console.error(e);
      setUser(null);
      return false;
    }
  };

  // Fetch Markets
  const fetchMarkets = async () => {
    try {
      const res = await fetch("/api/markets");
      const data = await res.json();
      if (data.success) {
        setMarkets(data.markets || []);
      }
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch Notices
  const fetchNotices = async () => {
    try {
      const res = await fetch("/api/notices");
      const data = await res.json();
      if (data.success) {
        setNotices(data.notices || []);
      }
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch Dynamic Settings
  const fetchSettings = async () => {
    try {
      const res = await fetch("/api/admin/settings");
      const data = await res.json();
      if (data.success && data.settings) {
        setSystemSettings((prev) => ({ ...prev, ...data.settings }));
      }
    } catch (e) {
      console.error(e);
    }
  };

  useEffect(() => {
    Promise.all([fetchCurrentUser(), fetchMarkets(), fetchNotices(), fetchSettings()]).then(([isLoggedIn]) => {
      setLoading(false);
      // USER REQUIREMENT: "app open ho to pahele registration bala option aye"
      if (!isLoggedIn) {
        setAuthMode("register");
        setAuthModalOpen(true);
      }
    });

    // Auto-refresh markets every 15 seconds for live ticker updates
    const interval = setInterval(() => {
      fetchMarkets();
    }, 15000);

    return () => clearInterval(interval);
  }, []);

  const handleLogout = async () => {
    try {
      await fetch("/api/auth/logout", { method: "POST" });
      setUser(null);
      setActiveView("games");
      setAuthMode("register");
      setAuthModalOpen(true);
    } catch (e) {
      console.error(e);
    }
  };

  const handleOpenAuth = (mode: "login" | "register") => {
    setAuthMode(mode);
    setAuthModalOpen(true);
  };

  const handleOpenWallet = (tab: "deposit" | "withdraw" | "ledger" = "deposit") => {
    if (!user) {
      handleOpenAuth("login");
      return;
    }
    setWalletTab(tab);
    setWalletModalOpen(true);
  };

  const handleOpenMyBets = () => {
    if (!user) {
      handleOpenAuth("login");
      return;
    }
    setMyBetsOpen(true);
  };

  const handleOpenChart = (marketId?: number) => {
    setChartMarketId(marketId);
    setChartModalOpen(true);
  };

  const handlePlayMarket = (market: any) => {
    if (!user) {
      handleOpenAuth("login");
      return;
    }
    setSelectedPlayMarket(market);
  };

  return (
    <div className="min-h-screen flex flex-col bg-[#08080a] text-neutral-100">
      {/* Navbar */}
      <Navbar
        user={user}
        onOpenAuth={handleOpenAuth}
        onLogout={handleLogout}
        onOpenWallet={handleOpenWallet}
        onOpenMyBets={handleOpenMyBets}
        onOpenRates={() => setRatesOpen(true)}
        onOpenChart={handleOpenChart}
        onOpenDownloadApk={() => setApkModalOpen(true)}
        activeView={activeView}
        setActiveView={setActiveView}
        socialLinks={{
          whatsappGroup: systemSettings.WHATSAPP_GROUP_URL,
          telegramChannel: systemSettings.TELEGRAM_CHANNEL_URL,
        }}
      />

      {/* Marquee Ticker with Banner Text from Admin Panel */}
      <MarqueeTicker 
        markets={markets} 
        notices={notices} 
        bannerText={systemSettings.BANNER_TEXT || systemSettings.ADMIN_NOTICE} 
      />

      {/* Community Links & Android APK Download Top Banner */}
      <div className="bg-gradient-to-r from-sky-950/40 via-[#10121d] to-sky-950/40 border-b border-neutral-800 py-2.5 px-4 text-xs font-semibold">
        <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Smartphone className="w-4 h-4 text-emerald-400 animate-bounce" />
            <span className="text-neutral-200">
              Official <strong>Subh Gaming</strong> App v3.5.2 Pro Available!
            </span>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {systemSettings.WHATSAPP_GROUP_URL && (
              <a
                href={systemSettings.WHATSAPP_GROUP_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="px-2.5 py-1 rounded-lg bg-emerald-950/80 hover:bg-emerald-900 border border-emerald-700/60 text-emerald-300 text-[11px] font-bold flex items-center gap-1.5 transition-all shadow"
              >
                <MessageCircle className="w-3.5 h-3.5 text-emerald-400" />
                Join WhatsApp Group
              </a>
            )}

            {systemSettings.TELEGRAM_CHANNEL_URL && (
              <a
                href={systemSettings.TELEGRAM_CHANNEL_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="px-2.5 py-1 rounded-lg bg-blue-950/80 hover:bg-blue-900 border border-blue-700/60 text-blue-300 text-[11px] font-bold flex items-center gap-1.5 transition-all shadow"
              >
                <Send className="w-3.5 h-3.5 text-blue-400" />
                Telegram Channel
              </a>
            )}

            <button
              onClick={() => setApkModalOpen(true)}
              className="px-3 py-1 rounded-lg bg-[#0288d1] hover:bg-sky-500 text-white text-[11px] font-extrabold flex items-center gap-1 shrink-0 shadow-md"
            >
              <Download className="w-3.5 h-3.5" />
              Download APK (14.8 MB)
            </button>
          </div>
        </div>
      </div>

      {/* Main App Body */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        {activeView === "admin" && (user?.role === "ADMIN" || user?.role === "STAFF") ? (
          <AdminPanel
            currentUser={user}
            markets={markets}
            onRefreshMarkets={fetchMarkets}
          />
        ) : (
          <LiveMarketsGrid
            markets={markets}
            onPlayMarket={handlePlayMarket}
            onOpenChart={handleOpenChart}
            user={user}
            onOpenAuth={handleOpenAuth}
          />
        )}
      </main>

      {/* Floating 24x7 WhatsApp Help Badge */}
      <a
        href={systemSettings.HELPLINE_WHATSAPP ? `https://wa.me/${systemSettings.HELPLINE_WHATSAPP.replace(/\D/g, "")}` : "https://wa.me/919999988888"}
        target="_blank"
        rel="noopener noreferrer"
        className="fixed bottom-6 right-6 z-40 flex items-center gap-2 px-4 py-3 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs shadow-2xl shadow-emerald-600/50 hover:scale-105 transition-all"
      >
        <MessageCircle className="w-5 h-5 fill-current" />
        <span className="hidden sm:inline">24x7 WhatsApp Helpline</span>
      </a>

      {/* MODALS */}
      {authModalOpen && (
        <AuthModal
          initialMode={authMode}
          onClose={() => setAuthModalOpen(false)}
          onAuthSuccess={(u) => {
            setUser(u);
            fetchMarkets();
          }}
        />
      )}

      {selectedPlayMarket && (
        <BettingModal
          market={selectedPlayMarket}
          user={user}
          onClose={() => setSelectedPlayMarket(null)}
          onBetPlacedSuccess={(newBalance) => {
            setUser((prev: any) => ({ ...prev, walletBalance: newBalance }));
            fetchMarkets();
          }}
        />
      )}

      {chartModalOpen && (
        <ResultsAndChartModal
          markets={markets}
          initialMarketId={chartMarketId}
          onClose={() => setChartModalOpen(false)}
        />
      )}

      {walletModalOpen && (
        <WalletModal
          initialTab={walletTab}
          user={user}
          onClose={() => setWalletModalOpen(false)}
          onBalanceUpdated={(newBal) => {
            setUser((prev: any) => ({ ...prev, walletBalance: newBal }));
          }}
        />
      )}

      {myBetsOpen && (
        <MyBetsModal onClose={() => setMyBetsOpen(false)} />
      )}

      {ratesOpen && (
        <GameRatesModal onClose={() => setRatesOpen(false)} />
      )}

      {apkModalOpen && (
        <ApkDownloadModal onClose={() => setApkModalOpen(false)} />
      )}

      {/* Luxury Footer with Subh Gaming branding */}
      <footer className="bg-[#10121d] border-t border-neutral-800 py-10 mt-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6 text-center sm:text-left">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="relative w-10 h-10 rounded-xl overflow-hidden border border-amber-500/30 bg-black p-1">
                <Image
                  src="/images/subh-gaming-logo.png"
                  alt="Subh Gaming Logo"
                  fill
                  className="object-contain"
                />
              </div>
              <div>
                <span className="font-black text-amber-400 text-lg tracking-wider">
                  SUBH GAMING
                </span>
                <p className="text-xs text-neutral-400">
                  India's Most Trusted High-Roller Online Matka & Casino Hub
                </p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-4 text-xs font-semibold text-neutral-400">
              {systemSettings.WHATSAPP_GROUP_URL && (
                <a href={systemSettings.WHATSAPP_GROUP_URL} target="_blank" rel="noopener noreferrer" className="text-emerald-400 font-bold hover:underline flex items-center gap-1">
                  <MessageCircle className="w-3.5 h-3.5" /> WhatsApp Group
                </a>
              )}
              {systemSettings.TELEGRAM_CHANNEL_URL && (
                <a href={systemSettings.TELEGRAM_CHANNEL_URL} target="_blank" rel="noopener noreferrer" className="text-blue-400 font-bold hover:underline flex items-center gap-1">
                  <Send className="w-3.5 h-3.5" /> Telegram Channel
                </a>
              )}
              <button onClick={() => setApkModalOpen(true)} className="text-amber-400 font-bold hover:underline flex items-center gap-1">
                <Smartphone className="w-3.5 h-3.5" /> Download App
              </button>
              <button onClick={() => setRatesOpen(true)} className="hover:text-amber-400">
                Game Rates
              </button>
              <button onClick={() => handleOpenChart()} className="hover:text-amber-400">
                Panel Charts
              </button>
              <button onClick={() => handleOpenWallet("deposit")} className="hover:text-amber-400">
                Instant Deposit
              </button>
              <button onClick={() => handleOpenWallet("withdraw")} className="hover:text-amber-400">
                Fast Withdrawal
              </button>
            </div>
          </div>

          <div className="border-t border-neutral-900 pt-6 flex flex-col sm:flex-row items-center justify-between text-xs text-neutral-500 gap-2">
            <p>© 2025 Subh Gaming. All rights reserved. 18+ Play Responsibly.</p>
            <p className="flex items-center gap-1">
              Engineered with <ShieldCheck className="w-4 h-4 text-emerald-500" /> Enterprise Security & Instant Auto-Settlement
            </p>
          </div>
        </div>
      </footer>
    </div>
  );
}
