"use client";

import React, { useState } from "react";
import { X, Plus, Trash2, ShieldCheck, CheckCircle2, AlertCircle, Coins, Sparkles } from "lucide-react";
import { GAME_RATES } from "@/lib/matka";

interface BettingModalProps {
  market: any;
  user: any;
  onClose: () => void;
  onBetPlacedSuccess: (newBalance: string) => void;
}

export default function BettingModal({
  market,
  user,
  onClose,
  onBetPlacedSuccess,
}: BettingModalProps) {
  const [gameType, setGameType] = useState<string>("SINGLE_DIGIT");
  const [sessionType, setSessionType] = useState<"OPEN" | "CLOSE">("OPEN");
  
  // Single selection inputs
  const [selectedNumber, setSelectedNumber] = useState<string>("");
  const [betAmount, setBetAmount] = useState<number>(100);

  // Cart / Queue of bets
  const [betQueue, setBetQueue] = useState<Array<{ number: string; amount: number; rate: number }>>([]);

  const [loading, setLoading] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string>("");
  const [successMsg, setSuccessMsg] = useState<string>("");

  const currentRate = GAME_RATES[gameType] || { rate: 9.5, ratio: "10 ka 95", minBet: 10 };

  const handleAddBet = (numOverride?: string) => {
    setErrorMsg("");
    setSuccessMsg("");
    const num = numOverride !== undefined ? numOverride : selectedNumber.trim();

    if (!num) {
      setErrorMsg("Please choose or enter a valid number");
      return;
    }

    if (gameType === "SINGLE_DIGIT" && !/^\d$/.test(num)) {
      setErrorMsg("Single digit must be 0-9");
      return;
    }

    if (gameType === "JODI_DIGIT" && !/^\d{2}$/.test(num)) {
      setErrorMsg("Jodi must be a 2-digit number (00-99)");
      return;
    }

    if ((gameType === "SINGLE_PANA" || gameType === "DOUBLE_PANA" || gameType === "TRIPLE_PANA") && !/^\d{3}$/.test(num)) {
      setErrorMsg("Pana must be a 3-digit number (e.g. 128)");
      return;
    }

    if (betAmount < currentRate.minBet) {
      setErrorMsg(`Minimum bet is ₹${currentRate.minBet}`);
      return;
    }

    // Check if number is already in queue
    const existingIndex = betQueue.findIndex((b) => b.number === num);
    if (existingIndex > -1) {
      const updated = [...betQueue];
      updated[existingIndex].amount += betAmount;
      setBetQueue(updated);
    } else {
      setBetQueue([...betQueue, { number: num, amount: betAmount, rate: currentRate.rate }]);
    }

    if (numOverride === undefined) {
      setSelectedNumber("");
    }
  };

  const handleRemoveBet = (index: number) => {
    setBetQueue(betQueue.filter((_, i) => i !== index));
  };

  const totalBetAmount = betQueue.reduce((acc, curr) => acc + curr.amount, 0);
  const userBalance = parseFloat(user?.walletBalance || "0");

  const handleSubmitAllBets = async () => {
    if (betQueue.length === 0) {
      setErrorMsg("Please add at least one bet to the list.");
      return;
    }

    if (userBalance < totalBetAmount) {
      setErrorMsg(`Insufficient balance. You need ₹${totalBetAmount}, available: ₹${userBalance.toFixed(2)}`);
      return;
    }

    setLoading(true);
    setErrorMsg("");
    setSuccessMsg("");

    try {
      const res = await fetch("/api/bets/place", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          marketId: market.id,
          gameType,
          sessionType: gameType === "JODI_DIGIT" ? "OPEN" : sessionType,
          betsList: betQueue.map((b) => ({ number: b.number, amount: b.amount })),
        }),
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || "Failed to place bets");
      }

      setSuccessMsg(data.message || "Bets placed successfully!");
      setBetQueue([]);
      if (data.newBalance) {
        onBetPlacedSuccess(data.newBalance);
      }
    } catch (err: any) {
      setErrorMsg(err.message || "Network error");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
      <div className="relative w-full max-w-2xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-6 my-auto">
        {/* Header */}
        <div className="flex items-start justify-between border-b border-neutral-800 pb-4">
          <div>
            <div className="flex items-center gap-2">
              <span className="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-400 border border-amber-500/30">
                LIVE PLAY
              </span>
              <h2 className="text-xl sm:text-2xl font-black text-white">
                {market.name}
              </h2>
            </div>
            <p className="text-xs text-neutral-400 mt-1">
              Rate: <span className="text-amber-400 font-bold">{currentRate.ratio}</span> (Multiplier: {currentRate.rate}x)
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-white transition-colors border border-neutral-800"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Game Type Selector */}
        <div>
          <label className="block text-xs font-bold text-neutral-400 uppercase tracking-wider mb-2">
            Select Game Type
          </label>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {[
              { id: "SINGLE_DIGIT", label: "Single Digit" },
              { id: "JODI_DIGIT", label: "Jodi Digit" },
              { id: "SINGLE_PANA", label: "Single Pana" },
              { id: "DOUBLE_PANA", label: "Double Pana" },
            ].map((type) => (
              <button
                key={type.id}
                onClick={() => {
                  setGameType(type.id);
                  setBetQueue([]);
                  setSelectedNumber("");
                }}
                className={`py-2 px-3 rounded-xl text-xs font-bold border transition-all ${
                  gameType === type.id
                    ? "bg-amber-500 text-black border-amber-400 shadow-lg shadow-amber-500/30"
                    : "bg-neutral-900/90 text-neutral-300 border-neutral-800 hover:border-neutral-700"
                }`}
              >
                {type.label}
              </button>
            ))}
          </div>
        </div>

        {/* Session Type (Open / Close) */}
        {gameType !== "JODI_DIGIT" && (
          <div>
            <label className="block text-xs font-bold text-neutral-400 uppercase tracking-wider mb-2">
              Draw Session
            </label>
            <div className="grid grid-cols-2 gap-2">
              <button
                onClick={() => setSessionType("OPEN")}
                className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                  sessionType === "OPEN"
                    ? "bg-emerald-600/90 text-white border-emerald-500 shadow-lg shadow-emerald-600/20"
                    : "bg-neutral-900 text-neutral-400 border-neutral-800"
                }`}
              >
                OPEN SESSION ({market.openTime})
              </button>
              <button
                onClick={() => setSessionType("CLOSE")}
                className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                  sessionType === "CLOSE"
                    ? "bg-rose-600/90 text-white border-rose-500 shadow-lg shadow-rose-600/20"
                    : "bg-neutral-900 text-neutral-400 border-neutral-800"
                }`}
              >
                CLOSE SESSION ({market.closeTime})
              </button>
            </div>
          </div>
        )}

        {/* Number Selection & Chips */}
        <div className="space-y-3 bg-neutral-950/70 p-4 rounded-2xl border border-neutral-800/80">
          {gameType === "SINGLE_DIGIT" ? (
            <div>
              <label className="block text-xs font-bold text-neutral-400 uppercase tracking-wider mb-2">
                Click Digit to Add (0 to 9)
              </label>
              <div className="grid grid-cols-5 sm:grid-cols-10 gap-2">
                {[1, 2, 3, 4, 5, 6, 7, 8, 9, 0].map((digit) => (
                  <button
                    key={digit}
                    onClick={() => handleAddBet(String(digit))}
                    className="aspect-square flex items-center justify-center rounded-xl bg-neutral-900 hover:bg-amber-500 hover:text-black border border-neutral-800 hover:border-amber-400 text-base font-black text-amber-300 transition-all shadow-inner"
                  >
                    {digit}
                  </button>
                ))}
              </div>
            </div>
          ) : (
            <div>
              <label className="block text-xs font-bold text-neutral-400 uppercase tracking-wider mb-2">
                Enter {gameType === "JODI_DIGIT" ? "Jodi (00-99)" : "3-Digit Pana"}
              </label>
              <div className="flex gap-2">
                <input
                  type="text"
                  maxLength={gameType === "JODI_DIGIT" ? 2 : 3}
                  value={selectedNumber}
                  onChange={(e) => setSelectedNumber(e.target.value.replace(/\D/g, ""))}
                  placeholder={gameType === "JODI_DIGIT" ? "e.g. 45" : "e.g. 128"}
                  className="flex-1 px-4 py-2.5 rounded-xl bg-neutral-900 border border-neutral-700 text-white text-lg font-mono font-bold focus:outline-none focus:border-amber-400"
                />
                <button
                  onClick={() => handleAddBet()}
                  className="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-black font-extrabold text-sm flex items-center gap-1.5 transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  Add Bid
                </button>
              </div>
            </div>
          )}

          {/* Quick Chip Amount Buttons */}
          <div>
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-bold text-neutral-400 uppercase tracking-wider">
                Bet Amount per Number
              </span>
              <span className="text-xs text-amber-400 font-bold">
                Selected: ₹{betAmount}
              </span>
            </div>
            <div className="flex flex-wrap gap-2">
              {[50, 100, 200, 500, 1000, 2000, 5000].map((chip) => (
                <button
                  key={chip}
                  onClick={() => setBetAmount(chip)}
                  className={`px-3 py-1.5 rounded-lg text-xs font-bold border transition-all ${
                    betAmount === chip
                      ? "bg-amber-500/20 text-amber-300 border-amber-400"
                      : "bg-neutral-900 text-neutral-400 border-neutral-800 hover:text-white"
                  }`}
                >
                  ₹{chip}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Queued Bets Preview Table */}
        {betQueue.length > 0 && (
          <div className="space-y-2">
            <div className="flex items-center justify-between text-xs font-bold text-neutral-400 uppercase">
              <span>Current Slip ({betQueue.length} Bids)</span>
              <button
                onClick={() => setBetQueue([])}
                className="text-red-400 hover:underline flex items-center gap-1"
              >
                Clear all
              </button>
            </div>
            <div className="max-h-36 overflow-y-auto space-y-1.5 pr-1">
              {betQueue.map((item, idx) => (
                <div
                  key={idx}
                  className="flex items-center justify-between px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-800 text-xs font-semibold"
                >
                  <div className="flex items-center gap-2">
                    <span className="w-6 h-6 rounded-lg bg-amber-500/20 text-amber-300 flex items-center justify-center font-mono font-bold">
                      {item.number}
                    </span>
                    <span className="text-neutral-300">
                      ₹{item.amount}
                    </span>
                    <span className="text-neutral-500">
                      (Win: ₹{(item.amount * item.rate).toLocaleString()})
                    </span>
                  </div>
                  <button
                    onClick={() => handleRemoveBet(idx)}
                    className="text-neutral-500 hover:text-red-400 p-1"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Error / Success messages */}
        {errorMsg && (
          <div className="p-3 rounded-xl bg-red-950/70 border border-red-800/80 text-red-300 text-xs flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{errorMsg}</span>
          </div>
        )}

        {successMsg && (
          <div className="p-3 rounded-xl bg-emerald-950/70 border border-emerald-800/80 text-emerald-300 text-xs flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 shrink-0" />
            <span>{successMsg}</span>
          </div>
        )}

        {/* Footer & Submit */}
        <div className="pt-2 border-t border-neutral-800 flex flex-col sm:flex-row items-center justify-between gap-4">
          <div className="text-center sm:text-left">
            <span className="text-xs text-neutral-400 block">Total Wager Amount</span>
            <div className="flex items-center gap-2">
              <span className="text-2xl font-black text-amber-400">
                ₹{totalBetAmount}
              </span>
              <span className="text-xs text-neutral-500">
                (Wallet: ₹{userBalance.toFixed(2)})
              </span>
            </div>
          </div>

          <div className="flex items-center gap-2 w-full sm:w-auto">
            <button
              onClick={onClose}
              className="flex-1 sm:flex-none px-4 py-3 rounded-xl text-xs font-bold text-neutral-300 bg-neutral-900 hover:bg-neutral-800 border border-neutral-800"
            >
              Cancel
            </button>
            <button
              onClick={handleSubmitAllBets}
              disabled={loading || betQueue.length === 0}
              className={`flex-1 sm:flex-none px-6 py-3 rounded-xl text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 ${
                betQueue.length > 0 && !loading
                  ? "gold-button cursor-pointer"
                  : "bg-neutral-800 text-neutral-500 cursor-not-allowed"
              }`}
            >
              <Coins className="w-4 h-4" />
              {loading ? "Processing..." : `Confirm & Place Bets`}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
