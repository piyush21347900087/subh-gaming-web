"use client";

import React, { useState, useEffect } from "react";
import { X, History, Trophy, Clock, XCircle, RefreshCw } from "lucide-react";

interface MyBetsModalProps {
  onClose: () => void;
}

export default function MyBetsModal({ onClose }: MyBetsModalProps) {
  const [bets, setBets] = useState<any[]>([]);
  const [filter, setFilter] = useState<string>("ALL");
  const [loading, setLoading] = useState<boolean>(true);

  const fetchBets = async (status: string) => {
    setLoading(true);
    try {
      const res = await fetch(`/api/bets/my?status=${status}`);
      const data = await res.json();
      if (data.success) {
        setBets(data.bets || []);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBets(filter);
  }, [filter]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
      <div className="relative w-full max-w-2xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-6 my-auto">
        {/* Header */}
        <div className="flex items-start justify-between border-b border-neutral-800 pb-4">
          <div className="flex items-center gap-2">
            <div className="p-2 rounded-xl bg-amber-500/20 text-amber-400">
              <History className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-xl sm:text-2xl font-black text-white">
                My Bet History
              </h2>
              <p className="text-xs text-neutral-400">
                Live wagers, declared outcomes, and payouts
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

        {/* Filter Pills */}
        <div className="flex items-center gap-1.5 bg-neutral-950 p-1.5 rounded-2xl border border-neutral-800">
          {["ALL", "PENDING", "WON", "LOST"].map((s) => (
            <button
              key={s}
              onClick={() => setFilter(s)}
              className={`flex-1 py-1.5 rounded-xl text-xs font-bold transition-all ${
                filter === s
                  ? "bg-amber-500 text-black shadow-md shadow-amber-500/30"
                  : "text-neutral-400 hover:text-white"
              }`}
            >
              {s}
            </button>
          ))}
        </div>

        {/* Bets List */}
        {loading ? (
          <div className="py-12 text-center">
            <RefreshCw className="w-6 h-6 text-amber-400 animate-spin mx-auto mb-2" />
            <p className="text-xs text-neutral-400">Fetching wagers...</p>
          </div>
        ) : bets.length === 0 ? (
          <div className="py-12 text-center text-neutral-500 text-xs">
            No bets found under this filter.
          </div>
        ) : (
          <div className="max-h-96 overflow-y-auto space-y-2.5 pr-1">
            {bets.map((b) => {
              const isWon = b.status === "WON";
              const isPending = b.status === "PENDING";
              const isLost = b.status === "LOST";

              return (
                <div
                  key={b.id}
                  className="p-3.5 rounded-2xl bg-neutral-900/90 border border-neutral-800 flex items-center justify-between gap-3 text-xs"
                >
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-white text-sm">
                        {b.marketName}
                      </span>
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-neutral-800 text-neutral-300">
                        {b.gameType}
                      </span>
                    </div>

                    <div className="flex items-center gap-3 text-neutral-400 font-mono">
                      <span>
                        Digit: <strong className="text-amber-300 text-sm">{b.selectedNumber}</strong>
                      </span>
                      <span>({b.sessionType})</span>
                      <span>Wager: ₹{b.amount}</span>
                    </div>
                  </div>

                  <div className="text-right space-y-1">
                    <span
                      className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider ${
                        isWon
                          ? "bg-emerald-950 text-emerald-400 border border-emerald-700/60"
                          : isPending
                          ? "bg-amber-950 text-amber-400 border border-amber-700/60"
                          : "bg-red-950 text-red-400 border border-red-700/60"
                      }`}
                    >
                      {isWon && <Trophy className="w-3 h-3 text-emerald-400" />}
                      {isPending && <Clock className="w-3 h-3 text-amber-400" />}
                      {isLost && <XCircle className="w-3 h-3 text-red-400" />}
                      {b.status}
                    </span>

                    <div className="font-mono text-xs">
                      {isWon ? (
                        <span className="text-emerald-400 font-black text-sm">
                          +₹{b.wonAmount} Won
                        </span>
                      ) : (
                        <span className="text-neutral-500">
                          Potential: ₹{b.potentialWin}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
