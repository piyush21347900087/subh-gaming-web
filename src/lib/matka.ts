/**
 * Matka Game Logic & Calculation Helper
 */

// Calculate Single Digit (Ank) from 3-digit Pana
// Example: 128 -> 1 + 2 + 8 = 11 -> Last digit is 1
export function calculateAnkFromPana(pana: string): string {
  if (!pana || pana.length !== 3) return "";
  const sum = pana
    .split("")
    .reduce((acc, char) => acc + (parseInt(char, 10) || 0), 0);
  return (sum % 10).toString();
}

// Validate if 3 digits are in ascending/valid Matka pana format
export function isValidPana(pana: string): boolean {
  if (!/^\d{3}$/.test(pana)) return false;
  return true;
}

// Default game rates
export const GAME_RATES: Record<string, { label: string; rate: number; ratio: string; minBet: number }> = {
  SINGLE_DIGIT: {
    label: "Single Digit (Ank)",
    rate: 9.5,
    ratio: "₹10 ka ₹95",
    minBet: 10,
  },
  JODI_DIGIT: {
    label: "Jodi Digit (00-99)",
    rate: 95,
    ratio: "₹10 ka ₹950",
    minBet: 10,
  },
  SINGLE_PANA: {
    label: "Single Pana (120)",
    rate: 140,
    ratio: "₹10 ka ₹1,400",
    minBet: 10,
  },
  DOUBLE_PANA: {
    label: "Double Pana (90)",
    rate: 280,
    ratio: "₹10 ka ₹2,800",
    minBet: 10,
  },
  TRIPLE_PANA: {
    label: "Triple Pana (10)",
    rate: 600,
    ratio: "₹10 ka ₹6,000",
    minBet: 10,
  },
  HALF_SANGAM: {
    label: "Half Sangam",
    rate: 1000,
    ratio: "₹10 ka ₹10,000",
    minBet: 10,
  },
  FULL_SANGAM: {
    label: "Full Sangam",
    rate: 10000,
    ratio: "₹10 ka ₹1,00,000",
    minBet: 10,
  },
};

// Evaluate whether a bet won based on declared results
export function evaluateBetWin(
  gameType: string,
  sessionType: string, // 'OPEN' or 'CLOSE'
  selectedNumber: string,
  openPana: string | null,
  openDigit: string | null,
  closeDigit: string | null,
  closePana: string | null
): { isWin: boolean; wonAmountMultiplier: number } {
  const normSelected = selectedNumber.trim();

  if (gameType === "SINGLE_DIGIT") {
    const targetDigit = sessionType === "OPEN" ? openDigit : closeDigit;
    if (targetDigit && normSelected === targetDigit) {
      return { isWin: true, wonAmountMultiplier: GAME_RATES.SINGLE_DIGIT.rate };
    }
  }

  if (gameType === "JODI_DIGIT") {
    if (openDigit && closeDigit) {
      const declaredJodi = `${openDigit}${closeDigit}`;
      const paddedSelected = normSelected.padStart(2, "0");
      if (paddedSelected === declaredJodi) {
        return { isWin: true, wonAmountMultiplier: GAME_RATES.JODI_DIGIT.rate };
      }
    }
  }

  if (gameType === "SINGLE_PANA" || gameType === "DOUBLE_PANA" || gameType === "TRIPLE_PANA") {
    const targetPana = sessionType === "OPEN" ? openPana : closePana;
    if (targetPana && normSelected === targetPana) {
      const rate = GAME_RATES[gameType]?.rate || 140;
      return { isWin: true, wonAmountMultiplier: rate };
    }
  }

  return { isWin: false, wonAmountMultiplier: 0 };
}
