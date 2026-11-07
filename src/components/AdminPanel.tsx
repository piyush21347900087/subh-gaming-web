"use client";

import React, { useState, useEffect } from "react";
import Image from "next/image";
import { 
  Crown, 
  RotateCcw, 
  MessageSquare, 
  PlayCircle, 
  BarChart3, 
  Users, 
  Activity, 
  UserX, 
  UserPlus, 
  LineChart, 
  Trophy, 
  Layers, 
  Edit3, 
  ListFilter, 
  Grid, 
  Sparkles, 
  Star, 
  CreditCard, 
  Building2, 
  Sliders, 
  MessageCircle, 
  Megaphone, 
  Gift, 
  ShieldCheck, 
  HelpCircle, 
  Percent, 
  Type, 
  Award, 
  TrendingUp, 
  Wallet, 
  ArrowDownLeft, 
  ArrowUpRight, 
  RefreshCw, 
  X, 
  CheckCircle, 
  AlertTriangle, 
  Search, 
  Lock, 
  Download, 
  LogOut, 
  Save, 
  Trash2,
  Send,
  Cpu,
  Target,
  ToggleLeft,
  ToggleRight,
  Filter,
  Check,
  UserCheck
} from "lucide-react";
import { calculateAnkFromPana } from "@/lib/matka";

interface AdminPanelProps {
  currentUser: any;
  markets: any[];
  onRefreshMarkets: () => void;
}

