import { Spinner } from 'react-bootstrap';

/**
 * Centered bootstrap spinner with optional label.
 */
export default function LoadingSpinner({ label = 'Loading...' }) {
  return (
    <div className="d-flex flex-column align-items-center justify-content-center py-5 gap-2">
      <Spinner animation="border" variant="success" role="status" aria-label={label} />
      <span className="text-muted small">{label}</span>
    </div>
  );
}