/**
 * Consistent page heading block used across customer pages.
 */
export default function PageHeader({ title, subtitle, children }) {
  return (
    <div className="page-header mb-4">
      <h1 className="h2 mb-1">{title}</h1>
      {subtitle && <p className="text-muted mb-0">{subtitle}</p>}
      {children}
    </div>
  );
}