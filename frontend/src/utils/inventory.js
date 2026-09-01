/**
 * Frontend mirror of the backend's derived stock status
 * (InventoryService::stockStatusFor) so badges rendered from basic product
 * fields always match the API-computed value.
 */
export function stockStatusFor(quantity, reorderLevel) {
  const qty = Number(quantity) || 0;
  const level = Number(reorderLevel) || 0;
  if (qty <= 0) return 'out_of_stock';
  if (qty <= level) return 'low_stock';
  return 'in_stock';
}