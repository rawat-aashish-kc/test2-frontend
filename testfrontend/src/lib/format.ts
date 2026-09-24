/**
 * Accepts number | string since some API fields (decimal-cast on the
 * backend) can arrive as numeric strings — coerce defensively rather than
 * trust every response shape at every call site.
 */
export function money(amount: number | string): string {
  return `$${Number(amount).toFixed(2)}`
}
