import { Badge } from 'react-bootstrap';

export const STOCK_STATUS_LABEL = {
  in_stock: 'In stock',
  low_stock: 'Low stock',
  out_of_stock: 'Out of stock',
};

export const STOCK_STATUS_VARIANT = {
  in_stock: 'success',
  low_stock: 'warning',
  out_of_stock: 'danger',
};

/**
 * Stock availability badge used across admin inventory screens and the
 * customer storefront. Mirrors the derived stock_status from the API so the
 * label and color can never drift from the backend value.
 */
export default function InventoryStatusBadge({ status, className = '', pill = true }) {
  const resolved = status in STOCK_STATUS_LABEL ? status : 'out_of_stock';
  const label = STOCK_STATUS_LABEL[resolved];

  return (
    <Badge bg={STOCK_STATUS_VARIANT[resolved]} pill={pill} className={className}>
      {label}
    </Badge>
  );
}