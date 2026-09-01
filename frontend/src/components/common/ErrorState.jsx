import { Alert } from 'react-bootstrap';

/**
 * User-friendly error message. Optionally offers a retry action.
 */
export default function ErrorState({ title = 'Something went wrong', message, onRetry, retryLabel = 'Try Again' }) {
  return (
    <Alert variant="danger" className="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
      <div>
        <Alert.Heading as="h2" className="h6 mb-1">{title}</Alert.Heading>
        {message && <p className="mb-0">{message}</p>}
      </div>
      {onRetry && (
        <button type="button" className="btn btn-outline-danger btn-sm flex-shrink-0" onClick={onRetry}>
          {retryLabel}
        </button>
      )}
    </Alert>
  );
}