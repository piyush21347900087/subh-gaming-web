"use client";

import React, { useState, useEffect } from "react";
import { 
  X, 
  Wallet, 
  ArrowUpRight, 
  ArrowDownLeft, 
  Copy, 
  Check, 
  Clock, 
  AlertCircle, 
  CheckCircle2, 
  QrCode,
  CreditCard,
  ShieldAlert,
  Send,
  MessageCircle,
  ExternalLink
} from "lucide-react";

interface WalletModalProps {
  initialTab?: "deposit" | "withdraw" | "ledger";
  user: any;
  onClose: () => void;
  onBalanceUpdated: (newBal: string) => void;
}

export default function WalletModal({
  initialTab = "deposit",
  user,
  onClose,
  onBalanceUpdated,
}: WalletModalProps) {
  const [activeTab, setActiveTab] = useState<"deposit" | "withdraw" | "ledger">(initialTab);

  // Dynamic Gateway and Support settings from Admin
  const [settings, setSettings] = useState<Record<string, string>>({
    UPI_ID: "mahadevking@icici",
    UPI_MERCHANT_NAME: "Mahadev King Official",
    WHATSAPP_GROUP_URL: "https://chat.whatsapp.com/MahadevKingVIPClub2025",
    TELEGRAM_CHANNEL_URL: "https://t.me/MahadevKingOfficial555",
    HELPLINE_WHATSAPP: "+91 99999 88888",
  });

  // Deposit State
  const [depositAmount, setDepositAmount] = useState<number>(1000);
  const [utrNumber, setUtrNumber] = useState<string>("");
  const [copiedUpi, setCopiedUpi] = useState<boolean>(false);

  // Withdrawal State
  const [withdrawAmount, setWithdrawAmount] = useState<number>(500);
  const [payoutMethod, setPayoutMethod] = useState<"UPI" | "BANK_TRANSFER">("UPI");
  const [payoutDetails, setPayoutDetails] = useState<string>(user?.upiId || "");

  // Ledger State
  const [transactions, setTransactions] = useState<any[]>([]);
  const [ledgerLoading, setLedgerLoading] = useState<boolean>(false);

  const [loading, setLoading] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string>("");
  const [successMsg, setSuccessMsg] = useState<string>("");

  useEffect(() => {
    // Fetch live configured settings from Admin backend
    fetch("/api/admin/settings")
      .then((res) => res.json())
      .then((data) => {
        if (data.success && data.settings) {
          setSettings((prev) => ({ ...prev, ...data.settings }));
        }
      })
      .catch((e) => console.error(e));
  }, []);

  const handleCopyUpi = () => {
    navigator.clipboard.writeText(settings.UPI_ID || "mahadevking@icici");
    setCopiedUpi(true);
    setTimeout(() => setCopiedUpi(false), 2000);
  };

  const fetchLedger = async () => {
    setLedgerLoading(true);
    try {
      const res = await fetch("/api/wallet/ledger");
      const data = await res.json();
      if (data.success) {
        setTransactions(data.transactions || []);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setLedgerLoading(false);
    }
  };

  useEffect(() => {
    if (activeTab === "ledger") {
      fetchLedger();
    }
  }, [activeTab]);

  const handleDepositSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg("");
    setSuccessMsg("");

    if (!utrNumber || utrNumber.trim().length < 6) {
      setErrorMsg("Please enter the 12-digit UPI Reference / UTR Number");
      return;
    }

    setLoading(true);
    try {
      const res = await fetch("/api/wallet/deposit", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          amount: depositAmount,
          paymentMethod: "UPI",
          utrNumber: utrNumber.trim(),
        }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Deposit failed");

      setSuccessMsg(data.message || "Deposit request processed!");
      setUtrNumber("");
      if (data.newBalance) {
        onBalanceUpdated(data.newBalance);
      }
    } catch (err: any) {
      setErrorMsg(err.message || "Failed to submit request");
    } finally {
      setLoading(false);
    }
  };

  const handleWithdrawSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg("");
    setSuccessMsg("");

    if (!payoutDetails || payoutDetails.trim().length < 4) {
      setErrorMsg("Please enter your receiving UPI ID or Bank account + IFSC");
      return;
    }

    setLoading(true);
    try {
      const res = await fetch("/api/wallet/withdraw", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          amount: withdrawAmount,
          payoutMethod,
          payoutDetails: payoutDetails.trim(),
        }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Withdrawal failed");

      setSuccessMsg(data.message || "Withdrawal request submitted!");
      if (data.newBalance) {
        onBalanceUpdated(data.newBalance);
      }
    } catch (err: any) {
      setErrorMsg(err.message || "Failed to submit request");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
      <div className="relative w-full max-w-xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-5 my-auto">
        {/* Header */}
        <div className="flex items-start justify-between border-b border-neutral-800 pb-4">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-2xl bg-amber-500/20 text-amber-400 border border-amber-500/30">
              <Wallet className="w-6 h-6" />
            </div>
            <div>
              <h2 className="text-xl sm:text-2xl font-black text-white">
                Member Wallet Hub
              </h2>
              <div className="flex items-center gap-2 mt-0.5">
                <span className="text-xs text-neutral-400">Available Balance:</span>
                <span className="text-sm font-black text-amber-400">
                  ₹{parseFloat(user?.walletBalance || "0").toLocaleString("en-IN", { minimumFractionDigits: 2 })}
                </span>
              </div>
            </div>
          </div>

          <button
            onClick={onClose}
            className="p-2 rounded-xl bg-neutral-900 hover:bg-neutral-800 text-neutral-400 hover:text-white transition-colors border border-neutral-800"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Community Links Quick Access */}
        <div className="grid grid-cols-2 gap-2">
          {settings.WHATSAPP_GROUP_URL && (
            <a
              href={settings.WHATSAPP_GROUP_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="p-2 rounded-xl bg-emerald-950/50 hover:bg-emerald-900/50 border border-emerald-800/60 text-emerald-300 text-[11px] font-bold flex items-center justify-center gap-1.5 transition-all"
            >
              <MessageCircle className="w-3.5 h-3.5 text-emerald-400" />
              Join WhatsApp Group
            </a>
          )}
          {settings.TELEGRAM_CHANNEL_URL && (
            <a
              href={settings.TELEGRAM_CHANNEL_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="p-2 rounded-xl bg-blue-950/50 hover:bg-blue-900/50 border border-blue-800/60 text-blue-300 text-[11px] font-bold flex items-center justify-center gap-1.5 transition-all"
            >
              <Send className="w-3.5 h-3.5 text-blue-400" />
              Join Telegram Channel
            </a>
          )}
        </div>

        {/* Navigation Tabs */}
        <div className="grid grid-cols-3 gap-2 bg-neutral-950 p-1.5 rounded-2xl border border-neutral-800">
          <button
            onClick={() => {
              setActiveTab("deposit");
              setErrorMsg("");
              setSuccessMsg("");
            }}
            className={`py-2 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all ${
              activeTab === "deposit"
                ? "bg-emerald-600 text-white shadow-lg shadow-emerald-600/30"
                : "text-neutral-400 hover:text-white"
            }`}
          >
            <ArrowDownLeft className="w-3.5 h-3.5" />
            Deposit
          </button>
          <button
            onClick={() => {
              setActiveTab("withdraw");
              setErrorMsg("");
              setSuccessMsg("");
            }}
            className={`py-2 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all ${
              activeTab === "withdraw"
                ? "bg-rose-600 text-white shadow-lg shadow-rose-600/30"
                : "text-neutral-400 hover:text-white"
            }`}
          >
            <ArrowUpRight className="w-3.5 h-3.5" />
            Withdraw
          </button>
          <button
            onClick={() => {
              setActiveTab("ledger");
              setErrorMsg("");
              setSuccessMsg("");
            }}
            className={`py-2 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all ${
              activeTab === "ledger"
                ? "bg-amber-500 text-black shadow-lg shadow-amber-500/30"
                : "text-neutral-400 hover:text-white"
            }`}
          >
            <Clock className="w-3.5 h-3.5" />
            Passbook
          </button>
        </div>

        {/* TAB 1: DEPOSIT */}
        {activeTab === "deposit" && (
          <form onSubmit={handleDepositSubmit} className="space-y-4">
            <div className="p-4 rounded-2xl bg-neutral-950/80 border border-neutral-800 space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <span className="text-xs font-bold text-neutral-400 uppercase tracking-wider block">
                    Admin Configured Receiving UPI
                  </span>
                  <span className="text-[11px] text-amber-400 font-medium">
                    {settings.UPI_MERCHANT_NAME || "Mahadev King Official"}
                  </span>
                </div>
                <span className="text-[10px] bg-emerald-950 text-emerald-400 px-2 py-0.5 rounded font-bold border border-emerald-800">
                  Auto-Verified Gateway
                </span>
              </div>
              <div className="flex items-center justify-between p-3 rounded-xl bg-neutral-900 border border-neutral-800 font-mono text-sm text-amber-300 font-bold">
                <span>{settings.UPI_ID || "mahadevking@icici"}</span>
                <button
                  type="button"
                  onClick={handleCopyUpi}
                  className="p-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-neutral-300 transition-colors flex items-center gap-1 text-xs"
                >
                  {copiedUpi ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                  <span>{copiedUpi ? "Copied" : "Copy"}</span>
                </button>
              </div>
              <p className="text-[11px] text-neutral-400">
                Send deposit from PhonePe, GPay, Paytm, or BHIM to the UPI ID above, then submit your 12-digit UTR below.
              </p>
            </div>

            {/* Quick Amounts */}
            <div>
              <label className="block text-xs font-bold text-neutral-400 uppercase mb-2">
                Deposit Amount (₹)
              </label>
              <div className="grid grid-cols-4 gap-2 mb-2">
                {[500, 1000, 2000, 5000].map((amt) => (
                  <button
                    key={amt}
                    type="button"
                    onClick={() => setDepositAmount(amt)}
                    className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                      depositAmount === amt
                        ? "bg-emerald-600/30 text-emerald-300 border-emerald-500"
                        : "bg-neutral-900 text-neutral-400 border-neutral-800"
                    }`}
                  >
                    ₹{amt}
                  </button>
                ))}
              </div>
              <input
                type="number"
                min="100"
                value={depositAmount}
                onChange={(e) => setDepositAmount(Number(e.target.value))}
                className="w-full px-4 py-2.5 rounded-xl bg-neutral-900 border border-neutral-700 text-white font-mono font-bold text-base focus:outline-none focus:border-emerald-500"
              />
            </div>

            {/* UTR Input */}
            <div>
              <label className="block text-xs font-bold text-neutral-400 uppercase mb-1">
                12-Digit UTR / Transaction ID
              </label>
              <input
                type="text"
                value={utrNumber}
                onChange={(e) => setUtrNumber(e.target.value)}
                placeholder="e.g. 439810291039"
                required
                className="w-full px-4 py-2.5 rounded-xl bg-neutral-900 border border-neutral-700 text-white font-mono text-sm focus:outline-none focus:border-emerald-500"
              />
            </div>

            {/* Status alerts */}
            {errorMsg && (
              <div className="p-3 rounded-xl bg-red-950/70 border border-red-800 text-red-300 text-xs flex items-center gap-2">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <span>{errorMsg}</span>
              </div>
            )}
            {successMsg && (
              <div className="p-3 rounded-xl bg-emerald-950/70 border border-emerald-800 text-emerald-300 text-xs flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 shrink-0" />
                <span>{successMsg}</span>
              </div>
            )}

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-sm uppercase tracking-wider transition-all shadow-lg shadow-emerald-600/30"
            >
              {loading ? "Submitting..." : "Submit Deposit Request"}
            </button>
          </form>
        )}

        {/* TAB 2: WITHDRAW */}
        {activeTab === "withdraw" && (
          <form onSubmit={handleWithdrawSubmit} className="space-y-4">
            <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2">
              <ShieldAlert className="w-4 h-4 shrink-0" />
              <span>Minimum withdrawal is ₹500. Payouts processed 24x7.</span>
            </div>

            <div>
              <label className="block text-xs font-bold text-neutral-400 uppercase mb-2">
                Withdrawal Amount (₹)
              </label>
              <input
                type="number"
                min="500"
                max={parseFloat(user?.walletBalance || "0")}
                value={withdrawAmount}
                onChange={(e) => setWithdrawAmount(Number(e.target.value))}
                className="w-full px-4 py-2.5 rounded-xl bg-neutral-900 border border-neutral-700 text-white font-mono font-bold text-base focus:outline-none focus:border-rose-500"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-neutral-400 uppercase mb-2">
                Payout Channel
              </label>
              <div className="grid grid-cols-2 gap-2 mb-2">
                <button
                  type="button"
                  onClick={() => setPayoutMethod("UPI")}
                  className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                    payoutMethod === "UPI"
                      ? "bg-rose-600/30 text-rose-300 border-rose-500"
                      : "bg-neutral-900 text-neutral-400 border-neutral-800"
                  }`}
                >
                  Direct UPI ID
                </button>
                <button
                  type="button"
                  onClick={() => setPayoutMethod("BANK_TRANSFER")}
                  className={`py-2 rounded-xl text-xs font-bold border transition-all ${
                    payoutMethod === "BANK_TRANSFER"
                      ? "bg-rose-600/30 text-rose-300 border-rose-500"
                      : "bg-neutral-900 text-neutral-400 border-neutral-800"
                  }`}
                >
                  Bank Transfer (IMPS)
                </button>
              </div>

              <input
                type="text"
                value={payoutDetails}
                onChange={(e) => setPayoutDetails(e.target.value)}
                placeholder={
                  payoutMethod === "UPI"
                    ? "Enter receiving UPI (e.g. mobile@paytm)"
                    : "Enter Bank A/C Number + IFSC Code"
                }
                required
                className="w-full px-4 py-2.5 rounded-xl bg-neutral-900 border border-neutral-700 text-white text-sm focus:outline-none focus:border-rose-500"
              />
            </div>

            {errorMsg && (
              <div className="p-3 rounded-xl bg-red-950/70 border border-red-800 text-red-300 text-xs flex items-center gap-2">
                <AlertCircle className="w-4 h-4 shrink-0" />
                <span>{errorMsg}</span>
              </div>
            )}
            {successMsg && (
              <div className="p-3 rounded-xl bg-emerald-950/70 border border-emerald-800 text-emerald-300 text-xs flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 shrink-0" />
                <span>{successMsg}</span>
              </div>
            )}

            <button
              type="submit"
              disabled={loading || parseFloat(user?.walletBalance || "0") < withdrawAmount}
              className={`w-full py-3 rounded-xl font-extrabold text-sm uppercase tracking-wider transition-all ${
                parseFloat(user?.walletBalance || "0") >= withdrawAmount
                  ? "bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-600/30 cursor-pointer"
                  : "bg-neutral-800 text-neutral-500 cursor-not-allowed"
              }`}
            >
              {loading ? "Processing..." : `Confirm Withdrawal (₹${withdrawAmount})`}
            </button>
          </form>
        )}

        {/* TAB 3: PASSBOOK LEDGER */}
        {activeTab === "ledger" && (
          <div className="space-y-3">
            <div className="text-xs font-bold text-neutral-400 uppercase">
              Recent Passbook Statement
            </div>

            {ledgerLoading ? (
              <p className="text-xs text-neutral-400 py-8 text-center">Loading statement...</p>
            ) : transactions.length === 0 ? (
              <p className="text-xs text-neutral-500 py-8 text-center">No transaction records found</p>
            ) : (
              <div className="max-h-80 overflow-y-auto space-y-2 pr-1">
                {transactions.map((tx) => {
                  const isPositive = parseFloat(tx.amount) > 0;
                  return (
                    <div
                      key={tx.id}
                      className="p-3 rounded-xl bg-neutral-900 border border-neutral-800 flex items-center justify-between text-xs"
                    >
                      <div className="space-y-0.5">
                        <span className="font-bold text-white block">
                          {tx.description}
                        </span>
                        <span className="text-[10px] text-neutral-500">
                          {new Date(tx.createdAt).toLocaleString()}
                        </span>
                      </div>
                      <div className="text-right">
                        <span
                          className={`font-mono font-bold text-sm block ${
                            isPositive ? "text-emerald-400" : "text-rose-400"
                          }`}
                        >
                          {isPositive ? `+₹${tx.amount}` : `₹${tx.amount}`}
                        </span>
                        <span className="text-[10px] text-neutral-400 font-mono">
                          Bal: ₹{tx.balanceAfter}
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
