"use client";

import React, { useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { 
  Wallet, 
  Crown, 
  ShieldCheck, 
  LogOut, 
  History, 
  TrendingUp, 
  Sparkles, 
  Menu, 
  X, 
  CreditCard, 
  Layers, 
  Download, 
  Smartphone, 
  MessageCircle, 
  Send 
} from "lucide-react";

interface NavbarProps {
  user: any;
  onOpenAuth: (mode: "login" | "register") => void;
  onLogout: () => void;
  onOpenWallet: (tab?: "deposit" | "withdraw" | "ledger") => void;
  onOpenMyBets: () => void;
  onOpenRates: () => void;
  onOpenChart: (marketId?: number) => void;
  onOpenDownloadApk: () => void;
  activeView: string;
  setActiveView: (view: string) => void;
  socialLinks?: {
    whatsappGroup?: string;
    telegramChannel?: string;
  };
}

export default function Navbar({
  user,
  onOpenAuth,
  onLogout,
  onOpenWallet,
  onOpenMyBets,
  onOpenRates,
  onOpenChart,
  onOpenDownloadApk,
  activeView,
  setActiveView,
  socialLinks,
}: NavbarProps) {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  return (
    <header className="sticky top-0 z-40 bg-[#10121d]/95 backdrop-blur-md border-b border-neutral-800">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-20">
          {/* Logo & Subh Gaming Brand Identity */}
          <div className="flex items-center gap-3 cursor-pointer" onClick={() => setActiveView("games")}>
            <div className="relative w-12 h-12 rounded-xl overflow-hidden border border-amber-500/40 shadow-lg shadow-amber-500/20 bg-black p-1">
              <Image
                src="/images/subh-gaming-logo.png"
                alt="Subh Gaming Logo"
                fill
                className="object-contain"
              />
            </div>
            <div>
              <div className="flex items-center gap-1.5">
                <span className="text-xl sm:text-2xl font-black tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-amber-400 to-amber-600">
                  SUBH GAMING
                </span>
              </div>
              <p className="text-[11px] font-medium text-amber-500/80 tracking-widest flex items-center gap-1">
                <Crown className="w-3 h-3 text-amber-400" />
                KING OF ONLINE WORLD
              </p>
            </div>
          </div>

          {/* Desktop Navigation Links */}
          <nav className="hidden md:flex items-center gap-1 lg:gap-2">
            <button
              onClick={() => setActiveView("games")}
              className={`px-3 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-1.5 ${
                activeView === "games"
                  ? "bg-amber-500/15 text-amber-400 border border-amber-500/30"
                  : "text-neutral-300 hover:text-white hover:bg-neutral-800/50"
              }`}
            >
              <Sparkles className="w-4 h-4 text-amber-400" />
              Live Markets
            </button>

            <button
              onClick={() => onOpenChart()}
              className="px-3 py-2 rounded-lg text-sm font-semibold text-neutral-300 hover:text-white hover:bg-neutral-800/50 transition-colors flex items-center gap-1.5"
            >
              <TrendingUp className="w-4 h-4 text-emerald-400" />
              Panel Charts
            </button>

            <button
              onClick={onOpenRates}
              className="px-3 py-2 rounded-lg text-sm font-semibold text-neutral-300 hover:text-white hover:bg-neutral-800/50 transition-colors flex items-center gap-1.5"
            >
              <Layers className="w-4 h-4 text-blue-400" />
              Game Rates
            </button>

            {/* Social Links */}
            {socialLinks?.whatsappGroup && (
              <a
                href={socialLinks.whatsappGroup}
                target="_blank"
                rel="noopener noreferrer"
                className="px-3 py-2 rounded-lg text-xs font-bold text-emerald-400 hover:text-emerald-300 hover:bg-emerald-950/40 border border-emerald-800/50 transition-all flex items-center gap-1.5"
              >
                <MessageCircle className="w-3.5 h-3.5" />
                WhatsApp Group
              </a>
            )}

            {socialLinks?.telegramChannel && (
              <a
                href={socialLinks.telegramChannel}
                target="_blank"
                rel="noopener noreferrer"
                className="px-3 py-2 rounded-lg text-xs font-bold text-blue-400 hover:text-blue-300 hover:bg-blue-950/40 border border-blue-800/50 transition-all flex items-center gap-1.5"
              >
                <Send className="w-3.5 h-3.5" />
                Telegram
              </a>
            )}

            {/* Download APK Link */}
            <button
              onClick={onOpenDownloadApk}
              className="px-3 py-2 rounded-lg text-xs font-black text-amber-400 hover:text-amber-300 hover:bg-amber-500/10 border border-amber-500/30 transition-all flex items-center gap-1.5"
            >
              <Smartphone className="w-4 h-4 text-emerald-400" />
              APK App
            </button>

            {/* Subh Gaming Admin Dashboard Link */}
            {user && (user.role === "ADMIN" || user.role === "STAFF") && (
              <button
                onClick={() => setActiveView("admin")}
                className={`px-3 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-1.5 ${
                  activeView === "admin"
                    ? "bg-[#0288d1] text-white shadow-lg shadow-sky-600/30"
                    : "bg-[#181a29] text-sky-400 border border-sky-500/40 hover:bg-sky-950/50"
                }`}
              >
                <ShieldCheck className="w-4 h-4 text-sky-400" />
                Subh Gaming Admin
              </button>
            )}
          </nav>

          {/* Right Action Area */}
          <div className="flex items-center gap-2 sm:gap-3">
            {user ? (
              <>
                {/* Wallet Button */}
                <button
                  onClick={() => onOpenWallet("deposit")}
                  className="flex items-center gap-2 px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl bg-gradient-to-r from-amber-500/10 to-amber-600/20 border border-amber-500/40 hover:border-amber-400 transition-all text-left shadow-inner"
                >
                  <div className="p-1 rounded-lg bg-amber-500/20 text-amber-400">
                    <Wallet className="w-4 h-4" />
                  </div>
                  <div>
                    <span className="block text-[10px] text-amber-300/80 uppercase font-medium leading-none">
                      Wallet
                    </span>
                    <span className="text-sm sm:text-base font-extrabold text-white font-mono">
                      ₹{parseFloat(user.walletBalance || "0").toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                    </span>
                  </div>
                </button>

                {/* My Bets Shortcut */}
                <button
                  onClick={onOpenMyBets}
                  title="My Bets"
                  className="p-2 sm:px-3 sm:py-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 border border-neutral-800 text-neutral-300 hover:text-white transition-colors flex items-center gap-1.5 text-xs font-semibold"
                >
                  <History className="w-4 h-4 text-amber-400" />
                  <span className="hidden sm:inline">My Bets</span>
                </button>

                {/* Deposit Shortcut */}
                <button
                  onClick={() => onOpenWallet("deposit")}
                  className="hidden sm:inline-flex px-3 py-2 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/20 transition-all items-center gap-1"
                >
                  <CreditCard className="w-3.5 h-3.5" />
                  + Deposit
                </button>

                {/* Logout */}
                <button
                  onClick={onLogout}
                  title="Logout"
                  className="p-2 rounded-xl bg-neutral-900 hover:bg-red-950/50 border border-neutral-800 hover:border-red-800 text-neutral-400 hover:text-red-400 transition-colors"
                >
                  <LogOut className="w-4 h-4" />
                </button>
              </>
            ) : (
              <div className="flex items-center gap-2">
                <button
                  onClick={() => onOpenAuth("login")}
                  className="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-neutral-200 hover:text-white bg-neutral-900 hover:bg-neutral-800 border border-neutral-800 transition-colors"
                >
                  Login
                </button>
                <button
                  onClick={() => onOpenAuth("register")}
                  className="gold-button px-4 py-2 rounded-xl text-xs sm:text-sm font-extrabold shadow-lg"
                >
                  Register
                </button>
              </div>
            )}

            {/* Mobile menu toggle */}
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="md:hidden p-2 rounded-xl bg-neutral-900 text-neutral-300 hover:text-white border border-neutral-800"
            >
              {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
          </div>
        </div>

        {/* Mobile menu dropdown */}
        {mobileMenuOpen && (
          <div className="md:hidden py-4 px-2 border-t border-neutral-800 space-y-2 bg-[#10121d]">
            <button
              onClick={() => {
                onOpenDownloadApk();
                setMobileMenuOpen(false);
              }}
              className="w-full text-left px-3 py-2.5 rounded-lg text-sm font-bold text-amber-400 bg-amber-500/10 border border-amber-500/30 flex items-center gap-2"
            >
              <Smartphone className="w-4 h-4 text-emerald-400" />
              Download Android APK (v3.5.2)
            </button>

            {socialLinks?.whatsappGroup && (
              <a
                href={socialLinks.whatsappGroup}
                target="_blank"
                rel="noopener noreferrer"
                className="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-emerald-400 bg-emerald-950/30 border border-emerald-800/40 flex items-center gap-2"
              >
                <MessageCircle className="w-4 h-4" />
                Join WhatsApp Group
              </a>
            )}

            {socialLinks?.telegramChannel && (
              <a
                href={socialLinks.telegramChannel}
                target="_blank"
                rel="noopener noreferrer"
                className="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-blue-400 bg-blue-950/30 border border-blue-800/40 flex items-center gap-2"
              >
                <Send className="w-4 h-4" />
                Join Telegram Channel
              </a>
            )}

            <button
              onClick={() => {
                setActiveView("games");
                setMobileMenuOpen(false);
              }}
              className="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-neutral-200 hover:bg-neutral-800 flex items-center gap-2"
            >
              <Sparkles className="w-4 h-4 text-amber-400" />
              Live Markets
            </button>
            <button
              onClick={() => {
                onOpenChart();
                setMobileMenuOpen(false);
              }}
              className="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-neutral-200 hover:bg-neutral-800 flex items-center gap-2"
            >
              <TrendingUp className="w-4 h-4 text-emerald-400" />
              Panel Charts
            </button>
            <button
              onClick={() => {
                onOpenRates();
                setMobileMenuOpen(false);
              }}
              className="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-neutral-200 hover:bg-neutral-800 flex items-center gap-2"
            >
              <Layers className="w-4 h-4 text-blue-400" />
              Game Rates
            </button>
            {user && (
              <>
                <button
                  onClick={() => {
                    onOpenWallet("deposit");
                    setMobileMenuOpen(false);
                  }}
                  className="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-neutral-200 hover:bg-neutral-800 flex items-center gap-2"
                >
                  <Wallet className="w-4 h-4 text-amber-400" />
                  Wallet Passbook (Deposit / Withdraw)
                </button>
                <button
                  onClick={() => {
                    onOpenMyBets();
                    setMobileMenuOpen(false);
                  }}
                  className="w-full text-left px-3 py-2 rounded-lg text-sm font-semibold text-neutral-200 hover:bg-neutral-800 flex items-center gap-2"
                >
                  <History className="w-4 h-4 text-amber-400" />
                  My Bets
                </button>
                {(user.role === "ADMIN" || user.role === "STAFF") && (
                  <button
                    onClick={() => {
                      setActiveView("admin");
                      setMobileMenuOpen(false);
                    }}
                    className="w-full text-left px-3 py-2 rounded-lg text-sm font-bold text-sky-400 hover:bg-sky-950/40 flex items-center gap-2"
                  >
                    <ShieldCheck className="w-4 h-4 text-sky-400" />
                    Subh Gaming Admin Dashboard
                  </button>
                )}
              </>
            )}
          </div>
        )}
      </div>
    </header>
  );
}
