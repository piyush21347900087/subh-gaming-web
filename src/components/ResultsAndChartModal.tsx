"use client";

import React, { useState, useEffect } from "react";
import { X, Calendar, TrendingUp, RefreshCw } from "lucide-react";

interface ResultsAndChartModalProps {
  markets: any[];
  initialMarketId?: number;
  onClose: () => void;
}

export default function ResultsAndChartModal({
  markets,
  initialMarketId,
  onClose,
}: ResultsAndChartModalProps) {
  const [selectedMarketId, setSelectedMarketId] = useState<number>(
    initialMarketId || (markets.length > 0 ? markets[0].id : 1)
  );
  const [history, setHistory] = useState<any[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [chartType, setChartType] = useState<"PANEL" | "JODI">("PANEL");

  const currentMarket = markets.find((m) => m.id === selectedMarketId);

  const fetchChartData = async (mId: number) => {
    setLoading(true);
    try {
      const res = await fetch(`/api/markets/${mId}`);
      const data = await res.json();
      if (data.success && data.history) {
        setHistory(data.history);
      } else {
        setHistory([]);
      }
    } catch (e) {
      console.error(e);
      setHistory([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (selectedMarketId) {
      fetchChartData(selectedMarketId);
    }
  }, [selectedMarketId]);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
      <div className="relative w-full max-w-4xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-6 my-auto">
        {/* Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-neutral-800 pb-4">
          <div>
            <div className="flex items-center gap-2">
              <span className="p-1.5 rounded-lg bg-emerald-500/20 text-emerald-400">
                <TrendingUp className="w-4 h-4" />
              </span>
              <h2 className="text-xl sm:text-2xl font-black text-white">
                Historical Matka Panel Chart
              </h2>
            </div>
            <p className="text-xs text-neutral-400 mt-0.5">
              Official verified outcomes for past 30 game sessions
            </p>
          </div>

          <div className="flex items-center gap-2 w-full sm:w-auto justify-between sm:justify-end">
            {/* Market Picker Dropdown */}
            <select
              value={selectedMarketId}
              onChange={(e) => setSelectedMarketId(Number(e.target.value))}
              className="px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-amber-300 text-xs font-bold focus:outline-none focus:border-amber-400"
            >
              {markets.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.name}
                </option>
              ))}
            </select>

            <button
              onClick={onClose}
              className="p-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-white transition-colors border border-neutral-800"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Chart View Toggle */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2 bg-neutral-900 p-1 rounded-xl border border-neutral-800">
            <button
              onClick={() => setChartType("PANEL")}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                chartType === "PANEL"
                  ? "bg-amber-500 text-black shadow-md shadow-amber-500/30"
                  : "text-neutral-400 hover:text-white"
              }`}
            >
              Full Panel (Pana + Jodi)
            </button>
            <button
              onClick={() => setChartType("JODI")}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                chartType === "JODI"
                  ? "bg-amber-500 text-black shadow-md shadow-amber-500/30"
                  : "text-neutral-400 hover:text-white"
              }`}
            >
              Jodi Only
            </button>
          </div>

          <div className="text-xs text-neutral-400 hidden sm:block">
            Showing <strong className="text-white">{currentMarket?.name}</strong> records
          </div>
        </div>

        {/* Content Table */}
        {loading ? (
          <div className="py-16 text-center space-y-3">
            <RefreshCw className="w-8 h-8 text-amber-400 animate-spin mx-auto" />
            <p className="text-xs text-neutral-400">Loading historical results...</p>
          </div>
        ) : history.length === 0 ? (
          <div className="py-16 text-center space-y-2 bg-neutral-950/50 rounded-2xl border border-neutral-800">
            <p className="text-sm font-bold text-neutral-300">No past records found for this market yet</p>
            <p className="text-xs text-neutral-500">Declared records will automatically populate here.</p>
          </div>
        ) : (
          <div className="overflow-x-auto rounded-2xl border border-neutral-800 bg-neutral-950/60 max-h-96">
            <table className="w-full text-left text-xs">
              <thead className="bg-[#151620] text-neutral-400 uppercase tracking-wider font-bold border-b border-neutral-800 sticky top-0">
                <tr>
                  <th className="py-3 px-4">Date</th>
                  {chartType === "PANEL" && <th className="py-3 px-4 text-center">Open Pana</th>}
                  <th className="py-3 px-4 text-center">Jodi Digit</th>
                  {chartType === "PANEL" && <th className="py-3 px-4 text-center">Close Pana</th>}
                  <th className="py-3 px-4 text-center">Full Result</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-900 font-mono">
                {history.map((row, i) => {
                  const jodi = `${row.openDigit ?? "*"}${row.closeDigit ?? "*"}`;
                  const isRedJodi =
                    row.openDigit &&
                    row.closeDigit &&
                    (row.openDigit === row.closeDigit || Math.abs(parseInt(row.openDigit) - parseInt(row.closeDigit)) === 5);

                  return (
                    <tr key={i} className="hover:bg-neutral-900/60 transition-colors">
                      <td className="py-3 px-4 font-sans text-neutral-300 flex items-center gap-1.5">
                        <Calendar className="w-3.5 h-3.5 text-neutral-500" />
                        {row.resultDate}
                      </td>
                      {chartType === "PANEL" && (
                        <td className="py-3 px-4 text-center text-amber-300 font-bold">
                          {row.openPana || "---"}
                        </td>
                      )}
                      <td className="py-3 px-4 text-center">
                        <span
                          className={`inline-block px-2.5 py-1 rounded-lg font-bold text-sm ${
                            isRedJodi
                              ? "bg-red-950/80 text-red-400 border border-red-700/60"
                              : "bg-neutral-800 text-neutral-200"
                          }`}
                        >
                          {jodi}
                        </span>
                      </td>
                      {chartType === "PANEL" && (
                        <td className="py-3 px-4 text-center text-amber-300 font-bold">
                          {row.closePana || "---"}
                        </td>
                      )}
                      <td className="py-3 px-4 text-center font-bold text-neutral-300">
                        {row.fullResult}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        <div className="pt-2 text-right">
          <button
            onClick={onClose}
            className="px-5 py-2.5 rounded-xl bg-neutral-900 hover:bg-neutral-800 border border-neutral-800 text-xs font-bold text-neutral-200"
          >
            Close Panel
          </button>
        </div>
      </div>
    </div>
  );
}
