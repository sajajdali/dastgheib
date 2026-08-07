export { CENTRAL_MODULES } from "./store-modules";

export const EXPIRY_FILTERS = [
  { key: "tomorrow", label: "فردا", limit: 1 },
  { key: "3days", label: "تا ۳ روز دیگر", limit: 3 },
  { key: "week", label: "تا یک هفته", limit: 7 },
  { key: "all", label: "همه", limit: Infinity },
];