export default function AdminPanel({
  currentUser,
  markets,
  onRefreshMarkets,
}: AdminPanelProps) {
  // Timeframe selector from screenshot: "Today", "7 Days", "15 Days", "30 Days", "All Time", "Custom..."
  const [timeframe, setTimeframe] = useState<string>("today");

  // Dashboard Metrics (Matching screenshot cards)
  const [metrics, setMetrics] = useState<any>({
    totalDeposit: "10000.00",
    totalWithdrawals: "0.00",
    profitAndLoss: "8750.00",
    bidTrade: "1250.00",
    bidRefund: "0.00",
    totalWinning: "0.00",
    usersWalletBalance: "44100.00",
    totalBonus: "0.00",
    balancePlusBonus: "44100.00",
    registrations: 5,
    pendingDeposits: 0,
    pendingWithdrawals: 0,
    pendingBanks: 0,
    gatewayCount: 1,
    liveUsers: 4,
    loginUsers: 5,
  });

  // Active Dialog / Modal when any of the 27 action buttons is clicked
  const [activeModal, setActiveModal] = useState<string | null>(null);

  // States for Modals
  // 1. All Bid Data & Number Exposure
  const [betsList, setBetsList] = useState<any[]>([]);
  const [numberExposure, setNumberExposure] = useState<any[]>([]);
  const [filterNumber, setFilterNumber] = useState<string>("");
  const [filterMarket, setFilterMarket] = useState<string>("ALL");
  const [filterUserSearch, setFilterUserSearch] = useState<string>("");
  const [betsLoading, setBetsLoading] = useState<boolean>(false);
  const [refundMsg, setRefundMsg] = useState<string>("");

  // 2. Open Market / Declare Result
  const [selectedMarketId, setSelectedMarketId] = useState<number>(markets[0]?.id || 1);
  const [openPana, setOpenPana] = useState<string>("");
  const [closePana, setClosePana] = useState<string>("");
  const [declareSuccess, setDeclareSuccess] = useState<string>("");
  const [declareError, setDeclareError] = useState<string>("");

  // 3. Payment & System Settings
  const [settingsForm, setSettingsForm] = useState<Record<string, string>>({
    PAYMENT_GATEWAY_PROVIDER: "UPI_DIRECT",
    UPI_ID: "subhgaming@icici",
    UPI_MERCHANT_NAME: "Subh Gaming Official",
    PAYMENT_API_KEY: "rzp_live_9a8bc76d54ef1234",
    PAYMENT_API_SECRET: "sec_live_9988776655443322",
    PAYMENT_AUTO_APPROVE: "true",
    WHATSAPP_GROUP_URL: "https://chat.whatsapp.com/SubhGamingVIPClub2025",
    TELEGRAM_CHANNEL_URL: "https://t.me/SubhGamingOfficial555",
    HELPLINE_WHATSAPP: "+91 99999 88888",
    HELPLINE_TELEGRAM: "@SubhGamingSupport24x7",
    BANNER_TEXT: "Subh Gaming - King of Online World! 100% genuine automated results & instant withdrawals 24x7.",
    ADMIN_NOTICE: "Official VIP Daily Jodi Leaks available on Telegram. Contact helpline for instant UPI deposit verification.",
    MIN_DEPOSIT: "100",
    MAX_DEPOSIT: "500000",
    MIN_WITHDRAWAL: "500",
    MAX_WITHDRAWAL: "200000",
    MAX_BET_PER_DIGIT: "50000",
    RATE_SINGLE_DIGIT: "9.5",
    RATE_JODI_DIGIT: "95",
    RATE_SINGLE_PANA: "140",
    RATE_DOUBLE_PANA: "280",
    RATE_TRIPLE_PANA: "600",
    RATE_HALF_SANGAM: "1000",
    RATE_FULL_SANGAM: "10000",
    BONUS_WELCOME: "0.00",
    BONUS_DEPOSIT_PCT: "5",
    BONUS_REFERRAL: "100",
    STARLINE_RULES: "Starline draws occur hourly from 10:00 AM to 10:00 PM. Bids close 5 mins prior to draw time. Single Ank pays 9.5x, Pana pays 100x. Results are automated.",
    HOW_TO_PLAY_TEXT: "1. Select any live market. 2. Choose Game Type (Single Digit 0-9, Jodi 00-99, or Pana 100-999). 3. Choose Open or Close session. 4. Enter your stake amount (min ₹10). 5. Winning amounts are credited instantly to your wallet upon result declaration.",
  });
  const [saveSuccess, setSaveSuccess] = useState<string>("");

  // 4. Users list
  const [allUsers, setAllUsers] = useState<any[]>([]);
  const [adjustingUserId, setAdjustingUserId] = useState<number | null>(null);
  const [adjustAmount, setAdjustAmount] = useState<string>("");
  const [adjustReason, setAdjustReason] = useState<string>("");
  const [userMsg, setUserMsg] = useState<string>("");

  // 5. Create Admin Form
  const [newAdminUser, setNewAdminUser] = useState<string>("");
  const [newAdminPass, setNewAdminPass] = useState<string>("");
  const [newAdminRole, setNewAdminRole] = useState<string>("STAFF");
  const [newAdminPhone, setNewAdminPhone] = useState<string>("");
  const [newAdminEmail, setNewAdminEmail] = useState<string>("");
  const [createAdminMsg, setCreateAdminMsg] = useState<string>("");
  const [createAdminErr, setCreateAdminErr] = useState<string>("");

  // 6. Deposits & Withdrawals
  const [deposits, setDeposits] = useState<any[]>([]);
  const [withdrawals, setWithdrawals] = useState<any[]>([]);
  const [payoutMsg, setPayoutMsg] = useState<string>("");

  // 7. DB Control state
  const [dbData, setDbData] = useState<any>(null);
  const [dbMsg, setDbMsg] = useState<string>("");

  // 8. Chat Support Desk
  const [supportChats, setSupportChats] = useState<any[]>([]);
  const [activeChatReplyId, setActiveChatReplyId] = useState<number | null>(null);
  const [chatReplyInput, setChatReplyInput] = useState<string>("");
  const [chatMsg, setChatMsg] = useState<string>("");

  // 9. Security Logs / OTP Records
  const [securityLogs, setSecurityLogs] = useState<any[]>([]);

  // 10. Add New Market state
  const [newMarketName, setNewMarketName] = useState<string>("");
  const [newMarketOpen, setNewMarketOpen] = useState<string>("10:00 AM");
  const [newMarketClose, setNewMarketClose] = useState<string>("12:00 PM");
  const [newMarketCat, setNewMarketCat] = useState<string>("REGULAR");
  const [marketMsg, setMarketMsg] = useState<string>("");

  // Fetch Dashboard Metrics
  const fetchDashboardMetrics = async (tf: string = timeframe) => {
    try {
      const res = await fetch(`/api/admin/stats?timeframe=${tf}`);
      const data = await res.json();
      if (data.success && data.dashboard) {
        setMetrics(data.dashboard);
      }
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch Bets for "All Bid Data" & "Total Winning"
  const fetchBets = async (numOverride?: string) => {
    setBetsLoading(true);
    try {
      const p = new URLSearchParams();
      if (filterMarket !== "ALL") p.append("marketId", filterMarket);
      const n = numOverride !== undefined ? numOverride : filterNumber;
      if (n.trim()) p.append("number", n.trim());
      if (filterUserSearch.trim()) p.append("search", filterUserSearch.trim());

      const res = await fetch(`/api/admin/bets?${p.toString()}`);
      const data = await res.json();
      if (data.success) {
        setBetsList(data.bets || []);
        setNumberExposure(data.numberExposure || []);
      }
    } catch (e) {
      console.error(e);
    } finally {
      setBetsLoading(false);
    }
  };

  // Fetch Settings
  const fetchSettings = async () => {
    try {
      const res = await fetch("/api/admin/settings");
      const data = await res.json();
      if (data.success && data.settings) {
        setSettingsForm((prev) => ({ ...prev, ...data.settings }));
      }
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch Users
  const fetchUsers = async () => {
    try {
      const res = await fetch("/api/admin/users");
      const data = await res.json();
      if (data.success) setAllUsers(data.users || []);
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch Deposits
  const fetchDeposits = async () => {
    try {
      const res = await fetch("/api/admin/deposits");
      const data = await res.json();
      if (data.success) setDeposits(data.requests || []);
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch Withdrawals
  const fetchWithdrawals = async () => {
    try {
      const res = await fetch("/api/admin/withdrawals");
      const data = await res.json();
      if (data.success) setWithdrawals(data.requests || []);
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch DB Info
  const fetchDbInfo = async () => {
    try {
      const res = await fetch("/api/admin/db-control");
      const data = await res.json();
      if (data.success) setDbData(data.database);
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch Support Chats
  const fetchChats = async () => {
    try {
      const res = await fetch("/api/admin/chat");
      const data = await res.json();
      if (data.success) setSupportChats(data.chats || []);
    } catch (e) {
      console.error(e);
    }
  };

  // Fetch Security Logs / OTP Records
  const fetchLogs = async () => {
    try {
      const res = await fetch("/api/admin/logs");
      const data = await res.json();
      if (data.success) setSecurityLogs(data.logs || []);
    } catch (e) {
      console.error(e);
    }
  };

  useEffect(() => {
    fetchDashboardMetrics(timeframe);
    fetchSettings();
  }, [timeframe]);

  const handleOpenAction = (actionKey: string) => {
    setActiveModal(actionKey);
    setSaveSuccess("");
    setDbMsg("");
    setPayoutMsg("");
    setUserMsg("");
    setCreateAdminMsg("");
    setCreateAdminErr("");
    setChatMsg("");
    setMarketMsg("");

    if (actionKey === "all_bids" || actionKey === "bid_revise" || actionKey === "total_winning" || actionKey === "recent_winners") {
      fetchBets();
    }
    if (actionKey === "users" || actionKey === "deactive_users" || actionKey === "live_account") {
      fetchUsers();
    }
    if (actionKey === "payout_update" || actionKey === "bank_update") {
      fetchDeposits();
      fetchWithdrawals();
    }
    if (actionKey === "reset_db") fetchDbInfo();
    if (actionKey === "chat") fetchChats();
    if (actionKey === "otp_record") fetchLogs();
  };

  const handleSaveSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const res = await fetch("/api/admin/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ settings: settingsForm }),
      });
      const data = await res.json();
      if (data.success) {
        setSaveSuccess("Settings updated successfully!");
        setTimeout(() => setSaveSuccess(""), 3000);
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleDeclareResult = async (e: React.FormEvent) => {
    e.preventDefault();
    setDeclareSuccess("");
    setDeclareError("");
    try {
      const res = await fetch("/api/admin/declare-result", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          marketId: selectedMarketId,
          openPana: openPana.trim() || undefined,
          closePana: closePana.trim() || undefined,
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to declare result");
      setDeclareSuccess(data.message);
      onRefreshMarkets();
      fetchDashboardMetrics();
      setOpenPana("");
      setClosePana("");
    } catch (err: any) {
      setDeclareError(err.message || "Result declaration failed");
    }
  };

  const handleRefundBet = async (betId: number, numVal: string, amtVal: string) => {
    if (!confirm(`Refund Bet #${betId} (₹${amtVal} on number '${numVal}') back to player wallet?`)) return;
    try {
      const res = await fetch("/api/admin/bets", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ betId, action: "REFUND", reason: "Refunded via Admin Boli Book" }),
      });
      const d = await res.json();
      if (d.success) {
        setRefundMsg(d.message);
        fetchBets();
        fetchDashboardMetrics();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleWithdrawalAction = async (requestId: number, action: "APPROVE" | "REJECT") => {
    try {
      const res = await fetch("/api/admin/withdrawals", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ requestId, action }),
      });
      const data = await res.json();
      if (data.success) {
        setPayoutMsg(data.message);
        fetchWithdrawals();
        fetchDashboardMetrics();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleToggleUser = async (userId: number) => {
    try {
      const res = await fetch("/api/admin/users", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ targetUserId: userId, action: "TOGGLE_STATUS" }),
      });
      const d = await res.json();
      if (d.success) {
        setUserMsg(d.message);
        fetchUsers();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleBalanceAdjust = async (userId: number) => {
    if (!adjustAmount || isNaN(parseFloat(adjustAmount))) return;
    try {
      const res = await fetch("/api/admin/users", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          targetUserId: userId,
          action: "ADJUST_BALANCE",
          value: adjustAmount,
          reason: adjustReason || "Admin manual adjustment",
        }),
      });
      const d = await res.json();
      if (d.success) {
        setUserMsg(d.message);
        setAdjustingUserId(null);
        setAdjustAmount("");
        setAdjustReason("");
        fetchUsers();
        fetchDashboardMetrics();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleCreateAdmin = async (e: React.FormEvent) => {
    e.preventDefault();
    setCreateAdminMsg("");
    setCreateAdminErr("");
    try {
      const res = await fetch("/api/admin/users", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: newAdminUser,
          password: newAdminPass,
          role: newAdminRole,
          phone: newAdminPhone,
          email: newAdminEmail,
        }),
      });
      const d = await res.json();
      if (!res.ok) throw new Error(d.error || "Failed to create admin");
      setCreateAdminMsg(d.message);
      setNewAdminUser("");
      setNewAdminPass("");
      setNewAdminPhone("");
      setNewAdminEmail("");
      fetchUsers();
      fetchDashboardMetrics();
    } catch (err: any) {
      setCreateAdminErr(err.message);
    }
  };

  const handleSendChatReply = async (chatId: number) => {
    if (!chatReplyInput) return;
    try {
      const res = await fetch("/api/admin/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ chatId, replyText: chatReplyInput }),
      });
      const d = await res.json();
      if (d.success) {
        setChatMsg("Reply sent to member successfully!");
        setChatReplyInput("");
        setActiveChatReplyId(null);
        fetchChats();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const handleToggleMarketStatus = async (marketId: number, currentStatus: string) => {
    const nextStatus = currentStatus === "OPEN" ? "CLOSED" : "OPEN";
    try {
      await fetch("/api/admin/markets", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ marketId, status: nextStatus }),
      });
      onRefreshMarkets();
    } catch (e) {
      console.error(e);
    }
  };

  const handleCreateMarket = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newMarketName) return;
    try {
      const res = await fetch("/api/admin/markets", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name: newMarketName,
          openTime: newMarketOpen,
          closeTime: newMarketClose,
          category: newMarketCat,
        }),
      });
      const d = await res.json();
      if (d.success) {
        setMarketMsg(`Market '${newMarketName}' created successfully!`);
        setNewMarketName("");
        onRefreshMarkets();
      }
    } catch (e) {
      console.error(e);
    }
  };

  const computedOpenAnk = openPana.length === 3 ? calculateAnkFromPana(openPana) : "";
  const computedCloseAnk = closePana.length === 3 ? calculateAnkFromPana(closePana) : "";

  // Filtered winning bets for "total_winning" and "recent_winners"
  const winningBets = betsList.filter((b) => b.status === "WON" || parseFloat(b.wonAmount || "0") > 0);

  return (
    <div className="space-y-4 text-white">
      {/* 1. TOP HEADER (Matching Screenshot Exactly) */}
      <div className="bg-[#10121d] border border-neutral-800 rounded-2xl p-3 flex flex-col md:flex-row items-center justify-between gap-3 shadow-xl">
        {/* Brand Logo & Name */}
        <div className="flex items-center gap-2.5">
          <div className="relative w-9 h-9 rounded-xl overflow-hidden border border-amber-500/50 bg-black p-1 shadow-md">
            <Image
              src="/images/subh-gaming-logo.png"
              alt="Logo"
              fill
              className="object-contain"
            />
          </div>
          <div className="flex items-center gap-1.5">
            <Crown className="w-5 h-5 text-amber-400 fill-amber-400" />
            <span className="font-black text-lg sm:text-xl tracking-wider text-white">
              SUBH GAMING ADMIN
            </span>
            <span className="text-xs text-amber-400 font-bold hidden lg:inline">
              (MASTER CONTROL)
            </span>
          </div>
        </div>

        {/* Timeframe Filter Pills (Today, 7 Days, 15 Days, 30 Days, All Time, Custom...) */}
        <div className="flex items-center gap-1 bg-[#181a29] p-1 rounded-xl border border-neutral-700/60 overflow-x-auto max-w-full">
          {[
            { id: "today", label: "Today" },
            { id: "7days", label: "7 Days" },
            { id: "15days", label: "15 Days" },
            { id: "30days", label: "30 Days" },
            { id: "all", label: "All Time" },
            { id: "custom", label: "Custom..." },
          ].map((tf) => (
            <button
              key={tf.id}
              onClick={() => setTimeframe(tf.id)}
              className={`px-3 py-1 rounded-lg text-xs font-bold transition-all whitespace-nowrap ${
                timeframe === tf.id
                  ? "bg-[#0288d1] text-white shadow-md shadow-sky-600/30"
                  : "text-neutral-300 hover:text-white hover:bg-neutral-800/60"
              }`}
            >
              {tf.label}
            </button>
          ))}
        </div>

        {/* Right Badges (APK: 0 & Logout) */}
        <div className="flex items-center gap-2 shrink-0">
          <a
            href="/api/download/apk?download=true"
            className="px-3 py-1 rounded-lg bg-[#181a29] border border-sky-500/40 text-sky-400 hover:text-sky-300 text-xs font-bold flex items-center gap-1.5 transition-colors"
          >
            <Download className="w-3.5 h-3.5" />
            <span>APK: 0</span>
          </a>
          <button
            onClick={() => window.location.href = "/"}
            className="px-3.5 py-1 rounded-lg bg-[#e53935] hover:bg-red-600 text-white text-xs font-bold flex items-center gap-1 shadow-md transition-all"
          >
            <LogOut className="w-3.5 h-3.5" />
            Logout
          </button>
        </div>
      </div>

      {/* 2. 27 COLORFUL ACTION BUTTONS (Exact 3 Rows of 9 Buttons) */}
      <div className="bg-[#10121d] border border-neutral-800 rounded-2xl p-3 shadow-xl space-y-2">
        {/* ROW 1 (9 Buttons) */}
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-1.5 text-xs font-bold">
          <button
            onClick={() => handleOpenAction("reset_db")}
            className="py-2.5 px-2 rounded-xl bg-[#e53935] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <RotateCcw className="w-3.5 h-3.5" />
            Reset DB
          </button>

          <button
            onClick={() => handleOpenAction("chat")}
            className="py-2.5 px-2 rounded-xl bg-[#43a047] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <MessageSquare className="w-3.5 h-3.5" />
            Chat 0
          </button>

          <button
            onClick={() => handleOpenAction("open_market")}
            className="py-2.5 px-2 rounded-xl bg-[#0288d1] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <PlayCircle className="w-3.5 h-3.5" />
            Open Market
          </button>

          <button
            onClick={() => handleOpenAction("statistics")}
            className="py-2.5 px-2 rounded-xl bg-[#fb8c00] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <BarChart3 className="w-3.5 h-3.5" />
            Statistics
          </button>

          <button
            onClick={() => handleOpenAction("users")}
            className="py-2.5 px-2 rounded-xl bg-[#8d6e63] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Users className="w-3.5 h-3.5" />
            Users ({metrics.loginUsers || 0})
          </button>

          <button
            onClick={() => handleOpenAction("live_account")}
            className="py-2.5 px-2 rounded-xl bg-[#43a047] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Activity className="w-3.5 h-3.5" />
            Live Account
          </button>

          <button
            onClick={() => handleOpenAction("deactive_users")}
            className="py-2.5 px-2 rounded-xl bg-[#c62828] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <UserX className="w-3.5 h-3.5" />
            Deactive Users
          </button>

          <button
            onClick={() => handleOpenAction("create_admin")}
            className="py-2.5 px-2 rounded-xl bg-[#8e24aa] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <UserPlus className="w-3.5 h-3.5" />
            Create Admin
          </button>

          <button
            onClick={() => handleOpenAction("data_analytics")}
            className="py-2.5 px-2 rounded-xl bg-[#039be5] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <LineChart className="w-3.5 h-3.5" />
            Data Analytics
          </button>
        </div>

        {/* ROW 2 (9 Buttons) */}
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-1.5 text-xs font-bold">
          <button
            onClick={() => handleOpenAction("total_winning")}
            className="py-2.5 px-2 rounded-xl bg-[#5e35b1] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Trophy className="w-3.5 h-3.5" />
            Rs Total Wining
          </button>

          <button
            onClick={() => handleOpenAction("all_bids")}
            className="py-2.5 px-2 rounded-xl bg-[#5d4037] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Layers className="w-3.5 h-3.5 text-amber-300" />
            All Bid Data
          </button>

          <button
            onClick={() => handleOpenAction("bid_revise")}
            className="py-2.5 px-2 rounded-xl bg-[#d84315] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Edit3 className="w-3.5 h-3.5" />
            Bid Revise
          </button>

          <button
            onClick={() => handleOpenAction("market_list")}
            className="py-2.5 px-2 rounded-xl bg-[#0288d1] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <ListFilter className="w-3.5 h-3.5" />
            Market List
          </button>

          <button
            onClick={() => handleOpenAction("market_list_2")}
            className="py-2.5 px-2 rounded-xl bg-[#00acc1] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Grid className="w-3.5 h-3.5" />
            Market List 2
          </button>

          <button
            onClick={() => handleOpenAction("market_list_4")}
            className="py-2.5 px-2 rounded-xl bg-[#b71c1c] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Sparkles className="w-3.5 h-3.5" />
            Market List 4
          </button>

          <button
            onClick={() => handleOpenAction("starline")}
            className="py-2.5 px-2 rounded-xl bg-[#6a1b9a] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Star className="w-3.5 h-3.5" />
            Starline Condn
          </button>

          <button
            onClick={() => handleOpenAction("payout_update")}
            className="py-2.5 px-2 rounded-xl bg-[#4e342e] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <CreditCard className="w-3.5 h-3.5" />
            Payout Update
          </button>

          <button
            onClick={() => handleOpenAction("bank_update")}
            className="py-2.5 px-2 rounded-xl bg-[#00897b] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Building2 className="w-3.5 h-3.5" />
            Bank Update
          </button>
        </div>

        {/* ROW 3 (9 Buttons) */}
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-9 gap-1.5 text-xs font-bold">
          <button
            onClick={() => handleOpenAction("set_limit")}
            className="py-2.5 px-2 rounded-xl bg-[#37474f] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Sliders className="w-3.5 h-3.5" />
            Set Limit
          </button>

          <button
            onClick={() => handleOpenAction("whatsap_update")}
            className="py-2.5 px-2 rounded-xl bg-[#2e7d32] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <MessageCircle className="w-3.5 h-3.5" />
            Whatsap Update
          </button>

          <button
            onClick={() => handleOpenAction("admin_notice")}
            className="py-2.5 px-2 rounded-xl bg-[#c2185b] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Megaphone className="w-3.5 h-3.5" />
            Admin Notice
          </button>

          <button
            onClick={() => handleOpenAction("bonus_rates")}
            className="py-2.5 px-2 rounded-xl bg-[#1565c0] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Gift className="w-3.5 h-3.5" />
            Bonus Rates
          </button>

          <button
            onClick={() => handleOpenAction("otp_record")}
            className="py-2.5 px-2 rounded-xl bg-[#455a64] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <ShieldCheck className="w-3.5 h-3.5" />
            OTP RECORD
          </button>

          <button
            onClick={() => handleOpenAction("how_to_play")}
            className="py-2.5 px-2 rounded-xl bg-[#ef6c00] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <HelpCircle className="w-3.5 h-3.5" />
            How to Play
          </button>

          <button
            onClick={() => handleOpenAction("game_rate")}
            className="py-2.5 px-2 rounded-xl bg-[#5d4037] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Percent className="w-3.5 h-3.5" />
            Game Rate
          </button>

          <button
            onClick={() => handleOpenAction("banner_text")}
            className="py-2.5 px-2 rounded-xl bg-[#37474f] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Type className="w-3.5 h-3.5" />
            Banner text
          </button>

          <button
            onClick={() => handleOpenAction("recent_winners")}
            className="py-2.5 px-2 rounded-xl bg-[#d81b60] hover:opacity-90 text-white flex items-center justify-center gap-1.5 shadow"
          >
            <Award className="w-3.5 h-3.5" />
            Recent Winners
          </button>
        </div>
      </div>

      {/* 3. SUBHEADER: Showing data: Today — Welcome, SUBH GAMING [ROLE: MASTER] ● Live: 0 | ● Login: 0 */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-2 px-3 py-1.5 text-xs text-neutral-300">
        <div className="flex items-center gap-2 flex-wrap">
          <span>Showing data: <strong className="text-white capitalize">{timeframe}</strong></span>
          <span>—</span>
          <span>Welcome, <strong className="text-white">SUBH GAMING</strong></span>
          <span className="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-[#2e7d32] text-white">
            ROLE: MASTER
          </span>
        </div>

        {/* Live and Login Counters Badge */}
        <div className="flex items-center gap-2 bg-[#181a29] px-3 py-1 rounded-full border border-neutral-800 text-[11px] font-bold">
          <span className="flex items-center gap-1 text-emerald-400">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping" />
            ● Live: {metrics.liveUsers || 0}
          </span>
          <span className="text-neutral-500">|</span>
          <span className="flex items-center gap-1 text-amber-300">
            ● Login: {metrics.loginUsers || 0}
          </span>
        </div>
      </div>

      {/* 4. METRIC CARDS GRID (14 White Cards with Colored Top Borders - Exactly as Screenshot) */}
      <div className="space-y-3">
        {/* ROW 1: 5 Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#00acc1] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">₹{metrics.totalDeposit}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Total Deposit</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#fb8c00] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">₹{metrics.totalWithdrawals}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Total Withdrawals</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#43a047] flex flex-col justify-center min-h-24">
            <div className="flex items-center justify-center gap-1 text-xl sm:text-2xl font-black font-mono text-emerald-700">
              <TrendingUp className="w-5 h-5 text-emerald-600" />
              <span>₹{metrics.profitAndLoss}</span>
            </div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Profit and Loss</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#0288d1] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">₹{metrics.bidTrade}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Bid Trade</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#8e24aa] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">₹{metrics.bidRefund}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Bid Refund</div>
          </div>
        </div>

        {/* ROW 2: 5 Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#00897b] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">₹{metrics.totalWinning}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Total Wining (Today)</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#039be5] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">₹{metrics.usersWalletBalance}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Users Wallet Balance</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#fbc02d] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">₹{metrics.totalBonus}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Total Bonus</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#7b1fa2] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">₹{metrics.balancePlusBonus}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Balance + Bonus</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#0288d1] flex flex-col justify-center min-h-24">
            <div className="text-xl sm:text-2xl font-black font-mono">{metrics.registrations}</div>
            <div className="text-xs font-bold text-neutral-600 mt-1">Registrations</div>
          </div>
        </div>

        {/* ROW 3: 4 Bottom Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#0288d1] flex flex-col justify-center min-h-20">
            <div className="text-2xl font-black font-mono">{metrics.pendingDeposits || 0}</div>
            <div className="text-xs font-bold text-neutral-600 mt-0.5">Pending Deposits</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#fbc02d] flex flex-col justify-center min-h-20">
            <div className="text-2xl font-black font-mono">{metrics.pendingWithdrawals || 0}</div>
            <div className="text-xs font-bold text-neutral-600 mt-0.5">Pending Withdrawals</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#6d4c41] flex flex-col justify-center min-h-20">
            <div className="text-2xl font-black font-mono">{metrics.pendingBanks || 0}</div>
            <div className="text-xs font-bold text-neutral-600 mt-0.5">Bank / UPI Requests</div>
          </div>

          <div className="bg-white text-neutral-900 rounded-2xl p-4 text-center shadow-lg border-t-4 border-[#00acc1] flex flex-col justify-center min-h-20">
            <div className="text-2xl font-black font-mono">{metrics.gatewayCount || 1}</div>
            <div className="text-xs font-bold text-neutral-600 mt-0.5">Active Payment Gateways</div>
          </div>
        </div>
      </div>

      {/* =========================================================================
          5. COMPLETE INTERACTIVE FUNCTIONAL DIALOGS FOR ALL 27 ACTION BUTTONS
          ========================================================================= */}

      {/* MODAL 1: RESET DB */}
      {activeModal === "reset_db" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-xl rounded-3xl bg-[#0f1017] border border-red-500/40 shadow-2xl p-5 sm:p-8 space-y-5 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-2xl bg-red-600/20 text-red-400 border border-red-600/30">
                  <RotateCcw className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="text-xl font-black text-white">Reset & Rebuild Database</h3>
                  <p className="text-xs text-neutral-400">PostgreSQL Relational Engine Control</p>
                </div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {dbMsg && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{dbMsg}</div>}
            <div className="p-4 rounded-2xl bg-red-950/30 border border-red-800/50 space-y-2">
              <h4 className="text-xs font-bold text-red-400 uppercase">Warning: Clean Database Rebuild</h4>
              <p className="text-xs text-neutral-300 leading-relaxed">
                Clicking the button below will clear all test wagers, reset account balances cleanly to ₹0.00, and generate official markets.
              </p>
            </div>
            <div className="flex items-center gap-2">
              <button
                onClick={async () => {
                  if (confirm("Confirm complete database reset & reseed?")) {
                    const res = await fetch("/api/admin/db-control", {
                      method: "POST",
                      headers: { "Content-Type": "application/json" },
                      body: JSON.stringify({ action: "RESEED_DATABASE" }),
                    });
                    const d = await res.json();
                    setDbMsg(d.message || "Database reset successfully!");
                    fetchDashboardMetrics();
                  }
                }}
                className="flex-1 py-3 rounded-xl bg-red-600 hover:bg-red-500 text-white font-extrabold text-xs uppercase tracking-wider shadow-lg shadow-red-600/30"
              >
                Confirm Reset & Reseed Database
              </button>
              <button onClick={() => setActiveModal(null)} className="px-4 py-3 rounded-xl bg-neutral-900 text-neutral-400 text-xs font-bold">Cancel</button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 2: CHAT 0 */}
      {activeModal === "chat" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-2xl rounded-3xl bg-[#0f1017] border border-emerald-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-emerald-600/20 text-emerald-400 border border-emerald-600/30"><MessageSquare className="w-5 h-5" /></div>
                <div><h3 className="text-xl font-black text-white">Live Member Chat & Support Desk</h3><p className="text-xs text-neutral-400">Incoming member queries and instant reply inbox</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {chatMsg && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{chatMsg}</div>}
            <div className="space-y-3">
              {supportChats.map((c) => (
                <div key={c.id} className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-2">
                  <div className="flex items-center justify-between">
                    <div>
                      <span className="font-bold text-white text-xs">{c.username}</span>
                      <span className="text-[11px] text-neutral-500 ml-2 font-mono">{c.phone} • {c.time}</span>
                    </div>
                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${c.status === "RESOLVED" ? "bg-emerald-950 text-emerald-400" : "bg-amber-950 text-amber-400"}`}>
                      {c.status}
                    </span>
                  </div>
                  <p className="text-xs text-neutral-200 bg-neutral-900 p-2.5 rounded-xl border border-neutral-800">{c.message}</p>
                  {c.reply && (
                    <div className="p-2.5 rounded-xl bg-emerald-950/40 border border-emerald-800/40 text-xs text-emerald-300">
                      <strong>Admin Reply:</strong> {c.reply}
                    </div>
                  )}
                  {activeChatReplyId === c.id ? (
                    <div className="flex gap-2 pt-2">
                      <input
                        type="text"
                        placeholder="Type reply to member..."
                        value={chatReplyInput}
                        onChange={(e) => setChatReplyInput(e.target.value)}
                        className="flex-1 px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white"
                      />
                      <button onClick={() => handleSendChatReply(c.id)} className="px-3 py-1.5 rounded-xl gold-button text-xs font-bold">Send</button>
                      <button onClick={() => setActiveChatReplyId(null)} className="px-2 py-1.5 rounded-xl bg-neutral-800 text-neutral-400 text-xs">X</button>
                    </div>
                  ) : (
                    <button onClick={() => { setActiveChatReplyId(c.id); setChatReplyInput(c.reply || ""); }} className="text-xs text-amber-400 hover:underline font-bold">
                      {c.reply ? "Edit Reply" : "Send Reply"}
                    </button>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: OPEN MARKET */}
      {activeModal === "open_market" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-5 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-2xl bg-sky-500/20 text-sky-400 border border-sky-500/30"><PlayCircle className="w-6 h-6" /></div>
                <div><h3 className="text-xl font-black text-white">Open Market & Declare Results</h3><p className="text-xs text-neutral-400">Automated payout calculation and winner distribution</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {declareSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{declareSuccess}</div>}
            {declareError && <div className="p-3 rounded-xl bg-red-950 text-red-300 text-xs border border-red-800">{declareError}</div>}
            <form onSubmit={handleDeclareResult} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Select Market</label>
                <select
                  value={selectedMarketId}
                  onChange={(e) => setSelectedMarketId(Number(e.target.value))}
                  className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-amber-300 text-xs font-bold"
                >
                  {markets.map((m) => (
                    <option key={m.id} value={m.id}>{m.name} [{m.fullResult}] ({m.openTime} - {m.closeTime})</option>
                  ))}
                </select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Open Pana (3 Digits)</label>
                  <input
                    type="text"
                    maxLength={3}
                    placeholder="e.g. 138"
                    value={openPana}
                    onChange={(e) => setOpenPana(e.target.value.replace(/\D/g, ""))}
                    className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-lg font-mono font-bold text-white"
                  />
                  <span className="text-[11px] text-neutral-400 mt-1 block">Ank: <strong className="text-amber-400">{computedOpenAnk || "-"}</strong></span>
                </div>
                <div>
                  <label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Close Pana (3 Digits)</label>
                  <input
                    type="text"
                    maxLength={3}
                    placeholder="e.g. 468"
                    value={closePana}
                    onChange={(e) => setClosePana(e.target.value.replace(/\D/g, ""))}
                    className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-lg font-mono font-bold text-white"
                  />
                  <span className="text-[11px] text-neutral-400 mt-1 block">Ank: <strong className="text-amber-400">{computedCloseAnk || "-"}</strong></span>
                </div>
              </div>
              <button type="submit" disabled={!openPana && !closePana} className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase tracking-wider">
                Declare Result & Distribute Winnings
              </button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 4: STATISTICS & POOLS */}
      {activeModal === "statistics" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-4xl rounded-3xl bg-[#0f1017] border border-orange-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-orange-600/20 text-orange-400 border border-orange-600/30"><BarChart3 className="w-5 h-5" /></div>
                <div><h3 className="text-xl font-black text-white">Live Market Statistics & Pool Volume</h3><p className="text-xs text-neutral-400">Total volume and wager breakdown across markets</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              {markets.map((m) => (
                <div key={m.id} className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-1">
                  <div className="flex justify-between items-center">
                    <span className="font-bold text-white text-sm">{m.name}</span>
                    <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${m.status === "OPEN" ? "bg-emerald-950 text-emerald-400" : "bg-red-950 text-red-400"}`}>{m.status}</span>
                  </div>
                  <span className="text-xs text-neutral-400 font-mono block">Timings: {m.openTime} - {m.closeTime}</span>
                  <span className="text-xs text-amber-400 font-mono font-bold block">Current: [{m.fullResult}]</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* MODAL 5: USERS (5) */}
      {activeModal === "users" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-4xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div><h3 className="text-xl font-black text-white">Users Management</h3><p className="text-xs text-neutral-400">Manage balances, toggle status, and roles</p></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {userMsg && <div className="p-2.5 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{userMsg}</div>}
            <div className="overflow-x-auto rounded-xl border border-neutral-800">
              <table className="w-full text-left text-xs font-sans">
                <thead className="bg-[#181a29] text-neutral-400 font-bold uppercase">
                  <tr>
                    <th className="py-2.5 px-3">Username</th>
                    <th className="py-2.5 px-3">Phone</th>
                    <th className="py-2.5 px-3">Role</th>
                    <th className="py-2.5 px-3">Wallet</th>
                    <th className="py-2.5 px-3">Status</th>
                    <th className="py-2.5 px-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-900 font-mono">
                  {allUsers.map((u) => (
                    <tr key={u.id} className="hover:bg-neutral-900/50">
                      <td className="py-2.5 px-3 font-bold text-white font-sans">{u.username}</td>
                      <td className="py-2.5 px-3 text-neutral-400">{u.phone || "No phone"}</td>
                      <td className="py-2.5 px-3 font-sans"><span className="px-2 py-0.5 rounded bg-neutral-800 text-neutral-300 text-[10px] font-bold">{u.role}</span></td>
                      <td className="py-2.5 px-3 font-bold text-amber-400">₹{u.walletBalance}</td>
                      <td className="py-2.5 px-3">
                        <button onClick={() => handleToggleUser(u.id)} className={`px-2 py-0.5 rounded text-[10px] font-bold ${u.status === "ACTIVE" ? "bg-emerald-950 text-emerald-400" : "bg-red-950 text-red-400"}`}>
                          {u.status}
                        </button>
                      </td>
                      <td className="py-2.5 px-3 text-right font-sans">
                        {adjustingUserId === u.id ? (
                          <div className="flex gap-1 justify-end">
                            <input
                              type="number"
                              placeholder="+/- ₹"
                              value={adjustAmount}
                              onChange={(e) => setAdjustAmount(e.target.value)}
                              className="w-20 px-2 py-1 rounded bg-neutral-900 border border-neutral-700 text-xs font-mono"
                            />
                            <button onClick={() => handleBalanceAdjust(u.id)} className="px-2 py-1 rounded bg-emerald-600 text-white font-bold text-xs">Save</button>
                            <button onClick={() => setAdjustingUserId(null)} className="px-2 py-1 rounded bg-neutral-800 text-neutral-400 text-xs">X</button>
                          </div>
                        ) : (
                          <button onClick={() => setAdjustingUserId(u.id)} className="px-2.5 py-1 rounded bg-neutral-900 border border-neutral-700 text-neutral-300 hover:text-white text-[11px] font-bold">
                            Adjust ₹
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 6: LIVE ACCOUNT */}
      {activeModal === "live_account" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-2xl rounded-3xl bg-[#0f1017] border border-emerald-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-emerald-600/20 text-emerald-400 border border-emerald-600/30"><Activity className="w-5 h-5" /></div>
                <div><h3 className="text-xl font-black text-white">Live Online Accounts</h3><p className="text-xs text-neutral-400">Members with active web/app sessions right now</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            <div className="space-y-2">
              {allUsers.slice(0, 5).map((u) => (
                <div key={u.id} className="p-3 rounded-2xl bg-neutral-950 border border-neutral-800 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-2">
                    <span className="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping" />
                    <div><strong className="text-white block">{u.username}</strong><span className="text-[10px] text-neutral-500 font-mono">{u.phone || "Active Session"}</span></div>
                  </div>
                  <div className="text-right"><span className="text-amber-400 font-mono font-bold">₹{u.walletBalance}</span><span className="text-[10px] text-emerald-400 block font-bold">ONLINE</span></div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* MODAL 7: DEACTIVE USERS */}
      {activeModal === "deactive_users" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-2xl rounded-3xl bg-[#0f1017] border border-red-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-red-600/20 text-red-400 border border-red-600/30"><UserX className="w-5 h-5" /></div>
                <div><h3 className="text-xl font-black text-white">Deactive & Restricted Users</h3><p className="text-xs text-neutral-400">Review accounts suspended or banned by administration</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {allUsers.filter((u) => u.status !== "ACTIVE").length === 0 ? (
              <div className="p-6 rounded-2xl bg-neutral-950 border border-neutral-800 text-center text-neutral-400 text-xs">
                All accounts are currently in good standing. No deactive users found.
              </div>
            ) : (
              <div className="space-y-2">
                {allUsers.filter((u) => u.status !== "ACTIVE").map((u) => (
                  <div key={u.id} className="p-3 rounded-2xl bg-neutral-950 border border-neutral-800 flex items-center justify-between text-xs">
                    <div><strong className="text-white block">{u.username}</strong><span className="text-[10px] text-neutral-500 font-mono">{u.phone}</span></div>
                    <button onClick={() => handleToggleUser(u.id)} className="px-3 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs">
                      Reactivate Account
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}

      {/* MODAL 8: CREATE ADMIN */}
      {activeModal === "create_admin" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-purple-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-purple-600/20 text-purple-400 border border-purple-600/30"><UserPlus className="w-5 h-5" /></div>
                <div><h3 className="text-xl font-black text-white">Create Admin / Staff Account</h3><p className="text-xs text-neutral-400">Issue administrative access credentials</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {createAdminMsg && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{createAdminMsg}</div>}
            {createAdminErr && <div className="p-3 rounded-xl bg-red-950 text-red-300 text-xs border border-red-800">{createAdminErr}</div>}
            <form onSubmit={handleCreateAdmin} className="space-y-3">
              <div>
                <label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Username</label>
                <input type="text" required value={newAdminUser} onChange={(e) => setNewAdminUser(e.target.value)} placeholder="e.g. subh_officer" className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" />
              </div>
              <div>
                <label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Password</label>
                <input type="password" required value={newAdminPass} onChange={(e) => setNewAdminPass(e.target.value)} placeholder="••••••••" className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" />
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Role</label>
                  <select value={newAdminRole} onChange={(e) => setNewAdminRole(e.target.value)} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-amber-300 font-bold">
                    <option value="STAFF">Staff Officer</option>
                    <option value="ADMIN">Super Admin</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Phone (Optional)</label>
                  <input type="tel" value={newAdminPhone} onChange={(e) => setNewAdminPhone(e.target.value)} placeholder="9876543210" className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" />
                </div>
              </div>
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Create Admin Credentials</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 9: DATA ANALYTICS */}
      {activeModal === "data_analytics" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-3xl rounded-3xl bg-[#0f1017] border border-sky-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-sky-600/20 text-sky-400 border border-sky-600/30"><LineChart className="w-5 h-5" /></div>
                <div><h3 className="text-xl font-black text-white">Data Analytics & Profit Loss Summary</h3><p className="text-xs text-neutral-400">Financial margin analysis and turnover statistics</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
              <div className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 text-center"><span className="text-xs text-neutral-400 uppercase font-bold">Total Staked Volume</span><div className="text-xl font-mono font-black text-white mt-1">₹{metrics.bidTrade}</div></div>
              <div className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 text-center"><span className="text-xs text-neutral-400 uppercase font-bold">Total Winnings Paid</span><div className="text-xl font-mono font-black text-emerald-400 mt-1">₹{metrics.totalWinning}</div></div>
              <div className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 text-center"><span className="text-xs text-neutral-400 uppercase font-bold">Net House Margin</span><div className="text-xl font-mono font-black text-amber-400 mt-1">₹{metrics.profitAndLoss}</div></div>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 10: RS TOTAL WINING */}
      {activeModal === "total_winning" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-4xl rounded-3xl bg-[#0f1017] border border-purple-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-purple-600/20 text-purple-400 border border-purple-600/30"><Trophy className="w-5 h-5" /></div>
                <div><h3 className="text-xl font-black text-white">Rs Total Wining Ledger</h3><p className="text-xs text-neutral-400">All winning bets, winning numbers, and payouts distributed</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            <div className="overflow-x-auto rounded-xl border border-neutral-800">
              <table className="w-full text-left text-xs">
                <thead className="bg-[#181a29] text-neutral-400 font-bold uppercase">
                  <tr>
                    <th className="py-2.5 px-3">Player</th>
                    <th className="py-2.5 px-3">Market</th>
                    <th className="py-2.5 px-3">Game Type</th>
                    <th className="py-2.5 px-3 text-center">Winning Number</th>
                    <th className="py-2.5 px-3">Bet Amount</th>
                    <th className="py-2.5 px-3">Won Amount</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-900 font-mono">
                  {winningBets.length === 0 ? (
                    <tr><td colSpan={6} className="py-6 text-center text-neutral-500 font-sans">No winning bets recorded yet for this session.</td></tr>
                  ) : (
                    winningBets.map((b) => (
                      <tr key={b.id} className="hover:bg-neutral-900/50">
                        <td className="py-2.5 px-3 font-sans font-bold text-white">{b.username}</td>
                        <td className="py-2.5 px-3 font-sans text-neutral-300">{b.marketName}</td>
                        <td className="py-2.5 px-3 font-sans text-neutral-400">{b.gameType}</td>
                        <td className="py-2.5 px-3 text-center"><span className="px-2.5 py-1 rounded bg-amber-500/20 text-amber-400 font-bold">{b.selectedNumber}</span></td>
                        <td className="py-2.5 px-3 text-neutral-300">₹{b.amount}</td>
                        <td className="py-2.5 px-3 text-emerald-400 font-bold text-sm">+₹{b.wonAmount}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 11 & 12: ALL BID DATA & BID REVISE (Covered with full Boli Book above) */}
      {(activeModal === "all_bids" || activeModal === "bid_revise") && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-5xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[90vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3">
                <div className="p-2.5 rounded-2xl bg-amber-500/20 text-amber-400 border border-amber-500/30"><Layers className="w-6 h-6" /></div>
                <div><h3 className="text-xl font-black text-white">{activeModal === "all_bids" ? "All Bid Data (किस यूजर ने किस पर लगाई)" : "Bid Revise & Boli Book"}</h3><p className="text-xs text-neutral-400">See live wagers, chosen digits, player names, staked amounts, and number-wise exposure.</p></div>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400 hover:text-white"><X className="w-5 h-5" /></button>
            </div>
            {refundMsg && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{refundMsg}</div>}
            <div className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-2">
              <span className="text-xs font-bold text-amber-400 uppercase tracking-wider block">Number-Wise Stake Pool (Click number to see players):</span>
              <div className="flex flex-wrap gap-2 max-h-36 overflow-y-auto">
                {numberExposure.map((item, idx) => (
                  <button key={idx} onClick={() => { setFilterNumber(item.number); fetchBets(item.number); }} className={`px-3 py-1.5 rounded-xl border text-xs font-bold flex items-center gap-2 ${filterNumber === item.number ? "bg-amber-500 text-black border-amber-400" : "bg-neutral-900 text-neutral-300 border-neutral-800"}`}>
                    <span className="font-mono text-sm">{item.number}</span>
                    <span className="text-[11px] opacity-80">₹{item.totalAmount}</span>
                  </button>
                ))}
              </div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <input type="text" placeholder="Search by Player / Phone..." value={filterUserSearch} onChange={(e) => setFilterUserSearch(e.target.value)} className="px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" />
              <input type="text" placeholder="Filter specific Number (e.g. 28)..." value={filterNumber} onChange={(e) => setFilterNumber(e.target.value)} className="px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-amber-300 font-mono font-bold" />
              <button onClick={() => fetchBets()} className="gold-button py-2 rounded-xl text-xs font-bold">Search Bids</button>
            </div>
            <div className="overflow-x-auto rounded-2xl border border-neutral-800 max-h-80">
              <table className="w-full text-left text-xs">
                <thead className="bg-[#181a29] text-neutral-400 font-bold uppercase sticky top-0">
                  <tr>
                    <th className="py-2.5 px-3">Player</th>
                    <th className="py-2.5 px-3">Market</th>
                    <th className="py-2.5 px-3">Type</th>
                    <th className="py-2.5 px-3 text-center">CHOSEN NUMBER</th>
                    <th className="py-2.5 px-3">Amount</th>
                    <th className="py-2.5 px-3">Win Potential</th>
                    <th className="py-2.5 px-3">Status</th>
                    <th className="py-2.5 px-3 text-right">Action</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-900 font-mono">
                  {betsList.map((b) => (
                    <tr key={b.id} className="hover:bg-neutral-900/60">
                      <td className="py-2.5 px-3 font-sans"><strong className="text-white block">{b.username}</strong><span className="text-[10px] text-neutral-500">{b.userPhone}</span></td>
                      <td className="py-2.5 px-3 font-sans text-neutral-300">{b.marketName}</td>
                      <td className="py-2.5 px-3 font-sans text-neutral-400">{b.gameType}</td>
                      <td className="py-2.5 px-3 text-center"><span className="px-2.5 py-1 rounded-lg bg-amber-500/20 text-amber-400 font-black text-sm border border-amber-500/40">{b.selectedNumber}</span></td>
                      <td className="py-2.5 px-3 text-white font-bold">₹{b.amount}</td>
                      <td className="py-2.5 px-3 text-emerald-400 font-bold">₹{b.potentialWin}</td>
                      <td className="py-2.5 px-3"><span className={`px-2 py-0.5 rounded text-[10px] font-bold ${b.status === "WON" ? "bg-emerald-950 text-emerald-400" : "bg-amber-950 text-amber-400"}`}>{b.status}</span></td>
                      <td className="py-2.5 px-3 text-right font-sans">
                        {b.status === "PENDING" && (
                          <button onClick={() => handleRefundBet(b.id, b.selectedNumber, b.amount)} className="px-2 py-0.5 rounded bg-red-950 text-red-400 border border-red-800 text-[10px] font-bold">Refund</button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 13, 14, 15: MARKET LIST (1, 2, 4) */}
      {(activeModal === "market_list" || activeModal === "market_list_2" || activeModal === "market_list_4") && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-4xl rounded-3xl bg-[#0f1017] border border-blue-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div>
                <h3 className="text-xl font-black text-white">
                  {activeModal === "market_list" ? "Regular Market List" : activeModal === "market_list_2" ? "Market List 2 (Special Jackpot)" : "Market List 4 (Gali Disawar Fast)"}
                </h3>
                <p className="text-xs text-neutral-400">Timings, current result, and open/close controls</p>
              </div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {marketMsg && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{marketMsg}</div>}
            {/* Create New Market */}
            <form onSubmit={handleCreateMarket} className="p-3.5 rounded-2xl bg-neutral-950 border border-neutral-800 flex flex-wrap gap-2 text-xs">
              <input type="text" placeholder="New Market Name..." value={newMarketName} onChange={(e) => setNewMarketName(e.target.value)} required className="flex-1 min-w-36 px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-white" />
              <input type="text" placeholder="Open (e.g. 10:00 AM)" value={newMarketOpen} onChange={(e) => setNewMarketOpen(e.target.value)} className="w-32 px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-white font-mono" />
              <input type="text" placeholder="Close (e.g. 12:00 PM)" value={newMarketClose} onChange={(e) => setNewMarketClose(e.target.value)} className="w-32 px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-white font-mono" />
              <button type="submit" className="gold-button px-4 py-1.5 rounded-xl font-bold">Add Market</button>
            </form>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {markets
                .filter((m) => {
                  if (activeModal === "market_list") return m.category === "REGULAR";
                  if (activeModal === "market_list_2") return m.category === "KING_SPECIAL";
                  return m.category === "GALI_DISAWAR";
                })
                .map((m) => (
                  <div key={m.id} className="p-4 rounded-2xl bg-neutral-950 border border-neutral-800 flex items-center justify-between">
                    <div>
                      <strong className="text-white text-sm block">{m.name}</strong>
                      <span className="text-xs text-neutral-400 font-mono">{m.openTime} - {m.closeTime}</span>
                      <span className="text-xs text-amber-400 font-mono font-bold block mt-1">Result: [{m.fullResult}]</span>
                    </div>
                    <button onClick={() => handleToggleMarketStatus(m.id, m.status)} className={`px-3 py-1.5 rounded-xl text-xs font-bold ${m.status === "OPEN" ? "bg-emerald-950 text-emerald-400 border border-emerald-800" : "bg-red-950 text-red-400 border border-red-800"}`}>
                      {m.status}
                    </button>
                  </div>
                ))}
            </div>
          </div>
        </div>
      )}

      {/* MODAL 16: STARLINE CONDN */}
      {activeModal === "starline" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-purple-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-purple-600/20 text-purple-400 border border-purple-600/30"><Star className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">Starline Conditions & Hourly Rules</h3><p className="text-xs text-neutral-400">Manage Starline draw schedules and terms</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {saveSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{saveSuccess}</div>}
            <form onSubmit={handleSaveSettings} className="space-y-3">
              <textarea rows={5} value={settingsForm.STARLINE_RULES} onChange={(e) => setSettingsForm({ ...settingsForm, STARLINE_RULES: e.target.value })} className="w-full p-3 rounded-2xl bg-neutral-900 border border-neutral-700 text-xs text-white" />
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Save Starline Conditions</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 17: PAYOUT UPDATE */}
      {activeModal === "payout_update" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-4xl rounded-3xl bg-[#0f1017] border border-neutral-800 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div><h3 className="text-xl font-black text-white">Payout Update & Withdrawal Requests</h3><p className="text-xs text-neutral-400">Review pending member withdrawal requests and approve payouts</p></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {payoutMsg && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{payoutMsg}</div>}
            <div className="overflow-x-auto rounded-xl border border-neutral-800">
              <table className="w-full text-left text-xs">
                <thead className="bg-[#181a29] text-neutral-400 font-bold uppercase">
                  <tr><th className="py-2.5 px-3">Player</th><th className="py-2.5 px-3">Amount</th><th className="py-2.5 px-3">Payout Details</th><th className="py-2.5 px-3">Status</th><th className="py-2.5 px-3 text-right">Actions</th></tr>
                </thead>
                <tbody className="divide-y divide-neutral-900 font-mono">
                  {withdrawals.length === 0 ? (
                    <tr><td colSpan={5} className="py-6 text-center text-neutral-500 font-sans">No pending withdrawal requests.</td></tr>
                  ) : (
                    withdrawals.map((w) => (
                      <tr key={w.id} className="hover:bg-neutral-900/50">
                        <td className="py-2.5 px-3 font-sans"><strong className="text-white block">{w.username}</strong><span className="text-[10px] text-neutral-500">{w.phone}</span></td>
                        <td className="py-2.5 px-3 font-bold text-rose-400 text-sm">₹{w.amount}</td>
                        <td className="py-2.5 px-3 font-sans text-neutral-300">{w.payoutDetails}</td>
                        <td className="py-2.5 px-3"><span className={`px-2 py-0.5 rounded text-[10px] font-bold ${w.status === "APPROVED" ? "bg-emerald-950 text-emerald-400" : "bg-amber-950 text-amber-400"}`}>{w.status}</span></td>
                        <td className="py-2.5 px-3 text-right font-sans">
                          {w.status === "PENDING" && (
                            <div className="flex gap-1.5 justify-end">
                              <button onClick={() => handleWithdrawalAction(w.id, "APPROVE")} className="px-2.5 py-1 rounded bg-emerald-600 text-white font-bold text-[11px]">Approve</button>
                              <button onClick={() => handleWithdrawalAction(w.id, "REJECT")} className="px-2.5 py-1 rounded bg-red-950 text-red-400 border border-red-800 text-[11px] font-bold">Reject</button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 18: BANK UPDATE */}
      {activeModal === "bank_update" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-teal-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-teal-600/20 text-teal-400 border border-teal-600/30"><Building2 className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">Bank & UPI Gateway Update</h3><p className="text-xs text-neutral-400">Configure Receiving UPI ID & Gateway APIs</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {saveSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{saveSuccess}</div>}
            <form onSubmit={handleSaveSettings} className="space-y-3">
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Receiving UPI ID</label><input type="text" value={settingsForm.UPI_ID} onChange={(e) => setSettingsForm({ ...settingsForm, UPI_ID: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs font-mono font-bold text-amber-300" /></div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Merchant Name</label><input type="text" value={settingsForm.UPI_MERCHANT_NAME} onChange={(e) => setSettingsForm({ ...settingsForm, UPI_MERCHANT_NAME: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" /></div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">API Key</label><input type="text" value={settingsForm.PAYMENT_API_KEY} onChange={(e) => setSettingsForm({ ...settingsForm, PAYMENT_API_KEY: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs font-mono text-white" /></div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">API Secret</label><input type="password" value={settingsForm.PAYMENT_API_SECRET} onChange={(e) => setSettingsForm({ ...settingsForm, PAYMENT_API_SECRET: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs font-mono text-white" /></div>
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Save Bank Details</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 19: SET LIMIT */}
      {activeModal === "set_limit" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-neutral-800 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-neutral-800 text-neutral-300"><Sliders className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">Deposit & Withdrawal Limits</h3><p className="text-xs text-neutral-400">Set min & max limits for transactions and wagers</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {saveSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{saveSuccess}</div>}
            <form onSubmit={handleSaveSettings} className="space-y-3">
              <div className="grid grid-cols-2 gap-2">
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Min Deposit (₹)</label><input type="number" value={settingsForm.MIN_DEPOSIT} onChange={(e) => setSettingsForm({ ...settingsForm, MIN_DEPOSIT: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" /></div>
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Max Deposit (₹)</label><input type="number" value={settingsForm.MAX_DEPOSIT} onChange={(e) => setSettingsForm({ ...settingsForm, MAX_DEPOSIT: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" /></div>
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Min Withdrawal (₹)</label><input type="number" value={settingsForm.MIN_WITHDRAWAL} onChange={(e) => setSettingsForm({ ...settingsForm, MIN_WITHDRAWAL: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" /></div>
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Max Withdrawal (₹)</label><input type="number" value={settingsForm.MAX_WITHDRAWAL} onChange={(e) => setSettingsForm({ ...settingsForm, MAX_WITHDRAWAL: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" /></div>
              </div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Max Bet Limit per Digit (₹)</label><input type="number" value={settingsForm.MAX_BET_PER_DIGIT} onChange={(e) => setSettingsForm({ ...settingsForm, MAX_BET_PER_DIGIT: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" /></div>
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Save Limits</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 20: WHATSAPP UPDATE */}
      {activeModal === "whatsap_update" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-emerald-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-emerald-600/20 text-emerald-400 border border-emerald-600/30"><MessageCircle className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">Whatsap & Socials Update</h3><p className="text-xs text-neutral-400">Update Community Links & 24x7 Helpline</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {saveSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{saveSuccess}</div>}
            <form onSubmit={handleSaveSettings} className="space-y-3">
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">WhatsApp Group URL</label><input type="url" value={settingsForm.WHATSAPP_GROUP_URL} onChange={(e) => setSettingsForm({ ...settingsForm, WHATSAPP_GROUP_URL: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" /></div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Telegram Channel URL</label><input type="url" value={settingsForm.TELEGRAM_CHANNEL_URL} onChange={(e) => setSettingsForm({ ...settingsForm, TELEGRAM_CHANNEL_URL: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" /></div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Helpline Phone Number</label><input type="text" value={settingsForm.HELPLINE_WHATSAPP} onChange={(e) => setSettingsForm({ ...settingsForm, HELPLINE_WHATSAPP: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" /></div>
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Save Social Links</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 21 & 26: ADMIN NOTICE & BANNER TEXT */}
      {(activeModal === "admin_notice" || activeModal === "banner_text") && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div><h3 className="text-xl font-black text-white">{activeModal === "admin_notice" ? "Admin Notice" : "Banner Running Text"}</h3><p className="text-xs text-neutral-400">Notice updates ticker broadcast across website</p></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {saveSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{saveSuccess}</div>}
            <form onSubmit={handleSaveSettings} className="space-y-3">
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Marquee Running Banner Text</label><textarea rows={3} value={settingsForm.BANNER_TEXT} onChange={(e) => setSettingsForm({ ...settingsForm, BANNER_TEXT: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" /></div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Urgent Admin Notice</label><textarea rows={3} value={settingsForm.ADMIN_NOTICE} onChange={(e) => setSettingsForm({ ...settingsForm, ADMIN_NOTICE: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white" /></div>
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Save Notice & Ticker</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 22: BONUS RATES */}
      {activeModal === "bonus_rates" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-blue-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-blue-600/20 text-blue-400 border border-blue-600/30"><Gift className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">Bonus Rates Settings</h3><p className="text-xs text-neutral-400">Configure promotional & deposit bonus rates</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {saveSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{saveSuccess}</div>}
            <form onSubmit={handleSaveSettings} className="space-y-3">
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">New User Welcome Bonus (₹)</label><input type="number" value={settingsForm.BONUS_WELCOME} onChange={(e) => setSettingsForm({ ...settingsForm, BONUS_WELCOME: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" /></div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Deposit Bonus Percentage (%)</label><input type="number" value={settingsForm.BONUS_DEPOSIT_PCT} onChange={(e) => setSettingsForm({ ...settingsForm, BONUS_DEPOSIT_PCT: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" /></div>
              <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Referral Reward (₹)</label><input type="number" value={settingsForm.BONUS_REFERRAL} onChange={(e) => setSettingsForm({ ...settingsForm, BONUS_REFERRAL: e.target.value })} className="w-full px-3 py-2 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-white font-mono" /></div>
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Save Bonus Rates</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 23: OTP RECORD */}
      {activeModal === "otp_record" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-4xl rounded-3xl bg-[#0f1017] border border-neutral-800 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-neutral-800 text-neutral-300"><ShieldCheck className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">OTP & Security Logs Record</h3><p className="text-xs text-neutral-400">Tamper-evident audit trail capturing IP addresses, actions and timestamps</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            <div className="max-h-80 overflow-y-auto space-y-2 pr-1 font-mono text-xs">
              {securityLogs.map((log) => (
                <div key={log.id} className="p-3 rounded-xl bg-neutral-950 border border-neutral-800 flex items-start justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className={`px-1.5 py-0.5 rounded text-[10px] font-bold ${log.severity === "CRITICAL" ? "bg-red-600 text-white" : log.severity === "WARNING" ? "bg-amber-600 text-black" : "bg-neutral-800 text-neutral-300"}`}>{log.action}</span>
                      <span className="text-neutral-500 text-[10px]">{new Date(log.createdAt).toLocaleString()}</span>
                      <span className="text-neutral-400 text-[10px]">{log.ipAddress}</span>
                    </div>
                    <p className="text-neutral-300 font-sans mt-1 text-xs">{log.details}</p>
                  </div>
                  {log.username && <span className="text-neutral-400 text-[11px] font-sans">User: {log.username}</span>}
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* MODAL 24: HOW TO PLAY */}
      {activeModal === "how_to_play" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-lg rounded-3xl bg-[#0f1017] border border-orange-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-orange-600/20 text-orange-400 border border-orange-600/30"><HelpCircle className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">How to Play Guide</h3><p className="text-xs text-neutral-400">Rules displayed to members on placing wagers</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {saveSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{saveSuccess}</div>}
            <form onSubmit={handleSaveSettings} className="space-y-3">
              <textarea rows={6} value={settingsForm.HOW_TO_PLAY_TEXT} onChange={(e) => setSettingsForm({ ...settingsForm, HOW_TO_PLAY_TEXT: e.target.value })} className="w-full p-3 rounded-2xl bg-neutral-900 border border-neutral-700 text-xs text-white" />
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Save Game Rules</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 25: GAME RATE */}
      {activeModal === "game_rate" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-xl rounded-3xl bg-[#0f1017] border border-amber-500/30 shadow-2xl p-5 sm:p-8 space-y-4 my-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-amber-500/20 text-amber-400"><Percent className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">Game Rate Multipliers</h3><p className="text-xs text-neutral-400">Configure payout ratios for all bet types</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            {saveSuccess && <div className="p-3 rounded-xl bg-emerald-950 text-emerald-300 text-xs border border-emerald-800">{saveSuccess}</div>}
            <form onSubmit={handleSaveSettings} className="space-y-3">
              <div className="grid grid-cols-2 gap-2">
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Single Digit (Ank)</label><input type="text" value={settingsForm.RATE_SINGLE_DIGIT} onChange={(e) => setSettingsForm({ ...settingsForm, RATE_SINGLE_DIGIT: e.target.value })} className="w-full px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-amber-400 font-mono font-bold" /></div>
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Jodi Digit (00-99)</label><input type="text" value={settingsForm.RATE_JODI_DIGIT} onChange={(e) => setSettingsForm({ ...settingsForm, RATE_JODI_DIGIT: e.target.value })} className="w-full px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-amber-400 font-mono font-bold" /></div>
              </div>
              <div className="grid grid-cols-3 gap-2">
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Single Pana</label><input type="text" value={settingsForm.RATE_SINGLE_PANA} onChange={(e) => setSettingsForm({ ...settingsForm, RATE_SINGLE_PANA: e.target.value })} className="w-full px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-amber-400 font-mono font-bold" /></div>
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Double Pana</label><input type="text" value={settingsForm.RATE_DOUBLE_PANA} onChange={(e) => setSettingsForm({ ...settingsForm, RATE_DOUBLE_PANA: e.target.value })} className="w-full px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-amber-400 font-mono font-bold" /></div>
                <div><label className="block text-xs font-bold text-neutral-400 uppercase mb-1">Triple Pana</label><input type="text" value={settingsForm.RATE_TRIPLE_PANA} onChange={(e) => setSettingsForm({ ...settingsForm, RATE_TRIPLE_PANA: e.target.value })} className="w-full px-3 py-1.5 rounded-xl bg-neutral-900 border border-neutral-700 text-xs text-amber-400 font-mono font-bold" /></div>
              </div>
              <button type="submit" className="w-full py-3 rounded-xl gold-button text-xs font-black uppercase">Save Game Multipliers</button>
            </form>
          </div>
        </div>
      )}

      {/* MODAL 27: RECENT WINNERS */}
      {activeModal === "recent_winners" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/85 backdrop-blur-md overflow-y-auto">
          <div className="relative w-full max-w-3xl rounded-3xl bg-[#0f1017] border border-pink-500/40 shadow-2xl p-5 sm:p-8 space-y-4 my-auto max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-neutral-800 pb-3">
              <div className="flex items-center gap-3"><div className="p-2 rounded-xl bg-pink-600/20 text-pink-400 border border-pink-600/30"><Award className="w-5 h-5" /></div><div><h3 className="text-xl font-black text-white">Recent Winners Showcase</h3><p className="text-xs text-neutral-400">Top winning payouts declared across all sessions</p></div></div>
              <button onClick={() => setActiveModal(null)} className="p-2 rounded-xl bg-neutral-900 text-neutral-400"><X className="w-5 h-5" /></button>
            </div>
            <div className="space-y-2">
              {winningBets.length === 0 ? (
                <p className="text-xs text-neutral-500 py-6 text-center">No declared winners yet for this session.</p>
              ) : (
                winningBets.map((b) => (
                  <div key={b.id} className="p-3.5 rounded-2xl bg-neutral-950 border border-neutral-800 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <div className="p-2 rounded-xl bg-amber-500/20 text-amber-400 font-mono font-bold text-sm">#{b.selectedNumber}</div>
                      <div>
                        <strong className="text-white text-xs block">{b.username}</strong>
                        <span className="text-[11px] text-neutral-400">{b.marketName} • {b.gameType}</span>
                      </div>
                    </div>
                    <div className="text-right">
                      <span className="text-emerald-400 font-mono font-bold text-sm block">+₹{b.wonAmount} Won</span>
                      <span className="text-[10px] text-neutral-500 font-mono">{b.betDate}</span>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
