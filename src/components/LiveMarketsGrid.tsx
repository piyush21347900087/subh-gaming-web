"use client";

import React, { useState } from "react";
import Image from "next/image";
import { Play, TrendingUp, Clock, ShieldCheck, Zap, Award, Sparkles, Crown } from "lucide-react";

interface Market {
  id: number;
  name: string;
  slug: string;
  tagline: string;
  openTime: string;
  closeTime: string;
  isActive: boolean;
  status: "OPEN" | "CLOSED" | "RUNNING";
  fullResult: string;
  currentOpenPana: string | null;
  currentOpenDigit: string | null;
  currentCloseDigit: string | null;
  currentClosePana: string | null;
  category: string;
}

interface LiveMarketsGridProps {
  markets: Market[];
  onPlayMarket: (market: Market) => void;
  onOpenChart: (marketId: number) => void;
  user: any;
  onOpenAuth: (mode: "login" | "register") => void;
}

export default function LiveMarketsGrid({
  markets,
  onPlayMarket,
  onOpenChart,
  user,
  onOpenAuth,
}: LiveMarketsGridProps) {
  const [selectedCategory, setSelectedCategory] = useState<string>("ALL");

  const filteredMarkets = markets.filter((m) => {
    if (!m.isActive) return false;
    if (selectedCategory === "ALL") return true;
    if (selectedCategory === "SPECIAL") return m.category === "KING_SPECIAL";
    if (selectedCategory === "REGULAR") return m.category === "REGULAR";
    if (selectedCategory === "GALI") return m.category === "GALI_DISAWAR";
    return true;
  });

  return (
    <div className="space-y-8">
      {/* Hero Brand Section - Subh Gaming & Mahadev King */}
      <div className="relative rounded-3xl overflow-hidden border border-neutral-800 bg-gradient-to-b from-[#10121d] via-[#0d0e17] to-[#08080a] p-6 sm:p-10 shadow-2xl">
        <div className="absolute -top-24 -right-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none" />
        <div className="absolute -bottom-24 -left-24 w-96 h-96 bg-red-600/10 rounded-full blur-3xl pointer-events-none" />

        <div className="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
          <div className="space-y-4 text-center md:text-left max-w-2xl">
            <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-widest">
              <Crown className="w-3.5 h-3.5" />
              Subh Gaming Official Matka & Casino Hub
            </div>
            <h1 className="text-3xl sm:text-5xl font-black tracking-tight text-white leading-tight">
              Play Live on{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-amber-300 via-amber-400 to-amber-600">
                SUBH GAMING
              </span>
            </h1>
            <p className="text-sm sm:text-base text-neutral-300 leading-relaxed">
              India's #1 Most Trusted Online Matka Network. Highest Market Rates Guaranteed (10 Ka 950 Jodi), Automated Fast Results Declaration, and 24x7 Instant UPI Withdrawals.
            </p>

            {/* Trust Badges */}
            <div className="pt-2 flex flex-wrap items-center justify-center md:justify-start gap-4 text-xs font-semibold text-neutral-300">
              <div className="flex items-center gap-1.5 bg-[#181a29] px-3 py-1.5 rounded-xl border border-neutral-700/60">
                <ShieldCheck className="w-4 h-4 text-emerald-400" />
                <span>100% Genuine & Safe</span>
              </div>
              <div className="flex items-center gap-1.5 bg-[#181a29] px-3 py-1.5 rounded-xl border border-neutral-700/60">
                <Zap className="w-4 h-4 text-amber-400" />
                <span>Instant Auto-Payouts</span>
              </div>
              <div className="flex items-center gap-1.5 bg-[#181a29] px-3 py-1.5 rounded-xl border border-neutral-700/60">
                <Award className="w-4 h-4 text-rose-400" />
                <span>Highest Rate: ₹10 ka ₹950</span>
              </div>
            </div>
          </div>

          {/* Right Brand Badge Showcase */}
          <div className="flex items-center gap-4 shrink-0">
            <div className="relative w-36 h-36 sm:w-48 sm:h-48 rounded-2xl overflow-hidden border-2 border-amber-500/50 shadow-2xl shadow-amber-500/20 bg-neutral-950 p-2 transform hover:scale-105 transition-transform">
              <Image
                src="/images/subh-gaming-logo.png"
                alt="Subh Gaming Logo"
                fill
                className="object-contain"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Category Tabs & Header */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-4 border-b border-neutral-800 pb-4">
        <div>
          <h2 className="text-xl sm:text-2xl font-black text-white flex items-center gap-2">
            <span className="w-2.5 h-6 bg-[#0288d1] rounded-full" />
            Live Market Results & Bidding
          </h2>
          <p className="text-xs sm:text-sm text-neutral-400">
            Select any market to place bids or inspect historical panel records
          </p>
        </div>

        {/* Filters */}
        <div className="flex items-center gap-1.5 bg-[#10121d] p-1 rounded-xl border border-neutral-800">
          <button
            onClick={() => setSelectedCategory("ALL")}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
              selectedCategory === "ALL"
                ? "bg-[#0288d1] text-white shadow-md shadow-sky-600/30"
                : "text-neutral-400 hover:text-white"
            }`}
          >
            All Markets ({markets.length})
          </button>
          <button
            onClick={() => setSelectedCategory("SPECIAL")}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
              selectedCategory === "SPECIAL"
                ? "bg-[#0288d1] text-white shadow-md shadow-sky-600/30"
                : "text-neutral-400 hover:text-white"
            }`}
          >
            King Special
          </button>
          <button
            onClick={() => setSelectedCategory("REGULAR")}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
              selectedCategory === "REGULAR"
                ? "bg-[#0288d1] text-white shadow-md shadow-sky-600/30"
                : "text-neutral-400 hover:text-white"
            }`}
          >
            Regular Matka
          </button>
          <button
            onClick={() => setSelectedCategory("GALI")}
            className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
              selectedCategory === "GALI"
                ? "bg-[#0288d1] text-white shadow-md shadow-sky-600/30"
                : "text-neutral-400 hover:text-white"
            }`}
          >
            Gali Disawar
          </button>
        </div>
      </div>

      {/* Markets Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {filteredMarkets.map((market) => {
          const isOpen = market.status === "OPEN";

          return (
            <div
              key={market.id}
              className={`rounded-2xl transition-all duration-300 relative overflow-hidden flex flex-col justify-between ${
                market.category === "KING_SPECIAL"
                  ? "bg-gradient-to-b from-[#181a29] via-[#10121d] to-[#0c0d12] border-2 border-amber-500/40 hover:border-amber-400 shadow-xl"
                  : "bg-gradient-to-b from-[#121422] via-[#0f1019] to-[#0a0a0e] border border-neutral-800 hover:border-neutral-700 shadow-lg"
              }`}
            >
              {/* Card Header */}
              <div className="p-5 border-b border-neutral-800/80">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <div className="flex items-center gap-2">
                      <h3 className="text-lg font-black text-white tracking-wide">
                        {market.name}
                      </h3>
                      {market.category === "KING_SPECIAL" && (
                        <span className="px-2 py-0.5 text-[10px] font-extrabold uppercase rounded bg-amber-500/20 text-amber-400 border border-amber-500/40">
                          JACKPOT
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-neutral-400 mt-0.5">
                      {market.tagline || "Official Draw"}
                    </p>
                  </div>

                  {/* Status Badge */}
                  <span
                    className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider ${
                      isOpen
                        ? "bg-emerald-950/80 text-emerald-400 border border-emerald-500/40"
                        : "bg-red-950/80 text-red-400 border border-red-500/40"
                    }`}
                  >
                    <span
                      className={`w-2 h-2 rounded-full ${
                        isOpen ? "bg-emerald-400 animate-ping" : "bg-red-400"
                      }`}
                    />
                    {isOpen ? "MARKET OPEN" : "MARKET CLOSED"}
                  </span>
                </div>

                {/* Market Result Banner */}
                <div className="mt-4 p-3.5 rounded-xl bg-black/60 border border-neutral-800/90 text-center">
                  <div className="text-[11px] text-neutral-400 uppercase tracking-widest font-semibold mb-1">
                    Today's Result
                  </div>
                  <div className="font-mono text-2xl sm:text-3xl font-black text-amber-400 tracking-wider">
                    {market.fullResult || "***-**-***"}
                  </div>
                </div>

                {/* Market Timings */}
                <div className="mt-3 flex items-center justify-between text-xs text-neutral-300 font-medium">
                  <div className="flex items-center gap-1 text-neutral-400">
                    <Clock className="w-3.5 h-3.5 text-amber-400" />
                    <span>Open: <strong className="text-white">{market.openTime}</strong></span>
                  </div>
                  <div className="flex items-center gap-1 text-neutral-400">
                    <Clock className="w-3.5 h-3.5 text-rose-400" />
                    <span>Close: <strong className="text-white">{market.closeTime}</strong></span>
                  </div>
                </div>
              </div>

              {/* Card Actions */}
              <div className="p-4 bg-black/40 flex items-center gap-2">
                <button
                  onClick={() => onOpenChart(market.id)}
                  className="flex-1 py-2.5 px-3 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-neutral-200 hover:text-white border border-neutral-800 text-xs font-bold flex items-center justify-center gap-1.5 transition-colors"
                >
                  <TrendingUp className="w-3.5 h-3.5 text-emerald-400" />
                  Panel Chart
                </button>

                <button
                  onClick={() => {
                    if (!user) {
                      onOpenAuth("login");
                    } else {
                      onPlayMarket(market);
                    }
                  }}
                  disabled={!isOpen}
                  className={`flex-1 py-2.5 px-3 rounded-xl text-xs font-black uppercase tracking-wider flex items-center justify-center gap-1.5 transition-all ${
                    isOpen
                      ? "gold-button cursor-pointer"
                      : "bg-neutral-800 text-neutral-500 cursor-not-allowed border border-neutral-700/50"
                  }`}
                >
                  <Play className="w-3.5 h-3.5 fill-current" />
                  {isOpen ? "Play Now" : "Closed"}
                </button>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
