"use client";

import React from "react";
import { X, Award, ShieldCheck, Zap } from "lucide-react";
import { GAME_RATES } from "@/lib/matka";

interface GameRatesModalProps {
  onClose: () => void;
}

export default function GameRatesModal({ onClose }: GameRatesModalProps) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
      <div className="relative w-full max-w-xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-6 my-auto">
        {/* Header */}
        <div className="flex items-start justify-between border-b border-neutral-800 pb-4">
          <div className="flex items-center gap-2">
            <div className="p-2 rounded-xl bg-amber-500/20 text-amber-400">
              <Award className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-xl sm:text-2xl font-black text-white">
                Official Game Rates
              </h2>
              <p className="text-xs text-neutral-400">
                Guaranteed industry-highest winning multipliers
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

        {/* Rates Table */}
        <div className="space-y-2">
          {Object.entries(GAME_RATES).map(([key, item]) => (
            <div
              key={key}
              className="p-3.5 rounded-2xl bg-neutral-900/80 border border-neutral-800 flex items-center justify-between text-xs"
            >
              <div className="space-y-0.5">
                <span className="font-bold text-white text-sm block">
                  {item.label}
                </span>
                <span className="text-[11px] text-neutral-400">
                  Minimum Bet: ₹{item.minBet}
                </span>
              </div>
              <div className="text-right">
                <span className="font-mono font-black text-amber-400 text-base block">
                  {item.ratio}
                </span>
                <span className="text-[10px] text-neutral-500 uppercase tracking-wider font-semibold">
                  Multiplier {item.rate}x
                </span>
              </div>
            </div>
          ))}
        </div>

        {/* Responsible Gaming Notice */}
        <div className="p-3.5 rounded-2xl bg-neutral-950 border border-neutral-800/90 text-xs text-neutral-400 flex items-start gap-2.5">
          <ShieldCheck className="w-5 h-5 text-emerald-400 shrink-0 mt-0.5" />
          <span>
            Payouts are computed automatically by the server engine immediately upon result declaration. Winning amounts are instantly credited to your wallet.
          </span>
        </div>

        <div className="text-right">
          <button
            onClick={onClose}
            className="px-5 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 border border-neutral-800 text-xs font-bold text-neutral-200"
          >
            Got It
          </button>
        </div>
      </div>
    </div>
  );
}
