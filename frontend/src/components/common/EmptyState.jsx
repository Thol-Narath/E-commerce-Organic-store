import { Button, Card } from 'react-bootstrap';
import { Link } from 'react-router-dom';

/**
 * Friendly empty-state block with optional message and a call-to-action.
 * Provide `onAction` for a button click or `actionTo` for a router link.
 */
export default function EmptyState({
  title = 'Nothing here yet',
  message,
  actionLabel,
  onAction,
  actionTo,
  actionVariant = 'success',
}) {
  return (
    <Card className="text-center border-0 bg-light py-5 empty-state">
      <Card.Body className="py-5">
        <div className="empty-state-icon mx-auto mb-3" aria-hidden="true">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
            <rect x="4" y="6" width="6" height="12" />
            <rect x="14" y="6" width="6" height="12" />
            <path d="M1 6h22M3 3h18" strokeWidth="1" />
          </svg>
        </div>
        <h2 className="h5 fw-semibold">{title}</h2>
        {message && <p className="text-muted mb-4 mx-auto" style={{ maxWidth: '26rem' }}>{message}</p>}
        {actionLabel && onAction && (
          <Button variant={actionVariant} onClick={onAction}>
            {actionLabel}
          </Button>
        )}
        {actionLabel && actionTo && (
          <Button as={Link} to={actionTo} variant={actionVariant}>
            {actionLabel}
          </Button>
        )}
      </Card.Body>
    </Card>
  );
}