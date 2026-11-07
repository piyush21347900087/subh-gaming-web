"use client";

import React from "react";
import { Bell, Flame } from "lucide-react";

interface MarqueeTickerProps {
  markets: any[];
  notices: any[];
  bannerText?: string;
}

export default function MarqueeTicker({ markets, notices, bannerText }: MarqueeTickerProps) {
  const resultStrings = markets
    .filter((m) => m.isActive)
    .map((m) => `${m.name}: [ ${m.fullResult || "***-**-***"} ]`)
    .join("  •  ");

  const latestNotice = notices && notices.length > 0 ? notices[0].content : null;

  return (
    <div className="bg-[#10121d] border-b border-neutral-800 text-xs text-neutral-300 overflow-hidden shadow-inner">
      {/* Notice / Running Announcement bar */}
      {(bannerText || latestNotice) && (
        <div className="bg-[#181a29] border-b border-neutral-800/80 px-4 py-1.5 flex items-center gap-2">
          <span className="flex items-center gap-1 font-extrabold text-amber-400 uppercase text-[10px] tracking-wider px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 shrink-0">
            <Bell className="w-3 h-3 text-amber-400 animate-pulse" />
            SUBH GAMING UPDATE
          </span>
          <div className="overflow-hidden whitespace-nowrap w-full">
            <div className="animate-marquee inline-block font-medium text-neutral-200 text-xs">
              {bannerText || latestNotice}
            </div>
          </div>
        </div>
      )}

      {/* Live Market Results ticker */}
      <div className="flex items-center px-4 py-2">
        <div className="flex items-center gap-1.5 text-amber-400 font-bold uppercase text-[11px] tracking-wider shrink-0 mr-3 pr-3 border-r border-neutral-800">
          <Flame className="w-3.5 h-3.5 text-amber-500 fill-amber-500" />
          <span>Live Results:</span>
        </div>
        <div className="overflow-hidden whitespace-nowrap w-full">
          <div className="animate-marquee inline-block font-mono font-medium text-amber-200/90 text-xs">
            {resultStrings || "Kalyan: 138-28-468  •  Milan Day: 246-24-158  •  Subh Gaming Special: 555-55-555  •  Main Bazar: 450-9*-***"}
          </div>
        </div>
      </div>
    </div>
  );
}
