/**
 * Minimal CSS shimmer skeleton used for loading placeholders.
 * `<Skeleton variant>` maps to a CSS class controlling width/height/shape.
 */
export default function Skeleton({ variant = 'text', className = '', style }) {
  return <span className={`skeleton skeleton-${variant} ${className}`} style={style} aria-hidden="true" />;
}
